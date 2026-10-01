[CmdletBinding()]
param(
    [switch]$Preflight,
    [switch]$Package,
    [switch]$DryRun,
    [switch]$Deploy
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$modeCount = @(@($Preflight, $Package, $DryRun, $Deploy) | Where-Object { $_ }).Count
if ($modeCount -ne 1) { throw 'Choose exactly one mode: -Preflight, -Package, -DryRun or -Deploy.' }
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$logs = Join-Path $root '.deploy-logs'
$packages = Join-Path $root '.deploy-packages'
$utf8 = New-Object System.Text.UTF8Encoding($false)

function Assert-True([bool]$condition, [string]$message) {
    if (-not $condition) { throw $message }
}

function Invoke-Checked([string]$name, [string[]]$arguments) {
    & $name @arguments
    if ($LASTEXITCODE -ne 0) { throw "$name failed (exit $LASTEXITCODE)." }
}

function Read-LocalConfig {
    $path = Join-Path $PSScriptRoot 'local-release.env'
    Assert-True (Test-Path -LiteralPath $path -PathType Leaf) 'deploy/local-release.env is required.'
    $values = @{}
    foreach ($line in [IO.File]::ReadAllLines($path)) {
        if ($line.Trim() -eq '' -or $line.TrimStart().StartsWith('#')) { continue }
        $pair = $line.Split(@('='), 2)
        Assert-True ($pair.Count -eq 2 -and $pair[0] -match '^[A-Z0-9_]+$') 'Invalid local-release.env entry.'
        $values[$pair[0]] = $pair[1]
    }
    return $values
}

function Assert-Target($config) {
    Assert-True ($config['LEMON_TARGET_DOMAIN'] -ceq 'lemon.derspros.com.tr') 'TARGET_DOMAIN must be lemon.derspros.com.tr.'
    Assert-True ($config['LEMON_APP_DIR'] -ceq '/lemon-app/') 'APP_DIR must be /lemon-app/.'
    foreach ($name in @('LEMON_FTP_SERVER', 'LEMON_FTP_USERNAME', 'LEMON_FTP_PASSWORD',
        'LEMON_FTP_PORT', 'LEMON_FTPS_CERT_FINGERPRINT')) {
        Assert-True (-not [string]::IsNullOrWhiteSpace($config[$name])) "$name is required."
    }
    Assert-True ($config['LEMON_FTP_SERVER'] -match '^[A-Za-z0-9.-]+$') 'Invalid FTPS server.'
    Assert-True ($config['LEMON_FTP_PORT'] -match '^\d{1,5}$') 'Invalid FTPS port.'
    Assert-True ([int]$config['LEMON_FTP_PORT'] -ge 1 -and [int]$config['LEMON_FTP_PORT'] -le 65535) 'Invalid FTPS port.'
    Assert-True ($config['LEMON_FTPS_CERT_FINGERPRINT'] -match '^(?i:[0-9a-f]{2}:){31}(?i:[0-9a-f]{2})$') 'Expected a pinned SHA-256 certificate fingerprint (32 colon-separated hex bytes).'
}

function Assert-Source([bool]$requireMain) {
    Push-Location $root
    try {
        $branch = (& git branch --show-current).Trim()
        $commit = (& git rev-parse HEAD).Trim()
        Assert-True ($LASTEXITCODE -eq 0 -and $commit -match '^[a-f0-9]{40}$') 'Invalid local commit.'
        Assert-True (-not (& git status --porcelain=v1)) 'Working tree is not clean.'
        if ($requireMain) {
            Assert-True ($branch -ceq 'main') 'Deploy requires the main branch.'
            Invoke-Checked 'git' @('fetch', 'origin')
            $remote = (& git rev-parse origin/main).Trim()
            Assert-True ($commit -ceq $remote) 'Local HEAD differs from origin/main.'
        }
        $releaseText = [IO.File]::ReadAllText((Join-Path $root 'config/release.php'))
        $match = [regex]::Match($releaseText, "'version'\s*=>\s*'(\d+)'")
        Assert-True $match.Success 'config/release.php has no readable version.'
        return @{ Branch = $branch; Commit = $commit; Version = [int]$match.Groups[1].Value }
    } finally { Pop-Location }
}

function Assert-Toolchain {
    foreach ($tool in @('php', 'composer', 'node')) {
        Assert-True ($null -ne (Get-Command $tool -ErrorAction SilentlyContinue)) "$tool is required."
    }
    $npm = Get-Command 'npm.cmd' -ErrorAction SilentlyContinue
    if ($null -eq $npm) { $npm = Get-Command 'npm' -ErrorAction SilentlyContinue }
    Assert-True ($null -ne $npm) 'npm is required.'
    $phpVersion = (& php -r 'echo PHP_VERSION;').Trim()
    Assert-True ($phpVersion -match '^8\.3\.\d+$') 'Exact PHP 8.3.x is required.'
    Assert-True ((& node --version).Trim() -match '^v22\.\d+\.\d+$') 'Node 22.x is required.'
    return $npm.Source
}

function Set-TestDatabase($config) {
    foreach ($key in @('HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD')) {
        $value = $config["LEMON_TEST_DB_$key"]
        Assert-True ($null -ne $value -and ($key -eq 'PASSWORD' -or $value -ne '')) "LEMON_TEST_DB_$key is required."
        [Environment]::SetEnvironmentVariable("DB_$key", $value, 'Process')
    }
    [Environment]::SetEnvironmentVariable('DB_CONNECTION', 'mysql', 'Process')
    [Environment]::SetEnvironmentVariable('DB_URL', '', 'Process')
    $probe = 'try { $pdo = new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); $pdo->query("SELECT 1"); } catch (Throwable $e) { exit(1); }'
    & php -r $probe
    Assert-True ($LASTEXITCODE -eq 0) 'MySQL/MariaDB integration test database is unavailable.'
}

function Invoke-QualityGate([string]$npm, $config) {
    Set-TestDatabase $config
    Push-Location $root
    try {
        Invoke-Checked 'composer' @('validate', '--strict', '--no-check-publish')
        Invoke-Checked 'composer' @('install')
        Invoke-Checked 'composer' @('check-platform-reqs')
        Invoke-Checked $npm @('ci')
        Invoke-Checked $npm @('run', 'build')
        Invoke-Checked $npm @('run', 'test:monthly-account')
        Invoke-Checked $npm @('run', 'test:personnel')
        Invoke-Checked 'php' @('vendor/bin/pint', '--test')
        Invoke-Checked 'php' @('vendor/bin/phpstan', 'analyse', '--no-progress')
        Invoke-Checked 'php' @('artisan', 'test', '--filter', 'StayDuplicateConcurrencyTest')
        Invoke-Checked 'php' @('artisan', 'test')
        Invoke-Checked 'composer' @('install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction', '--no-progress')
        Invoke-Checked 'composer' @('check-platform-reqs', '--no-dev')
        Invoke-Checked $npm @('ci')
        Invoke-Checked $npm @('run', 'build')
        Assert-True (Test-Path 'public/build/manifest.json' -PathType Leaf) 'Vite manifest is missing.'
        Assert-True (Test-Path 'vendor/autoload.php' -PathType Leaf) 'Production vendor autoload is missing.'
    } finally { Pop-Location }
}

function Assert-ArchivePath([string]$path) {
    Assert-True ($path -notmatch '(^/|\\|(^|/)(\.|\.\.|\.git|\.github|\.claude|storage|node_modules)(/|$)|(^|/)\.env(\.|$)|^bootstrap/cache(/|$)|^public/storage(/|$)|//)') "Unsafe package path: $path"
    Assert-True ($path -match '^(app|config|database|lang|public|vendor)/' -or
        $path -match '^(resources/views|routes)/' -or
        $path -in @('artisan', 'bootstrap/app.php', 'bootstrap/providers.php',
            'deploy/finalize.php', 'deploy/staged-release.php', 'deploy/maintenance-trigger.php')) "Non-production package path: $path"
}

$checkedPackageDirectories = @{}
function Assert-PackageFile([string]$fullName) {
    Assert-True ($fullName.StartsWith($root + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) 'Package file escapes repository root.'
    $parent = [IO.Path]::GetDirectoryName($fullName)
    while ($parent.Length -gt $root.Length) {
        if ($checkedPackageDirectories.ContainsKey($parent)) { break }
        $item = Get-Item -LiteralPath $parent -Force
        Assert-True (-not ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)) 'Symlink/junction directory in production package.'
        $checkedPackageDirectories[$parent] = $true
        $parent = [IO.Path]::GetDirectoryName($parent)
    }
}

function Get-PackageFiles {
    $paths = New-Object System.Collections.Generic.List[object]
    foreach ($directory in @('app', 'config', 'database', 'lang', 'public', 'resources/views', 'routes', 'vendor')) {
        $full = Join-Path $root $directory
        if (-not (Test-Path -LiteralPath $full -PathType Container)) { continue }
        foreach ($file in Get-ChildItem -LiteralPath $full -Recurse -File -Force) {
            Assert-True (-not ($file.Attributes -band [IO.FileAttributes]::ReparsePoint)) 'Symlink/reparse point in production package.'
            Assert-PackageFile $file.FullName
            $relative = $file.FullName.Substring($root.Length + 1).Replace('\', '/')
            if ($relative -eq 'public/hot') { continue }
            Assert-ArchivePath $relative
            $paths.Add(@{ Path = $relative; FullName = $file.FullName; Size = [int64]$file.Length })
        }
    }
    foreach ($relative in @('artisan', 'bootstrap/app.php', 'bootstrap/providers.php',
        'deploy/finalize.php', 'deploy/staged-release.php', 'deploy/maintenance-trigger.php')) {
        $full = Join-Path $root $relative
        Assert-True (Test-Path -LiteralPath $full -PathType Leaf) "Required production file missing: $relative"
        $file = Get-Item -LiteralPath $full
        Assert-True (-not ($file.Attributes -band [IO.FileAttributes]::ReparsePoint)) 'Symlink/reparse point in production package.'
        Assert-PackageFile $file.FullName
        $paths.Add(@{ Path = $relative; FullName = $full; Size = [int64]$file.Length })
    }
    return @($paths | Sort-Object Path)
}

function New-Package($source, [string]$releaseId) {
    Add-Type -AssemblyName System.IO.Compression
    $folder = Join-Path $packages $releaseId
    [void](New-Item -ItemType Directory -Path $folder -Force)
    $archive = Join-Path $folder 'release.zip'
    Assert-True (-not (Test-Path -LiteralPath $archive)) 'Release package already exists.'
    $files = @(Get-PackageFiles)
    $records = @($files | ForEach-Object {
        @{ path = $_.Path; sha256 = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant(); size = $_.Size }
    })
    $manifest = @{
        schema_version = 1
        commit = $source.Commit
        release_version = $source.Version
        created_at = [DateTimeOffset]::UtcNow.ToString('o')
        files = $records
    }
    $manifestPath = Join-Path $folder 'release-manifest.json'
    [IO.File]::WriteAllText($manifestPath, ($manifest | ConvertTo-Json -Depth 8 -Compress), $utf8)
    $stream = [IO.File]::Open($archive, [IO.FileMode]::CreateNew)
    try {
        $zip = New-Object System.IO.Compression.ZipArchive($stream, [IO.Compression.ZipArchiveMode]::Create)
        try {
            foreach ($file in $files) {
                $entry = $zip.CreateEntry($file.Path, [IO.Compression.CompressionLevel]::Optimal)
                $input = [IO.File]::OpenRead($file.FullName)
                $output = $entry.Open()
                try { $input.CopyTo($output) } finally { $input.Dispose(); $output.Dispose() }
            }
            $entry = $zip.CreateEntry('release-manifest.json', [IO.Compression.CompressionLevel]::Optimal)
            $output = $entry.Open()
            try {
                $bytes = [IO.File]::ReadAllBytes($manifestPath)
                $output.Write($bytes, 0, $bytes.Length)
            } finally { $output.Dispose() }
        } finally { $zip.Dispose() }
    } finally { $stream.Dispose() }
    return @{ Folder = $folder; Archive = $archive; Manifest = $manifestPath;
        Sha256 = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant() }
}

function Find-WinScp {
    $candidate = Get-Command 'WinSCP.com' -ErrorAction SilentlyContinue
    if ($null -ne $candidate) { return $candidate.Source }
    foreach ($path in @("${env:ProgramFiles(x86)}\WinSCP\WinSCP.com", "$env:ProgramFiles\WinSCP\WinSCP.com")) {
        if (Test-Path -LiteralPath $path -PathType Leaf) { return $path }
    }
    throw 'WinSCP.com is required. No insecure protocol fallback exists.'
}

function Quote-WinScp([string]$value) {
    Assert-True (-not ($value.Contains("`r") -or $value.Contains("`n"))) 'Invalid WinSCP value.'
    return '"' + $value.Replace('"', '""') + '"'
}

function Invoke-WinScp([string[]]$commands) {
    $temp = Join-Path $env:TEMP ('lemon-winscp-' + [guid]::NewGuid().ToString('N') + '.txt')
    $username = [Uri]::EscapeDataString($script:config['LEMON_FTP_USERNAME'])
    $url = "ftpes://$username@$($script:config['LEMON_FTP_SERVER']):$($script:config['LEMON_FTP_PORT'])/"
    $open = 'open ' + (Quote-WinScp $url) +
        ' -password=' + (Quote-WinScp $script:config['LEMON_FTP_PASSWORD']) +
        ' -certificate=' + (Quote-WinScp $script:config['LEMON_FTPS_CERT_FINGERPRINT'])
    $lines = @('option batch abort', 'option confirm off', 'option transfer binary', $open) + $commands + @('exit')
    try {
        [IO.File]::WriteAllLines($temp, $lines, $utf8)
        $output = & $script:winScp '/ini=nul' "/script=$temp" 2>&1
        if ($LASTEXITCODE -ne 0) { throw 'Pinned FTPS operation failed. Check server/credentials/fingerprint and remote paths.' }
        return @($output)
    } finally {
        if (Test-Path -LiteralPath $temp) { Remove-Item -LiteralPath $temp -Force }
    }
}

function Get-RemoteNames([string]$directory) {
    $listing = @(Invoke-WinScp @('ls ' + (Quote-WinScp $directory)))
    return @($listing | ForEach-Object {
        $line = [string]$_
        if ($line -match '(?:^|\s)([A-Za-z0-9_.-]+)\s*$') { $Matches[1] }
    })
}

function Test-RemoteName([string]$directory, [string]$name) {
    return ($name -in @(Get-RemoteNames $directory))
}

function Get-RemoteFile([string]$remote, [string]$local) {
    [void](Invoke-WinScp @('get ' + (Quote-WinScp $remote) + ' ' + (Quote-WinScp $local)))
    Assert-True (Test-Path -LiteralPath $local -PathType Leaf) 'Remote download did not produce a file.'
}

function Put-RemoteFile([string]$local, [string]$remote) {
    [void](Invoke-WinScp @('put ' + (Quote-WinScp $local) + ' ' + (Quote-WinScp $remote)))
}

function Assert-RemoteReady {
    $app = $script:config['LEMON_APP_DIR']
    Assert-True (-not (Test-RemoteName ($app + 'storage/app/') 'deploy-pending')) 'Remote deploy-pending exists; inspect existing deployment.'
    Assert-True (-not (Test-RemoteName ($app + 'storage/framework/') 'down')) 'Remote site is already in maintenance.'
    Assert-True (Test-RemoteName ($app + 'deploy/') 'staged-release.php') 'One-time server staged finalize bootstrap is required.'
    $remoteFinalize = Join-Path $logs 'remote-finalize.php'
    Get-RemoteFile ($app + 'deploy/finalize.php') $remoteFinalize
    Assert-True ([IO.File]::ReadAllText($remoteFinalize).Contains('LemonStagedRelease')) 'Remote finalize.php does not support staged markers.'
}

function Write-LocalText([string]$path, [string]$value) {
    [IO.File]::WriteAllText($path, $value, $utf8)
}

function New-MaintenanceFiles([string]$folder, [string]$owner) {
    $trigger = [IO.File]::ReadAllText((Join-Path $root 'deploy/maintenance-trigger.php'))
    $stubPath = Join-Path $root 'vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/maintenance-mode.stub'
    Assert-True (Test-Path -LiteralPath $stubPath -PathType Leaf) 'Laravel maintenance stub is missing.'
    $stub = [IO.File]::ReadAllText($stubPath)
    Assert-True ($stub.StartsWith('<?php')) 'Unexpected Laravel maintenance stub.'
    $shell = $trigger + [Environment]::NewLine + $stub.Substring(5) + [Environment]::NewLine + '// Deploy ' + $owner
    $shellPath = Join-Path $folder 'maintenance.php'
    Write-LocalText $shellPath $shell
    Invoke-Checked 'php' @('-l', $shellPath)
    $down = @{
        except = @(); redirect = $null; retry = 60; refresh = 15; secret = $null; status = 503
        template = '<!doctype html><html lang="tr"><meta charset="utf-8"><title>Güncelleme</title><p>Sistem güncelleniyor. Lütfen kısa bir süre sonra yeniden deneyin.</p></html>'
        deploy_release_id = $owner
    }
    $downPath = Join-Path $folder 'down'
    Write-LocalText $downPath ($down | ConvertTo-Json -Depth 5 -Compress)
    return @{ Shell = $shellPath; Down = $downPath }
}

try {
    $source = Assert-Source ([bool]$Deploy)
    $npm = Assert-Toolchain
    if ($Preflight) {
        Write-Output "Preflight OK: $($source.Branch) $($source.Commit), release $($source.Version). No remote write."
        return
    }
    if ($Deploy -or $DryRun) {
        $script:config = Read-LocalConfig
        Assert-Target $script:config
        $script:winScp = Find-WinScp
        [void](New-Item -ItemType Directory -Path $logs -Force)
    }
    if ($Deploy) {
        Invoke-QualityGate $npm $script:config
        $source = Assert-Source $true
    }
    Assert-True (Test-Path (Join-Path $root 'public/build/manifest.json') -PathType Leaf) 'Production Vite build is missing.'
    Assert-True (Test-Path (Join-Path $root 'vendor/autoload.php') -PathType Leaf) 'Production vendor is missing.'
    $runId = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds().ToString()
    $releaseId = 'local-' + $runId + '-' + $source.Commit.Substring(0, 8) + '-' + [guid]::NewGuid().ToString('N').Substring(0, 8)
    $package = New-Package $source $releaseId
    if ($Package) {
        Write-Output "Package ready: $($package.Archive); SHA-256 $($package.Sha256). No remote write."
        return
    }
    Assert-RemoteReady
    if ($DryRun) {
        Write-Output "Dry run OK: pinned FTPS, remote state and staged finalize checked. No remote write."
        return
    }

    $app = $script:config['LEMON_APP_DIR']
    $inboxBase = $app + 'storage/app/deploy-inbox/'
    if (-not (Test-RemoteName ($app + 'storage/app/') 'deploy-inbox')) {
        [void](Invoke-WinScp @('mkdir ' + (Quote-WinScp $inboxBase)))
    }
    [void](Invoke-WinScp @('mkdir ' + (Quote-WinScp ($inboxBase + $releaseId + '/'))))
    $inbox = $inboxBase + $releaseId + '/'
    Put-RemoteFile $package.Archive ($inbox + 'release.zip')
    Put-RemoteFile $package.Manifest ($inbox + 'release-manifest.json')
    $roundtripZip = Join-Path $package.Folder 'roundtrip.zip'
    $roundtripManifest = Join-Path $package.Folder 'roundtrip-manifest.json'
    Get-RemoteFile ($inbox + 'release.zip') $roundtripZip
    Get-RemoteFile ($inbox + 'release-manifest.json') $roundtripManifest
    Assert-True ((Get-FileHash $roundtripZip -Algorithm SHA256).Hash.ToLowerInvariant() -ceq $package.Sha256) 'Remote archive round-trip SHA-256 mismatch.'
    Assert-True ((Get-FileHash $roundtripManifest -Algorithm SHA256).Hash -ceq (Get-FileHash $package.Manifest -Algorithm SHA256).Hash) 'Remote manifest round-trip mismatch.'

    $statusPath = Join-Path $package.Folder 'deploy-status.json'
    Write-LocalText $statusPath (@{ release_id = $releaseId; commit = $source.Commit; phase = 'uploaded'; updated_at = [DateTimeOffset]::UtcNow.ToString('o') } | ConvertTo-Json -Compress)
    Put-RemoteFile $statusPath ($app + 'storage/app/deploy-status.json')

    $owner = "$runId-1"
    $maintenance = New-MaintenanceFiles $package.Folder $owner
    Put-RemoteFile $maintenance.Shell ($app + 'storage/framework/maintenance.php')
    Put-RemoteFile $maintenance.Down ($app + 'storage/framework/down')
    $markerPath = Join-Path $package.Folder 'deploy-pending'
    Write-LocalText $markerPath "mode=staged`ncommit=$($source.Commit)`nrun_id=$runId`nrun_attempt=1`nrelease_id=$releaseId`narchive_sha256=$($package.Sha256)`nmanaged_maintenance=1`n"
    Put-RemoteFile $markerPath ($app + 'storage/app/deploy-pending')
    try { [void](Invoke-WebRequest -Uri 'https://lemon.derspros.com.tr/' -UseBasicParsing -TimeoutSec 120) } catch { }

    $deadline = [DateTimeOffset]::UtcNow.AddMinutes(8)
    while ([DateTimeOffset]::UtcNow -lt $deadline) {
        Start-Sleep -Seconds 15
        if (Test-RemoteName ($app + 'storage/app/') 'deploy-pending') { continue }
        $receiptPath = Join-Path $package.Folder 'deploy-completed.json'
        Get-RemoteFile ($app + 'storage/app/deploy-completed.json') $receiptPath
        $receipt = Get-Content -LiteralPath $receiptPath -Raw | ConvertFrom-Json
        Assert-True ($receipt.commit -ceq $source.Commit -and $receipt.run_id -ceq $runId -and $receipt.run_attempt -ceq '1') 'Completion receipt does not match this release.'
        Write-Output "Local deploy complete: $releaseId $($source.Commit)"
        return
    }
    $phase = 'unknown'
    if (Test-RemoteName ($app + 'storage/app/') 'deploy-status.json') {
        $remoteStatus = Join-Path $package.Folder 'remote-deploy-status.json'
        Get-RemoteFile ($app + 'storage/app/deploy-status.json') $remoteStatus
        $phase = (Get-Content -LiteralPath $remoteStatus -Raw | ConvertFrom-Json).phase
    }
    throw "Finalize timeout. Release $releaseId, phase $phase, marker pending. Inspect /home/derspros/lemon-deploy.log; maintenance was not blindly removed."
} catch {
    Write-Error $_.Exception.Message
    exit 1
}

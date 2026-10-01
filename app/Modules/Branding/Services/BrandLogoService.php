<?php

declare(strict_types=1);

namespace App\Modules\Branding\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class BrandLogoService
{
    private const VARIANTS = ['on-light', 'on-dark'];

    private const EXTENSIONS = ['png', 'webp', 'jpg'];

    public function url(string $variant): ?string
    {
        $file = $this->uploadedFile($variant);
        if ($file !== null) {
            // Sabit dosya adıyla yeniden yükleme, tarayıcıdaki eski görseli tutmamalı.
            $version = sha1_file($file);

            return route('branding.logo.file', [
                'variant' => $variant,
                'v' => $version === false ? (string) filemtime($file) : substr($version, 0, 12),
            ]);
        }

        // Önceden özelleştirilmiş tek logo koyu menüde çalışmaya devam eder.
        $legacy = (string) config('hotel.brand.logo', '');
        if ($variant === 'on-dark' && $legacy !== 'images/logo.svg' && $legacy !== '' && is_file(public_path($legacy))) {
            return asset($legacy);
        }

        $fallback = (string) config('hotel.brand.logos.'.str_replace('-', '_', $variant).'_default', '');
        if ($fallback !== '' && is_file(public_path($fallback))) {
            return asset($fallback);
        }

        // Eski brand.logo yapılandırması kaldırılmaz; eksik varsayılan asset için son çare.
        return $legacy !== '' && is_file(public_path($legacy)) ? asset($legacy) : null;
    }

    public function uploadedFile(string $variant): ?string
    {
        $this->assertVariant($variant);
        $disk = Storage::disk('public');

        foreach (self::EXTENSIONS as $extension) {
            $path = $this->path($variant, $extension);
            if (! $disk->exists($path)) {
                continue;
            }

            $file = $disk->path($path);
            if (is_file($file) && @getimagesize($file) !== false) {
                return $file;
            }
        }

        return null;
    }

    public function replace(string $variant, UploadedFile $file): void
    {
        $this->assertVariant($variant);
        $extension = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/jpeg' => 'jpg',
            default => throw new InvalidArgumentException('Desteklenmeyen logo biçimi.'),
        };

        $disk = Storage::disk('public');
        $path = $this->path($variant, $extension);
        if ($disk->putFileAs('branding', $file, basename($path)) === false) {
            throw new RuntimeException('Logo dosyası kaydedilemedi.');
        }

        foreach (self::EXTENSIONS as $oldExtension) {
            if ($oldExtension !== $extension) {
                $disk->delete($this->path($variant, $oldExtension));
            }
        }
    }

    private function path(string $variant, string $extension): string
    {
        return 'branding/logo-'.$variant.'.'.$extension;
    }

    private function assertVariant(string $variant): void
    {
        if (! in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException('Geçersiz logo varyantı.');
        }
    }
}

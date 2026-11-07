<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PdfDownloadButtonTest extends TestCase
{
    #[Test]
    public function print_layout_includes_pdf_download_script(): void
    {
        $html = view('layouts.print')->render();

        $this->assertStringContainsString('PDF indir', $html);
        $this->assertStringContainsString('html2pdf', $html);
        $this->assertStringContainsString('navigator.share', $html);
    }

    #[Test]
    public function standalone_print_pages_include_pdf_download_partial(): void
    {
        foreach ([
            'app/Modules/Companies/Resources/views/statements/index-print.blade.php',
            'app/Modules/MonthlyAccount/Resources/views/summary-print.blade.php',
            'app/Modules/MonthlyAccount/Resources/views/print.blade.php',
        ] as $file) {
            $this->assertStringContainsString("@include('partials.pdf-download')", file_get_contents(base_path($file)), $file);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Branding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branding\Services\BrandLogoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class BrandLogoController extends Controller
{
    public function __construct(private readonly BrandLogoService $logos) {}

    public function edit(): View
    {
        return view('branding::edit');
    }

    public function update(Request $request, string $variant): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $file = $request->file('logo');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['logo' => 'Geçerli bir logo dosyası seçin.']);
        }

        $this->logos->replace($variant, $file);

        return redirect()->route('branding.logos.edit')->with('success', 'Marka logosu güncellendi.');
    }
}

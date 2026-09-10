<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Http\Requests\StoreCashMovementCategoryRequest;
use App\Modules\CashBook\Http\Requests\UpdateCashMovementCategoryRequest;
use App\Modules\CashBook\Models\CashMovementCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Kasa hareket türü (kategori) yönetimi — Admin/Patron (v2.1.1-B §5).
 *
 * DÜZENLEME UCU VARDIR ama SİLME UCU YOKTUR: bir kategori asla hard-delete
 * edilmez, yalnızca `is_active = false` yapılır (kullanıcı talimatı §3).
 * `system_type` yalnızca oluştururken seçilir; düzenleme onu HİÇ kabul etmez.
 */
class CashMovementCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CashMovementCategory::class);

        return view('cashbook::categories.index', [
            'categories' => CashMovementCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CashMovementCategory::class);

        return view('cashbook::categories.create', [
            'systemTypes' => CashMovementType::selectable(),
        ]);
    }

    public function store(StoreCashMovementCategoryRequest $request): RedirectResponse
    {
        CashMovementCategory::query()->create([
            'name' => $request->validated()['name'],
            'system_type' => $request->systemType()->value,
            'requires_recipient' => $request->boolean('requires_recipient'),
            'sort_order' => $request->validated()['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return redirect()->route('cashbook.categories.index')
            ->with('success', 'Kasa hareket türü eklendi.');
    }

    public function edit(CashMovementCategory $category): View
    {
        $this->authorize('update', $category);

        return view('cashbook::categories.edit', ['category' => $category]);
    }

    public function update(UpdateCashMovementCategoryRequest $request, CashMovementCategory $category): RedirectResponse
    {
        $category->update([
            'name' => $request->validated()['name'],
            'is_active' => $request->boolean('is_active'),
            'requires_recipient' => $request->boolean('requires_recipient'),
            'sort_order' => $request->validated()['sort_order'] ?? $category->sort_order,
        ]);

        return redirect()->route('cashbook.categories.index')
            ->with('success', 'Kasa hareket türü güncellendi.');
    }
}

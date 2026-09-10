<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Enums\Permission as PermissionEnum;
use App\Modules\Authorization\Exceptions\RoleInUseException;
use App\Modules\Authorization\Exceptions\SystemRoleException;
use App\Modules\Authorization\Http\Requests\RoleRequest;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Services\RoleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('authorization::index', [
            'roles' => $this->roles->all(),
            'permissionCount' => count(PermissionEnum::cases()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('authorization::create', [
            'grouped' => $this->groupedPermissions(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->roles->create($data['name'], $data['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Rol oluşturuldu.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('authorization::edit', [
            'role' => $role,
            'grouped' => $this->groupedPermissions(),
            'assigned' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->roles->update($role, $data['name'], $data['permissions'] ?? []);
        } catch (SystemRoleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('roles.index')->with('success', 'Rol güncellendi.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        try {
            $this->roles->delete($role);
        } catch (SystemRoleException|RoleInUseException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('roles.index')->with('success', 'Rol silindi.');
    }

    /**
     * Yetkileri ekranda gruplayarak sunar.
     *
     * @return array<string, array<int, PermissionEnum>>
     */
    private function groupedPermissions(): array
    {
        $grouped = [];

        foreach (PermissionEnum::cases() as $permission) {
            $grouped[$permission->group()][] = $permission;
        }

        return $grouped;
    }
}

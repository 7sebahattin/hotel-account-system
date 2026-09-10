<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Requests;

use App\Modules\Authorization\Enums\Permission as PermissionEnum;
use App\Modules\Authorization\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? $this->user()->can('update', $role)
            : $this->user()->can('create', Role::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:100',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('roles', 'name')->ignore($role?->getKey()),
            ],
            'permissions' => ['array'],
            // Yalnızca tanımlı yetkiler kabul edilir; forma eklenen serbest
            // değerler doğrulamada elenir (CLAUDE.md §7.2).
            'permissions.*' => ['string', Rule::in(PermissionEnum::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Rol adı yalnızca küçük harf, rakam, tire ve alt çizgi içerebilir.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Authorization\Services\RoleService;
use App\Modules\Users\Exceptions\LastActiveAdminException;
use App\Modules\Users\Exceptions\SelfActionException;
use App\Modules\Users\Http\Requests\StoreUserRequest;
use App\Modules\Users\Http\Requests\UpdateUserRequest;
use App\Modules\Users\Services\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly RoleService $roles,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->when($request->string('q')->trim()->value(), function ($query, string $term): void {
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('users::index', ['users' => $users]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users::create', ['roles' => $this->roles->options()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->users->create($data, $data['role'], $request->user());

        return redirect()->route('users.index')
            ->with('success', 'Kullanıcı oluşturuldu.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', User::class);

        return view('users::edit', [
            'user' => $user,
            'roles' => $this->roles->options(),
            'isLastActiveAdmin' => $this->users->isLastActiveAdmin($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->users->update($user, $data, $data['role']);
        } catch (LastActiveAdminException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('users.index')
            ->with('success', 'Kullanıcı güncellendi.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', User::class);

        try {
            $this->users->setActive($user, ! $user->is_active, $request->user());
        } catch (LastActiveAdminException|SelfActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $user->is_active
            ? 'Kullanıcı aktifleştirildi.'
            : 'Kullanıcı pasifleştirildi.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        try {
            $this->users->delete($user, $request->user());
        } catch (LastActiveAdminException|SelfActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('users.index')
            ->with('success', 'Kullanıcı çöp kutusuna taşındı. Çöp Kutusu\'ndan geri alınabilir.');
    }
}

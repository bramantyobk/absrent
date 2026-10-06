<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(
                fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
            ))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('dashboard.users.create', ['roles' => UserRole::cases()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($this->attributes($request->validated()));

        return redirect()
            ->route('dashboard.users.index')
            ->with('status', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('dashboard.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($this->attributes($request->validated()));

        return redirect()
            ->route('dashboard.users.index')
            ->with('status', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        if ($user->hasHistory()) {
            return back()->with('error', 'Pengguna memiliki riwayat pesanan atau verifikasi. Nonaktifkan akunnya sebagai gantinya.');
        }

        $user->delete();

        return redirect()
            ->route('dashboard.users.index')
            ->with('status', 'Pengguna berhasil dihapus.');
    }

    /**
     * Status bertugas hanya berlaku untuk operator yang aktif. Password kosong saat edit berarti tidak diubah.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $data['is_on_duty'] = $data['role'] === UserRole::Operator->value
            && $data['is_active']
            && $data['is_on_duty'];

        return $data;
    }
}

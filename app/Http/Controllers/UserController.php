<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();

        $users = User::query()
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            }))
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', compact('users', 'search'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(['super_admin', 'admin'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        DB::transaction(function () use ($data) {
            $user = User::create($data);
            $user->syncRoles([$data['role']]);
            ActivityLog::record('created', $user, "Membuat akun {$user->full_name}");
        });

        return redirect()->route('users.index')->with('success', 'Akun pengguna berhasil dibuat.');
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(['super_admin', 'admin'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data) {
            $user->update($data);
            $user->syncRoles([$data['role']]);
            ActivityLog::record('updated', $user, "Memperbarui akun {$user->full_name}");
        });

        return redirect()->route('users.index')->with('success', 'Akun pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        DB::transaction(function () use ($user) {
            ActivityLog::record('deleted', $user, "Menghapus akun {$user->full_name}");
            $user->delete();
        });

        return redirect()->route('users.index')->with('success', 'Akun pengguna berhasil dihapus.');
    }
}

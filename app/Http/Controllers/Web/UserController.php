<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Admin only (route group has role:admin). */
class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::query()->orderBy('name')->paginate(15)]);
    }

    public function create()
    {
        return view('users.form', ['user' => null, 'roles' => UserRole::cases()]);
    }

    public function store(Request $request, UserService $users): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(UserRole::values())],
            'password' => ['required', Password::min(8)],
        ]);
        $data['is_active'] = true;

        $users->createByAdmin($data, $request->user());

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user, 'roles' => UserRole::cases()]);
    }

    public function update(Request $request, User $user, UserService $users): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(UserRole::values())],
            'password' => ['nullable', Password::min(8)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        if ($user->id === $request->user()->id && ($data['role'] !== UserRole::Admin->value || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'You cannot remove your own administrator access or deactivate yourself.'])->withInput();
        }

        $users->updateByAdmin($user, $data, $request->user());

        return redirect()->route('users.index')->with('status', 'User updated.');
    }
}

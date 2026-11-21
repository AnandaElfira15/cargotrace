<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Arr;

class UserService
{
    public function __construct(private readonly AuditService $audit) {}

    /** Self-registration always creates a CUSTOMER; role can never come from the request. */
    public function registerCustomer(array $data): User
    {
        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'company' => $data['company'] ?? null]);
        $user->role = UserRole::Customer;
        $user->is_active = true;
        $user->save();

        $this->audit->log('user.registered', $user, null, ['email' => $user->email, 'role' => $user->role->value], $user);

        return $user;
    }

    /** Admin-only user creation with an explicit role. */
    public function createByAdmin(array $data, User $admin): User
    {
        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'company' => $data['company'] ?? null]);
        $user->role = UserRole::from($data['role']);
        $user->is_active = (bool) ($data['is_active'] ?? true);
        $user->save();

        $this->audit->log('user.created', $user, null, ['email' => $user->email, 'role' => $user->role->value], $admin);

        return $user;
    }

    public function updateByAdmin(User $user, array $data, User $admin): User
    {
        $old = ['name' => $user->name, 'role' => $user->role->value, 'is_active' => $user->is_active];

        $user->fill(Arr::only($data, ['name', 'company']));
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->role = UserRole::from($data['role']);
        $user->is_active = (bool) ($data['is_active'] ?? false);
        $user->save();

        if (! $user->is_active) {
            $user->tokens()->delete(); // revoke API access of deactivated accounts
        }

        $this->audit->log('user.updated', $user, $old, ['name' => $user->name, 'role' => $user->role->value, 'is_active' => $user->is_active], $admin);

        return $user;
    }
}

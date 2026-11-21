<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    /**
     * "role" and "is_active" are intentionally NOT mass assignable, so a crafted
     * request body can never promote itself. Services set them explicitly.
     */
    protected $fillable = ['name', 'email', 'password', 'company'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isOperator(): bool
    {
        return $this->role === UserRole::Operator;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    /** Admin or logistics operator. */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isOperator();
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'customer_id');
    }

    public function inbox(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function unreadNotificationCount(): int
    {
        return $this->inbox()->whereNull('read_at')->count();
    }
}

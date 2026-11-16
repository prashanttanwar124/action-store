<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'pos_pin'])]
#[Hidden(['password', 'remember_token', 'pos_pin'])]
class Admin extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * The guard name for Spatie permissions.
     */
    protected string $guard_name = 'admin';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'pos_pin' => 'hashed',
        ];
    }

    /**
     * Determine whether this admin has configured a POS quick-switch PIN.
     */
    public function hasPosPin(): bool
    {
        return ! empty($this->attributes['pos_pin'] ?? null);
    }
}

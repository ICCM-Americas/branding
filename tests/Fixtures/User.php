<?php

namespace ConferenceTools\Branding\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A minimal host-style user model for the package test suite. Real host apps
 * supply their own; the package only needs an authenticatable model with an
 * `isAdmin()` for the manage-branding gate.
 */
class User extends Authenticatable
{
    use HasFactory;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /** Whether the fixture user is an admin. */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}

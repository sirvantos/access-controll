<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Identity\PublicApi\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['email', 'password', 'role', 'company_id', 'deactivated_at', 'session_version', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements Actor
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function actorId(): int
    {
        return $this->id;
    }

    public function actorRole(): Role
    {
        return $this->role;
    }

    public function actorCompanyId(): ?int
    {
        return $this->company_id;
    }

    public function sessionVersion(): int
    {
        return $this->session_version;
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public static function findByEmail(string $email): ?self
    {
        return self::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();
    }

    public static function emailIsRegistered(string $email): bool
    {
        return self::findByEmail($email) instanceof self;
    }

    /**
     * @return array{
     *     id: 'integer',
     *     email: 'string',
     *     password: 'hashed',
     *     role: 'App\Modules\Identity\PublicApi\Role',
     *     company_id: 'integer',
     *     deactivated_at: 'datetime',
     *     session_version: 'integer',
     *     email_verified_at: 'datetime',
     *     remember_token: 'string',
     *     created_at: 'datetime',
     *     updated_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'email' => 'string',
            'password' => 'hashed',
            'role' => Role::class,
            'company_id' => 'integer',
            'deactivated_at' => 'datetime',
            'session_version' => 'integer',
            'email_verified_at' => 'datetime',
            'remember_token' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

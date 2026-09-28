<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ManualTestDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public const string PASSWORD = 'password';

    public const string INVITATION_TOKEN = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function run(): void
    {
        $acme = $this->company('Acme');
        $globex = $this->company('Globex');

        $this->user('owner@example.com', Role::SuperAdmin, null);
        $this->user('admin@acme.test', Role::CompanyAdmin, $acme);
        $this->user('viewer@acme.test', Role::Viewer, $acme);
        $this->user('admin@globex.test', Role::CompanyAdmin, $globex);
        $this->user('viewer@globex.test', Role::Viewer, $globex);

        Invitation::query()->firstOrCreate(
            ['token_hash' => hash('sha256', self::INVITATION_TOKEN)],
            [
                'company_id' => $acme->id,
                'email' => 'invitee@acme.test',
                'role' => Role::Viewer,
                'expires_at' => '2030-01-15 12:00:00',
                'accepted_at' => null,
                'revoked_at' => null,
            ],
        );
    }

    private function company(string $name): Company
    {
        return Company::query()->firstOrCreate(
            ['name' => $name],
            ['deactivated_at' => null],
        );
    }

    private function user(string $email, Role $role, ?Company $company): void
    {
        User::query()->firstOrCreate(
            ['email' => $email],
            [
                'password' => self::PASSWORD,
                'role' => $role,
                'company_id' => $company?->id,
                'email_verified_at' => '2026-01-15 12:00:00',
                'deactivated_at' => null,
                'session_version' => 1,
            ],
        );
    }
}

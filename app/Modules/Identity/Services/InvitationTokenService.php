<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Invitation;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Str;

final class InvitationTokenService
{
    public const int INVITATION_TOKEN_LENGTH = 64;

    public function __construct(private CompanyContext $companyContext) {}

    /**
     * @return array{token: string, hash: string}
     */
    public function generate(): array
    {
        $token = Str::random(self::INVITATION_TOKEN_LENGTH);

        return [
            'token' => $token,
            'hash' => $this->hash($token),
        ];
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function findByToken(string $token): ?Invitation
    {
        return $this->companyContext->withoutIsolation(
            fn (): ?Invitation => Invitation::query()->where('token_hash', $this->hash($token))->first(),
        );
    }
}

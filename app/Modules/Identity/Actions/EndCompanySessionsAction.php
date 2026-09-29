<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Companies\PublicApi\CompanyDeactivated;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Facades\Password;

final class EndCompanySessionsAction
{
    public function __construct(private CompanyContext $companyContext) {}

    public function __invoke(CompanyDeactivated $event): void
    {
        $this->companyContext->run($event->companyId, function () use ($event): void {
            User::query()
                ->where('company_id', $event->companyId)
                ->orderBy('id')
                ->each(fn (User $user) => $this->endUserSession($user));
        });
    }

    private function endUserSession(User $user): void
    {
        $user->forceFill([
            'session_version' => $user->session_version + 1,
        ])->save();

        Password::broker()->getRepository()->delete($user);
    }
}

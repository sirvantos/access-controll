<?php

declare(strict_types=1);

namespace App\Support;

final class CompanyContextStore
{
    private bool $companyBound = false;

    private ?int $companyId = null;

    private ?int $actorId = null;

    private ?string $actorRole = null;

    private bool $superAdminChangeRecorded = false;

    private int $withoutIsolationDepth = 0;

    /** @var null|callable(): mixed */
    private $unboundHandler = null;

    /**
     * @param  callable(): mixed  $handler
     */
    public function registerUnboundHandler(callable $handler): void
    {
        $this->unboundHandler = $handler;
    }

    public function bindCompany(int $companyId): void
    {
        $this->companyId = $companyId;
        $this->companyBound = true;
    }

    public function unbindCompany(): void
    {
        $this->companyBound = false;
        $this->companyId = null;
    }

    public function isCompanyBound(): bool
    {
        return $this->companyBound;
    }

    public function peekCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function requireCompanyId(): int
    {
        if (! $this->companyBound) {
            $this->invokeUnboundHandler();
        }

        if ($this->companyId === null) {
            throw new \LogicException('Company context is bound but company id is missing.');
        }

        return $this->companyId;
    }

    public function invokeUnboundHandler(): mixed
    {
        if ($this->unboundHandler === null) {
            throw new \RuntimeException('Company context is not bound and no handler is registered.');
        }

        return ($this->unboundHandler)();
    }

    public function setActorId(?int $actorId): void
    {
        $this->actorId = $actorId;
    }

    public function actorId(): ?int
    {
        return $this->actorId;
    }

    public function setActorRole(?string $role): void
    {
        $this->actorRole = $role;
    }

    public function actorRole(): ?string
    {
        return $this->actorRole;
    }

    public function hasRecordedSuperAdminChange(): bool
    {
        return $this->superAdminChangeRecorded;
    }

    public function markSuperAdminChangeRecorded(): void
    {
        $this->superAdminChangeRecorded = true;
    }

    public function isWithoutIsolation(): bool
    {
        return $this->withoutIsolationDepth > 0;
    }

    public function enterWithoutIsolation(): void
    {
        $this->withoutIsolationDepth++;
    }

    public function leaveWithoutIsolation(): void
    {
        $this->withoutIsolationDepth--;
    }

    public function flushRequestBinding(): void
    {
        $this->companyBound = false;
        $this->companyId = null;
        $this->actorId = null;
        $this->actorRole = null;
        $this->superAdminChangeRecorded = false;
    }

    public function flush(): void
    {
        $this->flushRequestBinding();
        $this->withoutIsolationDepth = 0;
    }
}

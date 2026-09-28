<?php

declare(strict_types=1);

namespace Access\Factory;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class Contract
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data) {}

    public static function load(string $root): self
    {
        $parsed = Yaml::parseFile($root.'/factory/factory.yaml');
        if (! is_array($parsed)) {
            throw new RuntimeException('factory/factory.yaml must contain a map.');
        }

        /** @var array<string, mixed> $parsed */
        return new self($parsed);
    }

    public function model(string $role): string
    {
        $models = $this->data['models'] ?? null;
        $model = is_array($models) ? ($models[$role] ?? null) : null;
        if (! is_string($model) || $model === '' || $model === 'REQUIRED') {
            throw new RuntimeException('Model for '.$role.' is not set in factory/factory.yaml.');
        }

        return $model;
    }

    public function verifyCommand(): string
    {
        $command = $this->data['verify_command'] ?? null;

        return is_string($command) && $command !== '' ? $command : 'make verify';
    }

    public function maxAttempts(): int
    {
        $retries = $this->data['max_retries'] ?? 2;

        return (is_int($retries) ? $retries : 2) + 1;
    }

    public function maxConvergeRounds(): int
    {
        $rounds = $this->data['max_converge_rounds'] ?? 2;

        return is_int($rounds) ? $rounds : 2;
    }

    public function agentTimeout(): int
    {
        $timeout = $this->data['agent_timeout_seconds'] ?? 4800;

        return is_int($timeout) ? $timeout : 4800;
    }

    /**
     * @return array<string, mixed>
     */
    public function cliConfig(string $role): array
    {
        $deny = [
            'Write(.specify/**)',
            'Write(.cursor/**)',
            'Write(factory/**)',
            'Write(.github/**)',
            'Write(.gitlab-ci.yml)',
            'Write(.gitlab/**)',
            'Write(Makefile)',
            'Write(phpstan.neon)',
            'Write(deptrac.php)',
            'Write(phpunit.xml)',
        ];

        if ($role === 'implementer') {
            array_push(
                $deny,
                'Write(specs/**/spec.md)',
                'Write(specs/**/plan.md)',
                'Write(specs/**/tasks.md)',
                'Write(specs/**/research.md)',
                'Write(specs/**/data-model.md)',
                'Write(specs/**/quickstart.md)',
                'Write(specs/**/constraints.md)',
                'Write(specs/**/contracts/**)',
                'Write(specs/**/checklists/**)',
            );
        }

        return [
            'permissions' => [
                'allow' => [],
                'deny' => $deny,
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Access\Factory;

use Symfony\Component\Process\Process;

final class CursorAgent implements AgentClient
{
    public function __construct(private int $timeoutSeconds) {}

    public function run(string $cwd, string $model, string $prompt, ?string $chatId): AgentReply
    {
        $command = [
            'cursor-agent',
            '-p',
            '--output-format',
            'stream-json',
            '--model',
            $model,
            '--trust',
            '--force',
            '--workspace',
            $cwd,
        ];

        if ($chatId !== null && $chatId !== '') {
            $command[] = '--resume';
            $command[] = $chatId;
        }

        $command[] = $prompt;

        $process = new Process($command, $cwd);
        $process->setTimeout($this->timeoutSeconds);
        $process->run();

        $stream = $process->getOutput()."\n".$process->getErrorOutput();
        if (! $process->isSuccessful() && trim($process->getOutput()) === '') {
            throw new FactoryStop('agent_failed', trim($process->getErrorOutput()));
        }

        return AgentReply::fromStream($stream);
    }
}

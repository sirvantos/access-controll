<?php

declare(strict_types=1);

use Access\Factory\Contract;
use Access\Factory\CursorAgent;
use Access\Factory\FactoryStop;
use Access\Factory\GitRepo;
use Access\Factory\RunInput;
use Access\Factory\ScriptFeatureScaffolder;
use Access\Factory\Workflow;

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);
$command = $argv[1] ?? '';
$contract = Contract::load($root);

$workflow = new Workflow(
    $root,
    $contract,
    new GitRepo($root),
    new CursorAgent($contract->agentTimeout()),
    new ScriptFeatureScaffolder,
);

try {
    match ($command) {
        'vet' => vet($workflow),
        'graph' => fwrite(STDOUT, $workflow->graphText()."\n"),
        'run' => run($workflow, RunInput::description($root, array_slice($argv, 2))),
        'status' => fwrite(STDOUT, $workflow->statusText()."\n"),
        'resume' => $workflow->resume(),
        'stale' => stale($workflow),
        'unlock' => $workflow->unlock(),
        default => usage(),
    };
} catch (FactoryStop $stop) {
    fwrite(STDERR, $stop->reason.': '.$stop->getMessage()."\n");
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}

exit(0);

function vet(Workflow $workflow): void
{
    $problems = $workflow->vet();
    if ($problems === []) {
        fwrite(STDOUT, "factory contract is valid\n");

        return;
    }

    foreach ($problems as $problem) {
        fwrite(STDERR, $problem."\n");
    }

    exit(1);
}

function run(Workflow $workflow, string $description): void
{
    if (trim($description) === '') {
        fwrite(STDERR, "Pass a feature description or -i <file>.\n");
        exit(1);
    }

    $workflow->start($description);
    fwrite(STDOUT, $workflow->statusText()."\n");
}

function stale(Workflow $workflow): void
{
    $ids = $workflow->staleIds();
    if ($ids === []) {
        fwrite(STDOUT, "no stale tasks\n");

        return;
    }

    fwrite(STDOUT, implode("\n", $ids)."\n");
    exit(2);
}

function usage(): never
{
    fwrite(STDERR, "Usage: php factory/orchestrate.php vet|graph|run [-i <file>]|status|resume|stale|unlock\n");
    exit(1);
}

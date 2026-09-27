<?php

declare(strict_types=1);

use Access\Factory\Contract;
use Access\Factory\CursorAgent;
use Access\Factory\FactoryLog;
use Access\Factory\FactoryStop;
use Access\Factory\GitRepo;
use Access\Factory\RunInput;
use Access\Factory\ScriptFeatureScaffolder;
use Access\Factory\Workflow;

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);
[$verbose, $arguments] = FactoryLog::extractVerbose($argv);
$command = $arguments[1] ?? '';
$log = new FactoryLog($root, $verbose);
$log->info($command !== '' ? $command.($verbose ? ' verbose' : '') : 'usage');
$contract = Contract::load($root);

$workflow = new Workflow(
    $root,
    $contract,
    new GitRepo($root),
    new CursorAgent($contract->agentTimeout(), $log),
    new ScriptFeatureScaffolder,
    $log,
);

try {
    match ($command) {
        'vet' => vet($workflow),
        'graph' => fwrite(STDOUT, $workflow->graphText()."\n"),
        'run' => run($workflow, RunInput::description($root, array_slice($arguments, 2))),
        'status' => fwrite(STDOUT, $workflow->statusText()."\n"),
        'resume' => $workflow->resume(),
        'stale' => stale($workflow),
        'unlock' => $workflow->unlock(),
        default => usage(),
    };
} catch (FactoryStop $stop) {
    $log->info('exit 2 '.$stop->reason);
    fwrite(STDERR, $stop->reason.': '.$stop->getMessage()."\n");
    exit(2);
} catch (Throwable $exception) {
    $log->info('exit 1 '.$exception->getMessage());
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
    fwrite(STDERR, "Usage: php factory/orchestrate.php [-v] vet|graph|run [-i <file>]|status|resume|stale|unlock\n");
    exit(1);
}

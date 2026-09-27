<?php

declare(strict_types=1);

namespace Access\Factory;

use RuntimeException;
use Symfony\Component\Process\Process;

final class Workflow
{
    private const STEPS = ['specify', 'clarify', 'plan', 'tasks', 'analyze', 'implement', 'converge', 'done'];

    public function __construct(
        private string $root,
        private Contract $contract,
        private GitRepo $git,
        private AgentClient $agent,
        private FeatureScaffolder $scaffolder,
    ) {}

    public function start(string $description): void
    {
        if ($this->statePath() !== null && is_file($this->statePath())) {
            throw new FactoryStop('already_started', 'This feature already has factory state. Use resume.');
        }

        $created = $this->scaffolder->create($this->root, $description);
        $this->git->ensureBranch($created['branch']);
        $this->git->commit($this->root, 'start '.$created['branch'], [
            $created['dir'],
            '.specify/feature.json',
        ]);

        $this->save(new FactoryState(
            $created['dir'],
            $created['branch'],
            $description,
            'specify',
            null,
            null,
            0,
            [],
        ));

        $this->continueRun();
    }

    public function resume(): void
    {
        $state = $this->load();
        if ($state->next === 'done') {
            return;
        }

        if ($state->next === 'stopped') {
            $this->reopen($state);
        }

        $this->continueRun();
    }

    public function statusText(): string
    {
        $state = $this->load();
        $reason = $state->stop['reason'] ?? '';

        return $state->featureDir.' '.$state->next.($reason !== '' ? ' ('.$reason.')' : '');
    }

    public function graphText(): string
    {
        $lines = [];
        foreach ($this->board() as $task) {
            $deps = $task->dependsOn === [] ? '' : ' depends on '.implode(', ', $task->dependsOn);
            $lines[] = $task->phase.' '.$task->id.($task->done ? ' done' : ' open').$deps;
        }

        return $lines === [] ? 'No tasks yet.' : implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    public function staleIds(): array
    {
        $state = $this->load();
        $tasks = $this->tasksById();
        $stale = [];

        foreach ($state->tasks as $id => $record) {
            if (! isset($tasks[$id]) || $record['spec_hash'] === '') {
                continue;
            }

            if ($record['spec_hash'] !== $this->hash($state->featureDir, $tasks[$id])) {
                $stale[] = $id;
            }
        }

        return $stale;
    }

    public function unlock(): void
    {
        $state = $this->load();
        $state->lock = null;
        $this->save($state);
    }

    /**
     * @return list<string>
     */
    public function vet(): array
    {
        $problems = [];

        foreach (['spec_author', 'implementer', 'reviewer'] as $role) {
            try {
                $this->contract->model($role);
            } catch (RuntimeException $exception) {
                $problems[] = $exception->getMessage();
            }
        }

        $cli = json_decode((string) file_get_contents($this->root.'/.cursor/cli.json'), true);
        if (! is_array($cli) || ! is_array($cli['permissions']['allow'] ?? null)) {
            $problems[] = '.cursor/cli.json needs permissions.allow.';
        }

        $tasks = $this->root.'/'.$this->featureDir().'/tasks.md';
        if (is_file($tasks)) {
            try {
                TaskBoard::order(TaskBoard::parse((string) file_get_contents($tasks)));
            } catch (RuntimeException $exception) {
                $problems[] = $exception->getMessage();
            }
        }

        return $problems;
    }

    private function continueRun(): void
    {
        $this->acquireLock();

        try {
            while (true) {
                $state = $this->load();
                if ($state->next === 'done' || $state->next === 'stopped') {
                    return;
                }

                $this->git->ensureBranch($state->branch);
                $this->dispatch($state->next);
            }
        } finally {
            $this->releaseLock();
        }
    }

    private function dispatch(string $step): void
    {
        if (in_array($step, ['specify', 'clarify', 'plan', 'tasks'], true)) {
            $this->authorStep($step);

            return;
        }

        if ($step === 'analyze') {
            $this->analyze();

            return;
        }

        if ($step === 'implement') {
            $this->implement();

            return;
        }

        if ($step === 'converge') {
            $this->converge();

            return;
        }

        throw new RuntimeException('Unknown factory step '.$step.'.');
    }

    private function authorStep(string $step): void
    {
        $state = $this->load();
        $worktree = $this->prepareWorktree($step, 'spec_author');
        $reply = $this->invoke($worktree, 'spec_author', $this->stepPrompt($step, $state), null, $step, 1);
        $this->sealWorktree($worktree, $state->featureDir);

        try {
            $this->assertCleanRole('spec_author', $state->featureDir, $worktree);
        } catch (FactoryStop $stop) {
            $this->git->discard($worktree);
            $this->stop($stop->reason, $stop->getMessage());
        }

        if ($reply->status === 'spec_gap') {
            $this->ensureQuestions($worktree, $state, $reply);
            $this->publishWorktree($worktree, $step, 'record clarify questions');
            $this->stop('unresolved_questions', 'Answer '.$state->featureDir.'/.factory/questions.md and run resume.');
        }

        if ($reply->status !== 'done') {
            $this->git->discard($worktree);
            $this->stop('agent_failed', $step.' did not finish with status done.');
        }

        $this->publishWorktree($worktree, $step, $step.' '.$state->featureDir);
        $this->advance($step);
    }

    private function analyze(): void
    {
        $state = $this->load();
        $worktree = $this->prepareWorktree('analyze', 'reviewer');
        $reply = $this->invoke($worktree, 'reviewer', $this->stepPrompt('analyze', $state), null, 'analyze', 1);
        $this->sealWorktree($worktree, $state->featureDir);

        if ($this->git->changedFiles($worktree) !== []) {
            $this->git->discard($worktree);
            $this->git->removeWorktree($worktree, 'factory/analyze');
            $this->stop('analyze_failure', 'Analyze changed files. The verdict was discarded.');
        }

        $this->git->removeWorktree($worktree, 'factory/analyze');

        if (! $reply->reviewAccepted()) {
            $this->stop('analyze_failure', 'Analyze requested changes. Implementation did not start.');
        }

        $this->advance('analyze');
    }

    private function implement(): void
    {
        foreach ($this->board() as $task) {
            if ($task->done) {
                continue;
            }

            $this->implementTask($task);
        }

        $this->advance('implement');
    }

    private function implementTask(TaskItem $task): void
    {
        $record = $this->load()->task($task->id);
        $attempts = $record['attempts'];
        $chatId = $record['chat_id'];

        while ($attempts < $this->contract->maxAttempts()) {
            $attempts++;
            $state = $this->load();
            $state->putTask($task->id, $attempts, 'running', $chatId, '');
            $this->save($state);

            $worktree = $this->prepareWorktree($task->id, 'implementer');
            $reply = $this->invoke(
                $worktree,
                'implementer',
                $this->implementPrompt($task),
                $chatId,
                $task->id,
                $attempts,
            );
            $chatId = $reply->chatId ?? $chatId;
            $featureDir = $this->load()->featureDir;
            $this->sealWorktree($worktree, $featureDir);

            try {
                $this->assertCleanRole('implementer', $featureDir, $worktree);
            } catch (FactoryStop $stop) {
                $this->git->discard($worktree);
                $this->rememberChat($task->id, $attempts, $chatId);
                if ($attempts >= $this->contract->maxAttempts()) {
                    $this->stop($stop->reason, $stop->getMessage());
                }

                continue;
            }

            if ($reply->status === 'spec_gap') {
                $this->ensureQuestions($worktree, $this->load(), $reply);
                $this->publishWorktree($worktree, $task->id, 'record spec gap for '.$task->id);
                $this->stop('unresolved_questions', 'The implementer reported a spec gap for '.$task->id.'.');
            }

            if ($reply->status !== 'done') {
                $this->rememberChat($task->id, $attempts, $chatId);
                $this->git->discard($worktree);
                $this->git->removeWorktree($worktree, '');

                continue;
            }

            $this->git->commit($worktree, $task->id.' attempt '.$attempts);

            if (! $this->verify($worktree, $task->id, $attempts) || $reply->blocksApproval() || ! $this->reviewsPass($worktree, $task, $attempts)) {
                $this->rememberChat($task->id, $attempts, $chatId);
                $this->git->removeWorktree($worktree, '');

                continue;
            }

            $this->git->removeWorktree($worktree, '');
            $this->git->squashMerge('factory/'.$task->id);
            $tasksPath = $this->root.'/'.$featureDir.'/tasks.md';
            file_put_contents($tasksPath, TaskBoard::markDone((string) file_get_contents($tasksPath), $task->id));
            $state = $this->load();
            $state->putTask($task->id, $attempts, 'done', $chatId, $this->hash($featureDir, $task));
            $this->save($state);
            $this->git->commit($this->root, $task->id.' '.$task->description);
            $this->git->removeWorktree('', 'factory/'.$task->id);

            return;
        }

        $this->stop('retries_exhausted', $task->id.' used '.$this->contract->maxAttempts().' attempts.');
    }

    private function reviewsPass(string $worktree, TaskItem $task, int $attempt): bool
    {
        foreach (['quality_reviewer', 'functional_reviewer'] as $prompt) {
            $before = $this->git->changedFiles($worktree);
            $reply = $this->invoke(
                $worktree,
                'reviewer',
                $this->read($this->root.'/factory/prompts/'.$prompt.'.md')."\n\n".$this->taskContext($task),
                null,
                $task->id.'-'.$prompt,
                $attempt,
            );

            if ($this->git->changedFiles($worktree) !== $before) {
                $this->git->discard($worktree);

                return false;
            }

            if (! $reply->reviewAccepted()) {
                return false;
            }
        }

        return true;
    }

    private function converge(): void
    {
        $state = $this->load();
        if ($state->convergeRounds >= $this->contract->maxConvergeRounds()) {
            $this->stop('retries_exhausted', 'Converge added tasks more than '.$this->contract->maxConvergeRounds().' times.');
        }

        $before = array_map(static fn (TaskItem $task): string => $task->id, $this->board());
        $worktree = $this->prepareWorktree('converge', 'converge');
        $reply = $this->invoke($worktree, 'reviewer', $this->stepPrompt('converge', $state), null, 'converge', $state->convergeRounds + 1);
        $this->sealWorktree($worktree, $state->featureDir);

        try {
            $this->assertCleanRole('converge', $state->featureDir, $worktree);
        } catch (FactoryStop $stop) {
            $this->git->discard($worktree);
            $this->stop($stop->reason, $stop->getMessage());
        }

        if ($reply->status !== 'done' && $reply->status !== 'approve') {
            $this->git->discard($worktree);
            $this->stop('agent_failed', 'Converge did not finish.');
        }

        $this->publishWorktree($worktree, 'converge', 'converge '.$state->featureDir);
        $after = array_map(static fn (TaskItem $task): string => $task->id, $this->board());
        $state = $this->load();
        $state->convergeRounds++;

        if (array_values(array_diff($after, $before)) !== []) {
            $state->next = 'implement';
            $this->save($state);

            return;
        }

        $state->next = 'done';
        $state->stop = null;
        $this->save($state);
    }

    private function implementPrompt(TaskItem $task): string
    {
        return $this->read($this->root.'/factory/prompts/implementer.md')
            ."\n\n".$this->taskContext($task)
            ."\n\nDo not run make verify. Do not commit.";
    }

    private function taskContext(TaskItem $task): string
    {
        $dir = $this->load()->featureDir;
        $plan = $this->root.'/'.$dir.'/plan.md';
        $parts = [
            $this->read($this->root.'/.specify/memory/constitution.md'),
            $this->read($this->root.'/'.$dir.'/spec.md'),
            $this->storyExcerpt($dir, $task->story),
            is_file($plan) ? $this->read($plan) : '',
            $task->source,
        ];

        return trim(implode("\n\n", array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    private function storyExcerpt(string $featureDir, ?string $story): string
    {
        if ($story === null || preg_match('/US(\d+)/', $story, $match) !== 1) {
            return '';
        }

        $spec = $this->read($this->root.'/'.$featureDir.'/spec.md');
        $start = strpos($spec, 'User Story '.$match[1]);
        if ($start === false) {
            return '';
        }

        $rest = substr($spec, $start);
        $next = strpos($rest, "\n## ");

        return $next === false ? $rest : substr($rest, 0, $next);
    }

    private function stepPrompt(string $step, FactoryState $state): string
    {
        $skill = match ($step) {
            'specify' => 'speckit-specify',
            'clarify' => 'speckit-clarify',
            'plan' => 'speckit-plan',
            'tasks' => 'speckit-tasks',
            'analyze' => 'speckit-analyze',
            'converge' => 'speckit-converge',
            default => throw new RuntimeException('No skill for '.$step.'.'),
        };

        $questions = $state->featureDir.'/.factory/questions.md';
        $closing = $step === 'analyze'
            ? '{"verdict":"approve","issues":[],"assumptions":[]}'
            : '{"status":"done","summary":"","files_changed":[],"assumptions":[]}';

        return <<<PROMPT
        Follow .cursor/skills/{$skill}/SKILL.md for {$state->featureDir}.
        The feature directory and branch already exist. Do not run git. Do not run Speckit git hooks.
        Feature description: {$state->description}
        If you need a human answer, append the question to {$questions} and finish with status spec_gap.
        Do not invent missing requirements.
        The entire final message is one JSON object and nothing else:
        {$closing}
        PROMPT;
    }

    private function invoke(string $cwd, string $role, string $prompt, ?string $chatId, string $name, int $attempt): AgentReply
    {
        $model = match ($role) {
            'implementer' => $this->contract->model('implementer'),
            'spec_author' => $this->contract->model('spec_author'),
            default => $this->contract->model('reviewer'),
        };
        $reply = $this->agent->run($cwd, $model, $prompt, $chatId);
        $this->writeLog($name, $attempt, $reply->text);

        return $reply;
    }

    private function verify(string $cwd, string $taskId, int $attempt): bool
    {
        $process = Process::fromShellCommandline($this->contract->verifyCommand(), $cwd);
        $process->setTimeout($this->contract->agentTimeout());
        $process->run();
        $this->writeLog($taskId.'-verify', $attempt, $process->getOutput()."\n".$process->getErrorOutput());

        return $process->isSuccessful();
    }

    private function prepareWorktree(string $name, string $role): string
    {
        $state = $this->load();
        $relative = $state->featureDir.'/.factory/state.json';
        if (is_file($this->root.'/'.$relative)) {
            $this->git->commit($this->root, 'factory state', [$relative]);
        }

        $safe = str_replace('/', '-', $name);
        $path = $this->root.'/.worktrees/'.$safe;
        if (! is_dir($this->root.'/.worktrees')) {
            mkdir($this->root.'/.worktrees', 0777, true);
        }

        $this->git->worktreeAdd($path, 'factory/'.$safe, 'HEAD');
        $this->git->linkDependencies($path);
        $roleName = $role === 'converge' ? 'converge' : $role;
        $this->git->writeJson($path.'/.cursor/cli.json', $this->contract->cliConfig($roleName));

        return $path;
    }

    private function sealWorktree(string $worktree, string $featureDir): void
    {
        $this->git->restore($worktree, '.cursor/cli.json');
        $stateFile = $featureDir.'/.factory/state.json';
        if (is_file($worktree.'/'.$stateFile)) {
            $this->git->restore($worktree, $stateFile);
        }
    }

    private function assertCleanRole(string $role, string $featureDir, string $worktree): void
    {
        $violations = DiffGuard::violations($role, $featureDir, $this->git->changedFiles($worktree));
        if ($violations !== []) {
            throw new FactoryStop('protected_path', 'Forbidden changes: '.implode(', ', $violations));
        }
    }

    private function publishWorktree(string $worktree, string $branchSuffix, string $message): void
    {
        $branch = 'factory/'.str_replace('/', '-', $branchSuffix);
        $this->git->commit($worktree, $message);
        $this->git->removeWorktree($worktree, '');
        $this->git->squashMerge($branch);
        $this->git->commit($this->root, $message);
        $this->git->removeWorktree('', $branch);
    }

    private function advance(string $step): void
    {
        $state = $this->load();
        $position = array_search($step, self::STEPS, true);
        $state->next = is_int($position) ? (self::STEPS[$position + 1] ?? 'done') : 'done';
        $state->stop = null;
        $this->save($state);
    }

    private function stop(string $reason, string $detail): never
    {
        $state = $this->load();
        $resumeAt = $state->next;
        $state->next = 'stopped';
        $state->stop = [
            'reason' => $reason,
            'detail' => $detail,
            'resume_at' => $resumeAt,
        ];
        $this->save($state);

        throw new FactoryStop($reason, $detail);
    }

    private function reopen(FactoryState $state): void
    {
        $reason = $state->stop['reason'] ?? '';
        $resumeAt = $reason === 'unresolved_questions' ? 'clarify' : ($state->stop['resume_at'] ?? '');
        if (! in_array($resumeAt, self::STEPS, true) || $resumeAt === 'done') {
            throw new FactoryStop($reason, $state->stop['detail'] ?? 'The factory is stopped.');
        }

        if ($reason === 'retries_exhausted' && $resumeAt === 'implement') {
            foreach ($state->tasks as $id => $record) {
                if ($record['status'] !== 'done') {
                    $state->putTask($id, 0, 'pending', $record['chat_id'], $record['spec_hash']);
                }
            }
        }

        if ($reason === 'retries_exhausted' && $resumeAt === 'converge') {
            $state->convergeRounds = 0;
        }

        $state->next = $resumeAt;
        $state->stop = null;
        $this->save($state);
    }

    private function rememberChat(string $taskId, int $attempts, ?string $chatId): void
    {
        $state = $this->load();
        $current = $state->task($taskId);
        $state->putTask($taskId, $attempts, 'retry', $chatId, $current['spec_hash']);
        $this->save($state);
    }

    private function ensureQuestions(string $worktree, FactoryState $state, AgentReply $reply): void
    {
        $path = $worktree.'/'.$state->featureDir.'/.factory/questions.md';
        if (is_file($path)) {
            return;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $summary = $reply->payload['summary'] ?? null;
        file_put_contents($path, (is_string($summary) && $summary !== '' ? $summary : $reply->text)."\n");
    }

    /**
     * @return list<TaskItem>
     */
    private function board(): array
    {
        $path = $this->root.'/'.$this->load()->featureDir.'/tasks.md';
        if (! is_file($path)) {
            return [];
        }

        return TaskBoard::order(TaskBoard::parse((string) file_get_contents($path)));
    }

    /**
     * @return array<string, TaskItem>
     */
    private function tasksById(): array
    {
        $tasks = [];
        foreach ($this->board() as $task) {
            $tasks[$task->id] = $task;
        }

        return $tasks;
    }

    private function hash(string $featureDir, TaskItem $task): string
    {
        $planPath = $this->root.'/'.$featureDir.'/plan.md';
        $plan = is_file($planPath) ? (string) file_get_contents($planPath) : '';
        $replaced = preg_replace('/^- \[[ xX]\]/', '- [ ]', $task->source);
        $line = is_string($replaced) ? $replaced : $task->source;

        return hash('sha256', $this->read($this->root.'/'.$featureDir.'/spec.md')."\n".$plan."\n".$line);
    }

    private function acquireLock(): void
    {
        $state = $this->load();
        $pid = $state->lock['pid'] ?? 0;
        if ($pid > 0 && $this->pidAlive($pid)) {
            throw new FactoryStop('locked', 'Factory is locked by pid '.$pid.'. Use unlock if that process is gone.');
        }

        $state->lock = ['pid' => $this->currentPid(), 'at' => gmdate('c')];
        $this->save($state);
    }

    private function releaseLock(): void
    {
        $path = $this->statePath();
        if ($path === null || ! is_file($path)) {
            return;
        }

        $state = $this->load();
        $state->lock = null;
        $this->save($state);
        $relative = $state->featureDir.'/.factory/state.json';
        if (is_file($this->root.'/'.$relative)) {
            $this->git->commit($this->root, 'factory state', [$relative]);
        }
    }

    private function currentPid(): int
    {
        $pid = getmypid();

        return is_int($pid) ? $pid : 0;
    }

    private function pidAlive(int $pid): bool
    {
        if ($pid <= 0 || ! function_exists('posix_kill')) {
            return true;
        }

        return posix_kill($pid, 0);
    }

    private function save(FactoryState $state): void
    {
        $this->git->writeJson($this->root.'/'.$state->featureDir.'/.factory/state.json', $state->toArray());
    }

    private function load(): FactoryState
    {
        $path = $this->statePath();
        if ($path === null || ! is_file($path)) {
            throw new FactoryStop('missing_state', 'No factory state. Start with run and a feature description.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Factory state is unreadable.');
        }

        return FactoryState::fromArray($decoded);
    }

    private function statePath(): ?string
    {
        $dir = $this->featureDir();
        if ($dir === '') {
            return null;
        }

        return $this->root.'/'.$dir.'/.factory/state.json';
    }

    private function featureDir(): string
    {
        $path = $this->root.'/.specify/feature.json';
        if (! is_file($path)) {
            return '';
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $dir = is_array($decoded) ? ($decoded['feature_directory'] ?? '') : '';

        return is_string($dir) ? $dir : '';
    }

    private function writeLog(string $name, int $attempt, string $body): void
    {
        $directory = $this->root.'/factory/runs/'.$name.'/attempt-'.$attempt;
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($directory.'/agent.log', $body);
    }

    private function read(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : '';
    }
}

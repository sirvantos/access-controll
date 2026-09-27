<?php

declare(strict_types=1);

namespace Access\Factory;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
        private ?FactoryLog $log = null,
    ) {}

    public function start(string $description): void
    {
        if ($this->statePath() !== null && is_file($this->statePath())) {
            throw new FactoryStop('already_started', 'This feature already has factory state. Use resume.');
        }

        $existing = $this->featureDir();
        if ($existing !== '' && is_file($this->root.'/'.$existing.'/spec.md')) {
            $created = ['branch' => basename($existing), 'dir' => $existing];
            $this->note('continue scaffold '.$existing);
        } else {
            $this->note('create feature from brief');
            $created = $this->scaffolder->create($this->root, $description);
            $this->note('branch '.$created['branch'].' directory '.$created['dir']);
        }

        $this->git->ensureBranch($created['branch']);

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
        $this->note('resume '.$state->featureDir.' at '.$state->next);
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

        foreach (['spec_author', 'spec_editor', 'implementer', 'reviewer'] as $role) {
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
                $this->note('step '.$state->next);
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
        $model = $this->authorModel($step);
        $worktree = $this->prepareWorktree($step, 'spec_author');
        $this->overlayFeature($worktree, $state->featureDir);
        $reply = $this->invoke($worktree, 'spec_author', $this->stepPrompt($step, $state), $this->authorChat($model), $step, 1, $model);
        $this->rememberAuthor($reply->chatId, $model);
        $this->sealWorktree($worktree, $state->featureDir);

        try {
            $this->assertCleanRole('spec_author', $state->featureDir, $worktree);
        } catch (FactoryStop $stop) {
            $this->git->discard($worktree);
            $this->stop($stop->reason, $stop->getMessage());
        }

        if ($reply->status === 'spec_gap') {
            $this->ensureQuestions($worktree, $state, $reply);
            $this->keepDraft($worktree, 'factory/'.$step, 'record clarify questions');
            $this->mirrorFeature($worktree, $state->featureDir);
            $this->git->removeWorktree($worktree, 'factory/'.$step);
            $this->stop('unresolved_questions', 'Answer '.$state->featureDir.'/.factory/questions.md and run resume.');
        }

        if ($reply->status !== 'done') {
            $this->git->discard($worktree);
            $this->stop('agent_failed', $step.' did not finish with status done.');
        }

        $this->keepDraft($worktree, 'factory/'.$step, $step.' '.$state->featureDir);
        $this->mirrorFeature($worktree, $state->featureDir);
        $this->git->removeWorktree($worktree, 'factory/'.$step);
        $this->advance($step);
    }

    private function analyze(): void
    {
        $state = $this->load();
        $repairs = 0;
        $max = $this->contract->maxAttempts();
        $round = 0;

        while (true) {
            $round++;
            $reply = $this->runAnalyze($state, $round);
            if ($this->analyzeClear($reply)) {
                $this->publishDraft('spec '.$state->featureDir, $state->featureDir);
                $this->advance('analyze');

                return;
            }

            $issues = $reply->actionableIssues();
            if ($issues === [] || $repairs >= $max) {
                $this->stop('analyze_failure', 'Analyze requested changes. Implementation did not start.');
            }

            $repairs++;
            $this->note('repair analyze findings with spec_editor');
            $this->repairAnalyze($state, $issues, $repairs);
        }
    }

    private function runAnalyze(FactoryState $state, int $attempt): AgentReply
    {
        $worktree = $this->prepareWorktree('analyze', 'reviewer');
        $this->overlayFeature($worktree, $state->featureDir);
        $reviewEdits = array_values(array_filter(
            $this->git->changedFiles($worktree),
            fn (string $path): bool => str_starts_with($path, $state->featureDir.'/'),
        ));
        if ($reviewEdits !== []) {
            $this->git->commit($worktree, 'apply review edits', [$state->featureDir]);
            $this->git->pointBranch('factory/draft', 'factory/analyze');
        }

        $reply = $this->invoke($worktree, 'reviewer', $this->stepPrompt('analyze', $state), null, 'analyze', $attempt);
        $this->sealWorktree($worktree, $state->featureDir);

        if ($this->git->changedFiles($worktree) !== []) {
            $this->git->discard($worktree);
            $this->git->removeWorktree($worktree, 'factory/analyze');
            $this->stop('analyze_failure', 'Analyze changed files. The verdict was discarded.');
        }

        $this->git->removeWorktree($worktree, 'factory/analyze');

        return $reply;
    }

    private function analyzeClear(AgentReply $reply): bool
    {
        if ($reply->blocksApproval() || ! in_array($reply->status, ['approve', 'changes_requested'], true)) {
            return false;
        }

        return $reply->actionableIssues() === [];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function repairAnalyze(FactoryState $state, array $issues, int $attempt): void
    {
        $model = $this->contract->model('spec_editor');
        $worktree = $this->prepareWorktree('analyze-fix', 'spec_author');
        $this->overlayFeature($worktree, $state->featureDir);
        $reply = $this->invoke(
            $worktree,
            'spec_author',
            $this->repairPrompt($state, $issues),
            $this->authorChat($model),
            'analyze-fix',
            $attempt,
            $model,
        );
        $this->rememberAuthor($reply->chatId, $model);
        $this->sealWorktree($worktree, $state->featureDir);

        try {
            $this->assertCleanRole('spec_author', $state->featureDir, $worktree);
        } catch (FactoryStop $stop) {
            $this->git->discard($worktree);
            $this->stop($stop->reason, $stop->getMessage());
        }

        if ($reply->status !== 'done' && $reply->status !== 'spec_gap') {
            $this->note('analyze repair status '.$reply->status);
            $this->git->discard($worktree);
            $this->git->removeWorktree($worktree, 'factory/analyze-fix');

            return;
        }

        $this->keepDraft($worktree, 'factory/analyze-fix', 'repair analyze findings');
        $this->mirrorFeature($worktree, $state->featureDir);
        $this->git->removeWorktree($worktree, 'factory/analyze-fix');
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function repairPrompt(FactoryState $state, array $issues): string
    {
        $encoded = json_encode($issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $findings = is_string($encoded) ? $encoded : '[]';

        return <<<PROMPT
        ANALYZE_FIX
        Apply these analyze findings to {$state->featureDir}. Edit the spec, plan, or tasks so each finding is resolved.
        Do not stop for a human. Do not return spec_gap. Apply the fix stated in the finding.
        Do not run git. Do not run Speckit git hooks. Do not invent requirements that the findings do not state.
        Findings:
        {$findings}
        The entire final message is one JSON object and nothing else:
        {"status":"done","summary":"","files_changed":[],"assumptions":[]}
        PROMPT;
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
        $this->note('task '.$task->id);

        while ($attempts < $this->contract->maxAttempts()) {
            $attempts++;
            $state = $this->load();
            $state->putTask($task->id, $attempts, 'running', $chatId, '');
            $this->save($state);

            $this->note('task '.$task->id.' attempt '.$attempts.' of '.$this->contract->maxAttempts());
            $worktree = $this->prepareWorktree($task->id, 'implementer');
            $this->overlayFeature($worktree, $state->featureDir);
            $reply = $this->invoke(
                $worktree,
                'implementer',
                $this->implementPrompt($task, $attempts),
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
                $this->note('spec gap '.$task->id);
                $this->ensureQuestions($worktree, $this->load(), $reply);
                $this->keepDraft($worktree, 'factory/'.$task->id, 'record spec gap for '.$task->id);
                $this->mirrorFeature($worktree, $featureDir);
                $this->git->removeWorktree($worktree, 'factory/'.$task->id);
                $this->stop('unresolved_questions', 'The implementer reported a spec gap for '.$task->id.'.');
            }

            if ($reply->status !== 'done') {
                $this->note('task '.$task->id.' status '.$reply->status);
                $this->rememberChat($task->id, $attempts, $chatId);
                $this->git->discard($worktree);
                $this->git->removeWorktree($worktree, '');

                continue;
            }

            $this->git->commit($worktree, $task->id.' attempt '.$attempts);

            if (! $this->verify($worktree, $task->id, $attempts) || $reply->blocksApproval() || ! $this->reviewsPass($worktree, $task, $attempts)) {
                $this->note('task '.$task->id.' attempt '.$attempts.' rejected');
                $this->rememberChat($task->id, $attempts, $chatId);
                $this->git->removeWorktree($worktree, '');

                continue;
            }

            $tasksPath = $worktree.'/'.$featureDir.'/tasks.md';
            file_put_contents($tasksPath, TaskBoard::markDone((string) file_get_contents($tasksPath), $task->id));
            $this->git->commit($worktree, $task->id.' '.$task->description);
            $this->git->pointBranch('factory/draft', 'factory/'.$task->id);
            $this->mirrorFeature($worktree, $featureDir);
            $this->git->removeWorktree($worktree, 'factory/'.$task->id);
            $state = $this->load();
            $state->putTask($task->id, $attempts, 'done', $chatId, $this->hash($featureDir, $task));
            $this->save($state);
            $this->note('drafted '.$task->id);

            return;
        }

        $this->stop('retries_exhausted', $task->id.' used '.$this->contract->maxAttempts().' attempts.');
    }

    private function reviewsPass(string $worktree, TaskItem $task, int $attempt): bool
    {
        foreach (['quality_reviewer', 'functional_reviewer'] as $prompt) {
            $this->note('review '.$prompt.' for '.$task->id);
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
                $this->note('review '.$prompt.' edited files');
                $this->git->discard($worktree);

                return false;
            }

            $this->note('review '.$prompt.' '.$reply->status);
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
        $this->overlayFeature($worktree, $state->featureDir);
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

        $this->keepDraft($worktree, 'factory/converge', 'converge '.$state->featureDir);
        $this->mirrorFeature($worktree, $state->featureDir);
        $this->git->removeWorktree($worktree, 'factory/converge');
        $after = array_map(static fn (TaskItem $task): string => $task->id, $this->board());
        $state = $this->load();
        $state->convergeRounds++;

        $added = array_values(array_diff($after, $before));
        if ($added !== []) {
            $this->note('converge added '.implode(', ', $added));
            $state->next = 'implement';
            $this->save($state);

            return;
        }

        $this->publishDraft('implement '.$state->featureDir, $state->featureDir);
        $state->next = 'done';
        $state->stop = null;
        $this->save($state);
    }

    private function implementPrompt(TaskItem $task, int $attempt): string
    {
        $prompt = $this->read($this->root.'/factory/prompts/implementer.md')
            ."\n\n".$this->taskContext($task)
            ."\n\nDo not run make verify. Do not commit.";
        $log = $this->previousVerifyLog($task->id, $attempt);
        if ($log === '') {
            return $prompt;
        }

        return $prompt."\n\nThe previous attempt failed make verify. Fix that failure in this task. The log follows.\n\n".$log;
    }

    private function previousVerifyLog(string $taskId, int $attempt): string
    {
        if ($attempt < 2) {
            return '';
        }

        $path = $this->root.'/factory/runs/'.$taskId.'-verify/attempt-'.($attempt - 1).'/agent.log';
        if (! is_file($path)) {
            return '';
        }

        $body = (string) file_get_contents($path);
        $limit = 12000;
        if (strlen($body) <= $limit) {
            return $body;
        }

        return substr($body, -$limit);
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
        $severity = $step === 'analyze'
            ? "\nA finding has file, line, severity, rule, problem, and fix. Severity is critical, high, medium, or low. A constitution conflict is critical. Approve only with an empty issues array."
            : '';

        return <<<PROMPT
        Follow .cursor/skills/{$skill}/SKILL.md for {$state->featureDir}.
        The feature directory and branch already exist. Do not run git. Do not run Speckit git hooks.
        Feature description: {$state->description}
        If you need a human answer, append the question to {$questions} and finish with status spec_gap.
        Do not invent missing requirements.
        The entire final message is one JSON object and nothing else:
        {$closing}{$severity}
        PROMPT;
    }

    private function authorModel(string $step): string
    {
        $role = in_array($step, ['specify', 'clarify'], true) ? 'spec_author' : 'spec_editor';

        return $this->contract->model($role);
    }

    private function authorChat(string $model): ?string
    {
        $state = $this->load();
        if ($state->authorModel !== $model || $state->authorChatId === null || $state->authorChatId === '') {
            return null;
        }

        return $state->authorChatId;
    }

    private function rememberAuthor(?string $chatId, string $model): void
    {
        if ($chatId === null || $chatId === '') {
            return;
        }

        $state = $this->load();
        $state->authorChatId = $chatId;
        $state->authorModel = $model;
        $this->save($state);
    }

    private function invoke(string $cwd, string $role, string $prompt, ?string $chatId, string $name, int $attempt, ?string $model = null): AgentReply
    {
        $model ??= match ($role) {
            'implementer' => $this->contract->model('implementer'),
            'spec_author' => $this->contract->model('spec_author'),
            default => $this->contract->model('reviewer'),
        };
        $this->note('agent '.$role.' '.$model.' '.$name.' attempt '.$attempt.($chatId !== null && $chatId !== '' ? ' resume '.$chatId : ''));
        $reply = $this->agent->run($cwd, $model, $prompt, $chatId);
        $this->writeLog($name, $attempt, $reply->text);
        $this->note('agent '.$name.' status '.$reply->status);

        return $reply;
    }

    private function verify(string $cwd, string $taskId, int $attempt): bool
    {
        $this->note('verify '.$this->contract->verifyCommand());
        $process = Process::fromShellCommandline($this->contract->verifyCommand(), $cwd);
        $process->setTimeout($this->contract->agentTimeout());
        $output = '';
        $process->run(function (string $type, string $buffer) use (&$output): void {
            $output .= $buffer;
            $this->log?->stream($buffer);
        });
        $this->writeLog($taskId.'-verify', $attempt, $output);
        $passed = $process->isSuccessful();
        $this->note($passed ? 'verify passed' : 'verify failed');

        return $passed;
    }

    private function prepareWorktree(string $name, string $role): string
    {
        $safe = str_replace('/', '-', $name);
        $path = $this->root.'/.worktrees/'.$safe;
        if (! is_dir($this->root.'/.worktrees')) {
            mkdir($this->root.'/.worktrees', 0777, true);
        }

        $start = $this->git->hasBranch('factory/draft') ? 'factory/draft' : 'HEAD';
        $this->note('worktree '.$path.' on factory/'.$safe.' from '.$start.' role '.$role);
        $this->git->worktreeAdd($path, 'factory/'.$safe, $start);
        $this->git->linkDependencies($path);
        $this->copyFeaturePointer($path);
        $roleName = $role === 'converge' ? 'converge' : $role;
        $this->git->writeJson($path.'/.cursor/cli.json', $this->contract->cliConfig($roleName));

        return $path;
    }

    private function copyFeaturePointer(string $worktree): void
    {
        $source = $this->root.'/.specify/feature.json';
        if (! is_file($source)) {
            return;
        }

        $directory = $worktree.'/.specify';
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        copy($source, $directory.'/feature.json');
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

    private function keepDraft(string $worktree, string $branch, string $message): void
    {
        $this->git->commit($worktree, $message);
        $this->git->pointBranch('factory/draft', $branch);
        $this->note('draft '.$branch);
    }

    private function publishDraft(string $message, string $featureDir): void
    {
        if (! $this->git->hasBranch('factory/draft')) {
            return;
        }

        $this->note('publish '.$message);
        $this->git->clearFeature($featureDir);
        $this->git->squashMerge('factory/draft');
        $this->git->commit($this->root, $message, [$featureDir]);
        $this->git->removeWorktree('', 'factory/draft');
    }

    private function overlayFeature(string $worktree, string $featureDir): void
    {
        $this->copyFeature($this->root.'/'.$featureDir, $worktree.'/'.$featureDir);
    }

    private function mirrorFeature(string $worktree, string $featureDir): void
    {
        $this->copyFeature($worktree.'/'.$featureDir, $this->root.'/'.$featureDir);
    }

    private function copyFeature(string $from, string $to): void
    {
        if (! is_dir($from)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($from) + 1);
            if ($relative === '.factory/state.json') {
                continue;
            }

            $target = $to.'/'.$relative;
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0777, true);
                }

                continue;
            }

            $directory = dirname($target);
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            copy($item->getPathname(), $target);
        }
    }

    private function advance(string $step): void
    {
        $state = $this->load();
        $position = array_search($step, self::STEPS, true);
        $state->next = is_int($position) ? (self::STEPS[$position + 1] ?? 'done') : 'done';
        $state->stop = null;
        $this->save($state);
        $this->note('next '.$state->next);
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
        $this->note('stop '.$reason.' '.$detail);

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

    private function note(string $message): void
    {
        $this->log?->info($message);
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

<?php

declare(strict_types=1);

use Access\Factory\AgentClient;
use Access\Factory\AgentReply;
use Access\Factory\Contract;
use Access\Factory\FactoryState;
use Access\Factory\FactoryStop;
use Access\Factory\FeatureScaffolder;
use Access\Factory\GitRepo;
use Access\Factory\ScriptFeatureScaffolder;
use Access\Factory\Workflow;
use Symfony\Component\Process\Process;

it('names a feature from ascii words and falls back for other text', function () {
    expect(ScriptFeatureScaffolder::shortName('Record a gate pass'))->toBe('record-a-gate-pass')
        ->and(ScriptFeatureScaffolder::shortName('Сотрудник отмечает проход'))->toBe('feature-request');
});

it('runs specify through converge with a scripted agent', function () {
    $root = factoryRoot();

    try {
        $agent = factoryAgent(function (string $cwd, string $prompt): string {
            $dir = 'specs/001-demo';
            if (str_contains($prompt, 'IMPLEMENT_TASK')) {
                file_put_contents($cwd.'/recorded.txt', "pass\n");

                return '{"status":"done","summary":"recorded","assumptions":[]}';
            }

            if (str_contains($prompt, 'QUALITY_REVIEW') || str_contains($prompt, 'FUNCTIONAL_REVIEW')) {
                return '{"verdict":"approve","issues":[],"scenarios":[{"id":"1","result":"pass"}]}';
            }

            if (str_contains($prompt, 'speckit-analyze')) {
                return '{"verdict":"approve","issues":[],"assumptions":[]}';
            }

            if (str_contains($prompt, 'speckit-specify')) {
                file_put_contents($cwd.'/'.$dir.'/spec.md', "User Story 1\n\nA pass is recorded.\n");
            }

            if (str_contains($prompt, 'speckit-plan')) {
                file_put_contents($cwd.'/'.$dir.'/plan.md', "plan\n");
            }

            if (str_contains($prompt, 'speckit-tasks')) {
                file_put_contents($cwd.'/'.$dir.'/tasks.md', "## Phase 1: Setup\n\n- [ ] T001 Record a pass\n");
            }

            return '{"status":"done","summary":"ok","assumptions":[]}';
        });

        $workflow = factoryWorkflow($root, $agent);
        $workflow->start('Record a gate pass');

        $tracked = new Process(['git', 'ls-files', '--', '.specify/feature.json'], $root);
        $tracked->mustRun();

        expect($workflow->statusText())->toBe('specs/001-demo done')
            ->and(trim($tracked->getOutput()))->toBe('')
            ->and(file_get_contents($root.'/recorded.txt'))->toBe("pass\n")
            ->and(file_get_contents($root.'/specs/001-demo/tasks.md'))->toContain('- [x] T001');

        $spec = (string) file_get_contents($root.'/specs/001-demo/spec.md');
        file_put_contents($root.'/specs/001-demo/spec.md', $spec."\nExtra.\n");

        expect($workflow->staleIds())->toBe(['T001']);

        file_put_contents($root.'/specs/001-demo/spec.md', $spec);
        $tasks = (string) file_get_contents($root.'/specs/001-demo/tasks.md');
        file_put_contents($root.'/specs/001-demo/tasks.md', str_replace('- [x] T001', '- [ ] T001', $tasks));

        expect($workflow->staleIds())->toBe([]);
    } finally {
        factoryRemove($root);
    }
});

it('stops when clarify needs a human answer', function () {
    $root = factoryRoot();

    try {
        $agent = factoryAgent(function (string $cwd, string $prompt): string {
            if (str_contains($prompt, 'speckit-specify')) {
                file_put_contents($cwd.'/specs/001-demo/spec.md', "User Story 1\n\nA pass is recorded.\n");
            }

            if (str_contains($prompt, 'speckit-clarify')) {
                return '{"status":"spec_gap","summary":"Which gate?","assumptions":[]}';
            }

            return '{"status":"done","summary":"ok","assumptions":[]}';
        });

        $workflow = factoryWorkflow($root, $agent);

        expect(fn () => $workflow->start('Record a gate pass'))
            ->toThrow(FactoryStop::class, 'questions.md');

        expect(file_get_contents($root.'/specs/001-demo/.factory/questions.md'))->toContain('Which gate?')
            ->and($workflow->statusText())->toContain('unresolved_questions');
    } finally {
        factoryRemove($root);
    }
});

it('stops when analyze requests a real change', function () {
    $root = factoryRoot();

    try {
        $agent = factoryAgent(function (string $cwd, string $prompt): string {
            $dir = 'specs/001-demo';
            if (str_contains($prompt, 'speckit-analyze')) {
                return '{"verdict":"changes_requested","issues":[{"rule":"constitution:I","problem":"layer"}],"assumptions":[]}';
            }

            if (str_contains($prompt, 'speckit-specify')) {
                file_put_contents($cwd.'/'.$dir.'/spec.md', "User Story 1\n\nA pass is recorded.\n");
            }

            if (str_contains($prompt, 'speckit-plan')) {
                file_put_contents($cwd.'/'.$dir.'/plan.md', "plan\n");
            }

            if (str_contains($prompt, 'speckit-tasks')) {
                file_put_contents($cwd.'/'.$dir.'/tasks.md', "## Phase 1: Setup\n\n- [ ] T001 Record a pass\n");
            }

            return '{"status":"done","summary":"ok","assumptions":[]}';
        });

        expect(fn () => factoryWorkflow($root, $agent)->start('Record a gate pass'))
            ->toThrow(FactoryStop::class, 'Analyze requested changes');

        expect(is_file($root.'/recorded.txt'))->toBeFalse();
    } finally {
        factoryRemove($root);
    }
});

it('refuses a second run while the current process holds the lock', function () {
    $root = factoryRoot();

    try {
        $dir = 'specs/001-demo';
        mkdir($root.'/'.$dir.'/.factory', 0777, true);
        file_put_contents($root.'/.specify/feature.json', json_encode(['feature_directory' => $dir]));
        $state = new FactoryState($dir, '001-demo', 'Record a gate pass', 'specify', null, [
            'pid' => getmypid() ?: 1,
            'at' => gmdate('c'),
        ], 0, []);
        file_put_contents(
            $root.'/'.$dir.'/.factory/state.json',
            json_encode($state->toArray(), JSON_PRETTY_PRINT)."\n",
        );

        $workflow = factoryWorkflow($root, factoryAgent(fn (): string => '{"status":"done","summary":"ok","assumptions":[]}'));

        expect(fn () => $workflow->resume())->toThrow(FactoryStop::class, 'locked');

        $workflow->unlock();

        $decoded = json_decode((string) file_get_contents($root.'/'.$dir.'/.factory/state.json'), true);
        expect(is_array($decoded) ? $decoded['lock'] : 'invalid')->toBeNull();
    } finally {
        factoryRemove($root);
    }
});

function factoryRoot(): string
{
    $root = sys_get_temp_dir().'/access-factory-'.bin2hex(random_bytes(4));
    mkdir($root);
    putenv('GIT_AUTHOR_NAME=Factory Test');
    putenv('GIT_AUTHOR_EMAIL=factory@example.com');
    putenv('GIT_COMMITTER_NAME=Factory Test');
    putenv('GIT_COMMITTER_EMAIL=factory@example.com');
    factoryGit($root, ['init', '-b', 'main']);
    factoryGit($root, ['commit', '--allow-empty', '-m', 'init']);
    file_put_contents($root.'/.gitignore', "/.worktrees/\n/factory/runs/\n");
    mkdir($root.'/.specify', 0777, true);
    file_put_contents($root.'/.specify/.gitignore', "feature.json\n");
    mkdir($root.'/factory/prompts', 0777, true);
    mkdir($root.'/.cursor', 0777, true);
    file_put_contents($root.'/factory/prompts/implementer.md', "IMPLEMENT_TASK\n");
    file_put_contents($root.'/factory/prompts/quality_reviewer.md', "QUALITY_REVIEW\n");
    file_put_contents($root.'/factory/prompts/functional_reviewer.md', "FUNCTIONAL_REVIEW\n");
    file_put_contents($root.'/.cursor/cli.json', '{"permissions":{"allow":[]}}');
    factoryGit($root, ['add', '--', '.gitignore', '.specify/.gitignore']);
    factoryGit($root, ['commit', '-m', 'ignore local files']);

    return $root;
}

it('continues a scaffold whose feature pointer is gitignored', function () {
    $root = factoryRoot();

    try {
        $dir = 'specs/002-demo';
        mkdir($root.'/'.$dir, 0777, true);
        file_put_contents($root.'/'.$dir.'/spec.md', "draft\n");
        file_put_contents($root.'/.specify/feature.json', json_encode(['feature_directory' => $dir])."\n");
        factoryGit($root, ['checkout', '-b', '002-demo']);

        $workflow = factoryWorkflow($root, factoryAgent(function (string $cwd, string $prompt): string {
            $dir = 'specs/002-demo';
            if (str_contains($prompt, 'IMPLEMENT_TASK')) {
                file_put_contents($cwd.'/recorded.txt', "pass\n");

                return '{"status":"done","summary":"recorded","assumptions":[]}';
            }

            if (str_contains($prompt, 'QUALITY_REVIEW') || str_contains($prompt, 'FUNCTIONAL_REVIEW')) {
                return '{"verdict":"approve","issues":[],"scenarios":[{"id":"1","result":"pass"}]}';
            }

            if (str_contains($prompt, 'speckit-analyze')) {
                return '{"verdict":"approve","issues":[],"assumptions":[]}';
            }

            if (str_contains($prompt, 'speckit-plan')) {
                file_put_contents($cwd.'/'.$dir.'/plan.md', "plan\n");
            }

            if (str_contains($prompt, 'speckit-tasks')) {
                file_put_contents($cwd.'/'.$dir.'/tasks.md', "## Phase 1: Setup\n\n- [ ] T001 Record a pass\n");
            }

            return '{"status":"done","summary":"ok","assumptions":[]}';
        }), new class implements FeatureScaffolder
        {
            public function create(string $root, string $description): array
            {
                throw new RuntimeException('Scaffold already exists.');
            }
        });

        $workflow->start('Record a gate pass');

        expect($workflow->statusText())->toBe('specs/002-demo done');
    } finally {
        factoryRemove($root);
    }
});

function factoryWorkflow(string $root, AgentClient $agent, ?FeatureScaffolder $scaffolder = null): Workflow
{
    return new Workflow(
        $root,
        new Contract([
            'verify_command' => 'true',
            'max_retries' => 0,
            'max_converge_rounds' => 2,
            'agent_timeout_seconds' => 30,
            'models' => [
                'spec_author' => 'spec-model',
                'implementer' => 'implement-model',
                'reviewer' => 'review-model',
            ],
        ]),
        new GitRepo($root),
        $agent,
        $scaffolder ?? new class implements FeatureScaffolder
        {
            public function create(string $root, string $description): array
            {
                $dir = 'specs/001-demo';
                mkdir($root.'/'.$dir, 0777, true);
                if (! is_dir($root.'/.specify')) {
                    mkdir($root.'/.specify', 0777, true);
                }
                file_put_contents($root.'/'.$dir.'/spec.md', "draft\n");
                file_put_contents($root.'/.specify/feature.json', json_encode([
                    'feature_directory' => $dir,
                ])."\n");

                return ['branch' => '001-demo', 'dir' => $dir];
            }
        },
    );
}

function factoryAgent(Closure $reply): AgentClient
{
    return new class($reply) implements AgentClient
    {
        public function __construct(private Closure $reply) {}

        public function run(string $cwd, string $model, string $prompt, ?string $chatId): AgentReply
        {
            return AgentReply::fromStream(($this->reply)($cwd, $prompt));
        }
    };
}

function factoryRemove(string $root): void
{
    if (! is_dir($root)) {
        return;
    }

    $process = new Process(['rm', '-rf', $root]);
    $process->run();
}

/**
 * @param  list<string>  $arguments
 */
function factoryGit(string $root, array $arguments): void
{
    $process = new Process(['git', ...$arguments], $root);
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()."\n".$process->getOutput()));
    }
}

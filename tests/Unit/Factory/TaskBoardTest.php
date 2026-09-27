<?php

declare(strict_types=1);

use Access\Factory\TaskBoard;

it('parses a speckit task line', function () {
    $tasks = TaskBoard::parse(<<<'MD'
    ## Phase 1: Setup

    - [ ] T001 Create the model

    ## Phase 2: Story

    - [x] T014 [P] [US1] Implement the service (depends on T001, T013)
    MD);

    expect($tasks)->toHaveCount(2);
    expect($tasks[0]->id)->toBe('T001')
        ->and($tasks[0]->done)->toBeFalse()
        ->and($tasks[0]->phase)->toBe('1')
        ->and($tasks[0]->parallel)->toBeFalse();

    expect($tasks[1]->done)->toBeTrue()
        ->and($tasks[1]->parallel)->toBeTrue()
        ->and($tasks[1]->story)->toBe('US1')
        ->and($tasks[1]->dependsOn)->toBe(['T001', 'T013'])
        ->and($tasks[1]->description)->toBe('Implement the service');
});

it('orders tasks within a phase and rejects a cycle', function () {
    $markdown = <<<'MD'
    ## Phase 1: Setup

    - [ ] T002 Second (depends on T001)
    - [ ] T001 First
    MD;

    $ordered = TaskBoard::order(TaskBoard::parse($markdown));

    expect(array_map(static fn ($task) => $task->id, $ordered))->toBe(['T001', 'T002']);

    expect(fn () => TaskBoard::order(TaskBoard::parse(<<<'MD'
    ## Phase 1: Setup

    - [ ] T001 First (depends on T002)
    - [ ] T002 Second (depends on T001)
    MD)))->toThrow(RuntimeException::class, 'cycle');
});

it('rejects a dependency in a later phase', function () {
    expect(fn () => TaskBoard::order(TaskBoard::parse(<<<'MD'
    ## Phase 1: Setup

    - [ ] T001 First (depends on T002)

    ## Phase 2: Story

    - [ ] T002 Second
    MD)))->toThrow(RuntimeException::class, 'later phase');
});

it('marks one checkbox without rewriting the rest of the line', function () {
    $markdown = "- [ ] T001 First\n- [ ] T002 Second\n";
    $updated = TaskBoard::markDone($markdown, 'T001');

    expect($updated)->toBe("- [x] T001 First\n- [ ] T002 Second\n");
    expect(TaskBoard::markDone($updated, 'T001'))->toBe($updated);
});

<?php

declare(strict_types=1);

use Access\Factory\AgentReply;

it('reads the final status from a stream', function () {
    $reply = AgentReply::fromStream(<<<'JSON'
    {"type":"system","session_id":"chat-1"}
    {"type":"assistant","message":{"content":[{"text":"working"}]}}
    {"type":"result","result":"{\"status\":\"done\",\"summary\":\"ok\",\"assumptions\":[]}","session_id":"chat-1"}
    JSON);

    expect($reply->status)->toBe('done')
        ->and($reply->chatId)->toBe('chat-1')
        ->and($reply->blocksApproval())->toBeFalse();
});

it('accepts a bare json payload', function () {
    $reply = AgentReply::fromStream('{"verdict":"approve","issues":[],"assumptions":[]}');

    expect($reply->status)->toBe('approve')
        ->and($reply->reviewAccepted())->toBeTrue();
});

it('discards an issue whose rule is not real', function () {
    $discarded = AgentReply::fromStream('{"verdict":"changes_requested","issues":[{"rule":"style","problem":"rename"}],"assumptions":[]}');
    $kept = AgentReply::fromStream('{"verdict":"changes_requested","issues":[{"rule":"constitution:I","problem":"layer"}],"assumptions":[]}');
    $assumptions = AgentReply::fromStream('{"verdict":"approve","issues":[],"assumptions":["guessed a column"]}');
    $scenario = AgentReply::fromStream('{"verdict":"approve","issues":[],"scenarios":[{"id":"1","result":"missing_test"}]}');

    expect($discarded->reviewAccepted())->toBeTrue()
        ->and($kept->reviewAccepted())->toBeFalse()
        ->and($assumptions->blocksApproval())->toBeTrue()
        ->and($assumptions->reviewAccepted())->toBeFalse()
        ->and($scenario->reviewAccepted())->toBeFalse();
});

it('treats critical through medium findings as actionable and skips low', function () {
    $critical = AgentReply::fromStream('{"verdict":"changes_requested","issues":[{"severity":"critical","rule":"constitution:I","problem":"layer"}],"assumptions":[]}');
    $middle = AgentReply::fromStream('{"verdict":"changes_requested","issues":[{"severity":"middle","rule":"constitution:IV","problem":"resource"}],"assumptions":[]}');
    $low = AgentReply::fromStream('{"verdict":"changes_requested","issues":[{"severity":"low","rule":"constitution:Conventions","problem":"wording"}],"assumptions":[]}');
    $unstated = AgentReply::fromStream('{"verdict":"changes_requested","issues":[{"rule":"constitution:Definition of Done","problem":"verify"}],"assumptions":[]}');

    expect($critical->actionableIssues())->toHaveCount(1)
        ->and($middle->actionableIssues())->toHaveCount(1)
        ->and($low->actionableIssues())->toBe([])
        ->and($unstated->actionableIssues())->toHaveCount(1);
});

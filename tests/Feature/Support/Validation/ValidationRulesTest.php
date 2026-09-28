<?php

declare(strict_types=1);

use App\Support\Validation\EmailRules;
use App\Support\Validation\PasswordRules;
use Illuminate\Support\Facades\Validator;

it('enforces the password character limits', function (string $password, int $characters, bool $passes) {
    $result = Validator::make(
        ['password' => $password],
        ['password' => PasswordRules::rules()],
    );

    expect(mb_strlen($password))->toBe($characters)
        ->and($result->passes())->toBe($passes);
})->with([
    '7 characters' => [str_repeat('a', 7), 7, false],
    '8 cyrillic characters' => [str_repeat('я', 8), 8, true],
    '128 cyrillic characters' => [str_repeat('я', 128), 128, true],
    '129 characters' => [str_repeat('я', 129), 129, false],
]);

it('enforces the email character limit', function (int $length, bool $passes) {
    $email = boundaryEmailAddress($length);

    $result = Validator::make(
        ['email' => $email],
        ['email' => EmailRules::rules()],
    );

    expect(mb_strlen($email))->toBe($length)
        ->and($result->passes())->toBe($passes);
})->with([
    '255 characters' => [255, true],
    '256 characters' => [256, false],
]);

function boundaryEmailAddress(int $length): string
{
    $local = 'user';
    $remaining = $length - strlen($local) - 1;
    $labels = [];
    $isFirstLabel = true;

    while ($remaining > 0) {
        $maxLabelLength = $isFirstLabel ? 63 : 62;

        if ($remaining <= $maxLabelLength) {
            $labels[] = str_repeat('b', $remaining);

            break;
        }

        $labels[] = str_repeat($isFirstLabel ? 'b' : 'c', $maxLabelLength);
        $remaining -= ($maxLabelLength + 1);
        $isFirstLabel = false;
    }

    return $local.'@'.implode('.', $labels);
}

<?php

declare(strict_types=1);

use App\Http\Requests\CreateCompanyRequest;
use App\Rules\Companies\WorkingDaySettingsRangeRule;
use App\Support\Validation\BinRules;
use App\Support\Validation\EmailRules;
use App\Support\Validation\OptionalEmailRules;
use App\Support\Validation\PhoneRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

it('accepts a 12 digit bin and rejects other lengths', function (?string $bin, bool $passes) {
    $result = Validator::make(
        ['bin' => $bin],
        ['bin' => BinRules::rules()],
    );

    expect(BinRules::COMPANY_BIN_LENGTH)->toBe(12)
        ->and($result->passes())->toBe($passes);
})->with([
    '11 digits' => ['12345678901', false],
    '12 digits' => ['123456789012', true],
    '13 digits' => ['1234567890123', false],
    'empty string' => ['', true],
    'null' => [null, true],
]);

it('enforces the company phone pattern and length', function (?string $phone, bool $passes) {
    $result = Validator::make(
        ['phone' => $phone],
        ['phone' => PhoneRules::rules()],
    );

    expect(PhoneRules::COMPANY_PHONE_MAX_LENGTH)->toBe(32)
        ->and($result->passes())->toBe($passes);
})->with([
    'too short' => ['123456789', false],
    'valid with spaces and plus' => ['+7 (700) 123-45-67', true],
    'over 32 characters' => ['+'.str_repeat('1', 10).str_repeat(' ', 22), false],
    'empty string' => ['', true],
    'null' => [null, true],
]);

it('enforces the optional email limit', function (int $length, bool $passes) {
    $email = companyBoundaryEmailAddress($length);

    $result = Validator::make(
        ['email' => $email],
        ['email' => OptionalEmailRules::rules()],
    );

    expect(mb_strlen($email))->toBe($length)
        ->and(EmailRules::EMAIL_MAX_LENGTH)->toBe(255)
        ->and($result->passes())->toBe($passes);
})->with([
    '255 characters' => [255, true],
    '256 characters' => [256, false],
]);

it('allows an empty optional email', function (?string $email) {
    $result = Validator::make(
        ['email' => $email],
        ['email' => OptionalEmailRules::rules()],
    );

    expect($result->passes())->toBeTrue();
})->with([
    'empty string' => [''],
    'null' => [null],
]);

it('rejects a time zone that is not in the iana set', function (string $timeZone, bool $passes) {
    $result = Validator::make(
        ['time_zone' => $timeZone],
        ['time_zone' => ['timezone:all']],
    );

    expect($result->passes())->toBe($passes);
})->with([
    'Asia/Almaty' => ['Asia/Almaty', true],
    'unknown' => ['Not/AZone', false],
]);

it('names the contact person limit', function () {
    expect(CreateCompanyRequest::COMPANY_CONTACT_PERSON_MAX_LENGTH)->toBe(255);
});

it('rejects an end time that is not after the start time', function (string $start, string $end) {
    $result = Validator::make(
        defaultWorkingDaySettingsPayload([
            'start_time' => $start,
            'end_time' => $end,
        ]),
        WorkingDaySettingsRangeRule::rules(),
    );

    expect($result->fails())->toBeTrue()
        ->and($result->errors()->has('end_time'))->toBeTrue();
})->with([
    'end before start' => ['18:00', '09:00'],
    'end equal to start' => ['09:00', '09:00'],
]);

it('rejects an empty working day list', function () {
    $result = Validator::make(
        defaultWorkingDaySettingsPayload([
            'working_days' => [],
        ]),
        WorkingDaySettingsRangeRule::rules(),
    );

    expect($result->fails())->toBeTrue()
        ->and($result->errors()->has('working_days'))->toBeTrue()
        ->and($result->errors()->has('end_time'))->toBeFalse();
});

it('rejects a break and grace equal to the working day length', function () {
    $length = companyWorkingDayLengthMinutes('09:00', '18:00');

    $result = Validator::make(
        defaultWorkingDaySettingsPayload([
            'break_duration_minutes' => $length,
            'lateness_grace_minutes' => $length,
        ]),
        WorkingDaySettingsRangeRule::rules(),
    );

    expect($result->fails())->toBeTrue()
        ->and($result->errors()->has('break_duration_minutes'))->toBeTrue()
        ->and($result->errors()->has('lateness_grace_minutes'))->toBeTrue();
});

it('accepts the default working day settings', function () {
    $result = Validator::make(
        defaultWorkingDaySettingsPayload(),
        WorkingDaySettingsRangeRule::rules(),
    );

    expect($result->passes())->toBeTrue();
});

function companyBoundaryEmailAddress(int $length): string
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

function companyWorkingDayLengthMinutes(string $start, string $end): int
{
    $startTime = Carbon::createFromFormat('!H:i', $start);
    $endTime = Carbon::createFromFormat('!H:i', $end);

    throw_unless($startTime instanceof Carbon && $endTime instanceof Carbon, LogicException::class);

    return (int) $startTime->diffInMinutes($endTime);
}

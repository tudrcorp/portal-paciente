<?php

declare(strict_types=1);

use App\Models\PatientReminder;
use App\Services\Reminders\ReminderDueCalculator;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-13 10:00:00'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('chronic treatment due times honor days and hours inside the window', function () {
    $reminder = new PatientReminder([
        'type' => PatientReminder::TYPE_CHRONIC,
        'schedule_times' => ['08:00', '20:00'],
        'schedule_days' => [1], // Monday
        'starts_on' => '2026-07-01',
        'ends_on' => null,
    ]);

    $calculator = new ReminderDueCalculator;
    $due = $calculator->dueTimes(
        $reminder,
        CarbonImmutable::parse('2026-07-13 00:00:00'), // Monday
        CarbonImmutable::parse('2026-07-13 23:59:00'),
    );

    expect(array_map(fn ($d) => $d->format('Y-m-d H:i'), $due))->toBe([
        '2026-07-13 08:00',
        '2026-07-13 20:00',
    ]);
});

test('specific treatment requires an end date and respects range', function () {
    $reminder = new PatientReminder([
        'type' => PatientReminder::TYPE_SPECIFIC,
        'schedule_times' => ['09:00'],
        'schedule_days' => [1, 2, 3, 4, 5],
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-07-14',
    ]);

    $calculator = new ReminderDueCalculator;

    $inside = $calculator->dueTimes(
        $reminder,
        CarbonImmutable::parse('2026-07-13 08:00:00'),
        CarbonImmutable::parse('2026-07-13 10:00:00'),
    );

    $outside = $calculator->dueTimes(
        $reminder,
        CarbonImmutable::parse('2026-07-15 08:00:00'),
        CarbonImmutable::parse('2026-07-15 10:00:00'),
    );

    expect($inside)->toHaveCount(1)
        ->and($inside[0]->format('Y-m-d H:i'))->toBe('2026-07-13 09:00')
        ->and($outside)->toBe([]);
});

test('appointment lead minutes produce due times before the visit', function () {
    $reminder = new PatientReminder([
        'type' => PatientReminder::TYPE_APPOINTMENT,
        'appointment_at' => '2026-07-14 15:00:00',
        'lead_minutes' => [1440, 60],
    ]);

    $calculator = new ReminderDueCalculator;
    $due = $calculator->dueTimes(
        $reminder,
        CarbonImmutable::parse('2026-07-13 14:00:00'),
        CarbonImmutable::parse('2026-07-14 15:00:00'),
    );

    expect(array_map(fn ($d) => $d->format('Y-m-d H:i'), $due))->toBe([
        '2026-07-13 15:00',
        '2026-07-14 14:00',
    ]);
});

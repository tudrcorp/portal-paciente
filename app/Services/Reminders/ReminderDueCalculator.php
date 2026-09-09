<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Models\PatientReminder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

final class ReminderDueCalculator
{
    /**
     * Returns due datetimes inside [windowStart, windowEnd] inclusive (minute precision).
     *
     * @return list<CarbonImmutable>
     */
    public function dueTimes(
        PatientReminder $reminder,
        DateTimeInterface|CarbonInterface $windowStart,
        DateTimeInterface|CarbonInterface $windowEnd,
    ): array {
        $start = CarbonImmutable::parse($windowStart)->startOfMinute();
        $end = CarbonImmutable::parse($windowEnd)->startOfMinute();

        if ($end->lt($start)) {
            return [];
        }

        return match ($reminder->type) {
            PatientReminder::TYPE_APPOINTMENT => $this->appointmentDueTimes($reminder, $start, $end),
            PatientReminder::TYPE_CHRONIC, PatientReminder::TYPE_SPECIFIC => $this->treatmentDueTimes($reminder, $start, $end),
            default => [],
        };
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function appointmentDueTimes(
        PatientReminder $reminder,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): array {
        if ($reminder->appointment_at === null) {
            return [];
        }

        $appointmentAt = CarbonImmutable::parse($reminder->appointment_at)->startOfMinute();
        $leads = $reminder->lead_minutes ?? [1440, 60];
        $due = [];

        foreach ($leads as $minutes) {
            $minutes = (int) $minutes;
            if ($minutes < 0) {
                continue;
            }

            $candidate = $appointmentAt->subMinutes($minutes);
            if ($candidate->betweenIncluded($windowStart, $windowEnd)) {
                $due[] = $candidate;
            }
        }

        return $this->uniqueSorted($due);
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function treatmentDueTimes(
        PatientReminder $reminder,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): array {
        $times = $this->normalizeTimes($reminder->schedule_times ?? []);
        if ($times === []) {
            return [];
        }

        $days = $this->normalizeDays($reminder->schedule_days ?? []);
        $rangeStart = $reminder->starts_on
            ? CarbonImmutable::parse($reminder->starts_on)->startOfDay()
            : null;
        $rangeEnd = $reminder->ends_on
            ? CarbonImmutable::parse($reminder->ends_on)->endOfDay()
            : null;

        if ($reminder->type === PatientReminder::TYPE_SPECIFIC && $rangeEnd === null) {
            return [];
        }

        $due = [];
        $cursor = $windowStart->startOfDay();
        $lastDay = $windowEnd->startOfDay();

        while ($cursor->lte($lastDay)) {
            $isoDay = $cursor->dayOfWeek; // 0 Sunday … 6 Saturday

            if ($days !== [] && ! in_array($isoDay, $days, true)) {
                $cursor = $cursor->addDay();

                continue;
            }

            if ($rangeStart !== null && $cursor->lt($rangeStart->startOfDay())) {
                $cursor = $cursor->addDay();

                continue;
            }

            if ($rangeEnd !== null && $cursor->gt($rangeEnd->startOfDay())) {
                break;
            }

            foreach ($times as $time) {
                [$hour, $minute] = array_map('intval', explode(':', $time));
                $candidate = $cursor->setTime($hour, $minute);

                if ($candidate->betweenIncluded($windowStart, $windowEnd)) {
                    $due[] = $candidate;
                }
            }

            $cursor = $cursor->addDay();
        }

        return $this->uniqueSorted($due);
    }

    /**
     * @param  list<mixed>  $times
     * @return list<string>
     */
    private function normalizeTimes(array $times): array
    {
        $normalized = [];

        foreach ($times as $time) {
            if (! is_string($time) && ! is_numeric($time)) {
                continue;
            }

            $value = trim((string) $time);
            if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $matches)) {
                continue;
            }

            $normalized[] = sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  list<mixed>  $days
     * @return list<int>
     */
    private function normalizeDays(array $days): array
    {
        if ($days === []) {
            return [0, 1, 2, 3, 4, 5, 6];
        }

        $normalized = [];

        foreach ($days as $day) {
            if (! is_numeric($day)) {
                continue;
            }

            $value = (int) $day;
            if ($value >= 0 && $value <= 6) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<CarbonImmutable>  $items
     * @return list<CarbonImmutable>
     */
    private function uniqueSorted(array $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $map[$item->toDateTimeString()] = $item;
        }

        ksort($map);

        return array_values($map);
    }
}

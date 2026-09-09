<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendPatientReminderJob;
use App\Models\PatientNotificationDelivery;
use App\Models\PatientReminder;
use App\Services\Reminders\ReminderDueCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class DispatchDuePatientRemindersCommand extends Command
{
    protected $signature = 'reminders:dispatch
                            {--lookback=2 : Minutes to look back from now}
                            {--lookahead=0 : Minutes to look ahead from now}';

    protected $description = 'Detecta recordatorios del paciente vencidos y encola su envío';

    public function handle(ReminderDueCalculator $calculator): int
    {
        $lookback = max(0, (int) $this->option('lookback'));
        $lookahead = max(0, (int) $this->option('lookahead'));

        $now = CarbonImmutable::now()->startOfMinute();
        $windowStart = $now->subMinutes($lookback);
        $windowEnd = $now->addMinutes($lookahead);

        $created = 0;
        $queued = 0;

        PatientReminder::query()
            ->active()
            ->orderBy('id')
            ->chunkById(100, function ($reminders) use ($calculator, $windowStart, $windowEnd, &$created, &$queued): void {
                foreach ($reminders as $reminder) {
                    /** @var PatientReminder $reminder */
                    $dueTimes = $calculator->dueTimes($reminder, $windowStart, $windowEnd);

                    foreach ($dueTimes as $dueAt) {
                        foreach ($reminder->enabledChannels() as $channel) {
                            $delivery = $this->firstOrCreatePending($reminder, $channel, $dueAt);
                            if ($delivery === null) {
                                continue;
                            }

                            if ($delivery->wasRecentlyCreated) {
                                $created++;
                            }

                            if ($delivery->status === PatientNotificationDelivery::STATUS_PENDING) {
                                SendPatientReminderJob::dispatch($delivery->id);
                                $queued++;
                            }
                        }
                    }
                }
            });

        $this->info("Recordatorios: {$created} deliveries nuevas, {$queued} jobs encolados.");

        return self::SUCCESS;
    }

    private function firstOrCreatePending(
        PatientReminder $reminder,
        string $channel,
        CarbonImmutable $dueAt,
    ): ?PatientNotificationDelivery {
        $scheduledFor = $dueAt->toDateTimeString();

        try {
            return PatientNotificationDelivery::query()->firstOrCreate(
                [
                    'patient_reminder_id' => $reminder->id,
                    'channel' => $channel,
                    'scheduled_for' => $scheduledFor,
                ],
                [
                    'telemedicine_patient_id' => $reminder->telemedicine_patient_id,
                    'status' => PatientNotificationDelivery::STATUS_PENDING,
                ],
            );
        } catch (QueryException $exception) {
            // Carrera concurrente sobre el índice único.
            return PatientNotificationDelivery::query()
                ->where('patient_reminder_id', $reminder->id)
                ->where('channel', $channel)
                ->where('scheduled_for', $scheduledFor)
                ->first();
        }
    }
}

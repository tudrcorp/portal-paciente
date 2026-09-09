<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\PatientNotificationDelivery;
use App\Services\Notifications\Channels\InAppChannel;
use App\Services\Notifications\Channels\NotificationChannel;
use App\Services\Notifications\Channels\PushChannel;
use App\Services\Notifications\Channels\SmsChannel;
use App\Services\Notifications\Channels\WhatsAppUltramsgChannel;
use InvalidArgumentException;

final class NotificationChannelManager
{
    /**
     * @var array<string, NotificationChannel>
     */
    private array $channels;

    public function __construct(
        WhatsAppUltramsgChannel $whatsApp,
        InAppChannel $inApp,
        SmsChannel $sms,
        PushChannel $push,
    ) {
        $this->channels = [
            PatientNotificationDelivery::CHANNEL_WHATSAPP => $whatsApp,
            PatientNotificationDelivery::CHANNEL_IN_APP => $inApp,
            PatientNotificationDelivery::CHANNEL_SMS => $sms,
            PatientNotificationDelivery::CHANNEL_PUSH => $push,
        ];
    }

    public function get(string $channel): NotificationChannel
    {
        if (! isset($this->channels[$channel])) {
            throw new InvalidArgumentException("Canal de notificación desconocido: {$channel}");
        }

        return $this->channels[$channel];
    }
}

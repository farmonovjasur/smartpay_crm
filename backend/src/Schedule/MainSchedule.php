<?php

declare(strict_types=1);

namespace App\Schedule;

use App\Message\CleanExpiredTokens;
use App\Message\DailyDebtCheck;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
final class MainSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

        // Daily debt check at 03:00 Tashkent time
        $schedule->add(RecurringMessage::cron('0 3 * * *', new DailyDebtCheck()));

        // Clean expired refresh tokens daily at 02:00
        $schedule->add(RecurringMessage::cron('0 2 * * *', new CleanExpiredTokens()));

        return $schedule;
    }
}

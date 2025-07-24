<?php

namespace App\Services;

use App\Models\ClinicCalendar;
use App\Models\ClinicWeeklySchedule;
use Carbon\Carbon;

class ClinicDateResolverService
{
    public static function isOpen($date): bool
    {
        $carbon = Carbon::parse($date);
        $override = ClinicCalendar::whereDate('date', $carbon)->first();

        if ($override) {
            return $override->is_open;
        }

        $weekly = ClinicWeeklySchedule::where('weekday', $carbon->dayOfWeek)->first();
        return $weekly ? $weekly->is_open : false;
    }

    public static function getOpenDaysInRange($start, $end): array
    {
        $openDates = [];

        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        while ($startDate->lte($endDate)) {
            if (self::isOpen($startDate)) {
                $openDates[] = $startDate->toDateString();
            }
            $startDate->addDay();
        }

        return $openDates;
    }
}

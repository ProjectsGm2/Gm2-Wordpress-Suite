<?php

declare(strict_types=1);

namespace Gm2\Presets\Events;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

use function cal_days_in_month;
use function explode;
use function function_exists;
use function in_array;
use function is_numeric;
use function is_string;
use function preg_replace;
use function sort;
use function strpos;
use function trim;
use function wp_timezone;

use const CAL_GREGORIAN;

final class EventComputed
{
    /**
     * Compute the next occurrence for an event using its recurrence rule.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $context
     */
    public static function computeNextOccurrence(array $values, array $context = []): ?string
    {
        $timezone = self::determineTimezone($values);

        $start = self::parseDateTime($values['start_date'] ?? null, $timezone);
        if ($start === null) {
            return null;
        }

        $rule = null;
        if (isset($values['recurrence_rule']) && is_string($values['recurrence_rule'])) {
            $rule = self::parseRule(trim($values['recurrence_rule']), $timezone);
        }

        $now = new DateTimeImmutable('now', $timezone);

        if ($rule === null) {
            return $start >= $now ? $start->format(DateTimeInterface::ATOM) : null;
        }

        if ($rule['count'] !== null && $rule['count'] < 1) {
            return null;
        }

        if ($rule['until'] !== null && $start > $rule['until']) {
            return null;
        }

        if ($start >= $now) {
            return $start->format(DateTimeInterface::ATOM);
        }

        $occurrence = $start;
        $occurrenceIndex = 1;
        $maxIterations = 1000;

        while ($maxIterations-- > 0) {
            $next = self::advanceOccurrence($occurrence, $rule);
            if ($next === null) {
                return null;
            }

            $occurrence = $next;
            $occurrenceIndex++;

            if ($rule['count'] !== null && $occurrenceIndex > $rule['count']) {
                return null;
            }

            if ($rule['until'] !== null && $occurrence > $rule['until']) {
                return null;
            }

            if ($occurrence >= $now) {
                return $occurrence->format(DateTimeInterface::ATOM);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function determineTimezone(array $values): DateTimeZone
    {
        foreach (['start_time_zone', 'end_time_zone'] as $key) {
            if (!isset($values[$key]) || !is_string($values[$key])) {
                continue;
            }
            $candidate = trim($values[$key]);
            if ($candidate === '') {
                continue;
            }
            $timezone = self::createTimezone($candidate);
            if ($timezone !== null) {
                return $timezone;
            }
        }

        if (function_exists('wp_timezone')) {
            $timezone = wp_timezone();
            if ($timezone instanceof DateTimeZone) {
                return $timezone;
            }
        }

        return new DateTimeZone('UTC');
    }

    private static function createTimezone(string $value): ?DateTimeZone
    {
        try {
            return new DateTimeZone($value);
        } catch (Exception) {
            return null;
        }
    }

    private static function parseDateTime(mixed $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, $timezone);
        } catch (Exception) {
            $fallback = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
            if ($fallback instanceof DateTimeImmutable) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     freq: string,
     *     interval: int,
     *     count: int|null,
     *     until: DateTimeImmutable|null,
     *     byday: array<int>,
     *     bymonthday: array<int>,
     *     bymonth: array<int>
     * }|null
     */
    private static function parseRule(string $rule, DateTimeZone $timezone): ?array
    {
        if ($rule === '') {
            return null;
        }

        $parts = [];
        foreach (explode(';', $rule) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || strpos($segment, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $segment, 2);
            $parts[strtoupper(trim($key))] = trim($value);
        }

        if (!isset($parts['FREQ'])) {
            return null;
        }

        $frequency = strtoupper($parts['FREQ']);
        if (!in_array($frequency, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            return null;
        }

        $interval = 1;
        if (isset($parts['INTERVAL']) && is_numeric($parts['INTERVAL'])) {
            $interval = (int) $parts['INTERVAL'];
            if ($interval < 1) {
                $interval = 1;
            }
        }

        $count = null;
        if (isset($parts['COUNT']) && is_numeric($parts['COUNT'])) {
            $count = (int) $parts['COUNT'];
            if ($count < 0) {
                $count = 0;
            }
        }

        $until = null;
        if (isset($parts['UNTIL'])) {
            $until = self::parseUntil($parts['UNTIL'], $timezone);
        }

        $byday = isset($parts['BYDAY']) ? self::parseByDayList($parts['BYDAY']) : [];
        $bymonthday = isset($parts['BYMONTHDAY']) ? self::parseByMonthDayList($parts['BYMONTHDAY']) : [];
        $bymonth = isset($parts['BYMONTH']) ? self::parseByMonthList($parts['BYMONTH']) : [];

        return [
            'freq' => $frequency,
            'interval' => $interval,
            'count' => $count,
            'until' => $until,
            'byday' => $byday,
            'bymonthday' => $bymonthday,
            'bymonth' => $bymonth,
        ];
    }

    private static function advanceOccurrence(DateTimeImmutable $current, array $rule): ?DateTimeImmutable
    {
        $interval = $rule['interval'];
        $interval = $interval > 0 ? $interval : 1;

        switch ($rule['freq']) {
            case 'DAILY':
                return $current->add(new DateInterval('P' . $interval . 'D'));
            case 'WEEKLY':
                return self::advanceWeekly($current, $rule['byday'], $interval);
            case 'MONTHLY':
                return self::advanceMonthly($current, $rule['bymonthday'], $interval);
            case 'YEARLY':
                return self::advanceYearly($current, $rule['bymonth'], $rule['bymonthday'], $interval);
        }

        return null;
    }

    /**
     * @param array<int> $byday
     */
    private static function advanceWeekly(DateTimeImmutable $current, array $byday, int $interval): DateTimeImmutable
    {
        if ($byday === []) {
            return $current->add(new DateInterval('P' . (7 * $interval) . 'D'));
        }

        $currentDow = (int) $current->format('N');
        foreach ($byday as $day) {
            if ($day > $currentDow) {
                $diff = $day - $currentDow;
                return $current->add(new DateInterval('P' . $diff . 'D'));
            }
        }

        $days = (7 * ($interval - 1)) + (7 - $currentDow) + $byday[0];
        return $current->add(new DateInterval('P' . $days . 'D'));
    }

    /**
     * @param array<int> $bymonthday
     */
    private static function advanceMonthly(DateTimeImmutable $current, array $bymonthday, int $interval): DateTimeImmutable
    {
        if ($bymonthday === []) {
            return self::addMonths($current, $interval);
        }

        $currentDay = (int) $current->format('j');
        foreach ($bymonthday as $day) {
            if ($day > $currentDay) {
                return self::setDay($current, $day);
            }
        }

        $nextBase = self::addMonths($current, $interval);
        return self::setDay($nextBase, $bymonthday[0]);
    }

    /**
     * @param array<int> $bymonth
     * @param array<int> $bymonthday
     */
    private static function advanceYearly(DateTimeImmutable $current, array $bymonth, array $bymonthday, int $interval): DateTimeImmutable
    {
        if ($bymonth === [] && $bymonthday === []) {
            return self::addYears($current, $interval);
        }

        $months = $bymonth !== [] ? $bymonth : [(int) $current->format('n')];
        $days = $bymonthday !== [] ? $bymonthday : [(int) $current->format('j')];

        sort($months);
        sort($days);

        $currentMonth = (int) $current->format('n');
        $currentDay = (int) $current->format('j');
        $currentYear = (int) $current->format('Y');

        foreach ($months as $month) {
            if ($month < $currentMonth) {
                continue;
            }

            if ($month > $currentMonth) {
                $base = $current->setDate($currentYear, $month, 1);
                $day = self::firstValidDay($base, $days);
                return self::setDateComponents($base, $currentYear, $month, $day);
            }

            $nextDay = self::nextDayInList($days, $currentDay, $currentYear, $month);
            if ($nextDay !== null) {
                return self::setDateComponents($current, $currentYear, $month, $nextDay);
            }
        }

        $targetYear = $currentYear + $interval;
        $firstMonth = $months[0];
        $base = $current->setDate($targetYear, $firstMonth, 1);
        $day = self::firstValidDay($base, $days);

        return self::setDateComponents($base, $targetYear, $firstMonth, $day);
    }

    /**
     * @param array<int> $days
     */
    private static function firstValidDay(DateTimeImmutable $base, array $days): int
    {
        $days = $days !== [] ? $days : [(int) $base->format('j')];
        sort($days);
        $month = (int) $base->format('n');
        $year = (int) $base->format('Y');
        $max = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        foreach ($days as $day) {
            if ($day >= 1 && $day <= $max) {
                return $day;
            }
        }

        return $max;
    }

    private static function nextDayInList(array $days, int $currentDay, int $year, int $month): ?int
    {
        $max = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        foreach ($days as $day) {
            if ($day <= $currentDay) {
                continue;
            }
            if ($day >= 1 && $day <= $max) {
                return $day;
            }
        }

        return null;
    }

    private static function setDay(DateTimeImmutable $date, int $day): DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        return self::setDateComponents($date, $year, $month, $day);
    }

    private static function addMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');

        $month += $months;
        while ($month > 12) {
            $month -= 12;
            $year++;
        }
        while ($month < 1) {
            $month += 12;
            $year--;
        }

        return self::setDateComponents($date, $year, $month, $day);
    }

    private static function addYears(DateTimeImmutable $date, int $years): DateTimeImmutable
    {
        $year = (int) $date->format('Y') + $years;
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');

        return self::setDateComponents($date, $year, $month, $day);
    }

    private static function setDateComponents(DateTimeImmutable $date, int $year, int $month, int $day): DateTimeImmutable
    {
        $max = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        if ($day < 1) {
            $day = 1;
        } elseif ($day > $max) {
            $day = $max;
        }

        return $date->setDate($year, $month, $day);
    }

    private static function parseUntil(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = [
            ['Ymd\THis\Z', new DateTimeZone('UTC')],
            ['Ymd\THis', $timezone],
            ['Ymd', $timezone],
        ];

        foreach ($formats as [$format, $tz]) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $tz);
            if ($date instanceof DateTimeImmutable) {
                if ($tz->getName() !== $timezone->getName()) {
                    return $date->setTimezone($timezone);
                }

                return $date;
            }
        }

        try {
            return new DateTimeImmutable($value, $timezone);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @return array<int>
     */
    private static function parseByDayList(string $value): array
    {
        $days = [];
        foreach (explode(',', $value) as $part) {
            $part = strtoupper(trim($part));
            if ($part === '') {
                continue;
            }

            $part = preg_replace('/^[+-]?\d+/', '', $part);
            $map = [
                'MO' => 1,
                'TU' => 2,
                'WE' => 3,
                'TH' => 4,
                'FR' => 5,
                'SA' => 6,
                'SU' => 7,
            ];

            if (isset($map[$part])) {
                $days[] = $map[$part];
            }
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }

    /**
     * @return array<int>
     */
    private static function parseByMonthDayList(string $value): array
    {
        $values = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part === '' || !is_numeric($part)) {
                continue;
            }
            $number = (int) $part;
            if ($number === 0) {
                continue;
            }
            $values[] = $number;
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @return array<int>
     */
    private static function parseByMonthList(string $value): array
    {
        $values = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part === '' || !is_numeric($part)) {
                continue;
            }
            $month = (int) $part;
            if ($month < 1 || $month > 12) {
                continue;
            }
            $values[] = $month;
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }
}

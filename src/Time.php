<?php
declare(strict_types=1);

use MongoDB\BSON\UTCDateTime;

/** All conversions between Mongo UTC dates and Pakistan local time. */
final class Time
{
    public static function tz(): DateTimeZone { return new DateTimeZone(Database::cfg('timezone')); }

    /** "2026-10-16T21:16:59" or "2026-10-16 21:16" (Pakistan time) -> UTCDateTime */
    public static function parse(?string $local): ?UTCDateTime
    {
        $local = trim((string)$local);
        if ($local === '') return null;
        $dt = new DateTimeImmutable($local, self::tz());
        return new UTCDateTime($dt->getTimestamp() * 1000);
    }

    /** UTCDateTime -> "2026-10-16T21:16:59" in Pakistan time (for inputs) */
    public static function toLocal($d): ?string
    {
        if (!$d instanceof UTCDateTime) return null;
        return $d->toDateTime()->setTimezone(self::tz())->format('Y-m-d\TH:i:s');
    }

    /** Business-day window for a date: [start, end) as UTCDateTime */
    public static function window(string $date): array
    {
        $s = new DateTimeImmutable($date . ' ' . Database::cfg('day_start'), self::tz());
        $e = (new DateTimeImmutable($date . ' ' . Database::cfg('day_end'), self::tz()))->modify('+1 day');
        return [new UTCDateTime($s->getTimestamp() * 1000), new UTCDateTime($e->getTimestamp() * 1000)];
    }

    public static function now(): UTCDateTime { return new UTCDateTime((int)(microtime(true) * 1000)); }
}

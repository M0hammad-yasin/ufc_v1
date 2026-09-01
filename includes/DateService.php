<?php
/**
 * includes/DateService.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Centralized date/time utility for the UFC Master Framework.
 */

declare(strict_types=1);

final class DateService
{
    public const FORMAT_STORAGE = 'Y-m-d H:i:s';
    public const FORMAT_DATE    = 'Y-m-d';
    public const FORMAT_DISPLAY = 'M j, Y';

    private const FALLBACK_TZ = 'UTC';
    private static string $appTimezone = self::FALLBACK_TZ;

    private function __construct() {}

    public static function setAppTimezone(string $timezone): void
    {
        if (self::isValidTimezone($timezone)) {
            self::$appTimezone = $timezone;
        }
    }

    public static function getAppTimezone(): string
    {
        return self::$appTimezone;
    }

    public static function nowUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format(self::FORMAT_STORAGE);
    }

    public static function nowTimestamp(): int
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp();
    }

    public static function todayUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format(self::FORMAT_DATE);
    }

    public static function nowDisplay(
        ?string $timezone = null,
        string  $format   = self::FORMAT_DISPLAY
    ): string {
        $tz = self::resolveTimezone($timezone ?? self::$appTimezone);
        return (new DateTimeImmutable('now', $tz))->format($format);
    }

    public static function toUserTimezone(
        ?string $utcDatetime,
        ?string $timezone = null,
        string  $format   = self::FORMAT_DISPLAY,
        string  $fallback = '—'
    ): string {
        if ($utcDatetime === null || $utcDatetime === '') {
            return $fallback;
        }

        $tz = self::resolveTimezone($timezone ?? self::$appTimezone);

        try {
            $dt = new DateTimeImmutable($utcDatetime, new DateTimeZone('UTC'));
            return $dt->setTimezone($tz)->format($format);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public static function toUtc(string $localDatetime, string $timezone): string
    {
        $tz = self::resolveTimezone($timezone);
        $dt = new DateTimeImmutable($localDatetime, $tz);
        return $dt->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT_STORAGE);
    }

    public static function format(
        ?string $utcDatetime,
        string  $format,
        ?string $timezone = null,
        ?string $fallback = null
    ): ?string {
        if ($utcDatetime === null || $utcDatetime === '') {
            return $fallback;
        }

        $tz = self::resolveTimezone($timezone ?? self::$appTimezone);

        try {
            $dt = new DateTimeImmutable($utcDatetime, new DateTimeZone('UTC'));
            return $dt->setTimezone($tz)->format($format);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public static function isValidTimezone(string $timezone): bool
    {
        if ($timezone === '') {
            return false;
        }
        try {
            new DateTimeZone($timezone);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private static function resolveTimezone(string $timezone): DateTimeZone
    {
        if ($timezone === '') {
            return new DateTimeZone(self::FALLBACK_TZ);
        }

        try {
            return new DateTimeZone($timezone);
        } catch (\Exception) {
            return new DateTimeZone(self::FALLBACK_TZ);
        }
    }
}

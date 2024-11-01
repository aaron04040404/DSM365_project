<?php
/**
 * File io functions.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Lib;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Every handler about time.
 * @author Nick Feng
 *
 */
class TimeConv
{
    const REGEX_FULL_DATETIME  = '/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/';
    const REGEX_W3C_DATETIME   = '/([0-2][0-9]{3})\-([0-1][0-9])\-([0-3][0-9])T([0-2][0-9])\:([0-5][0-9])\:([0-5][0-9])(([\-\+]([0-1][0-9])\:([0-5][0-9])))/';
    const REGEX_W3C_TIMEZONE   = '/(([\-\+]([0-1][0-9])\:([0-5][0-9])))/';

    /**
     *
     * @param int $timestamp it can be higher than 2147483648 ( upper to int64 )
     * @param string $format
     * @return false|string
     */
    public static function utcDatetime (int $timestamp, string $format = 'Y-m-d H:i:s')
    {
        return gmdate( $format, $timestamp );
    }
    
    /**
     * Detect W3C datetime string format.
     * 
     * @param string $datetime
     * @return int|false like preg_match returns 1 if the pattern matches given subject,
     *         0 if it does not, or false if an error occurred. 
     */
    public static function isW3C (string $datetime) 
    {
        return preg_match( self::REGEX_W3C_DATETIME, $datetime );
    }
    
    /**
     * Convert w3c time zone string to be integer in second or mini-second.
     *
     * @param string $tz E.g. +08:00, it must in W3C string format
     * @param bool $isMinSec If you want to get value based on mini-second, set it to be TRUE.
     * @return int|boolean
     */
    public static function TimeZoneStr2Sec (string $tz, bool $isMinSec = false)
    {
        if ( preg_match( self::REGEX_W3C_TIMEZONE, $tz ) ) {
            $s = substr($tz, 0, 1) === '-' ? -1 : 1;
            $h = intval(substr($tz, 1, 2));
            $m = intval(substr($tz, 4, 2));
            return $s * ($h * 3600 + $m * 60) * ($isMinSec ? 1000 : 1);
        }
        return false;
    }

    /**
     * Convert to DateTime object structure.
     *
     * @param string $str any kind of datetime string in formal.
     * @param string|null $timezone E.g. UTC, ...
     * @return DateTime if fail, return false.
     * @throws Exception
     */
    public static function str2Datetime(string $str, string $timezone = NULL): DateTime
    {
        $date = date_create($str);
        if ($timezone !== NULL && $date) {
            $date->setTimezone( new DateTimeZone($timezone) ); // UTC
        }
        return $date;
    }
    
    /**
     * Collapse W3C date time string in 3 parts, date, time, and time zone. 
     * 
     * @param string $w3c_datetime string in w3c format
     * @return array ['date' => $d, 'time' => $tm, 'time_zone' => $tz].
     */
    public static function collapseDatetimeW3cStr (string $w3c_datetime): array
    {
        $tm_pos = strpos($w3c_datetime, 'T');
        $tz_pos = strpos($w3c_datetime, '+');
        if (!$tz_pos) $tz_pos = strrpos($w3c_datetime, '-');
        
        $tz = substr($w3c_datetime, $tz_pos);
        $d  = substr($w3c_datetime, 0, $tm_pos);
        $tm = substr($w3c_datetime, ($tm_pos + 1), $tz_pos);
        return ['date' => $d, 'time' => $tm, 'time_zone' => $tz];
    }
}
<?php
/**
 * String processing functions.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Lib;

/**
 * All regex string for comparison.
 * @author Nick Feng
 */
class StrProc
{
    const SHA512_PW_LEN   = 128;
    const ADDRESS_STR_LEN = 256;
    const MAX_TAG_ARR_LEN = 256;
    
    // for regex string test.
    const MD5_HASH_PREG = '/^[0-9a-fA-F]{32}$/';
    
    const REGEX_HOST_IP        = '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\z/';
    const REGEX_USER_ID        = '/^[0-9]{1,20}$/';              // number
    const REGEX_NORMAL_NAME    = '/^([\w.\-\s]){2,32}$/';        // number, alphabet, underline, and space.
    const REGEX_INT_FLOAT      = '/^[+-]?\d+(\.\d+)?$/';         // number in int, double, and float
    const REGEX_SPECIAL_CHAR   = '/^[^!-\/:-@\\[-`\\{-~]*$/';    // 不要刪除，很好用
    
    const REGEX_EMAIL_CHAR     = '/^[A-Za-z0-9][\w\-\.]+[A-Za-z0-9]@[A-Za-z0-9]([\w\-\.]+[A-Za-z0-9]\.)+([A-Za-z]){2,4}$/';
    const REGEX_PWD_CHAR       = '/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,16}/';
    const REGEX_OTP_6CHAR      = '/^[A-Za-z0-9]{6}$/';
    
    const REGEX_PHONE_ZONE_CHAR   = '/^\+?\d{1,10}$/';
    const REGEX_PHONE_ZONE_ALPHA2 = '/^[a-zA-Z]{2}$/';
    
    //const REGEX_POST_ZIPCODE = '/^[-0-9]{3,15}$/';
    
    const REGEX_PHONE_NUM_CHAR_0 = '/^\+?\d+$/';
    const REGEX_PHONE_NUM_CHAR_1 = '/\d?(\s?|-?|\+?|\.?)((\(\d{1,4}\))|(\d{1,3})|\s?)(\s?|-?|\.?)((\(\d{1,3}\))|(\d{1,3})|\s?)(\s?|-?|\.?)((\(\d{1,3}\))|(\d{1,3})|\s?)(\s?|-?|\.?)\d{3}(-|\.|\s)\d{4}/'; 
    const REGEX_PHONE_NUM_CHAR_2 = '/^[0-9]{8,13}$/';
    const REGEX_PHONE_NUM_CHAR_3 = '/^\+?(\d?|\s?|-?)+$/';
    const REGEX_PHONE_NUM_EXT    = '/^\#?[0-9]{1,10}$/';
    
    const REGEX_TAG_CHAR_FILTER = '/[\s!-\,\.-\/:-@\\[-\^`\\{-~]+/'; // for preg_replace();
    
    /**
     * you can not use it
     */
    private function __construct() {}
    
    /**
     * It is more safe to get string length between strlen() and mb_strlen().
     * @param string $s
     * @return int Return false for fail, or number for string length(in utf-8).
     */
    public static function safeStrlen( string $s ): int
    {
        if (!function_exists('mb_detect_encoding')) {
            return strlen($s);
        }
        if (false === $encoding = mb_detect_encoding($s)) {
            return strlen($s);
        }
        if (!function_exists('mb_strlen')) {
            return strlen($s) ;
        }
        return mb_strlen($s, $encoding);
    }

    /**
     * It is more safe to get substring substr() and mb_substr().
     * @param string $s String to deal with.
     * @param int $startPos start position.
     * @param int|null $len length for substring.
     * @return string Empty is fail.
     */
    public static function safeSubstr (string $s, int $startPos = 0, int $len = NULL): string
    {
        if (!function_exists('mb_detect_encoding')) {
            return substr($s, $startPos, $len);
        }
        if (false === $encoding = mb_detect_encoding($s)) {
            return substr($s, $startPos, $len);
        }
        if (!function_exists('mb_substr')) {
            return substr($s, $startPos, $len) ;
        }
        return mb_substr($s, $startPos, $len, $encoding) ;
    }
    
    /**
     * Check country code in alpha 2 or alpha 3.
     * 
     * @param string $alp
     * @return int
     */
    public static function isCountryAlpha ( string $alp ): int
    {
        return preg_match( '/^[a-zA-Z]{2,3}$/', $alp );
    }
    
    /**
     * purify address string
     *
     * @param string $address
     * @return array|string|string[]|null
     */
    public static function purifyAddress (string $address)
    {
        $address = filter_var( trim( $address ), FILTER_SANITIZE_STRING );
        return preg_replace( '/\s+/', ' ', $address ); // Replace multiple spaces with one space
    }
    
    /**
     * check address length.
     * 
     * @param string $address
     * @return boolean
     */
    public static function isAddress (string $address): bool
    {
        return self::safeStrlen($address) <= self::ADDRESS_STR_LEN;
    }
    
    /**
     * 
     * @param string $code
     * @return array|string|string[]|null
     */
    public static function purifyPhone (string $code)
    {
        $code = filter_var( trim( $code ), FILTER_SANITIZE_STRING );
        return preg_replace( '/[-\+\s\s+]/', '', $code );
    }
    
    /**
     * 
     * @param string $zone
     * @return boolean
     */
    public static function isPhoneZoneAlpha2 (string $zone): bool
    {
        return !empty( $zone ) && preg_match( self::REGEX_PHONE_ZONE_ALPHA2, $zone );
    }

    /**
     * check phone number(without zone code) and extension code.
     *
     * @param string $phone
     * @return bool
     */
    public static function isPhoneNum ( string $phone ): bool
    {
        $_p = explode( '#', $phone );
        if ( empty( $phone ) || self::safeStrlen( $phone ) > 24 ) {
            return false;
        } else if ( !preg_match( self::REGEX_PHONE_NUM_CHAR_1, $_p[0] )
            && !preg_match( self::REGEX_PHONE_NUM_CHAR_2, $_p[0] )
            && !preg_match( self::REGEX_PHONE_NUM_CHAR_0, $_p[0] ) )
        {
            return false;
        } else if ( count($_p) === 2 && !preg_match( self::REGEX_PHONE_NUM_EXT, $_p[1] ) ) {
            return false;
        }
        return true;
    }

    /**
     * for phone number single input string, no matter any kind of phone
     *
     * @param string $phone A full phone number string including country code, zone code
     * @return bool
     */
    public static function isPhoneNum_v2 ( string $phone ): bool
    {
        $_p = explode( '#', $phone );
        if ( empty( $phone ) || self::safeStrlen( $phone ) > 24 ) {
            return false;
        } else if ( !preg_match( self::REGEX_PHONE_NUM_CHAR_3, $_p[0] ) ) {
            return false;
        } else if ( count( $_p ) === 2 && !preg_match( self::REGEX_PHONE_NUM_EXT, $_p[1] ) ) {
            return false;
        }
        return true;
    }

    /**
     *
     * @param float $lat
     * @param float $lng
     * @return boolean
     */
    public static function isGPS ( float $lat, float $lng ): bool
    {
        return $lat <= 90.0 && $lat >= -90.0 && $lng <= 180.0 && $lng >= -180.0;
    }
    
    /**
     * Repair all tags you give if it can be.
     * 
     * @param array $tags
     * @return array Return repaired tags in array if it can be
     */
    public static function fixTags ( array $tags ): array
    {
        if ( empty( $tags ) ) {
            return $tags;
        }
        // length can not over 256, so toke off the items over length.
        if ( count( $tags ) > self::MAX_TAG_ARR_LEN ) {
            $tags = array_slice( $tags, 0, self::MAX_TAG_ARR_LEN );
        }
        // remove all space like (\s) tab(\t), return(\r\n),....
        foreach ( $tags as &$val ) {
            $val = preg_replace( self::REGEX_TAG_CHAR_FILTER, '', $val );
        }
        unset( $val );
        $tags = array_unique( $tags );
        $tags = array_filter( $tags, function ( $v, $k ) {
            $len = self::safeStrlen( $v );
            return $len >= 1 && $len <= 100;
        }, ARRAY_FILTER_USE_BOTH );
        return array_values( $tags ); // resort array before return
    }
}
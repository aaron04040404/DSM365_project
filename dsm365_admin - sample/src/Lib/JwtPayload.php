<?php
/**
 * Jwt functions.
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
 * JWT payload function handler
 * 
 * @author Nick Feng
 */
class JwtPayload
{
    const PAYLOAD_AUTH_CHANNEL_USR     = 'usr';
    const PAYLOAD_AUTH_CHANNEL_MCB     = 'mcb';
    const PAYLOAD_AUTH_CHANNEL_MCBAUTO = 'mcb-auto';
    const PAYLOAD_AUTH_CHANNEL_OUTSOURCE = 'outsource';

    const PAYLOAD_ISS = 'DynaScan365-TCC';

    /**
     * Generate a random seed depended on different php versions supporting.
     *
     * @param int $length
     * @return string A seed of random.
     * @throws Exception
     */
    private static function randomSeed ( int$length = 32 ): string
    {
        if(!isset($length) || $length <= 8 ) {
            $length = 32;
        }
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length));  // for php 8.2 or higher
        } else if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length));
        } else {
            return GnRandom::randomStr('v1', $length);
        }
    }

    /**
     * Get a 44 char length string from randomSeed().
     *
     * @param string $seed
     * @param int $length
     * @return string
     * @throws Exception
     */
    public static function genSalt(string $seed = '', int $length = 32): string
    {
        return substr(strtr(base64_encode(hex2bin(self::randomSeed($length)).'|'.$seed), '+', '.'), 0, 44);
    }

    /**
     * JWT official properties, and default expired time will be 24h
     *
     * @param array $data
     * @param string $seed
     * @param string $exp
     * @param string $time_zone
     * @return array a typical payload array.
     * @throws Exception
     */
    public static function genPayload( array $data=[], string $seed='', string $exp = 'now +24 hours', string $time_zone = 'UTC' ): array
    {
        $tz = new DateTimeZone($time_zone);    // it's better to sett the time zone to be UTC.
        return [
            'iat'  => (new DateTime(NULL, $tz))->getTimeStamp(),  // Issued at: time when the token was generated
            'jti'  => self::genSalt($seed),                        // Json Token ID: a unique identifier for the token
            'iss'  => self::PAYLOAD_ISS,                           // Issuer
            'nbf'  => (new DateTime('now', $tz))->getTimeStamp(), // Not before
            'exp'  => (new DateTime($exp, $tz))->getTimeStamp(),  //(new \DateTime('now +24 hours'))->getTimeStamp(),// Expire default is a week
            'data' => $data
        ];
    }

    /**
     * Encode a string for login table with browser information and user password.
     *
     * @param string $seed
     * @param string $type
     * @return string a string encoded.
     */
    public static function genAuthCode( string $seed, string $type = 'md5' ): string
    {
        return hash( $type, $seed );
    }
    
    /**
     * Get separating of JWT Token for different way to detect the token is legal or not.
     *
     * @param string   $channel
     * @param mixed    $uid
     * @return array
     */
    public static function genPayloadData_Auth( string $channel, $uid ): array
    {
        return [
            'uid'     => $uid,
            'channel' => $channel      // let jwt middleware decoder know where does it come from.
        ];
    }
}
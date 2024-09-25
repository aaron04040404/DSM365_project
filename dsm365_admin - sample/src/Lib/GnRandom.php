<?php
/**
 * Jwt functions.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Lib;

/**
 * JWT payload function handler
 * 
 * @author Nick Feng
 */
class GnRandom
{
    /**
     * shuffle seed in 76 character
     * 
     * @var string
     */
    const SHUFFLE_SEED_v1 = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()-_+=';
    
    /**
     * shuffle seed in 62 character
     *
     * @var string
     */
    const SHUFFLE_SEED_v2 = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     *
     * @param string $seed_ver
     * @param int $length
     * @param string $header
     * @return string
     */
    public static function randomStr( string $seed_ver = 'v1', int $length = 32, string $header = '' ): string
    {
        $shuffledCharacters = NULL; // 打亂字符集
        switch( $seed_ver ) {
            case 'v1':
                $shuffledCharacters = str_shuffle( self::SHUFFLE_SEED_v1 );
                break;
            case 'v2':
                $shuffledCharacters = str_shuffle( self::SHUFFLE_SEED_v2 );
                break;
            default:
                return false;
        }
        // 取出指定長度的子字串
        $rand_str = substr( $shuffledCharacters, 0, $length );
        // if there is any header, separate it with "-"
        return (strlen( $header ) > 0) ? $header . '-' . $rand_str : $rand_str;
    }
}
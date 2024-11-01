<?php
/**
 * Regex constant only for DynaScan system.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Lib;

use Gn\Interfaces\StageInterface;

/**
 * All regex string for comparison.
 * @author Nick Feng
 */
class DsParamProc extends RegexConst {
    
    // ============================= for general data checking =============================[start]
    
    public static function isIntArray( array $arr ): bool
    {
        foreach ( $arr as $v ) {
            if ( !is_int( $v ) ) {
                return false;
            }
        }
        return !empty($arr);
    }
    
    public static function isUnsignedInt( array &$arr, bool $toInt = false ): bool
    {
        foreach ( $arr as &$v ) {
            if ( ctype_digit( $v ) ) {
                if ( $toInt ) {
                    $v = (int)$v;
                }
                continue;
            } else if ( is_int( $v ) ) {
                if ( $v >= 0 ) {
                    continue;
                }
            }
            return false;
        }
        unset( $v );
        return !empty($arr);
    }
    
    public static function isNumberArray( array &$arr, bool $toInt = false ): bool
    {
        foreach ( $arr as &$v ) {
            if ( !is_numeric( $v ) && !is_int( $v ) ) {
                return false;
            }
            if ( $toInt ) {
                $v = (int)$v;
            }
        }
        unset( $v );
        return !empty($arr);
    }
    
    public static function uniqueArray( array $arr ): array
    {
        return array_values( array_unique( $arr ) );
    }
    
    public static function isArrayKeysEqual( array $a1, array $a2 ): bool
    {
        return !array_diff_key( $a1, $a2 ) && !array_diff_key( $a2, $a1 );
    }
    
    // ============================= for MCB data checking =============================[start]
    
    public static function isUuidStrArray( array $uuid_arr ): bool
    {
        foreach ( $uuid_arr as $_uuid ) {
            if ( !Uuid::is_valid( $_uuid ) ) {
                return false;
            }
        }
        return !empty($uuid_arr);
    }
    
    public static function isSeriesNumber( string $sn ): bool
    {
        if ( !preg_match( RegexConst::REGEX_SSID_CHAR, $sn ) ) {
            return false;
        }
        return true;
    }
    
    public static function isSeriesNumberArray( array $arr ): bool
    {
        foreach ( $arr as $v ) {
            if ( !is_string( $v ) ) {
                return false;
            } else if ( !self::isSeriesNumber( $v ) ) {
                return false;
            }
        }
        return !empty($arr);
    }
    
    // ============================= for member data checking =============================[start]
    
    public static function isMemberType( int $type ): bool
    {
        if ( $type > 0 && $type < 4 ) {
            return true;
        }
        return false;
    }
    
    // ============================= for stage data checking =============================[start]
    
    public static function inStageScope( int $code ): bool
    {
        if ( $code >= StageInterface::STAGE_CODE_MIN && $code <= StageInterface::STAGE_CODE_MAX ) {
            return true;
        }
        return false;
    }
    
    public static function isStageStatus( int $code ): bool
    {
        if ( $code !== 1 && $code !== 0 ) {
            return false;
        }
        return true;
    }
}
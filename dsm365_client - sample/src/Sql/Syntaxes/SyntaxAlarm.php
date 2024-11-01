<?php
/**
 * Copyright Nick Feng 2021
 * SQL function basic method to extend.
 *
 * Display alarm syntax extensions.
 *
 * @author  Nick Feng
 * @since 1.0
 */
namespace Gn\Sql\Syntaxes;

use Gn\Interfaces\DisplayAlarmInterface;
use Gn\Lib\DsParamProc;

/**
 * SQL syntax functions for redundant expression SQL
 *
 * @author Nick
 */
class SyntaxAlarm implements DisplayAlarmInterface
{
    /**
     *
     * @param array $alarm_codes
     * @param string $table_header
     * @return string|boolean string SQL text for WHERE syntax
     */
    public static function sqlRealtimeAlarmFilter( array $alarm_codes, string $table_header = '' )
    {
        $alarm_codes = DsParamProc::uniqueArray( $alarm_codes );
        if ( empty( $alarm_codes ) ) {
            return '';
        } else {
            $table_header = strlen( $table_header ) === 0 ? $table_header : ( $table_header . '.' );
            $sql_alarm = '';
            $alarm_keys = array_keys( self::DISPLAY_ALARM_FILTER_MAP );  // get key for input checking.
            foreach ( $alarm_codes as $_k ) {
                $_k = (string)$_k; // ensure the parameter is in string.
                if ( !ctype_digit( $_k ) ) {
                    return false;
                } else if ( !in_array( $_k, $alarm_keys ) ) {
                    return false;
                } else { // for mcb alarm filter.
                    if ( strlen( DisplayAlarmInterface::DISPLAY_ALARM_FILTER_MAP[ $_k ] ) === 0 ) {
                        continue;   // skip this round when there is no value(/empty) for the key.
                    }

                    if ( $_k == '201' ) {   // 特殊處理 201。因為他是代表離線狀態(condition_flg=3)，
                        $sql_alarm .= ' OR ' . $table_header . DisplayAlarmInterface::DISPLAY_ALARM_FILTER_MAP[ $_k ] . ' = 3';
                    } else {
                        $sql_alarm .= ' OR ' . $table_header . DisplayAlarmInterface::DISPLAY_ALARM_FILTER_MAP[ $_k ] . ' > 0';
                    }
                }
            }
            return empty( $sql_alarm ) ? $sql_alarm : '(' . trim( $sql_alarm, ' OR ' ) . ')';
        }
    }
    
    /**
     * 
     * @param array $alarm_codes
     * @param string $table_header
     * @return string|bool string SQL text for WHERE syntax
     */
    public static function sqlRealtimeAlarmColumns( array $alarm_codes, string $table_header = '' )
    {
        if ( empty( $alarm_codes ) ) {
            return '';
        } else {
            $table_header = strlen( $table_header ) === 0 ? $table_header : ( $table_header . '.' );
            $sql_cols = '';
            //$alarm_vals = array_values( self::DISPLAY_ALARM_FILTER_MAP );  // get key for input checking.
            foreach ( $alarm_codes as $_k ) {
                $_k = (string)$_k; // ensure the parameter is in string.
                if ( !ctype_digit( $_k ) ) {
                    return false;
                } else if ( !isset( DisplayAlarmInterface::DISPLAY_ALARM_FILTER_MAP[ $_k ] ) ) {
                    return false;
                } else {
                    $_col_name = DisplayAlarmInterface::DISPLAY_ALARM_FILTER_MAP[ $_k ];
                    if ( strlen( $_col_name ) === 0 ) {
                        continue;   // skip this round when there is nothing.
                    }
                    $sql_cols .= ', MAX(' . $table_header . $_col_name . ') AS ' . $_col_name;
                }
            }
            return empty( $sql_cols ) ? $sql_cols : trim( $sql_cols, ', ' );
        }
    }
}

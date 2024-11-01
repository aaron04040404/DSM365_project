<?php
/**
 * Device remote io functions.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Lib;

/**
 * Class deals with dynascan device remote io.
 * @author Nick Feng
 */
class RemoteIO
{
    /**
     * Before call this method about remote device, I suggest to check every mcb account status, and skip them.
     * data json structure as below:
     * [
     *     {
     *         "cmd": 56,
     *         "data": {
     *             "S": 2,
     *             "FO1": 1,
     *             "FO2": 1,
     *             "FO3": 4,
     *             "G": 0
     *         },
     *         "timestamp": int number
     *     },......
     * ]
     *
     * NOTE: this function will repair data you want to detect.
     *
     * @param array $data
     * @return bool
     */
    public static function isRemoteCmdJson ( array &$data ): bool
    {
        if ( empty( $data ) ) {
            return false;
        }
        // check each element
        foreach ( $data as &$v ) {
            // ensure all fields of each command are existed.
            if ( !isset( $v['cmd'] ) || !isset( $v['data'] ) || !isset( $v['timestamp'] ) ) {
                return false;
            }
            // cmd can be an int string or int number
            if ( is_string( $v['cmd'] ) ) {
                // remove all whitespace and line break
                $v['cmd'] = preg_replace( '/\s+/', '', $v['cmd'] );
                if ( !ctype_digit( $v['cmd'] ) || (int)$v['cmd'] === 0 ) { // ctype_digit('-1') return false.
                    return false;
                }
            } else if ( is_int( $v['cmd'] ) ) {
                if ( $v['cmd'] <= 0 ) {
                    return false;
                }
            } else {
                return false;
            }
            // timestamp can be an int string or int number
            if ( is_string( $v['timestamp'] ) ) {
                // remove all whitespace and line break for timestamp
                $v['timestamp'] = preg_replace( '/\s+/', '', $v['timestamp'] );
                if ( !ctype_digit( $v['timestamp'] ) || (int)$v['timestamp'] === 0 ) {
                    return false;
                }
            } else if ( is_int( $v['timestamp'] ) ) {
                if ( $v['timestamp'] <= 0 ) {
                    return false;
                }
            } else {
                return false;
            }
            // remove all whitespace and line break in argument and value.
            $keys = array_keys( $v['data'] );
            if ( gettype( $keys ) !== 'array' || count( $keys ) === 0 ) {
                return false;
            }
            foreach ( $keys as $old_key ) {
                $new_key = strtoupper( preg_replace( '/\s+/', '', $old_key ) );
                // if $new_key is empty, the value will remove forever,
                // and it will not store into database.
                // P.S. when value is ZERO, it doesn't mean empty, so DON'T use empty() to do detection.
                if( strlen( $new_key ) === 0 ) {
                    return false;
                }
                $new_value = $v['data'][ $old_key ];
                if ( is_string( $new_value ) ) {
                    $new_value = preg_replace( '/\s+/', '', $new_value );
                    if ( strlen( $new_value ) === 0 ) {
                        return false;
                    }
                }
                // If new key is different to old one, remove old one.
                $v['data'][ $new_key ] = $new_value;
                if( strcmp( $new_key, $old_key ) !== 0 ) {
                    unset( $v['data'][ $old_key ] );
                }
            }
        }
        unset( $v );
        return true;
    }
    
    /**
     * Work for remote attribute json file to check all properties are correct in 100%.
     *
     * @var array
     */
    const REMOTE_ATTR_VALUE_TYPE_ARR = [
        1,  // 特別指定的 label 名稱 => 特別數值
        2,  // min ~ max 類型
        3,  // table url 
        4   // 保留。暫時無用
    ];
    
    /**
     * Detect the JSON string content is for remote attribute or not.
     *
     * @param string $jsonString
     * @return mixed If it is, it returns a json array, or return false on failure.
     */
    public static function isRemoteAttrJson( string $jsonString )
    {
        $jsonArray = json_decode( $jsonString, true );
        if ( empty( $jsonArray ) ) {
            return false;
        }
        // check content structure.
        foreach ( $jsonArray as $k => $v ) {
            if ( !preg_match( '/[0-9A-Z]{2}[H]/', $k ) ) {
                return false;
            } else if ( empty( $v['desc'] ) || !is_string( $v['desc'] ) ) {
                return false;
            } else if (empty( $v['data'] )) {
                return false;
            }
            foreach ( $v['data'] as $k2 => $v2 ) {
                if ( !preg_match( '/[A-Za-z0-9_-]+$/', $k2 ) ) {
                    return false;
                } else if ( empty( $v2['name'] ) || !is_string( $v2['name'] ) ) {
                    return false;
                } else if (empty( $v2['value'] )) {
                    return false;
                } else if ( !isset( $v2['read_only'] ) || ( $v2['read_only'] !== 1 && $v2['read_only'] !== 0 ) ) {
                    return false;
                } else if ( !isset( $v2['value_type'] ) || !in_array( $v2['value_type'], self::REMOTE_ATTR_VALUE_TYPE_ARR, true ) ) {
                    return false;
                }
                // check values of each value type
                switch ( $v2['value_type'] ) {
                    case self::REMOTE_ATTR_VALUE_TYPE_ARR[0]:
                        foreach ( $v2['value'] as $k3 => $v3 ) {
                            if ( !preg_match( '/[\(\)A-Za-z0-9_-]+$/', $k3 ) ) {
                                return false;
                            } else if ( is_null( $v3 ) ) {
                                return false;
                            }
                        }
                        break;
                    case self::REMOTE_ATTR_VALUE_TYPE_ARR[1]:
                        if ( count( $v2['value'] ) !== 2 ) {
                            return false;
                        } else if ( !isset( $v2['value']['min'] ) || !is_int( $v2['value']['min'] ) ) {
                            return false;
                        } else if ( !isset( $v2['value']['max'] ) || !is_int( $v2['value']['max'] ) ) {
                            return false;
                        }
                        break;
                    case self::REMOTE_ATTR_VALUE_TYPE_ARR[2]:   // back to db find out the big table data.
                        if ( !isset( $v2['value']['template'] ) ) {
                            return false;
                        } else if ( empty( $v2['value']['template'] ) || !is_string( $v2['value']['template'] ) ) {
                            return false;
                        }
                        break;
                    case self::REMOTE_ATTR_VALUE_TYPE_ARR[3]:
                        // do nothing now
                        break;
                    default:
                        return false;
                }
            }
        }
        
        // @2022-08-24: 為了 Sean 可以直接透過 OSD setting 的 RAW data 資料index名稱，直接對應到相同名稱做查詢value
        //              所以在此統一都把 cmd 的字串轉成為大寫，與 Raw data saving 一樣都會轉大寫
        foreach ( $jsonArray as &$v ) {
            $v['data'] = array_combine(
                array_map(
                    function( $str ) {
                        return strtoupper( $str );
                    },
                    array_keys( $v['data'] )
                ),
                array_values( $v['data'] )
            );
        }
        unset( $v );
        
        return $jsonArray;
    }
}
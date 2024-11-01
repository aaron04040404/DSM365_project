<?php
/**
 * All works for wazaari monolog mysql handler in vendor
 */
namespace Gn\Lib;

/**
 * All http response default message string are collected in this interface for implementing
 * 
 * @author Nick Feng 2019-08-30
 */
class WazaariMySqlLog
{
    CONST LOG_TABLE_INDEX = 'table';
    
    // work for wazaari monolog mysql handler additional fields
    const WAZAARI_MYSQL_ADDITIONAL_FIELDS = [
        'url',
        'ip',
        'http_method',
        'server',
        'referrer'
    ];
    /**
     * For MySQL database SELECT syntax.
     * 
     * NOTE: time must be timestamp integer!
     * 
     * @var array
     */
    const LOG_EXPORT_COLUMNS = [
        'id', 
        'channel', 
        'level', 
        'message',
        //'UNIX_TIMESTAMP(time) AS time',
        'time',
        'url', 
        'ip', 
        'http_method', 
        'server', 
        'referrer'
    ];
    
    public static function getDefaultAdditionalFieldValues ( array $values = NULL ): array
    {
        $default_values = [
            'url'         => ($_SERVER['REQUEST_URI'] ?? NULL),
            'ip'          => ($_SERVER['REMOTE_ADDR'] ?? NULL),
            'http_method' => ($_SERVER['REQUEST_METHOD'] ?? NULL),
            'server'      => ($_SERVER['SERVER_NAME'] ?? NULL),
            'referrer'    => ($_SERVER['HTTP_REFERER'] ?? NULL)
        ];
        if ( !empty( $values ) ) {
            foreach ( $values as $k => $v ) {
                if ( isset( $default_values[ $k ] ) ) {
                    $default_values[ $k ] = $v;
                }
            }
        }
        return $default_values;
    }
}

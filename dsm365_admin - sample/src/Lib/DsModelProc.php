<?php
/**
 * Regex constant only for DynaScan system.
 *
 * @author Nick Feng
 * @since 1.0
 */
namespace Gn\Lib;

/**
 * All regex string for comparison.
 * 
 * @author Nick Feng
 */
class DsModelProc extends RegexConst {
    
    /**
     * where or how the user to use the machine to
     *
     * @var array
     */
    const APPLICATION_MAP = [
        'O' => 'outdoor',
        'I' => 'in-door',
        'W' => 'window',
        'S' => 'semi outdoor',
        'K' => 'kiosk'
    ];
    
    /**
     * 
     * @var array
     */
    const BORDER_MAP = [
        'T' => 'borderless',
        'R' => 'border',
        'N' => 'narrow border',
        'H' => 'lightbox'
    ];
    
    /**
     * Decode model name to be the specified specification information.
     * 
     * @param string $model_name
     * @return array|bool
     */
    public static function decode( string $model_name )
    {
        if ( !preg_match( RegexConst::REGEX_MODELNAME_CHAR, $model_name ) ) {
            return false;
        }
        
        $out = [
            'machine_type'  => '',      // 機器種類 -> 用在什麼用途
            'monitor_size'  => '',      // 是幾吋的螢幕大小
            'generation'    => '',      // 第幾代
            //'specification' => '',    // 機器規格 -> 什麼用途
            'monitor_type'  => '',      // double side, single side, .....
            'bright'        => '',      // 螢幕亮度等級
            'border'        => ''       // 邊框、無邊匡、窄邊框、...
        ];
        
        $_offset = 0;
        $str = strtoupper( substr( $model_name, 0, 1 ) );
        switch ( $str ) {
            case 'D':   // 抓後面一個字元
                break;
            case 'C':   // 抓後面兩個字元
                $_offset = 1;
                break;
            default:
                return false;
        }
        // $client_name = strtoupper( substr( $model_name, 1, 1 ) );   // 客戶字首
        $out['machine_type'] = strtoupper( substr( $model_name, ( 1 + $_offset ), 1 ) );   // 機器種類
        $out['machine_type'] = array_key_exists( $out['machine_type'], self::APPLICATION_MAP ) ? self::APPLICATION_MAP[ $out['machine_type'] ] : '';
        
        $out['monitor_size'] = strtoupper( substr( $model_name, ( 2 + $_offset ), 3 ) );   // 是幾吋的螢幕大小
        switch ( $out['monitor_size'] ) {
            case '100': // 目前100吋沒有第幾代的字串可以表示
                $out['generation'] = '1';
                break;
            default:
                $out['generation']   = strtoupper( substr( $out['monitor_size'], 2, 1 ) );  // 第幾代
                $out['monitor_size'] = strtoupper( substr( $out['monitor_size'], 0, 2 ) );
        }
        
        $specification = strtoupper( substr( $model_name, ( 5 + $_offset ), 2 ) );
        switch( strtoupper( substr( $specification, 0, 1 ) ) ) {
            case 'D':   // D
                $out['monitor_type'] = 'double-side';
                break;
            default:    // L, S, ..... 
                $out['monitor_type'] = 'single-side';
        }
        $out['border'] = strtoupper( substr( $specification, 1, 1 ) );
        $out['border'] = array_key_exists( $out['border'], self::BORDER_MAP ) ? self::BORDER_MAP[ $out['border'] ] : '';
        $out['bright'] = strtoupper( substr( $model_name, ( 7 + $_offset ), 1 ) );
        switch ( $out['bright'] ) {
            case '2':
                $out['bright'] = '1000';    // 應該是說1000以下
                break;
            case '3':
                $out['bright'] = '2000';    // Phone說已經沒有了，但是也不清楚到底是多少，所以目前暫定2000
                break;
            case '4':
                $out['bright'] = '3500';
                break;
            case '5':
                $out['bright'] = '4000';
                break;
            case '6':
                $out['bright'] = '5000';
                break;
            case '7':
                $out['bright'] = '10000';
                break;
            default:
                $out['bright'] = '';
        }
        return $out;
    }
}
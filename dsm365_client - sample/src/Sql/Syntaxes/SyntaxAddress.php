<?php
/**
 * Copyright Nick Feng 2021
 * SQL function basic method to extend.
 *
 * The class works for displays or something else in SQL data
 * reading depending on different device model name
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Sql\Syntaxes;

use Gn\Obj\DsAddressObj;

/**
 * Basic functions for SQL
 * @author nick
 *
 */
class SyntaxAddress
{
    /**
     * 檢查地址過濾參數有無問題，以及安排他們做過濾的語法
     *
     * <b>
     * IMPORTANT:
     * addr_country_code table sub-name must be c
     * address table sub-name must be ad
     * </b>
     *
     * @param string $address_tab_agent
     * @param string $address_country_agent
     * @param DsAddressObj $addrObj
     * @param string $sql_txt   output a SQL syntax string
     * @param array $sql_vals   output an array of SQL output syntax
     * @return bool
     */
    public static function sqlAddressFilter ( 
        string $address_tab_agent, 
        string $address_country_agent,  
        DsAddressObj $addrObj, 
        string &$sql_txt, 
        array &$sql_vals ): bool
    {
        if ( empty( $address_tab_agent ) ) {
            return false;
        } else if ( empty( $address_country_agent ) ) {
            return false;
        } else if ( !$addrObj->isValid() ) {
            return false;
        } else if ( $addrObj->isEmpty() ) {
            return true;
        }
        
        $sql_txt = '';
        $sql_vals = [];
        $logic_syntax = ' AND ';
        $addr_filter_arr = [];
        if ( $addrObj->logic === DsAddressObj::ADDR_LOGIC_OR ) {
            $logic_syntax = ' OR ';
        }
        
        if ( !empty( $addrObj->country ) ) {
            $addr_filter_arr[] = '(' . $address_country_agent . '.alpha_2 = ? OR ' . $address_country_agent . '.alpha_3 = ?)';
            array_push( $sql_vals, $addrObj->country, $addrObj->country );
        }
        
        if ( !empty( $addrObj->zip ) ) {
            $addr_filter_arr[] = $address_tab_agent . '.zip_code = ?';
            $sql_vals[] = $addrObj->zip;
        }
        
        if ( !empty( $addrObj->state ) ) {
            $addr_filter_arr[] = $address_tab_agent . '.state = ?';
            $sql_vals[] = $addrObj->state;
        }
        
        if ( !empty( $addrObj->city ) ) {
            $addr_filter_arr[] = $address_tab_agent . '.city = ?';
            $sql_vals[] = $addrObj->city;
        }
        
        if ( !empty( $addr_filter_arr ) ) {
            $sql_txt = '(' . implode( $logic_syntax, $addr_filter_arr ) . ')';
        }
        return true;
    }
}

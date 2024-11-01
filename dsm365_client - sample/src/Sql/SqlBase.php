<?php
/**
 * Copyright Nick Feng 2017
 * SQL function basic method to extend.
 * 
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Sql;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Basic functions for SQL
 *
 * @author Nick
 */
abstract class SqlBase extends SqlPdo
{
    /**
     * Datetime string format for SQL.
     * 
     * @var string
     */
    const SQL_DATE_FORMAT = 'Y-m-d H:i:s';
    
    /**
     * Invitation expired time default is 1 week.
     *
     * @var string
     */
    const INVITATION_EXP_TIME = 'now +168 hours';
    
    /**
     *
     *
     * @var string
     */
    const FULLTEXT_SPECIAL_CHAR = '/[\s\*\+\-\(\)\<\>\"\'\@\~\#\$\%\^\&\=\\\\\\/]+/';

    public function __construct() {}

    /**
     * Eclipse special character for SQL searching string.
     * @param string $str
     * @return array|string|string[]
     */
    public function addSlash2SQLSearchStr(string $str)
    {
        $search = array('%','$','*','+','-','!','(',')','|','^','\'','\"');
        $replace = array('\%','\$','\*','\+','\-','\!','\(','\)','\|','\^','\\\'','\\\"');
        return str_replace( $search, $replace, $str );
    }
    
    /**
     * Eclipse special character for SQL searching string in FULLTEXT.
     * @param string $s
     * @return array|string|string[]|null
     */
    public function removeFullTextSearchSpecialChar ( string $s )
    {
        return preg_replace( self::FULLTEXT_SPECIAL_CHAR, ' ', $s );
    }
    
    /**
     * Remove all special char in tag string.
     * @param string $s
     * @return array|string|string[]|null
     */
    public function removeTagSpecialChar( string $s )
    {
        return preg_replace( '/[\@\#\&\s\*\+\-\(\)\<\>\"\']+/', '', $s );
    }

    /**
     * Return datetime object in customs.
     * @param string|null $time_str
     * @param string $time_zone Default is utc
     * @return DateTime
     * @throws Exception
     */
    public function getDateTime( string $time_str = null, string $time_zone = 'UTC' ): DateTime
    {
        return ( new DateTime( $time_str, new DateTimeZone( $time_zone ) ) );
    }
    
    /**
     * For PDO prepare values' placeholders. E.g. explore ?,?,?,.....
     * 
     * @param string $text
     * @param int $count
     * @param string $separator comma is default
     * @return string
     */
    public function pdoPlaceHolders( string $text, int $count = 0, string $separator = ','): string
    {
        $result = '';
        if ( $count > 0 ) {
            for ( $x = 0; $x < $count; $x++ ) {
                $result .= $text;
                $result .= $separator;
            }
            $result = rtrim( $result, $separator );
        }
        return $result;
    }
    
    /**
     * Export parameters to SQL string for text search.
     *
     * @param array $cols E.g. m.id, id, message, ....
     * @param string $search_txt
     * @return string SQL command for WHERE syntax
     */
    public function pdoSearchHolder (array $cols, string $search_txt = '?'): string
    {
        if ($search_txt !== '?') {
            $search_txt = '\'' . preg_replace( '/[\s,]+/', '|', self::addSlash2SQLSearchStr( $search_txt ) ) . '\'';
        }
        return '(CONCAT_WS(\',\',' . implode( ',', $cols ) . ') REGEXP ' . $search_txt . ')';
    }

    /**
     *
     * @param string|null $txt
     * @return string
     */
    public function fulltextSearchWeights ( string $txt = null ): string
    {
        $txt = trim( $txt );
        $txt = trim( $txt, ',' );
        $out = '';
        if ( empty( $txt ) ) {
            return $out;
        }
        //$elems = preg_split( '/[\s,]+/', self::removeFullTextSearchSpecialChar( $txt ) );
        $elems = preg_split( '/[\s,]+/', $txt );
        foreach ( $elems as $s ) {
            if ( strlen( $s ) > 0 ) {
                if ( preg_match( self::FULLTEXT_SPECIAL_CHAR, $s ) ) {
                    $out .= '"' . $s . '*" ';
                } else {
                    $out .= $s . '* ';
                }
            }
        }
        return rtrim( $out, ' ' );
    }
    
    /**
     * Working for SQL FULLTEXT searching.
     * 
     * NOTE: IN NATURAL MODE WITH QUERY EXPANSION
     * 
     * @param string $q
     * @param array ...$args
     * @return string
     */
    public function fulltextSearchSQL ( string $q, ...$args ): string
    {
        $sql = '';
        $q = self::fulltextSearchWeights( $q );
        if ( empty( $q ) ) {
            return $sql;
        }
        foreach ( $args as $arr ) {
            if ( strlen( $sql ) > 0 ) {
                $sql .= ' OR ';
            }
            if ( is_array( $arr ) ) {
                $cols = '';
                foreach ( $arr as $col_str ) {
                    if ( is_string( $col_str ) && !empty( $col_str ) ) {
                        $cols .= ',' . $col_str;
                    }
                }
                $cols = trim( $cols, ',' );
                $sql .= 'MATCH(' . $cols . ') AGAINST(\'' . $q . '\' IN BOOLEAN MODE)';
            }
        }
        return '(' . $sql . ')';
    }

    /**
     * 效能不是很好，所以不得以之際不要使用它。鼓勵使用 fulltextSearchSQL()
     *
     * @param string $q
     * @param mixed ...$col_name_arr
     * @return string
     */
    public function likeSearchSQL ( string $q, ...$col_name_arr ): string
    {
        $sql = '';
        $q = trim( $q );
        $q = trim( $q, ',' );
        if ( empty( $q ) || sizeof( $col_name_arr ) === 0 ) {
            return $sql;
        }
        //$elems = preg_split( '/[\s,]+/', self::removeFullTextSearchSpecialChar( $txt ) );
        $elems = preg_split( '/[\s,]+/', $q );
        foreach ( $elems as &$s ) {
            $_cache = [];
            foreach ( $col_name_arr as $col ) {
                $_cache[] = ($col . ' LIKE \'%' . $s . '%\'');
            }
            $s = implode( ' OR ', $_cache );
        }
        unset($s);
        return '('.implode( ' OR ', $elems ).')';
    }

    /**
     *
     * @param array $orders
     * @param array $columns
     * @return boolean|string
     */
    public function datatablesOrder2Sql ( array $orders, array $columns )
    {
        // check orders
        $query = '';
        if ( !empty( $orders ) ) {
            if ( count( $orders ) > count( $columns ) ) {
                return false;
            }
            foreach ( $orders as $obj ) {
                if ( !isset( $obj['column'] ) || !is_numeric( $obj['column'] ) ) {
                    return false;
                } else if ( !isset( $obj['dir'] ) || !is_string( $obj['dir'] ) ) {
                    return false;
                } else {
                    if ( !isset( $columns[ (int)$obj['column'] ] ) ) {
                        return false;
                    }
                    $obj['dir'] = trim( strtoupper( $obj['dir'] ) );
                    if ( $obj['dir'] !== 'ASC' && $obj['dir'] !== 'DESC' ) {
                        return false;
                    }
                    $query .= ',' . $columns[ (int)$obj['column'] ] . ' ' . $obj['dir'];
                }
            }
        }
        return trim( $query, ',' ); // take of the beginning and the end comma
    }
    
    /**
     * prepare for datatables javascript framework receiver.
     *
     * @param int $sum
     * @param array $data
     * @return array
     */
    public function datatableProp ( int $sum, array $data = [] ): array
    {
        return array(
            'total' => $sum,
            'data'  => $data
        );
    }
}
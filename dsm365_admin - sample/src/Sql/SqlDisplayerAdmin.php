<?php
/**
 * Device/MCB account admin management.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Sql;

use ErrorException;
use Exception;
use Gn\Lib\DsModelProc;
use Gn\Lib\RegexConst;
use Gn\Lib\StrProc;
use Gn\Interfaces\DisplayModelInterface;
use Gn\Lib\DsParamProc;
use PDOException;

/**
 * All functions about client side display information.
 * 
 * NOTE: you don't have to care about the display network when you release a new display machine account.
 *       Because the display-network protection must be set-up on Ctrl-side
 * 
 * @author Nick
 */
class SqlDisplayerAdmin extends SqlKioskViewer implements DisplayModelInterface
{
    /**
     * Constructor and get pdo connection.
     * 
     * @param array $db_settings
     */
    public function __construct ( array $db_settings )
    {
        parent::__construct( $db_settings );
    }

    /**
     *
     * @param array $arr
     * @param bool $from_sn FALSE => int to sn; TRUE => sn to int
     * @return array|bool
     */
    public function convertMcbSn( array $arr, bool $from_sn = false )
    {
        $sql_input_name  = 'id';
        $sql_output_name = 'sn';
        if ( $from_sn ) {
            if ( !DsParamProc::isSeriesNumberArray( $arr ) ) {
                return false;
            }
            $sql_input_name  = 'sn';
            $sql_output_name = 'id';
        } else {
            if ( !DsParamProc::isUnsignedInt( $arr, true ) ) {
                return false;
            }
        }
        $arr = DsParamProc::uniqueArray( $arr );    // no need to use DISTINCT() in SQL again
        $rows = self::selectTransact(
            'SELECT ' . $sql_output_name .
            ' FROM displayer WHERE ' . $sql_input_name .
            ' IN(' . parent::pdoPlaceHolders( '?', sizeof( $arr ) ) . ')', $arr );
        if ( is_int( $rows ) ) {
            return false;
        }
        if ( count( $arr ) === count( $rows ) ) {
            return parent::getRowsColVal( $rows, $sql_output_name );
        }
        return false;
    }

    /**
     *
     * @param array $mcb_arr
     * @return bool
     */
    public function isMcbExisted ( array $mcb_arr ): bool
    {
        if ( !DsParamProc::isUnsignedInt( $mcb_arr, TRUE ) ) {
            return false;
        }
        $mcb_arr = DsParamProc::uniqueArray( $mcb_arr );    // no need to use DISTINCT() in SQL again
        $rows = self::selectTransact( 'SELECT COUNT(*) AS num FROM displayer WHERE id IN(' . implode( ',', $mcb_arr ) . ')' );
        if ( is_int( $rows ) ) {
            return false;
        }
        if ( count( $mcb_arr ) === $rows[0]['num'] ) {
            return true;
        }
        return false;
    }

    /**
     * Get display account information.
     *
     * @param int $company
     * @param string|int $displayer
     * @param bool $is_series_num If $is_series_num is true, $displayer is a string.
     * @return array|int
     * @author Nick
     */
    public function displayerDetails ( int $company, $displayer, bool $is_series_num = false )
    {
        if ( $company <= 0 ) {
            return self::PROC_INVALID;
        } else if ( $is_series_num && !preg_match( RegexConst::REGEX_SSID_CHAR, $displayer ) ) {
            return self::PROC_INVALID;
        } else if ( !$is_series_num && $displayer <= 0 ) {
            return self::PROC_INVALID;
        }
        $sql = 'SELECT d.id,
                       d.sn,
                       b.mount,
                       d.status,
                       d.model,
                       d.descp,
                       (
                           SELECT
                               JSON_OBJECT( 
                                   \'total_num\', face_total_num, 
                                   \'normal\', face_normal_group, 
                                   \'touch\', face_touch_group,
                                   \'poster\', face_poster_group
                               )
                           FROM displayer_model 
                           WHERE model = d.model
                       ) AS face_info,
                       IFNULL( dk.main_descp, \'\' ) AS main_descp,
                       b.bonding AS main_sn,
                       IFNULL( b.condition_flg, CONVERT( 0, UNSIGNED INTEGER ) ) AS alarm,
                       b.alarm_tags,
                       b.status_tags,
                       IFNULL( c.alpha_2, \'\' ) AS country,
                       IFNULL( ad.zip_code, \'\' ) AS zip_code,
                       IFNULL( ad.state, \'\' ) AS state,
                       IFNULL( ad.city, \'\' ) AS city,
                       IFNULL( ad.address_1, \'\' ) AS address_1,
                       IFNULL( ad.address_2, \'\' ) AS address_2,
                       IFNULL( ST_Y( ad.gps ) + 0.0, 0.0 ) AS gps_lat,
                       IFNULL( ST_X( ad.gps ) + 0.0, 0.0 ) AS gps_lng,
                       UNIX_TIMESTAMP( d.modify_on ) AS modify_on,
                       UNIX_TIMESTAMP( d.create_on ) AS create_on
                FROM displayer AS d
                INNER JOIN displayer_realtime_sync AS b ON b.id = d.id
                LEFT JOIN displayer_kiosk_info AS dk ON dk.main_sn = b.bonding AND dk.company_id = ' . $company .
               ' LEFT JOIN address AS ad ON ad.hash_id = d.address_hash
                LEFT JOIN addr_country_code AS c ON c.code = ad.country_code
                WHERE d.status != \'D\' AND d.belong_to = ' . $company .
               ( $is_series_num ? ' AND d.sn = \'' . $displayer . '\'' : ' AND d.id = ' . $displayer );
        $out = self::selectTransact( $sql );
        if ( !is_int( $out ) ) {
            if ( count( $out ) > 0 ) {
                $out = $out[0];
                if ( !is_null( $out['alarm_tags'] ) ) {
                    $out['alarm_tags'] = json_decode( $out['alarm_tags'], true );
                }
                if ( !is_null( $out['status_tags'] ) ) {
                    $out['status_tags'] = json_decode( $out['status_tags'], true );
                }
                // if it is an empty json or a null return, give it a default array of single side.
                $out['face_info'] = empty( $out['face_info'] ) ? 
                    static::DISPLAY_MODLE_FACE_INFO_DEFAULT : json_decode( $out['face_info'], true );
                return $out;
            }
            // out put an empty array
        }
        return $out;
    }
    
    /**
     * processing:
     *
     * step 1: Get/check ex-address hash in displayer table.
     * step 2: If there is any ex-address hash in displayer table, try to remove them and ignore the one using by others.
     * step 3: Add a new address you're giving to address table, and get the new address hash code.
     * step 4: Update the new hash code into address_hash column in displayer table
     *
     * NOTE: 找出是否有同一個kiosk底下的mcb—帳號, if there is, update them all.
     * IMPORTANT: 這邊已經強迫不會發生「只有地址，沒有GPS；沒有地址，只有GPS」
     *
     * @author Nick
     * @param int $company
     * @param int $display
     * @param string $country_code
     * @param string $zip_code
     * @param string $state
     * @param string $city
     * @param string $addr_01
     * @param string $addr_02
     * @param array $gps
     * @return int Status code in integer
     */
    public function editDisplayAddress (
        int $company,
        int $display,
        string $country_code,
        string $zip_code,
        string $state,
        string $city,
        string $addr_01,
        string $addr_02,
        array $gps ): int
    {
        if ( $company <= 0 || $display <= 0 ) {
            return self::PROC_INVALID;
        }
        
        $country_code = trim( $country_code );
        $zip_code     = trim( $zip_code );
        $state        = StrProc::purifyAddress( $state );
        $city         = StrProc::purifyAddress( $city );
        $addr_01      = StrProc::purifyAddress( $addr_01 );
        $addr_02      = StrProc::purifyAddress( $addr_02 );
        
        if ( !empty( $country_code ) && !StrProc::isCountryAlpha( $country_code ) ) {
            return self::PROC_INVALID;
        } else if ( StrProc::safeStrlen( $zip_code ) > RegexConst::STR_ADDR_ZIPCODE_LEN ) { // there are no post zip code in somewhere
            return self::PROC_INVALID;
        } else if ( StrProc::safeStrlen( $state ) > RegexConst::STR_ADDR_STATE_LEN ) { // there are no state in somewhere
            return self::PROC_INVALID;
        } else if ( StrProc::safeStrlen( $city ) > RegexConst::STR_ADDR_CITY_LEN ) { // there are no city in somewhere
            return self::PROC_INVALID;
        } else if ( StrProc::safeStrlen( $addr_01 ) > RegexConst::STR_ADDR_01_LEN ) {
            return self::PROC_INVALID;
        } else if ( StrProc::safeStrlen( $addr_02 ) > RegexConst::STR_ADDR_02_LEN ) {
            return self::PROC_INVALID;
        }
        // check GPS
        if ( empty( $gps ) ) {
            $gps = [ 0.0, 0.0 ];
        } else if ( count( $gps ) === 2 ) {
            if ( !isset( $gps[0] ) || !isset( $gps[1] ) ) {
                return self::PROC_INVALID;
            }
            $gps[0] = (float)$gps[0];
            $gps[1] = (float)$gps[1];
            if ( !StrProc::isGPS( $gps[0], $gps[1] ) ) {
                return self::PROC_INVALID;
            }
        } else {
            return self::PROC_INVALID;
        }
        
        $addr_hash = $country_code . $zip_code . $state . $city . $addr_01 . $addr_02;
        if ( !empty( $addr_hash ) ) {
            if ( empty( $country_code ) ) { // country code is required field
                return self::PROC_INVALID;
            }
            $addr_hash = hash( 'md5', ( $addr_hash . ':' . $gps[0] . ',' . $gps[1] ) );
        } else {
            $addr_hash = NULL;
        }
        
        // NOTE: Don't need to check mount flag. @2021-10-20 Nick
        $sql_where_def = 'WHERE status != \'D\' AND belong_to = ' . $company .
                         ' AND ( id = ' . $display . 
                               ' OR id IN ( SELECT id FROM displayer_realtime_sync 
                                            WHERE bonding = ( SELECT bonding FROM displayer_realtime_sync 
                                                              WHERE id = ' . $display . ' ) AND bonding != \'\' ) )';
        
        $sql_country = '( SELECT code FROM addr_country_code WHERE alpha_2 = \'' . 
                        $country_code . '\' OR alpha_3 = \'' . $country_code . '\' )';
        
        $ex_hash_codes = array();
        $result = self::PROC_FAIL;
        $stat   = NULL;
        try {
            // step 1: check the previous address and return values without duplicate.
            $stat = $this->pdo->query(
                'SELECT HEX( address_hash ) AS address_hash FROM displayer ' . $sql_where_def .
                ' GROUP BY address_hash HAVING address_hash IS NOT NULL' );
            if ( $stat !== false ) {
                while ( $row = $stat->fetch() ) {
                    $ex_hash_codes[] = $row['address_hash'];
                }
                $stat->closeCursor();
                
                $this->pdo->beginTransaction();
                if ( count( $ex_hash_codes ) === 1 && $ex_hash_codes[0] === $addr_hash ) { // nothing change.
                    //step 2.1: no need to update
                    $result = self::PROC_OK;
                } else {
                    // step 2.2: insert the new address into the table.
                    // NOTE: gps saving into POINT structure must be POINT( lng, lat )
                    $go_next = true;
                    if ( !is_null( $addr_hash ) ) {
                        $stat = $this->pdo->prepare(
                            'INSERT INTO address ( hash_id, country_code, zip_code, state, city, address_1, address_2, gps )
                             SELECT UNHEX( ? ), ' . $sql_country . ', ?, ?, ?, ?, ?, POINT( ?, ? ) FROM DUAL
                             WHERE NOT EXISTS( SELECT hash_id FROM address WHERE hash_id = UNHEX( ? ) )' );
                        $sql_val = [ $addr_hash, $zip_code, $state, $city, $addr_01, $addr_02, $gps[1], $gps[0], $addr_hash ];
                        $go_next = $stat->execute( $sql_val );
                    }
                    
                    // step 3: update display address_hash column value if it is different to previous one.
                    if ( $go_next ) {
                        $stat = $this->pdo->prepare( 'UPDATE displayer SET modify_on = NOW(), address_hash = UNHEX( ? ) ' . $sql_where_def );
                        $go_next = $stat->execute( [ $addr_hash ] );
                    }
                    
                    // step 4: remove previous address content in address table if there is anything existed.
                    if ( $go_next ) {
                        if ( !empty( $ex_hash_codes ) ) {
                            // ignore the using hash code of address. SQL will skip it, and just remove the un-using one.
                            $stat = $this->pdo->prepare(
                                'DELETE IGNORE FROM address WHERE hash_id IN (' .
                                parent::pdoPlaceHolders( 'UNHEX( ? )', sizeof( $ex_hash_codes ) ) . ')' );
                            if ( $stat->execute( $ex_hash_codes ) ) {
                                $result = self::PROC_OK;
                            }
                        } else {
                            $result = self::PROC_OK;
                        }
                    }
                }
                // commit or not for all changing
                if ( $result === self::PROC_OK ) {
                    $this->pdo->commit();
                } else {
                    $this->pdo->rollBack();
                }
            }
        } catch ( PDOException $e ) {
            if ( $this->pdo->inTransaction() ) {
                $this->pdo->rollBack();
            }
            $result = self::PROC_SQL_ERROR; // SQL error code
        }
        if ( $stat !== NULL ) {
            $stat->closeCursor();
            $stat = NULL;
        }
        return $result;
    }

    /**
     * NOTE: By Nick at 2021-06-19 決定把 mark, status description 與 地址相關的東西拆開來
     *       The description saving are separated by mcb account, no need to bind with KIOSK SN
     *
     * @param int $company
     * @param int $id
     * @param int $status
     * @param string|null $desc_txt
     * @return int
     * @author Nick
     */
    public function editDisplayerDetails ( int $company, int $id, int $status = 0, string $desc_txt = NULL ): int
    {
        if ( $status === 0 && is_null( $desc_txt ) ) {
            return self::PROC_INVALID;
        } else if ( $company <= 0 || $id <= 0 ) {
            return self::PROC_INVALID;
        } else if ( $status < 0 || $status > 2 ) { // don't allow 3(status = D)
            return self::PROC_INVALID;
        }
        
        if ( !is_null( $desc_txt ) ) {
            $desc_txt = trim( $desc_txt );
            // empty string for remove ex-content, so must let empty string pass.
            if ( StrProc::safeStrlen( $desc_txt ) > 4096 ) {
                return self::PROC_INVALID;
            }
        }
        
        $out  = self::PROC_FAIL;
        $stat = NULL;
        try {
            // create SQL cmd
            // status = 3(D) is only for account reset, so here cannot set it to be ZERO.
            // Edit them by each other selves.
            $sql = 'UPDATE displayer SET modify_on = NOW()'
                   . ( $status === 0            ? '' : ', status = ?' )
                   . ( is_null( $desc_txt )     ? '' : ', descp = ?' )
                   . ' WHERE belong_to = ' . $company . ' AND id =' . $id
                   . ( $status === 0 ? '' : ' AND status != \'D\'' );
            // set SQL values
            $sql_val = [];
            if ( $status > 0 ) {
                switch ( $status ) {
                    case 1:
                        $sql_val[] = 'A';
                        break;
                    case 2:
                        $sql_val[] = 'N';
                        break;
                    // case 3 is not existed in client side database.
                }
            }
            if ( !is_null( $desc_txt ) ) {
                $sql_val[] = $desc_txt;
            }
            
            $this->pdo->beginTransaction();
            $stat = $this->pdo->prepare( $sql );
            if ( $stat->execute( $sql_val ) ) {
                $this->pdo->commit();
                $out = self::PROC_OK;
            } else {
                $this->pdo->rollBack();
            }
        } catch ( Exception $e ) {
            if ( $this->pdo->inTransaction() ) {
                $this->pdo->rollBack();
            }
            $out = self::PROC_SQL_ERROR;
        }
        if ( $stat !== NULL ) {
            $stat->closeCursor();
            $stat = NULL;
        }
        return $out;
    }
    
    /**
     * 
     * NOTE: 如果未來再 display management 有什要旨針對自己user個人可是的記號做紀錄，請透過這個method
     * 
     * @author Nick
     * @param int $company
     * @param int $display
     * @param int $manage_mark 0 is normal, 1 is highlighted
     * @return int
     */
    public function setDisplayCustomizedMark ( int $company, int $display, int $manage_mark ): int
    {
        if ( $company <= 0 || $display <= 0 ) {
            return self::PROC_INVALID;
        } else if ( $manage_mark < 0 || $manage_mark > 1 ) {
            return self::PROC_INVALID;
        }
        
        $out  = self::PROC_FAIL;
        $stat = NULL;
        try {
            $this->pdo->beginTransaction();
            // NOTE: Don't need to check mount flag. @2021-10-20 Nick
            $stat = $this->pdo->query( 
                'UPDATE displayer SET modify_on = NOW(), mark = ' . $manage_mark .
                ' WHERE status != \'D\' AND belong_to = ' . $company . 
                ' AND ( id = ' . $display . 
                      ' OR id IN ( SELECT id FROM displayer_realtime_sync
                                   WHERE bonding = ( SELECT bonding FROM displayer_realtime_sync 
                                                     WHERE id = ' . $display . ' ) AND bonding != \'\' ) )' );
            if ( $stat !== false ) {
                $this->pdo->commit();
                $out = self::PROC_OK;
            } else {
                $this->pdo->rollBack();
            }
        } catch ( Exception $e ) {
            if ( $this->pdo->inTransaction() ) {
                $this->pdo->rollBack();
            }
            $out = self::PROC_SQL_ERROR;
        }
        if ( $stat !== NULL ) {
            $stat->closeCursor();
            $stat = NULL;
        }
        return $out;
    }

    /**
     * Output all tags about displayer you want to know.
     *
     * @param int $company
     * @param array $displayerIDs
     * @return int|array
     */
    public function getDisplayerTags ( int $company, array $displayerIDs )
    {
        // check vars
        if ( $company <= 0 ) {
            return self::PROC_INVALID;
        } else if ( !DsParamProc::isNumberArray( $displayerIDs ) ) {
            return self::PROC_INVALID;
        }
        $rows = self::selectTransact( 
            'SELECT a.displayer_id, GROUP_CONCAT( b.tag ) AS tags
             FROM displayer_tag AS a
             INNER JOIN tags AS b ON b.uuid = a.tag_uuid
             INNER JOIN displayer AS d ON d.id = a.displayer_id
             WHERE d.belong_to = ' . $company .
            ' AND a.displayer_id IN (' . implode( ',', $displayerIDs ) .
            ') GROUP BY a.displayer_id' );
        if ( is_int( $rows ) ) {
            return $rows;
        }
        $out = [];
        foreach ( $rows as $row ) {
            $out[ $row['displayer_id'] ] = $row['tags'];
        }
        return $out;
    }

    /**
     * Add tags to displayer account and mapping them.
     *
     * Please delete item not existed in $tag.
     *
     * IMPORTANT: admin-side tag 是各自管理的。但是，TCC client-side 是兩邊都要統一的，所以這邊的處理 kiosk group problem
     *
     * IMPORTANT: Serialization failure: 1213 Deadlock found when trying to get lock;
     *            try restarting transaction 問題會有可能出現。請 client 端的程式等待 response 完成後，再進行下一筆資料的修改
     *
     * @param int $company
     * @param int $displayer_id
     * @param array $tags
     * @return int
     * @throws ErrorException
     */
    public function setDisplayerTags ( int $company, int $displayer_id, array $tags ): int
    {
        // check parameters.
        if ( $company <= 0 ) {
            return self::PROC_INVALID;
        } else if ( $displayer_id <= 0 ) {
            return self::PROC_INVALID;
        }
        
        // find out others if it is one on KIOSK machine.
        $kiosk_group = parent::getKioskGroup( $company, $displayer_id, false, true );
        if ( is_int( $kiosk_group ) ) {
            return self::PROC_MCB_KIOSK_GROUP_ERROR;
        }
        
        $displayer_arr = [ $displayer_id ];
        // if there is any kiosk machine, push them int to their array of mcb ID array
        foreach ( $kiosk_group['group'] as $v ) {   // if it is empty, the loop won't work too.
            $displayer_arr[] = $v['id'];
        }
        
        // check tags
        $sql_mcb_tags    = array();
        $sql_insert_tags = array();
        if ( !empty( $tags ) ) {
            $tags = RegexConst::fixTags( $tags );
            if ( empty( $tags ) ) {
                return self::PROC_INVALID;
            }
            // convert each tags to hashed code for tags table saving.
            foreach ( $tags as $t ) {
                $hash_code = hash( 'md5', $t, true ); // a binary code
                array_push( $sql_insert_tags, $hash_code, $t );
                foreach ( $displayer_arr as $id ) {
                    array_push( $sql_mcb_tags, $id, $hash_code );
                }
            }
        }
        
        // ensure the display machine account belongs to the company.
        $rows = parent::selectTransact( 
            'SELECT COUNT(*) AS num FROM displayer WHERE belong_to =' . $company . ' AND id = ' . $displayer_id );
        if ( is_int( $rows ) ) {
            return self::PROC_INVALID;
        } else if ( $rows[0]['num'] !== 1) {
            return self::PROC_FAIL;
        }

        $out  = self::PROC_FAIL;
        $stat = NULL;
        try {
            $this->pdo->beginTransaction();
            // remove all tags unused.
            $stat = $this->pdo->query( 'DELETE displayer_tag FROM displayer_tag WHERE displayer_id IN( ' . implode( ',', $displayer_arr ) . ')' );
            if ( $stat !== false ) {
                // insert new things if there is something new to insert into table; otherwise, just delete it.
                if ( !empty( $tags ) ) {
                    // insert into tags table, and ignore error from existed tag.
                    $stat = $this->pdo->prepare(
                        'INSERT IGNORE INTO tags ( uuid, tag ) VALUES' . parent::pdoPlaceHolders( '( ?, ? )', sizeof( $tags ) ) );
                    if ( $stat->execute( $sql_insert_tags ) ) {
                        // insert tags to displayer_tag table for N:N.
                        $stat = $this->pdo->prepare(
                            'INSERT IGNORE INTO displayer_tag ( displayer_id, tag_uuid ) VALUES' .
                            parent::pdoPlaceHolders( '(?, ?)', ( sizeof( $sql_mcb_tags ) ) / 2 ) );
                        if ( $stat->execute( $sql_mcb_tags ) ) {
                            $out = self::PROC_OK;
                        }
                    }
                } else {
                    $out = self::PROC_OK;
                }
            }
            if ( $out === self::PROC_OK ) {
                $this->pdo->commit();
            } else {
                $this->pdo->rollBack();
            }
        } catch ( PDOException $e ) {
            $out = parent::sqlExceptionProc( $e );
        }
        if ( $stat !== NULL ) {
            $stat->closeCursor();
            $stat = NULL;
        }
        return $out;
    }

    /**
     * Display's current time data lite.
     *
     * 2024-03-21 因為 James 說要斷開 TCC 端的 displayer_realtime_sync 與 admin-side 的 displayer_realtime 於
     * condition_flg 的條建異步。所以，要將原本在 SqlBackSideDisplayInsight、SqlBackSideDisplay兩個類別搬過來一些 method
     *
     * @param int $company_id
     * @param int $display_id
     * @return array|int
     * @author Nick
     */
    public function getDisplayRealtimeLite ( int $company_id, int $display_id )
    {
        if ( $company_id <= 0 || $display_id <= 0 ) {
            return self::PROC_INVALID;
        }

        $out = self::selectTransact(
            'SELECT a.id, 
                    a.sn, 
                    a.model, 
                    a.condition_flg, 
                    a.bonding AS main_sn, 
                    a.soc_tags, 
                    a.alarm_tags, 
                    a.status_tags,
                    UNIX_TIMESTAMP( a.update_on ) AS modify_on
             FROM displayer_realtime_sync AS a
             INNER JOIN displayer AS b ON b.id = a.id
             WHERE b.status != \'D\' AND b.belong_to = ' . $company_id .
            ' AND a.id = ' . $display_id );
        if ( !is_int( $out ) ) {
            // in fact, there is only one result for the display ID you give to.
            if ( count( $out ) > 0 ) {
                // convert JSON data to array type
                $out[0]['soc_tags']    = empty( $out[0]['soc_tags'] )    ? NULL : json_decode( $out[0]['soc_tags'], true );
                $out[0]['alarm_tags']  = empty( $out[0]['alarm_tags'] )  ? NULL : json_decode( $out[0]['alarm_tags'], true );
                $out[0]['status_tags'] = empty( $out[0]['status_tags'] ) ? NULL : json_decode( $out[0]['status_tags'], true );
                return $out[0];
            } else {
                return self::PROC_FAIL;
            }
        }
        return $out;
    }

    /**
     * Get the Current real time saving status from displayer_realtime_sync on back-side server.
     *
     * NOTE: 2024-03-21 因為 James 說要斷開 TCC 端的 displayer_realtime_sync 與 admin-side 的 displayer_realtime 於
     *       condition_flg 的條建異步。所以，要將原本在 SqlBackSideDisplayInsight、SqlBackSideDisplay兩個類別搬過來一些 method
     *
     * IMPORTANT: 這邊有應用到 dynascan365_main、dynascan365_client 兩個 database。所以要小心謹慎
     *
     * @param int $company_id
     * @param int $display_id
     * @return int|array return an array; otherwise, return integer fail code.
     *@author Nick
     */
    public function getDisplayRealtime ( int $company_id, int $display_id )
    {
        if ( $company_id <= 0 || $display_id <= 0 ) {
            return self::PROC_INVALID;
        }

        $result = self::PROC_FAIL;
        $stat   = NULL;
        try {
            // you should prevent the output result is not the calculating warning events by Python back-process.
            $stat = $this->pdo->query(
                'SELECT a.id, 
                        a.sn,
                        a.model,
                        c.name AS company_name,
                        (
                            SELECT
                                JSON_OBJECT( 
                                    \'total_num\', face_total_num, 
                                    \'normal\',    face_normal_group, 
                                    \'touch\',     face_touch_group,
                                    \'poster\',    face_poster_group
                                )
                            FROM dynascan365_client.displayer_model 
                            WHERE model = a.model
                        ) AS face_info,
                        a.ping,
                        a.mount,
                        a.condition_flg, 
                        a.bonding AS main_sn, 
                        IF( a.bonding != \'\' AND a.bonding IS NOT NULL, 
                            IFNULL( ( SELECT GROUP_CONCAT( bb.id ) 
                                      FROM dynascan365_client.displayer_realtime_sync AS bb
                                      INNER JOIN dynascan365_client.displayer AS aa ON aa.id = bb.id
                                      WHERE aa.status != \'D\' AND 
                                            bb.bonding = a.bonding AND 
                                            bb.id != a.id AND 
                                            aa.belong_to = ' . $company_id . ' ), \'\' ),
                            \'\' 
                        ) AS bonding,
                        a.firmware_ver, 
                        a.hardware_ver, 
                        (
                            SELECT CONVERT( (
                                    IF( SUM( r.running ) = 0 OR SUM( r.running ) IS NULL, 0, SUM( r.running ) / 3600 )
                                ), DECIMAL( 10, 2 ) )
                            FROM dynascan365_main.displayer_runningtime AS r
                            WHERE r.belong_to = ' . $company_id . ' AND r.id = ' . $display_id .
                       ') AS running_time, 
                        a.dsm365_v, 
                        a.dsservice_v,
                        a.rd_fw_ver,
                        a.android_v, 
                        a.soc_tags, 
                        a.alarm_tags, 
                        a.status_tags, 
                        UNIX_TIMESTAMP( a.update_on ) AS modify_on,
                        (
                            SELECT IFNULL( MIN( UNIX_TIMESTAMP( time_on ) ), 0 )
                            FROM dynascan365_main.displayer_runningtime
                            WHERE belong_to = ' . $company_id . ' AND id = ' . $display_id .
                       ' ) AS life_on,
                        IFNULL( CAST( JSON_UNQUOTE( JSON_EXTRACT( a.last_raw, \'$.B0H.data.THRESHOLD\' ) ) AS SIGNED ) , 0 ) AS bright_threshold
                 FROM dynascan365_client.displayer_realtime_sync AS a 
                 INNER JOIN dynascan365_client.displayer AS b ON b.id = a.id
                 INNER JOIN dynascan365_client.company AS c ON c.id = b.belong_to
                 WHERE c.status = 1 AND b.status != \'D\' AND b.belong_to = ' . $company_id .
                ' AND a.id = ' . $display_id );
            if ( $stat !== false ) {
                if ( $stat->rowCount() > 0 ) { // return the only one result.
                    $result = $stat->fetch();

                    // add a new column to output the decoded model name for spec
                    $result['model_spec'] = DsModelProc::decode( $result['model'] );
                    /*if ( $result['model_spec'] === false ) {
                        $result['model_spec'] = [];
                    }*/

                    // Sean needs the dsservice_v to map different panel version for display on website
                    $result['dsservice_v'] = preg_replace( '/[\'|\"]+/', '', preg_replace( '/[\s|\\\\n|\\\\r]+/', ' ', trim( $result['dsservice_v'] ) ) );
                    /*$result['dsservice_v'] = trim($result['dsservice_v'], '"');
                    $result['dsservice_v'] = preg_replace('/\n/', ' ', $result['dsservice_v']);
                    $result['dsservice_v'] = preg_replace('/\\n/', ' ', $result['dsservice_v']);*/
                    $result['dsservice_v'] = explode( ' ', $result['dsservice_v'] );
                    $result['dsservice_v'] = $result['dsservice_v'][0];

                    // convert JSON data to array type
                    $result['soc_tags']    = empty( $result['soc_tags'] )    ? NULL : json_decode( $result['soc_tags'], true );
                    $result['alarm_tags']  = empty( $result['alarm_tags'] )  ? NULL : json_decode( $result['alarm_tags'], true );
                    $result['status_tags'] = empty( $result['status_tags'] ) ? NULL : json_decode( $result['status_tags'], true );
                    // if it is an empty json or a null return, give it a default array of single side.
                    $result['face_info'] = empty( $result['face_info'] ) ?
                        static::DISPLAY_MODLE_FACE_INFO_DEFAULT : json_decode( $result['face_info'], true );
                    // earlier version may not have the tag. you should to use it to repair it.
                    if ( !is_null( $result['alarm_tags'] ) ) {
                        if ( !isset( $result['alarm_tags']['thermal'] ) || !ctype_digit( $result['alarm_tags']['thermal'] ) ) {
                            $stat->closeCursor();
                            // append thermal flag to realtime with default value.
                            $result['alarm_tags']['thermal'] = '';
                            // thermal-up condition up, find out the max protection level value.
                            $stat = $this->pdo->query(
                                'SELECT JSON_UNQUOTE( JSON_EXTRACT( args, \'$.OS\' ) ) AS max_lv
                                 FROM dynascan365_main.mcb_detail_record
                                 WHERE mcb_id = ' . $display_id .
                                ' AND cmd = 78 AND status = 0
                                  AND record_time <= NOW() AND create_on <= NOW()
                                  AND CONVERT( JSON_EXTRACT( args, \'$.O\' ), UNSIGNED ) = 0
                                 LIMIT 1'
                            );
                            if ( $stat !== false ) {
                                $row = $stat->fetch();
                                if ( !empty( $row ) && !empty( $row['max_lv'] ) ) {
                                    $result['alarm_tags']['thermal'] = $row['max_lv']; // keep the number in string.
                                }
                            }
                        }
                    }
                }
            }
        } catch ( PDOException $e ) {
            $result = parent::sqlExceptionProc( $e );
        }
        if ( $stat !== NULL ) {
            $stat->closeCursor();
            $stat = NULL;
        }
        return $result;
    }
}

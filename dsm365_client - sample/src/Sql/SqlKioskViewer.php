<?php
/**
 * It works for KIOSK machines
 *
 * @author Nick Feng
 * @since 1.0
 */
namespace Gn\Sql;

use Gn\Lib\DsParamProc;

/**
 * 這是專門處理KIOSK機器的視角，找出它可以看到的所有 mcb ID所對應的所有KIOSK成員
 *
 * IMPORTANT: admin 的處理與 client 的處理事有些許不同的。有要考慮 belong_to 的問題
 *
 * @author Nick Feng 2023-09-05
 */
abstract class SqlKioskViewer extends SqlTransact
{
    /**
     * Constructor and get pdo connection.
     *
     * @param array $db_settings database settings from Slim 3 settings array
     */
    public function __construct ( array $db_settings )
    {
        parent::__construct( $db_settings );
    }

    /**
     * Working for kiosk devices
     *
     * IMPORTANT: "WITHOUT NETWORK & STAGE CONFINE"
     *
     * @2023-09-19: Nick 決定用這個 function 取代 SqlDsService.php -> getKioskDisplays()。
     *              因為要把kiosk視為一個機器，所以同一個機器底下的 mcb 就無必要再去檢查 network & stage。
     *              如果一個kiosk底下的mcb有被不同的 stage & network 所區別開來，其實應該要視為系統的問題，而不是在這邊去區分。
     *              應該要去最源頭的地方解決問題才是。
     *
     * @param int $company
     * @param int $mcb_id
     * @param bool $hasSelf 當你如果不確定他是不是 kiosk 的lcm 之時，千萬不要用 true。否則，它會是 empty 的 group 一樣回傳給你
     * @param bool $hasUnmount
     * @param bool $hasDeleted If true, it means working with removed one.
     * @return array|int if it is not a kiosk, you will get empty output structure as
     *         [
     *             'main_sn'  => '',
     *             'face_num' => 0,
     *             'group'    => []
     *         ]
     * @author Nick
     */
    public function getKioskGroup ( int $company, int $mcb_id, bool $hasSelf = false, bool $hasUnmount = false, bool $hasDeleted = false )
    {
        if ( $company <= 0 ) {
            return self::PROC_INVALID;
        } else if ( $mcb_id <= 0 ) {
            return self::PROC_INVALID;
        }
        // SQL syntax
        // NOTE: @2022-09-02
        //       if there is no model name in displayer_model table, it means it is not a kiosk display.
        //       in other words, it makes sure the kiosk finding it right.
        //       由於新增了 displayer_model，可以藉由這個表單確認是否為 kiosk，
        //       而不用單獨依靠 realtime table 的 bonding 欄位來判定。因為該欄位很容易有人為輸入的問題
        $sql_tabs = 'FROM displayer AS a
                     INNER JOIN displayer_realtime_sync AS b ON b.id = a.id
                     INNER JOIN displayer_model AS c ON c.model = a.model ';
        $sql_where_base = 'WHERE b.bonding = (
                               SELECT bonding FROM displayer_realtime_sync
                               WHERE bonding != \'\' AND id = ' . $mcb_id . 
                          ') AND ( c.face_normal_num + c.face_touch_num = c.face_total_num ) AND c.face_total_num >= 2 AND
                           a.belong_to = ' . $company . 
                          ( $hasUnmount ? ' ' : ' AND b.mount = 1 ' ) .
                          ( $hasDeleted ? ' ' : ' AND a.status != \'D\' ' );
        // NOTE: because of network you have to group-by all results
        $out = self::selectTransact(
            'SELECT a.id, a.sn, a.belong_to, b.lcm_id, c.face_total_num, b.bonding AS main_sn, UNIX_TIMESTAMP( b.update_on ) AS update_on ' .
            $sql_tabs . 
            $sql_where_base . 
            'GROUP BY a.id, a.sn, b.lcm_id, c.face_total_num, b.bonding, update_on ' .
            ( $hasSelf ? '' : 'HAVING a.id != ' . $mcb_id ) );
        if ( is_int( $out ) ) {
            return $out;
        }
        $rows = [
            'main_sn'  => '',
            'face_num' => 0,
            'group'    => []
        ];
        foreach ( $out as $row ) {
            $rows['main_sn']  = $row['main_sn'];
            $rows['face_num'] = $row['face_total_num'];
            $rows['group'][] = array(
                'id' => $row['id'],
                'sn' => $row['sn'],
                'lcm_id' => $row['lcm_id'],
                'update_on' => $row['update_on'],   // the last time point for update from raw data passing
                'belong_to' => $row['belong_to']
            );
        }
        return $rows;
    }

    /**
     * Detect whether the MCB ID(s) belongs to KIOSK machine or not
     *
     * @param int $company
     * @param array $mcb_id_arr
     * @return array|false
     */
    public function isKiosk ( int $company, array $mcb_id_arr )
    {
        if ( $company <= 0 || !DsParamProc::isUnsignedInt( $mcb_id_arr ) ) {   // if the array is empty, it will return false too.
            return static::PROC_INVALID;
        }
        $id_str = implode( ',', $mcb_id_arr );
        $out = self::selectTransact(
            'SELECT a.id
             FROM displayer AS a
             INNER JOIN displayer_realtime_sync AS b ON b.id = a.id
             INNER JOIN displayer_model AS c ON c.model = b.model
             WHERE a.id IN(' . $id_str .
            ') AND c.is_kiosk = 1 AND b.bonding != \'\' AND b.bonding IS NOT NULL AND a.belong_to = ' .
            $company
        );
        if ( !is_int( $out) ) {
            if ( count( $out ) == count( $mcb_id_arr ) ) {
                return $out;
            }
        }
        return false;
    }
    
    /**
     * Filter the Kiosk and get it other side
     * Note:
     * Input the array of mcb_id and return array that contain the mcb_id what belong to Kiosk(is_kiosk = 1) 
     * and the other side of the input id
     *
     * @param int $company
     * @param array $mcb_id_arr
     * @return array|false
     */
    public function KioskotherSide(int $company, array $mcb_id_arr)
    {  
        if ( $company <= 0 || !DsParamProc::isUnsignedInt( $mcb_id_arr ) ) {   // if the array is empty, it will return false too.
            return static::PROC_INVALID;
        }
        $id_str = implode( ',', $mcb_id_arr );
        $sql = 'SELECT DISTINCT bb.id
                FROM
                (SELECT a.id, b.bonding AS main_sn
                FROM displayer AS a
                INNER JOIN displayer_realtime_sync AS b ON b.id = a.id
                INNER JOIN displayer_model AS c ON c.model = b.model
                WHERE a.id IN(' . $id_str . ') AND c.is_kiosk = 1 AND b.bonding != \'\' AND b.bonding IS NOT NULL AND a.belong_to = ' . 
                $company .') AS tab_1
                LEFT JOIN displayer_realtime_sync AS bb ON tab_1.main_sn = bb.bonding;';
        $out = self::selectTransact($sql);
        if( !is_int($out) ){
            return parent::getRowsColVal($out, 'id');//取id的值
        }

        return false;

    } 
}

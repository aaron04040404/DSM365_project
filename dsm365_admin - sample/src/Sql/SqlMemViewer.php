<?php
/**
 * Vision of member among stage and network.
 *
 * @author Nick Feng
 * @since 1.0
 */
namespace Gn\Sql;

use ErrorException;
use Gn\Interfaces\DisplayNetworkInterface;
use Gn\Lib\DsParamProc;

/**
 * 這是由一個使用者的 stage & network 視角，找出它可以看到的所有 mcb ID
 * 只要不是 owner (member type = 1) 等級的人，全部都要接受 stage 的檢驗
 * NOTE: 因為 network 和 stage 的資料已經都在 constructor 的時候就透過參數傳進來了
 *       所以之後的過濾就不用在一直使用 join network 的方式去一直過濾了。只需要將member 自己帶來了
 *       network uuid 去做 where 的過濾即可
 * 
 * @author Nick Feng 2023-09-05
 */
abstract class SqlMemViewer extends SqlKioskViewer implements DisplayNetworkInterface
{
    /**
     * It is from SqlRegister::isApiJwt method output.
     * If there is anything changed from SqlRegister::isApiJwt, you have to confirm this part.
     * 
     * @var array
     */
    protected $requester_auth = NULL;

    /**
     * Constructor and get pdo connection.
     *
     * @param array $db_settings database settings from Slim 3 settings array
     * @param array $requester_auth It is from SqlRegister::isApiJwt method output:
     *          [
     *              'id'         => $perms['user_id'],
     *              'company'    => $perms['company_id'],
     *              'plan'       => $perms['plan_id'],
     *              'type'       => $perms['type'],
     *              'permission' => array(),
     *              'network'    => array(),
     *              'stage'      => array()
     *          ];
     * @throws ErrorException
     */
    public function __construct ( array $db_settings, array $requester_auth )
    {
        parent::__construct( $db_settings );
        if ( empty( $requester_auth ) ) {
            throw new ErrorException( static::EXCEPTION_MSG_AUTH_EMPTY );
        }
        $this->requester_auth = $requester_auth;
    }

    /**
     * 這是由一個使用者的 stage 視角，找出它可以看到的所有 mcb ID
     * 只要不是 owner (member type = 1) 等級的人，全部都要接受 stage 的檢驗
     *
     * @param array $stage_filter
     * @param bool $strictMount
     * @param bool $strictStatus
     * @return array|int         Array of mcb ID, or error number
     */
    public function mem2mcbStageView( array $stage_filter = [], bool $strictMount = false, bool $strictStatus = true )
    {
        if ( !empty( $stage_filter ) ) {
            $stage_filter = DsParamProc::uniqueArray( $stage_filter );  // unique & re-index
            foreach ( $stage_filter as $id ) {
                if ( !is_int( $id ) && !ctype_digit( $id ) ) {
                    return static::PROC_INVALID;
                }
            }
        }
        // NOTE: 因為有些地方(E.g. remote)是以 LCM 為單位做的列表。所以，只要 mount = 0 就不需要列出來了。除非去把小九重新註冊 mount = 1，它才會再出現在這個列表之中。
        //       所以，大部分只要是 kiosk 多面一機的機種，大多都是可以忽略 mount = 1 的要求。否則，會造成(全面都unmount的狀態下)可能全機序號消失在列表的問題。
        // NOTE: kWh做統計的時候，就不會需要篩選掉 status != 'D' 的項目。因為是過往曾經已經成立的因素，不需要被遮蔽。所以，也需要手動可以調節的 $strictStatus。
        // IMPORTANT: client端才需要去考量 belong_to 欄位的歸屬，以免造成讀取到錯誤的company 產品
        $rows = NULL;
        $sql = 'SELECT a.id
                FROM displayer AS a
                INNER JOIN displayer_realtime_sync AS b ON b.id = a.id ' .
                    ( $strictMount ? 'AND b.mount = 1 ' : '' ) .
                    ( $strictStatus ? ' AND a.status != \'D\' ' : '' ) . 
               'WHERE a.belong_to = ' . $this->requester_auth['company'];
        if ( empty( $stage_filter ) ) {
            if ( $this->requester_auth['type'] === 1 ) {
                $rows = parent::selectTransact( $sql );
            } else {
                if ( empty( $this->requester_auth['stage'] ) ) {
                    return [];
                }
                $rows = parent::selectTransact(
                    $sql . ' AND a.situation IN (' . ( parent::pdoPlaceHolders( '?', sizeof( $this->requester_auth['stage'] ) ) ) . ')', 
                    $this->requester_auth['stage'] );
            }
        } else {
            if ( $this->requester_auth['type'] !== 1 ) {
                $stage_filter = array_intersect( $this->requester_auth['stage'], $stage_filter );
                if ( empty( $stage_filter ) ) {
                    return [];
                }
                sort( $stage_filter );
            }
            $rows = parent::selectTransact(
                $sql . ' AND a.situation IN (' . ( parent::pdoPlaceHolders( '?', sizeof( $stage_filter ) ) ) . ')',
                $stage_filter );
        }
        if ( is_int( $rows ) ) {
            return $rows;
        }
        return parent::getRowsColVal( $rows, 'id' );    // it may be an empty array when the id is not found or the rows array is empty.
    }

    /**
     * 這是由一個使用者的 network 視角，找出它可以看到的所有 mcb ID
     * 只要不是 owner (type = 1) & admin (type = 2) 等級的人，全部都要接受 network 的檢驗
     *
     * @param array $net_filter
     * @param bool $strictMount
     * @param bool $strictStatus
     * @return array|int         Array of mcb ID, or error number
     */
    public function mem2mcbNetworkView( array $net_filter = [], bool $strictMount = false, bool $strictStatus = true )
    {
        if ( !empty( $net_filter ) ) {
            if ( !DsParamProc::isUuidStrArray( $net_filter ) ) {
                return static::PROC_INVALID;
            }
            $net_filter = DsParamProc::uniqueArray( $net_filter );  // unique & re-index
        }
        
        // NOTE: 這邊跟客戶端有點不同。客戶端無論是什麼等級，都必須要經過這檢查，至少要檢查是否都屬於客戶自己公司的機器才行！
        $sql = 'SELECT DISTINCT( a.mcb_id ) AS id 
                FROM displayer_network_mcb AS a 
                INNER JOIN displayer_network AS b ON a.net_uuid = b.uuid AND b.status = 1 AND b.company_id = ' . $this->requester_auth['company'] . ' ' .
               'INNER JOIN displayer_realtime_sync AS c ON c.id = a.mcb_id ' . ( $strictMount ? 'AND c.mount = 1 ' : '' ) .
               'INNER JOIN displayer AS d ON d.id = a.mcb_id ' . ( $strictStatus ? 'AND d.status != \'D\' ' : '' ) .
               'WHERE d.belong_to = ' . $this->requester_auth['company'];
        $rows = NULL;
        if ( empty( $net_filter ) ) {
            if ( $this->requester_auth['type'] > self::DETECT_THRESHOLD ) {
                if ( empty( $this->requester_auth['network'] ) ) {
                    return [];
                }
                $rows = parent::selectTransact(
                    $sql . ' AND b.uuid IN (' .
                    parent::pdoPlaceHolders( 'UNHEX( REPLACE( ?, \'-\', \'\' ) )', sizeof( $this->requester_auth['network'] ) ) . ')', 
                    $this->requester_auth['network'] );
            } else {
                // IMPORTANT: client端才需要去考量 belong_to 欄位的歸屬，以免造成讀取到錯誤的company 產品
                $rows = parent::selectTransact(
                    'SELECT a.id 
                     FROM displayer AS a 
                     INNER JOIN displayer_realtime_sync AS b ON b.id = a.id ' .
                        ( $strictMount ? 'AND b.mount = 1 ' : '' ) .
                        ( $strictStatus ? ' AND a.status != \'D\' ' : '' ) . 
                    'WHERE a.belong_to = ' .$this->requester_auth['company'] );
            }
        } else {
            if ( $this->requester_auth['type'] > self::DETECT_THRESHOLD ) {
                $net_filter = array_intersect( $this->requester_auth['network'], $net_filter );
                if ( empty( $net_filter ) ) {
                    return [];
                }
                sort( $net_filter );
            }
            $rows = parent::selectTransact(
                $sql . ' AND b.uuid IN (' .
                parent::pdoPlaceHolders( 'UNHEX( REPLACE( ?, \'-\', \'\' ) )', sizeof( $net_filter ) ) . ')', $net_filter );
        }
        if ( is_int( $rows ) ) {
            return $rows;
        }
        return parent::getRowsColVal( $rows, 'id' );    // it may be an empty array when the id is not found or the rows array is empty.
    }

    /**
     * Export all MCB ID after stage & network effect.
     *
     * @param array $net_cond
     * @param array $stage_cond
     * @param array $mcb_cond check the mcb whether they are in the view. if it is, return the intersected result array
     * @param bool $strictMount
     * @param bool $strictStatus
     * @return array|int
     */
    public function mem2mcbView ( array $net_cond = [], array $stage_cond = [], array $mcb_cond = [], bool $strictMount = false, bool $strictStatus = true ) 
    {
        $out = [];
        $n_id = self::mem2mcbNetworkView( $net_cond, $strictMount, $strictStatus );
        if ( is_int( $n_id ) ) {
            return static::PROC_MEM_VIEW_ERROR;
        } else {
            if ( count( $n_id ) === 0 ) {
                return $out;
            }
        }
        
        $s_id = self::mem2mcbStageView( $stage_cond, $strictMount, $strictStatus );
        if ( is_int( $s_id ) ) {
            return static::PROC_MEM_VIEW_ERROR;
        } else {
            if ( count( $s_id ) === 0 ) {
                return $out;
            }
        }
        
        // 如果所指定的id有存在於 stage & network 過濾後的視角之中，則把存在的取出
        if ( empty( $mcb_cond ) ) {
            $out = array_intersect( $n_id, $s_id );
        } else {
            $out = array_intersect( $mcb_cond, $n_id, $s_id );
        }
        sort( $out );   // 因為上面的處理過後，很有可能 index 會混亂
        return $out;
    }
}

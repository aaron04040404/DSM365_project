<?php
/**
 * It works for user & displayer account stage grouping
 * 
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Sql;

use ErrorException;
use Exception;
use Gn\Lib\DsParamProc;
use Gn\Interfaces\StageInterface;
use Gn\Sql\Syntaxes\SyntaxKiosk;
use PDOException;

/**
 * user register database interaction.
 *
 * @author nick
 */
class SqlDisplayStage extends SqlMemViewer implements StageInterface
{
    /**
     * Constructor and get pdo connection.
     *
     * @param array $db_settings
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
    public function __construct( array $db_settings, array $requester_auth ) 
    {
        parent::__construct( $db_settings, $requester_auth );
    }

    /**
     * IMPORTANT: displayer_situation_company的資料必須是由displayer_situation的允許而進行鍊結
     *
     * @param bool $is_system
     * @return array|int
     */
    public function stageTab ( bool $is_system )
    {
        if ( $is_system ) { 
            // get all kinds of display stage in system
            return $this->selectTransact(
                'SELECT id, name, txt
                 FROM displayer_situation
                 WHERE status = 1' );
        } else {    
            // get all type of display stage from a company
            return $this->selectTransact(
                'SELECT b.id, b.name, b.txt
                 FROM displayer_situation_company AS a
                 INNER JOIN displayer_situation AS b ON b.id = a.life_code
                 WHERE a.company_id = ' . $this->requester_auth['company'] );
        }
    }
    
    /**
     * IMPORTANT: 基本上我不設計可以修改 displayer_situation 表單內容的函式。因為它要是固定的，
     *            """ 而且這個修改功能，目前只可以開放給 super admin 使用 """
     *
     * NOTE: 操控 admin 端的 displayer_situation_company table 各項功能的啟閉之時，要記得把被關閉的項目，全部轉成 0x00 NaN 的項目。
     *       所以，換句話說，NaN的項目 status 是不可以被更改成為 0 的，不然就沒有任何狀態可以歸類了！！！切記！！！
     *
     * @param array $data [
     *     {
     *         'id':     life code id,
     *         'status': 0 is removing, 1 is adding
     *     }
     * ]
     * @return int
     */
    public function stageEditor ( array $data ): int
    {
        if ( empty( $data ) ) {
            return self::PROC_INVALID;
        }
        
        $val_add_lifecode_arr = [];
        $val_del_lifecode_arr = [];
        foreach ( $data as $v ) {
            if ( !isset( $v['id'] ) || !is_int( $v['id'] ) ) {
                return self::PROC_INVALID;
            } else if ( !DsParamProc::inStageScope( $v['id'] ) ) {
                return self::PROC_INVALID;
            }  else if ( !isset( $v['status'] ) || !is_int( $v['status'] ) || $v['status'] < 0 || $v['status'] > 1 ) {
                return self::PROC_INVALID;
            }
            
            // NOTE: push the life-code id which turn to 0 into array
            if ( $v['status'] === 0 ) {
                $val_del_lifecode_arr[] = $v['id'];
            } else if ( $v['status'] === 1 ) {
                $val_add_lifecode_arr[] = $v['id'];
            } else {
                return self::PROC_INVALID;
            }
        }
        
        $out  = self::PROC_FAIL;
        $stat = NULL;
        try {
            $this->pdo->beginTransaction();
            $is_success = true;
            // step 1: remove what you don't need in displayer_situation_company table.
            if ( !empty( $val_del_lifecode_arr ) ) {
                $stat = $this->pdo->prepare(
                    'DELETE FROM displayer_situation_company
                     WHERE company_id = ' . $this->requester_auth['company'] . ' AND life_code IN (' .
                    ( parent::pdoPlaceHolders( '?', sizeof( $val_del_lifecode_arr ) ) ) . ')' );
                if ( $stat->execute( $val_del_lifecode_arr ) === false ) {
                    $is_success = false;
                }
                
                // remove items in displayer_situation_mem of each member has it
                if ( $is_success ) {
                    $stat = $this->pdo->prepare(
                        'DELETE FROM displayer_situation_mem
                         WHERE company_id = ' . $this->requester_auth['company'] . ' AND life_code IN (' . 
                        ( parent::pdoPlaceHolders( '?', sizeof( $val_del_lifecode_arr ) ) ) . ')' );
                    if ( $stat->execute( $val_del_lifecode_arr ) === false ) {
                        $is_success = false;
                    }
                }
                
                // you have to update all displays' stage code to 0 when they are removed
                if ( $is_success ) {
                    // IMPORTANT: the belong_to column value works for client side. you don't have to
                    //            filter it on admin-side with it
                    $stat = $this->pdo->prepare(
                        'UPDATE displayer SET situation = 0
                         WHERE situation IN (' .
                        ( parent::pdoPlaceHolders( '?', sizeof( $val_del_lifecode_arr ) ) ) . ')');
                    if ( $stat->execute( $val_del_lifecode_arr ) === false ) {
                        $is_success = false;
                    }
                }
            }
            // step 2: add what you need to be in displayer_situation_company table.
            if ( $is_success && !empty( $val_add_lifecode_arr ) ) {
                $stat = $this->pdo->prepare(
                    'INSERT INTO displayer_situation_company (company_id, life_code)
                     SELECT ' . $this->requester_auth['company'] . ', id
                     FROM displayer_situation
                     WHERE status = 1 AND id IN (' .
                        ( parent::pdoPlaceHolders( '?', sizeof( $val_add_lifecode_arr ) ) ) .
                    ') ON DUPLICATE KEY UPDATE modify_on = NOW()' );
                if ( $stat->execute( $val_add_lifecode_arr ) === false ) {
                    $is_success = false;
                }
            }
            // commit for success or rollback on failure.
            if ( $is_success ) {
                $this->pdo->commit();
                $out = self::PROC_OK;
            } else {
                $this->pdo->rollBack();
            }
        } catch ( Exception $e ) {
            $out = parent::sqlExceptionProc( $e );
        }
        if ( $stat !== NULL ) {
            $stat->closeCursor();
            $stat = NULL;
        }
        return $out;
    }

    /**
     * Get stage(s) which a member can work to.
     *
     * @param int $member_id
     * @param bool $strictNum
     * @return array|int
     */
    public function getMemberStages ( int $member_id, bool $strictNum = false )
    {
        if ( $member_id <= 0 ) {
            return self::PROC_INVALID;
        }
        
        // check member view on stage and network effect
        // NOTE: 如果要找尋特定 alarm 的條件，則 mount = 0 就不可以被帶入，而必須被過濾掉。
        $_id_arr = $this->mem2mcbView();
        if ( is_int( $_id_arr ) ) {
            return $_id_arr;
        }
        
        $sql = NULL;
        if ( count( $_id_arr ) === 0 ) {    // NOTE: member view 是否有東西可以顯示，決定你要不要進一步去做統計資料出來
            $sql = 'SELECT a.life_code, b.name, b.txt,
                           IF( ( SELECT type FROM member WHERE id = ' . $member_id . ' ) = 1 OR EXISTS(
                               SELECT * FROM displayer_situation_mem
                               WHERE company_id = ' . $this->requester_auth['company'] .
                              ' AND mem_id = ' . $member_id . ' and life_code = a.life_code ), 1, 0 ) AS enable ' .
                          ( $strictNum ? ', (
                              SELECT COUNT(*)
                              FROM displayer_situation_mem AS aa
                              INNER JOIN member AS bb ON bb.id = aa.mem_id
                              WHERE aa.life_code = a.life_code AND bb.status != 0 AND aa.company_id = ' . $this->requester_auth['company'] .
                          ') AS mem_count, 0 AS mcb_count ' : '' ) .
                   'FROM displayer_situation_company AS a
                    INNER JOIN displayer_situation AS b ON b.id = a.life_code
                    WHERE a.company_id = ' . $this->requester_auth['company'];
        } else {
            $sql = 'SELECT a.life_code, b.name, b.txt,
                           IF( ( SELECT type FROM member WHERE id = ' . $member_id . ' ) = 1 OR EXISTS(
                               SELECT * FROM displayer_situation_mem
                               WHERE company_id = ' . $this->requester_auth['company'] .
                              ' AND mem_id = ' . $member_id . ' and life_code = a.life_code ), 1, 0 ) AS enable ' .
                          ( $strictNum ? ', (
                              SELECT COUNT(*)
                              FROM displayer_situation_mem AS aa
                              INNER JOIN member AS bb ON bb.id = aa.mem_id
                              WHERE aa.life_code = a.life_code AND 
                                    bb.status != 0 AND 
                                    aa.company_id = ' . $this->requester_auth['company'] .
                          ') AS mem_count, (
                              SELECT COUNT(*) AS num
                              FROM (
                                  SELECT '. SyntaxKiosk::sqlBondingMainSn( 'aa', 'bb', 'sn' ).', 
                                         MAX( aa.situation ) AS stage
                                  FROM displayer AS aa
                                  INNER JOIN displayer_realtime_sync AS bb ON bb.id = aa.id
                                  WHERE aa.id IN (' . implode( ',', $_id_arr ) . ')
                                  GROUP BY sn
                              ) AS t
                              WHERE t.stage = a.life_code ' .
                          ') AS mcb_count ' : '' ) .
                   'FROM displayer_situation_company AS a
                    INNER JOIN displayer_situation AS b ON b.id = a.life_code
                    WHERE a.company_id = ' . $this->requester_auth['company'];
        }
        return $this->selectTransact( $sql, null, true );
        
        /*
        // NOTE: TCC客戶端獨有的要求，所以要統計各個stage之中的 member & mcb 的數量
        $sql = 'SELECT a.life_code, b.name, b.txt,
                       IF( ( SELECT type FROM member WHERE id = '.$member_id.' ) = 1 OR EXISTS(
                           SELECT * FROM displayer_situation_mem
                           WHERE company_id = ' . $this->requester_auth['company'] .
                           ' AND mem_id = ' . $member_id . ' and life_code = a.life_code ), 1, 0 ) AS enable ' .
                      ( $strictNum ? ',(
                           SELECT COUNT(*)
                           FROM displayer_situation_mem AS aa
                           INNER JOIN member AS bb ON bb.id = aa.mem_id
                           WHERE aa.life_code = a.life_code AND bb.status != 0 AND aa.company_id = ' . $this->requester_auth['company'] .
                      ') AS mem_count, (
                           SELECT COUNT(*)
                           FROM displayer
                           WHERE situation = a.life_code AND status != \'D\' AND belong_to = ' . $this->requester_auth['company'] .
                      ') AS mcb_count ' : '' ) . 
               'FROM displayer_situation_company AS a
                INNER JOIN displayer_situation AS b ON b.id = a.life_code
                WHERE a.company_id = ' . $this->requester_auth['company'];
        return $this->selectTransact( $sql ); 
        */
    }
    
    /**
     * 將一個 member 與 displayer stage code 做關聯。以便管理該 member 是否只可以檢視哪些場景下，它可以看見的機器
     *
     * @param bool $del
     * @param int $mem_id
     * @param array $stage_arr
     * @return int
     */
    public function memStageMount ( bool $del, int $mem_id, array $stage_arr ): int
    {
        if ( $mem_id <= 0 ) {
            return self::PROC_INVALID;
        } else if ( empty( $stage_arr ) ) {
            return self::PROC_INVALID;
        }
        foreach ( $stage_arr as $v ) {
            if ( !is_int( $v ) ) {
                return self::PROC_INVALID;
            } else if ( !DsParamProc::inStageScope( $v ) ) {
                return self::PROC_INVALID;
            }
        }
        
        $out  = self::PROC_FAIL;
        $stat = NULL;
        try {
            $is_success = true;
            $this->pdo->beginTransaction();
            // 必須檢查這個 stage 是否這間公司可以使用，以及檢查 system 是否有把這個 stage (life code)在全域之中封鎖
            if ( $del ) {
                // NOTE: 刪除的時候，完全不必再去檢查 displayer_situation 中的狀態。只需要在 add 的時候做檢查，從源頭管理好就可以了
                $stat = $this->pdo->prepare(
                    'DELETE FROM displayer_situation_mem
                     WHERE mem_id = ? AND life_code = ? AND company_id = ' .
                    $this->requester_auth['company'] );
                foreach ( $stage_arr as $v ) {
                    if ( $stat->execute( [ $mem_id, $v ] ) === false ) {
                        $is_success = false;
                        break;
                    }
                }
            } else {
                $stat = $this->pdo->prepare(
                    'INSERT INTO displayer_situation_mem (company_id, mem_id, life_code)
                     SELECT ' . $this->requester_auth['company'] . ', ' . $mem_id . ', a.life_code
                     FROM displayer_situation_company AS a
                     INNER JOIN displayer_situation AS b ON b.id = a.life_code AND b.status = 1
                     WHERE a.company_id = ' . $this->requester_auth['company'] . ' AND a.life_code = ?
                     ON DUPLICATE KEY UPDATE modify_on = NOW()' );
                foreach ( $stage_arr as $v ) {
                    if ( $stat->execute( [ $v ] ) === false ) {
                        $is_success = false;
                        break;
                    }
                }
            }
            // commit for success or rollback on failure.
            if ( $is_success ) {
                $this->pdo->commit();
                $out = self::PROC_OK;
            } else {
                $this->pdo->rollBack();
            }
        } catch ( Exception $e ) {
            $out = parent::sqlExceptionProc( $e );
        }
        if ( $stat !== NULL ) {
            $stat->closeCursor();
            $stat = NULL;
        }
        return $out;
    }
    
    /**
     * Edit stage code of a displayer
     *
     * @param int $company
     * @param array $life_arr [
     *     {
     *         'id':   mcb int id,
     *         'life': int life code
     *     }
     * ]
     * @return int Status code
     */
    public function editDisplayerStageCode( int $company, array $life_arr ): int
    {
        if ( empty( $life_arr ) || $company <= 0 ) {
            return self::PROC_INVALID;
        }
        // check array contents
        foreach ( $life_arr as $v ) {
            if ( !isset( $v['id'] ) || !is_int( $v['id'] ) || $v['id'] <= 0 ) {
                return self::PROC_INVALID;
            } else if ( !isset( $v['life'] ) || !is_int( $v['life'] ) ) {
                return self::PROC_INVALID;
            } else if ( !DsParamProc::inStageScope( $v['life'] ) ) {
                return self::PROC_INVALID;
            }
        }
        
        $result = self::PROC_FAIL;
        $stat   = NULL;
        try {
            $isDead = false;
            $this->pdo->beginTransaction();
            // NOTE: 如果是 kiosk 機器，就要連同它其他面一起修改，達成所有LCM 的 stage 數值都是統一。
            //       免得影響 displayer main list filter的執行
            // NOTE: 1. If there is no stage code in displayer_situation_company table, please keep original value.
            //       2. If the stage code "status" is ZERO(disable), please keep original value.
            //       3. 因為這裡是 admin 端的程式。所以，只會有一個公司的 id 且為 1
            $stat = $this->pdo->prepare(
                'UPDATE displayer
                 SET situation = IFNULL(
                     ( SELECT life_code
                       FROM displayer_situation_company
                       WHERE life_code = ? AND company_id = '.$company.' ), situation )
                 WHERE status != \'D\' AND (
                     id = ? OR 
                     id IN (
                         SELECT id FROM (
                             SELECT id 
                             FROM displayer_realtime_sync
                             WHERE bonding = (
                                 SELECT bonding 
                                 FROM displayer_realtime_sync AS b
                                 INNER JOIN displayer AS a ON a.id = b.id
                                 INNER JOIN displayer_model AS c ON c.model = a.model
                                 WHERE a.id = ? AND b.bonding != \'\' AND b.bonding IS NOT NULL 
                             ) AND bonding != \'\'
                         ) AS subquery
                     )
                 )' );
            foreach ( $life_arr as $v ) {
                if ( !$stat->execute( [ $v['life'], $v['id'], $v['id'] ] ) ) {
                    $isDead = true;
                    break;
                }
            }
            if ( $isDead ) {
                $this->pdo->rollBack();
            } else {
                $this->pdo->commit();
                $result = self::PROC_OK;
            }
        } catch ( PDOException $e ) { //PDOException
            $result = parent::sqlExceptionProc( $e );
        }
        if ( $stat !== NULL ) {
            $stat->closeCursor();
            $stat = NULL;
        }
        return $result;
    }

    /**
     *
     * @param array $mcb_arr
     * @return int
     */
    public function inMemberStages( array $mcb_arr ): int
    {
        if ( !DsParamProc::isUnsignedInt( $mcb_arr, true ) ) {
            return self::PROC_INVALID;
        }
        $mcb_arr = DsParamProc::uniqueArray( $mcb_arr );
        $_id_arr = $this->mem2mcbStageView();
        if ( is_int( $_id_arr ) ) {
            return $_id_arr;
        }
        $_cache_arr = array_diff( $mcb_arr, $_id_arr );
        if ( empty( $_cache_arr ) ) {
            return self::PROC_OK;
        }
        return self::PROC_FAIL;
    }
}

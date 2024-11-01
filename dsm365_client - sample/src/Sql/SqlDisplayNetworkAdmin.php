<?php
/**
 * Device/MCB account register.
 *
 * @author Nick Feng
 * @since 1.0
 */
namespace Gn\Sql;

use ErrorException;
use Gn\Lib\Uuid;
use Gn\Lib\RegexConst;
use Gn\Lib\DsParamProc;
use Gn\Sql\Syntaxes\SyntaxKiosk;

/**
 * All functions about Ds Service APP
 * 
 * @author Nick
 */
class SqlDisplayNetworkAdmin extends SqlMemViewer
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
    public function __construct ( array $db_settings, array $requester_auth )
    {
        // IMPORTANT: 在規劃 network 的時候，必須要先優先考量自身的 stage 是否可以看到這些機器之後，才可以依照自己看到的機器，去指派他的 network 
        parent::__construct( $db_settings, $requester_auth );
    }

    /**
     * All networks under a company.
     *
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string $search
     * @param boolean $withGio output with GOOD, ISSUE, OFFLINE
     * @param bool $is_personal
     * @param array $stage_arr
     * @return array|int
     */
    public function networkTable ( 
        int    $page,
        int    $per,
        array  $order = [],
        string $search = '',
        bool   $withGio = false,
        bool   $is_personal = false,
        array  $stage_arr = [] )
    {
        if ( $page < 0 || $per < 0 || $per > 200 ) {
            return self::PROC_INVALID;
        }
        
        // check orders
        $sql_order = parent::datatablesOrder2Sql(
            $order, [
                't1.name',
                't1.descp',
                'mcb_count',
                'mem_count'
            ]
        );
        // if nothing for order, set default.
        if ( $sql_order === false ) {
            return self::PROC_INVALID;
        } else if ( empty( $sql_order ) ) {
            $sql_order = 't1.name ASC';
        }
        
        // for search string.
        $sql_search = parent::fulltextSearchSQL(
            $search, [
                'n.name',
                'n.descp'
            ]
        );
        if ( !empty( $sql_search ) ) {
            $sql_search = ' AND ' . $sql_search;
        }
        
        // check member view on stage and network effect
        // NOTE: 如果要找尋特定 alarm 的條件，則 mount = 0 就不可以被帶入，而必須被過濾掉。
        $_id_arr = $this->mem2mcbView( [], $stage_arr );
        if ( is_int( $_id_arr ) ) {
            return $_id_arr;
        }

        if( $is_personal ) {
            // check member type for output format choosing.
            $out = parent::selectTransact(
                'SELECT type FROM member WHERE id = ' .
                $this->requester_auth['id'] .
                ' AND company_id = ' .
                $this->requester_auth['company']
            );
            if ( is_int( $out ) ) {
                return $out;
            } else if ( count( $out ) === 0 ) {
                // IMPORTANT: 可能無此人或是此人不再這間公司，避免有不正當的 member 切換後，拿到其他公司的 network 資料
                return self::PROC_FAIL;
            }
            $member_type = $out[0]['type'];
            if( $member_type <= self::DETECT_THRESHOLD ) {
                $is_personal = false;
                // if you are an admin user, even if you set $is_personal = true,
                // the value will be reverse back to false.
            }
        }

        $out = parent::selectTransact(
            'SELECT COUNT(*) AS num 
             FROM displayer_network AS n ' .
            ( $is_personal ?
                'INNER JOIN displayer_network_mem AS nm ON nm.net_uuid = n.uuid AND nm.mem_id = ' . $this->requester_auth['id'] . ' ' :
                '' ) .
            'WHERE ' . ( $is_personal ? 'n.status >= 1 AND ' : '') . 'n.company_id = ' . $this->requester_auth['company'] .
            $sql_search
        );
        if ( !is_int( $out ) ) {
            $num = $out[0]['num'];
            if( $num > 0 ) {
                // NOTE: 當一個 kiosk 所有LCM面都變成 mount = 0 時，整個 kiosk機器就會消失再這個列表之中。這個樣子不對！
                //       必須要 status != D 並且 mount = 0 的時候，依舊要保持出現在列表之中。繼續被統計
                $sql = NULL;
                if ( count( $_id_arr ) === 0 ) {    // NOTE: member view 是否有東西可以顯示，決定你要不要進一步去做統計資料出來
                    $sql = 'SELECT t1.uuid, 
                                   t1.name,
                                   t1.descp, 
                                   t1.status,
                                   IFNULL( t2.num, 0 ) AS mem_count, ' . 
                                  ( $withGio ? ' 0 AS mcb_count, 0 AS good_num, 0 AS issue_num, 0 AS offline_num ' : ' 0 AS mcb_count ' ) .
                           'FROM (
                                SELECT n.uuid, 
                                       n.name,
                                       n.descp, 
                                       n.status
                                FROM displayer_network AS n ' .
                                ( $is_personal ?
                                    'INNER JOIN displayer_network_mem AS nm ON nm.net_uuid = n.uuid AND nm.mem_id = ' . $this->requester_auth['id'] . ' ' :
                                    '' ) .
                               'WHERE ' . ( $is_personal ? 'n.status >= 1 AND ' : '' ) . 'n.company_id = ' .
                               $this->requester_auth['company'] .
                               $sql_search .
                           ') AS t1
                            LEFT JOIN (
                                SELECT dnm.net_uuid, 
                                       COUNT(*) AS num
                                FROM displayer_network_mem AS dnm
                                INNER JOIN displayer_network AS dn ON dn.uuid = dnm.net_uuid
                                INNER JOIN member AS m ON m.id = dnm.mem_id AND m.status != 0
                                WHERE ' . ( $is_personal ? 'dn.status >= 1 AND ' : '' ) .
                                     'dn.company_id = ' . $this->requester_auth['company'] .
                                     ' AND m.company_id = ' . $this->requester_auth['company'] .
                               ' GROUP BY dnm.net_uuid
                            ) AS t2 ON t2.net_uuid = t1.uuid 
                            ORDER BY ' . $sql_order .
                           ' LIMIT ' . $page . ', ' . $per;
                } else {
                    $sql = 'SELECT t1.uuid, 
                                   t1.name, 
                                   t1.descp, 
                                   t1.status,
                                   IFNULL( t2.num, 0 ) AS mem_count, ' .
                                  ( $withGio ? 'IFNULL( ( t3.nan_num + t3.good_num + t3.issue_num + t3.offline_num ), 0 ) AS mcb_count,
                                                IFNULL( t3.good_num, 0 ) AS good_num,
                                                IFNULL( t3.issue_num, 0 ) AS issue_num,
                                                IFNULL( t3.offline_num, 0 ) AS offline_num ' : 'IFNULL( t3.num, 0 ) AS mcb_count ' ) .
                           'FROM (
                                SELECT n.uuid, 
                                       n.name, 
                                       n.descp, 
                                       n.status
                                FROM displayer_network AS n ' .
                                ( $is_personal ?
                                    'INNER JOIN displayer_network_mem AS nm ON nm.net_uuid = n.uuid AND nm.mem_id = ' . $this->requester_auth['id'] . ' ' :
                                    '' ) .
                                'WHERE ' . ( $is_personal ? 'n.status >= 1 AND ' : '' ) . 'n.company_id = ' .
                                $this->requester_auth['company'] .
                                $sql_search .
                           ') AS t1
                            LEFT JOIN (
                                SELECT dnm.net_uuid, 
                                       COUNT(*) AS num 
                                FROM displayer_network_mem AS dnm
                                INNER JOIN displayer_network AS dn ON dn.uuid = dnm.net_uuid
                                INNER JOIN member AS m ON m.id = dnm.mem_id AND m.status != 0
                                WHERE ' . ( $is_personal ? 'dn.status >= 1 AND ' : '' ) .
                                    'dn.company_id = ' .
                                    $this->requester_auth['company'] .
                                    ' AND m.company_id = ' .
                                    $this->requester_auth['company'] .
                               ' GROUP BY dnm.net_uuid
                            ) AS t2 ON t2.net_uuid = t1.uuid
                            LEFT JOIN (
                                 SELECT t.net_uuid, ' .
                                       ( $withGio ? 'SUM( IF( t.condition_flg = 0, 1, 0 ) ) AS nan_num,
                                                     SUM( IF( t.condition_flg = 1, 1, 0 ) ) AS good_num,
                                                     SUM( IF( t.condition_flg = 2, 1, 0 ) ) AS issue_num,
                                                     SUM( IF( t.condition_flg = 3, 1, 0 ) ) AS offline_num ' : 'COUNT(*) AS num ' ) .
                                'FROM (
                                    SELECT dnm.net_uuid, ' .
                                          SyntaxKiosk::sqlBondingMainSn( 'a', 'b', 'sn' ) .
                                          ( $withGio ? ', ' . SyntaxKiosk::sqlBondingConditionFlg() . ' ' : ' ' ) .
                                   'FROM displayer_network_mcb AS dnm
                                    INNER JOIN displayer_network AS dn ON dn.uuid = dnm.net_uuid
                                    INNER JOIN displayer AS a ON a.id = dnm.mcb_id AND a.status != \'D\'
                                    INNER JOIN displayer_realtime_sync AS b ON b.id = a.id ' .
                                   ( $withGio ? 'LEFT JOIN displayer_model AS dm ON dm.model = a.model ' : '' ) .
                                   'WHERE dn.company_id = ' . $this->requester_auth['company'] .
                                        ' AND a.id IN (' . implode( ',', $_id_arr ) . ') ' .
                                        ( $is_personal ? ' AND dn.status >= 1 ' : '' ) .
                                   'GROUP BY dnm.net_uuid, sn
                                ) AS t
                                GROUP BY t.net_uuid
                            ) AS t3 ON t3.net_uuid = t1.uuid ' .
                           'ORDER BY ' . $sql_order .
                           ' LIMIT ' . $page . ', ' . $per;
                }
                $out = parent::selectTransact( $sql );
                if ( !is_int( $out ) ) {
                    foreach ( $out as &$row ) {
                        $row['uuid'] = Uuid::toString( $row['uuid'] );
                    }
                    unset( $row );
                    $out = parent::datatableProp( $num, $out );
                }
            } else {    // empty table
                $out = parent::datatableProp( $num );
            }
        }
        return $out;
    }

    /**
     * Add a new network, and the network name is unique under a company id.
     *
     * @param string $name
     * @param string $descp
     * @return array|int
     */
    public function addNetwork ( string $name, string $descp = '' )
    {
        $name  = filter_var( trim( $name, FILTER_SANITIZE_STRING ) );
        $descp = filter_var( trim( $descp, FILTER_SANITIZE_STRING ) );
        if ( RegexConst::safeStrlen( $name ) <= 0 || RegexConst::safeStrlen( $name ) > 100 ) {
            return self::PROC_INVALID;
        } else if ( RegexConst::safeStrlen( $descp ) > 1024 ) {
            return self::PROC_INVALID;
        }
        $_uuid = Uuid::v4();
        $_bin_uuid = Uuid::toBinary( $_uuid );
        $out = parent::writeTransact( 
            'INSERT INTO displayer_network ( uuid, company_id, name, descp )
             VALUES( ?, ' . $this->requester_auth['company'] . ', ?, ? )', 
            [ $_bin_uuid, $name, $descp ] );
        if ( $out === self::PROC_OK ) {
            // output the new one's uuid to Vue.js side.
            return array( 'uuid' => $_uuid );
        }
        return $out;
    }

    /**
     * Get all information of the network.
     *
     * @param string $uuid
     * @return array|int
     */
    public function getNetwork ( string $uuid )
    {
        if ( !Uuid::is_valid( $uuid ) ) {
            return self::PROC_INVALID;
        }
        $uuid_bin = Uuid::toBinary( $uuid );
        $sql = 'SELECT company_id, name, status, descp, 
                       UNIX_TIMESTAMP( modify_on ) AS modify_on, 
                       UNIX_TIMESTAMP( create_on ) AS create_on
                FROM displayer_network 
                WHERE uuid = ? AND company_id = ' . $this->requester_auth['company'];
        $out = parent::selectTransact( $sql, [ $uuid_bin ] );
        if ( !is_int( $out ) ) {
            $out = $out[0];
        }
        return $out;
    }

    /**
     * Edit information of display network group.
     *
     * @param string $uuid
     * @param int $status ignore the parameter when it is -1
     * @param string|null $name ignore the parameter when it is NULL
     * @param string|null $descp ignore the parameter when it is NULL
     * @return int
     */
    public function editNetwork ( string $uuid, int $status = -1, string $name = NULL, string $descp = NULL ): int
    {
        if ( $status === -1 && is_null( $name ) && is_null( $descp ) ) {
            return self::PROC_INVALID;
        }

        $null_name = is_null( $name );
        $null_descp = is_null( $descp );
        if ( !$null_name ) {
            $name  = trim( filter_var( $name, FILTER_SANITIZE_STRING ) );
        }
        if ( !$null_descp ) {
            $descp = trim( filter_var( $descp, FILTER_SANITIZE_STRING ) );
        }

        if ( !Uuid::is_valid( $uuid ) ) {
            return self::PROC_INVALID;
        } else if ( $status < -1 || $status > 1 ) {
            return self::PROC_INVALID;
        } else if ( !$null_name && ( RegexConst::safeStrlen( $name ) <= 0 || RegexConst::safeStrlen( $name ) > 100 ) ) {
            return self::PROC_INVALID;
        } else if ( !$null_descp && RegexConst::safeStrlen( $descp ) > 1024 ) {
            return self::PROC_INVALID;
        }

        $sql_val = array();
        $sql_col = '';
        if ( $status >= 0 ) {
            $sql_col .= 'status = ' . $status;
        }
        if ( !$null_name ) {
            $sql_col .= ( strlen( $sql_col ) > 0 ? ', ' : '' ) . 'name = ?';
            $sql_val[] = $name;
        }
        if ( !$null_descp ) {
            $sql_col .= ( strlen( $sql_col ) > 0 ? ', ' : '' ) . 'descp = ?';
            $sql_val[] = $descp;
        }
        $uuid_bin = Uuid::toBinary( $uuid );
        $sql_val[] = $uuid_bin;

        $sql = 'UPDATE displayer_network SET ' . $sql_col .
            ' WHERE uuid = ? AND company_id =' . $this->requester_auth['company'];
        return parent::writeTransact( $sql, $sql_val );
    }

    /**
     *
     * @param string $uuid
     * @return int
     */
    public function delNetwork ( string $uuid ): int
    {
        if ( !Uuid::is_valid( $uuid ) ) {
            return self::PROC_INVALID;
        }
        $uuid_bin = Uuid::toBinary( $uuid );
        $sql = 'DELETE FROM displayer_network WHERE uuid = ? AND company_id = ' . $this->requester_auth['company'];
        return parent::writeTransact( $sql, [ $uuid_bin ] );
    }

    /**
     * If you want to add/remove multiple member or multiple display network,
     * you better add/remove them all one by one via the method
     *
     * @param int $mem_id
     * @param string $net_uuid
     * @param boolean $is_del If true, it means to remove it from table
     * @return int
     */
    public function user2DsNetwork( int $mem_id, string $net_uuid, bool $is_del = false ): int
    {
        if ( $mem_id <= 0 ) {
            return self::PROC_INVALID;
        } else if ( !Uuid::is_valid( $net_uuid ) ) {
            return self::PROC_INVALID;
        }
        $uuid_bin = Uuid::toBinary( $net_uuid );
        if ( $is_del ) {
            // IMPORTANT: you have to ensure the member is from the company that the network comes from.
            $sql = 'DELETE FROM displayer_network_mem
                    WHERE net_uuid = ? AND mem_id = ' . $mem_id .
                   ' AND EXISTS( SELECT uuid FROM displayer_network WHERE uuid = ? AND company_id = ' . $this->requester_auth['company'] .
                   ') AND EXISTS( SELECT id FROM member WHERE id = ' . $mem_id .
                   ' AND company_id = ' . $this->requester_auth['company'] . ' )';
            return parent::writeTransact( $sql, [ $uuid_bin, $uuid_bin ] );
        } else {
            // NOTE: All user can join network except super admin(type = 1)
            $sql = 'INSERT INTO displayer_network_mem ( net_uuid, mem_id )
                    SELECT uuid, ' . $mem_id . ' FROM displayer_network
                    WHERE uuid = ? AND company_id = ' . $this->requester_auth['company'] .
                   ' AND EXISTS( SELECT id FROM member WHERE type > 1 AND id = ' . $mem_id .
                               ' AND company_id = ' . $this->requester_auth['company'] . ' )';
            return parent::writeTransact( $sql, [ $uuid_bin ] );
        }
    }

    /**
     * To view a member belongs to which display network(s).
     * NOTE: A member can be in one or more display network in the same time.
     *
     * IMPORTANT: if you are an admin(super admin) you will get all networks,
     *            but the out column, removable, may not be all in true(1)
     *
     * @param int $mem_id
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string|null $search
     * @return array|int
     */
    public function listUserNetworks (
        int $mem_id,
        int $page,
        int $per,
        array $order = [],
        string $search = NULL )
    {
        if ( $mem_id <= 0 ) {
            return self::PROC_INVALID;
        } else if ( $page < 0 || $per < 0 || $per > 200 ) {
            return self::PROC_INVALID;
        }
        
        // check orders
        $sql_order = parent::datatablesOrder2Sql(
            $order, [
                'n.name',
                'n.descp'
            ]
        );
        // if nothing for order, set default.
        if ( $sql_order === false ) {
            return self::PROC_INVALID;
        } else if ( empty( $sql_order ) ) {
            $sql_order = 'n.name ASC';
        }
        
        // for search string.
        $sql_search = parent::fulltextSearchSQL(
            $search,
            [ 'n.name', 'n.descp' ] );
        if ( !empty( $sql_search ) ) {
            $sql_search = ' AND ' . $sql_search;
        }
        
        // check member type for output format choosing.
        $out = parent::selectTransact( 
            'SELECT type FROM member WHERE id = ' . $mem_id . ' AND company_id = ' . $this->requester_auth['company'] );
        if ( is_int( $out ) ) {
            return $out;
        } else if ( count( $out ) === 0 ) {
            // IMPORTANT: 可能無此人或是此人不再這間公司，避免有不正當的 member 切換後，拿到其他公司的 network 資料
            return self::PROC_FAIL; 
        }
        $member_type = $out[0]['type'];
        
        // output all network to admin; otherwise, just output what the member can see.
        $sql_body = 'FROM displayer_network AS n
                     LEFT JOIN displayer_network_mem AS nm ON nm.net_uuid = n.uuid AND nm.mem_id = ' . $mem_id .
                    ' WHERE n.status >= 1 AND n.company_id = ' . $this->requester_auth['company'] .
                    $sql_search .
                    ( $member_type <= self::DETECT_THRESHOLD ? '' : ' AND nm.net_uuid IS NOT NULL ' );
                    
        $out = parent::selectTransact( 'SELECT COUNT(*) AS num ' . $sql_body );
        if ( !is_int( $out ) ) {
            $num = $out[0]['num'];
            if ( $num > 0 ) {
                $out = parent::selectTransact(
                    'SELECT n.uuid, n.name, n.descp, IF( nm.net_uuid IS NULL, 0, 1 ) AS removable ' .
                    $sql_body .
                    ' ORDER BY ' . $sql_order .
                    ' LIMIT ' . $page . ', ' . $per );
                if ( !is_int( $out ) ) {
                    foreach ( $out as &$row ) {
                        $row['uuid'] = Uuid::toString( $row['uuid'] );
                    }
                    unset( $row );
                    $out = parent::datatableProp( $num, $out );
                }
            } else {    // empty table
                $out = parent::datatableProp( $num );
            }
        }
        return $out;
    }

    /**
     * To view a display device belongs to which display network(s).
     *
     * NOTE: A display device can be in one or more network in the same time.
     *
     * @param int $mcb_id
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string|null $search
     * @return array|int
     */
    public function listDisplayNetworks( int $mcb_id, int $page, int $per, array $order = [], string $search = NULL )
    {
        if ( $mcb_id <= 0 ) {
            return self::PROC_INVALID;
        } else if ( $page < 0 || $per < 0 || $per > 200 ) {
            return self::PROC_INVALID;
        }
        
        // check orders
        $sql_order = parent::datatablesOrder2Sql(
            $order,
            [ 'm.name', 'm.descp' ] );
        // if nothing for order, set default.
        if ( $sql_order === false ) {
            return self::PROC_INVALID;
        } else if ( empty( $sql_order ) ) {
            $sql_order = 'm.name ASC';
        }
        
        // for search string.
        $sql_search = parent::fulltextSearchSQL(
            $search,
            [ 'm.name', 'm.descp' ] );
        if ( !empty( $sql_search ) ) {
            $sql_search = ' AND ' . $sql_search;
        }
        
        $sql_tab = 'FROM displayer_network_mcb AS n
                    INNER JOIN displayer_network AS m ON m.uuid = n.net_uuid
                    INNER JOIN displayer AS a ON a.id = n.mcb_id ';
        $sql_where = 'WHERE n.mcb_id = ' . $mcb_id . ' AND a.belong_to = ' . $this->requester_auth['company'] .
                     ' AND m.status >= 1 AND m.company_id = ' . $this->requester_auth['company'] . $sql_search;
        
        $out = parent::selectTransact( 'SELECT COUNT(*) AS num ' . $sql_tab . $sql_where );
        if ( !is_int( $out ) ) {
            $num = $out[0]['num'];
            if( $num > 0 ) {
                $out = parent::selectTransact( 
                    'SELECT m.uuid, m.name, m.descp ' . $sql_tab . $sql_where . ' ORDER BY ' . $sql_order . ' LIMIT ' . $page . ', ' . $per );
                if ( !is_int( $out ) ) {
                    foreach ( $out as &$row ) {
                        $row['uuid'] = Uuid::toString( $row['uuid'] );
                    }
                    unset( $row );
                    $out = parent::datatableProp( $num, $out );
                }
            } else {    // empty table
                $out = parent::datatableProp( $num );
            }
        }
        return $out;
    }

    /**
     * To show how many member belongs to the specified network.
     *
     * @param string $uuid
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string|null $search
     * @return array|int
     */
    public function listNetworkUsers ( string $uuid, int $page, int $per, array $order = [], string $search = NULL )
    {
        if ( $page < 0 || $per < 0 || $per > 200 ) {
            return self::PROC_INVALID;
        } else if ( !Uuid::is_valid( $uuid ) ) {
            return self::PROC_INVALID;
        }
        
        // check orders
        $sql_order = parent::datatablesOrder2Sql(
            $order, [
                'm.email',
                'm.nickname',
                'm.status',
                'm.type'
            ]
        );
        // if nothing for order, set default.
        if ( $sql_order === false ) {
            return self::PROC_INVALID;
        } else if ( empty( $sql_order ) ) {
            $sql_order = ' m.nickname ASC ';
        }
        
        // for search string.
        $sql_search = parent::fulltextSearchSQL(
            $search, [
                'm.email',
                'm.nickname',
                'm.msg'
            ]
        );
        if ( !empty( $sql_search ) ) {
            $sql_search = ' AND ' . $sql_search;
        }
        
        $uuid_bin = Uuid::toBinary( $uuid );
        $sql_val = array( $uuid_bin );
        $sql = 'FROM displayer_network_mem AS n
                INNER JOIN member AS m ON m.id = n.mem_id
                INNER JOIN displayer_network AS dn ON dn.uuid = n.net_uuid
                WHERE m.status != 0 AND dn.company_id = ' . $this->requester_auth['company'] .
               ' AND n.net_uuid = ? AND m.company_id = ' . $this->requester_auth['company'] .
               $sql_search;
        
        $out = parent::selectTransact( 'SELECT COUNT(*) AS num ' . $sql, $sql_val );
        if ( !is_int( $out ) ) {
            $num = $out[0]['num'];
            if( $num > 0 ) {
                $out = parent::selectTransact( 
                    'SELECT m.id, 
                                m.email,
                                m.nickname AS name, 
                                m.status, m.type ' .
                    $sql .
                    ' ORDER BY ' . $sql_order .
                    ' LIMIT ' . $page . ', ' . $per,
                    $sql_val );
                if ( !is_int( $out ) ) {
                    $out = parent::datatableProp( $num, $out );
                }
            } else {    // empty table
                $out = parent::datatableProp( $num );
            }
        }
        return $out;
    }

    /**
     * 出一個這個使用者才可以看到的範圍的列表，並且標示出來，列表中有哪些機器是歸屬在這個特別指定的 network uuid 之下
     *
     * @param string $uuid
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string|null $search
     * @return array|int
     */
    public function listNetworkDisplays( string $uuid, int $page, int $per, array $order = [], string $search = NULL )
    {
        if ( $page < 0 || $per < 0 || $per > 200 ) {
            return self::PROC_INVALID;
        } else if ( !Uuid::is_valid( $uuid ) ) {
            return self::PROC_INVALID;
        }
        // for search string.
        // NOTE: in company text searching, it is different to admin-side. because client side has the company them belongs to.
        // but the admin-side has only the company name clone from the client database table, not own it directly
        $searchCondition = parent::fulltextSearchSQL(
            $search,
            [ 'a.sn', 'a.model', 'a.descp' ],
            [ 'b.bonding' ],
            [ 'c.name', 'c.phone_num', 'c.address' ],
            [ 't.tag' ],
            [ 'dk.main_descp' ]
        );
        if ( !empty( $searchCondition ) ) {
            $searchCondition = ' AND ' . $searchCondition;
        }
        // check orders
        // Table output columns are: SN, status, condition, company(name), description text, update_on(last)
        $orderbySQL = parent::datatablesOrder2Sql(
            $order, [
                'sn',
                'situation',
                'company',
                'descp',
                'tag',
                'is_selected'
            ]
        );
        // if nothing for order, set default.
        if ( $orderbySQL === false ) {
            return self::PROC_INVALID;
        } else if ( empty( $orderbySQL ) ) {
            $orderbySQL = ' is_selected DESC, sn ASC ';
        } else {
            // @2024-08-15: Cleo 發現我這邊必續強制對一個unique的欄位在排序，免得不同的頁面一直出像同樣的帳號人物
            if ( strpos( $orderbySQL, 'is_selected' ) !== false ) {
                $orderbySQL .= ', sn ASC ';
            }
        }

        // check life cycle filter parameters
        // NOTE: 照理來說，這個欄位的數值在kiosk機器之下，應該都是統一的，不會有差異。所以，只需要過濾其中一者即可。
        //       但是，如果有差異，則是有bug的產生，需要立即調查釋出錯在哪個環節的資料修改之時，無法作到所有 lcm 的狀態統一
        // check member view on stage and network effect
        // NOTE: 如果要找尋特定 alarm 的條件，則 mount = 0 就不可以被帶入，而必須被過濾掉。
        $_id_arr = $this->mem2mcbView();
        if ( is_int( $_id_arr ) ) {
            return $_id_arr;
        } else if ( count( $_id_arr ) === 0 ) {
            return parent::datatableProp( 0 );  // 都已經不在 view 之中了，理所當然 table 是空的
        }

        // table level 1
        $sql_tabs = 'FROM displayer AS a
                     INNER JOIN displayer_realtime_sync AS b ON b.id = a.id
                     INNER JOIN company AS c ON c.id = a.belong_to
                     LEFT JOIN displayer_kiosk_info AS dk ON dk.main_sn = b.bonding AND dk.company_id = '.
                    $this->requester_auth['company'].
                    ' LEFT JOIN displayer_tag AS dt ON dt.displayer_id = a.id
                     LEFT JOIN tags AS t ON t.uuid = dt.tag_uuid ';
        $sql_where_base = 'WHERE a.id IN(' . implode( ',', $_id_arr ) . ') ';
        $table = self::selectTransact(
            'SELECT COUNT(*) AS num
             FROM (
                 SELECT '.SyntaxKiosk::sqlBondingMainSn().
                ' FROM displayer AS a
                 INNER JOIN displayer_realtime_sync AS b ON b.id = a.id
                 WHERE a.status != \'D\' 
                 GROUP BY main_sn
             ) AS tab_1
             INNER JOIN (
                 SELECT '.SyntaxKiosk::sqlBondingMainSn('a2', 'b2').
                ' FROM (
                     SELECT DISTINCT( a.id ) AS id ' .
                     $sql_tabs .
                     $sql_where_base .
                     $searchCondition .
                ') AS t1
                 INNER JOIN displayer AS a2 ON a2.id = t1.id
                 INNER JOIN displayer_realtime_sync AS b2 ON b2.id = a2.id
                 GROUP BY main_sn
             ) AS tab_2 ON tab_2.main_sn = tab_1.main_sn'
        );
        if ( !is_int( $table ) ) {
            $num = $table[0]['num'];
            if ( $num > 0 ) {
                $network_uuid_bin = Uuid::toBinary( $uuid );
                $table = parent::selectTransact(
                    'SELECT 
                            tab_1.main_sn AS sn, 
                            tab_1.main_id, 
                            ( 
                                 SELECT JSON_OBJECT( 
                                           \'id\', aa.situation, 
                                           \'name\', bb.name ) 
                                 FROM displayer AS aa
                                 INNER JOIN displayer_situation AS bb ON bb.id = aa.situation
                                 WHERE aa.id = tab_1.main_id 
                            ) AS situation,
                            ( 
                                SELECT name 
                                FROM company WHERE id = ( SELECT belong_to FROM displayer WHERE id = tab_1.main_id ) 
                            ) AS company,
                            IFNULL( 
                                ( SELECT main_descp 
                                  FROM displayer_kiosk_info 
                                  WHERE main_sn = tab_1.main_sn AND company_id = ' . $this->requester_auth['company'] . ' ),
                                ( SELECT descp 
                                  FROM displayer 
                                  WHERE id = tab_1.main_id ) 
                            ) AS descp,
                            IFNULL( IF( tab_1.is_dual = 1, (
                                    SELECT GROUP_CONCAT( DISTINCT( t2.tag ) )
                                    FROM displayer_tag AS dt2
                                    INNER JOIN tags AS t2 ON t2.uuid = dt2.tag_uuid
                                    WHERE dt2.displayer_id IN( SELECT id FROM displayer_realtime_sync WHERE bonding = tab_1.main_sn )
                                ), (
                                    SELECT GROUP_CONCAT( DISTINCT( t2.tag ) )
                                    FROM displayer_tag AS dt2
                                    INNER JOIN tags AS t2 ON t2.uuid = dt2.tag_uuid
                                    WHERE dt2.displayer_id = tab_1.main_id
                                )
                            ), \'\' ) AS tag,
                            ( 
                                SELECT EXISTS( 
                                    SELECT net_uuid FROM displayer_network_mcb WHERE mcb_id = tab_1.main_id AND net_uuid = ? 
                                )
                            ) AS is_selected
                     FROM ( 
                         SELECT '.SyntaxKiosk::sqlBondingMainSn().', '  // as main_sn
                                 .SyntaxKiosk::sqlBondingMainId().', '  // as main_id
                                 .SyntaxKiosk::sqlIsDualSide().         // as is_dual
                        ' FROM displayer AS a
                         INNER JOIN displayer_realtime_sync AS b ON b.id = a.id
                         LEFT JOIN displayer_model AS dm ON dm.model = a.model
                         WHERE a.status != \'D\' 
                         GROUP BY main_sn 
                     ) AS tab_1 
                     INNER JOIN (                      
                         SELECT '.SyntaxKiosk::sqlBondingMainSn('a2', 'b2').
                        ' FROM (
                             SELECT DISTINCT( a.id ) AS id ' .
                             $sql_tabs .
                             $sql_where_base .
                             $searchCondition .
                        ') AS t1
                         INNER JOIN displayer AS a2 ON a2.id = t1.id
                         INNER JOIN displayer_realtime_sync AS b2 ON b2.id = a2.id
                         GROUP BY main_sn                           
                     ) AS tab_2 ON tab_2.main_sn = tab_1.main_sn 
                     ORDER BY ' . $orderbySQL . ' LIMIT ' . $page . ', ' . $per,
                    [$network_uuid_bin], true
                );
                if ( !is_int( $table ) ) {
                    foreach ( $table as &$row ) {
                        $row['situation'] = empty( $row['situation'] ) ? NULL : json_decode( $row['situation'], true );
                    }
                    unset( $row );
                    $table = parent::datatableProp( $num, $table );
                }
            } else {    // empty table
                $table = parent::datatableProp( $num );
            }
        }
        return $table;
    }

    /**
     * IMPORTANT: The admin-side mapping way is different to client-side.
     *            You don't have to detect whether the display account is under the same company group.
     *
     * @param int $mcb_id
     * @param string $net_uuid
     * @param bool $is_del
     * @return int
     */
    public function mcb2DsNetwork ( int $mcb_id, string $net_uuid, bool $is_del = false ): int
    {
        if ( $mcb_id <= 0 ) {
            return self::PROC_INVALID;
        } else if ( !Uuid::is_valid( $net_uuid ) ) {
            return self::PROC_INVALID;
        }
        $uuid_bin = Uuid::toBinary( $net_uuid );
        
        // check uuid is correct and under right company
        $out = parent::selectTransact(
            'SELECT uuid FROM displayer_network WHERE uuid = ? AND company_id = ' . $this->requester_auth['company'],
            [ $uuid_bin ] );
        if ( is_int( $out ) ) {
            return $out;
        } else if ( empty( $out ) ) {
            return self::PROC_INVALID;
        }
        
        // find out others if it is one on KIOSK machine.
        $kiosk_group = parent::getKioskGroup( $this->requester_auth['company'], $mcb_id, false, true );
        if ( is_int( $kiosk_group ) ) {
            return self::PROC_MCB_KIOSK_GROUP_ERROR;
        }
        
        if ( $is_del ) {
            $sql_val = [ $uuid_bin, $mcb_id ];
            foreach ( $kiosk_group['group'] as $v ) {   // if it is empty, the loop won't work too.
                $sql_val[] = $v['id'];
            }
            return parent::writeTransact(
                'DELETE FROM displayer_network_mcb WHERE net_uuid = ? AND mcb_id IN (' .
                parent::pdoPlaceHolders( '?', ( sizeof( $sql_val ) - 1 ) ) . ')',
                $sql_val );
        } else {
            $num = 1;
            $sql_val = [ $uuid_bin, $mcb_id ];
            foreach ( $kiosk_group['group'] as $v ) {   // if it is empty, the loop won't work too.
                array_push( $sql_val, $uuid_bin, $v['id'] );
                $num += 1;
            }
            return parent::writeTransact(
                'INSERT INTO displayer_network_mcb ( net_uuid, mcb_id ) VALUES' .
                parent::pdoPlaceHolders( '(?, ?)', $num ),
                $sql_val );
        }
    }

    /**
     * Detect display account is existed in network of member.
     *
     * NOTE: if the user level is admin or super admin, you only have to do is check the company is correct.
     *
     * @param array $mcb_arr
     * @param bool $isSN
     * @return int
     */
    public function isMemberHasDisplays ( array $mcb_arr, bool $isSN = false ): int
    {
        if ( $isSN ) {
            if ( !DsParamProc::isSeriesNumberArray( $mcb_arr ) ) {
                return self::PROC_INVALID;
            }
        } else {
            if ( !DsParamProc::isUnsignedInt( $mcb_arr, true ) ) {  // al things in array are back in INT if it is a number string array
                return self::PROC_INVALID;
            }
        }
        $mcb_arr = DsParamProc::uniqueArray( $mcb_arr );
        $_id_arr = $this->mem2mcbNetworkView();
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

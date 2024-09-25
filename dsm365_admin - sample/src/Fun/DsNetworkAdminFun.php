<?php
/**
 * All displayer management processing controller functions are here.
 * 
 * @author  Nick Feng
 *
 * @since 1.0
 * 
 */
namespace Gn\Fun;

use ErrorException;
use Gn\Sql\SqlDisplayNetworkAdmin;

/**
 * network instruction functions.
 * 
 * @author Nick Feng
 */
class DsNetworkAdminFun
{
    /**
     * network data SQL process.
     * 
     * @var SqlDisplayNetworkAdmin
     */
    protected $sqlDsNetwork = NULL;

    /**
     * Constructor
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
    public function __construct( array $db_settings, array $requester_auth )
    {
        $this->sqlDsNetwork = new SqlDisplayNetworkAdmin( $db_settings, $requester_auth );
    }

    /**
     *
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string $search
     * @param bool $withGio
     * @param bool $is_personal
     * @param array $stage_arr
     * @return array|int
     */
    public function displayNetworkTab ( 
        int    $page, 
        int    $per, 
        array  $order = [], 
        string $search = '', 
        bool   $withGio = false,
        bool   $is_personal = false,
        array  $stage_arr = [] )
    {
        return $this->sqlDsNetwork->networkTable( $page, $per, $order, $search, $withGio, $is_personal, $stage_arr );
    }

    /**
     *
     * @param string $name
     * @param string $text
     * @return array|int
     */
    public function createDisplayNetwork ( string $name, string $text = '' )
    {
        return $this->sqlDsNetwork->addNetwork( $name, $text );
    }

    /**
     * view information of a network.
     *
     * @param string $uuid
     * @return array|int
     */
    public function viewDisplayNetwork ( string $uuid )
    {
        return $this->sqlDsNetwork->getNetwork( $uuid );
    }

    /**
     *
     * @param string $uuid
     * @param int $status ignore the parameter when it is -1
     * @param string|null $name ignore the parameter when it is NULL
     * @param string|null $descp ignore the parameter when it is NULL
     * @return int
     */
    public function editDisplayNetwork ( string $uuid, int $status = -1, string $name = NULL, string $descp = NULL ): int
    {
        return $this->sqlDsNetwork->editNetwork( $uuid, $status, $name, $descp );
    }

    /**
     *
     * @param string $uuid
     * @return int
     */
    public function delDisplayNetwork ( string $uuid ): int
    {
        return $this->sqlDsNetwork->delNetwork( $uuid );
    }

    /**
     *
     * @param int $mem_id
     * @param string $net_uuid
     * @param bool $is_del
     * @return int
     */
    public function linkUser2DisplayNetwork ( int $mem_id, string $net_uuid, bool $is_del = false ): int
    {
        return $this->sqlDsNetwork->user2DsNetwork( $mem_id, $net_uuid, $is_del );
    }

    /**
     *
     * @param string $uuid
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string|null $search
     * @return array|int
     */
    public function displayNetworkUserTab ( string $uuid, int $page, int $per, array $order = [], string $search = NULL )
    {
        return $this->sqlDsNetwork->listNetworkUsers( $uuid, $page, $per, $order, $search );
    }

    /**
     *
     * @param string $uuid
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string|null $search
     * @return array|int
     */
    public function mcb_DisplayerNetworkTab ( string $uuid, int $page, int $per, array $order = [], string $search = NULL )
    {
        return $this->sqlDsNetwork->listNetworkDisplays( $uuid, $page, $per, $order, $search );
    }

    /**
     *
     * @param int $mem_id
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string|null $search
     * @return array|int
     */
    public function userDisplayNetworkTab (
        int $mem_id,
        int $page,
        int $per,
        array $order = [],
        string $search = NULL )
    {
        return $this->sqlDsNetwork->listUserNetworks( $mem_id, $page, $per, $order, $search );
    }

    /**
     *
     * @param int $mcb_id
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string|null $search
     * @return array|int
     */
    public function mcbDisplayNetworkTab ( int $mcb_id, int $page, int $per, array $order = [], string $search = NULL )
    {
        return $this->sqlDsNetwork->listDisplayNetworks( $mcb_id, $page, $per, $order, $search );
    }

    /**
     *
     * @param int $mcb_id
     * @param string $net_uuid
     * @param bool $is_del
     * @return int
     */
    public function linkMcb2DisplayNetwork ( int $mcb_id, string $net_uuid, bool $is_del = false ): int
    {
        return $this->sqlDsNetwork->mcb2DsNetwork( $mcb_id, $net_uuid, $is_del );
    }

    /**
     *
     * @param array $mcb_arr
     * @param bool $isSN
     * @return int
     */
    public function isMemHasDisplayer ( array $mcb_arr, bool $isSN = false ): int
    {
        return $this->sqlDsNetwork->isMemberHasDisplays( $mcb_arr, $isSN );
    }
}

<?php
/**
 * All about dashboard information and navigation bar information
 * processing controller functions are here.
 *
 * @author Nick Feng
 * @since 1.0
 */
namespace Gn\Fun;

use ErrorException;
use Gn\Sql\SqlDisplayer;
use Gn\Obj\DsAddressObj;

/**
 * All dashboard function of interaction.
 *
 * @author Nick Feng
 *
 */
class DisplayerFun
{
    /**
     * notification database.
     *
     * @var SqlDisplayer
     */
    protected $sqlDisplayer = NULL;

    /**
     * Constructor, and look up jwt id automatically when it called.
     *
     * @param array $db_settings
     * @param array $requester_auth It is from SqlRegister::isApiJwt method output:
     *          [
     *              'id'         => $perms['user_id'],
     *              'company'    => $perms['company_id'],
     *              'plan'       => $perms['plan_id'],
     *              'type'       => $perms['type'],
     *              'permission' => array();
     *          ];
     * @throws ErrorException
     */
    public function __construct( array $db_settings, array $requester_auth )
    {
        $this->sqlDisplayer = new SqlDisplayer( $db_settings, $requester_auth );
    }

    /**
     *
     *
     * @param int $condit_code
     * @param DsAddressObj|null $address_filter
     * @param string $search
     * @param array $tag
     * @param array $alarm_code_arr
     * @param array $net_arr
     * @param array $stage_arr
     * @param bool $strictMount
     * @param bool $strictStatus
     * @return array|int
     */
    public function displayerIdList ( 
        int $condit_code = -1, 
        DsAddressObj $address_filter = NULL, 
        string $search = '', 
        array $tag = [], 
        array $alarm_code_arr = [], 
        array $net_arr = [], 
        array $stage_arr = [],
        bool $strictMount = false,
        bool $strictStatus = true )
    {
        return $this->sqlDisplayer->displayFullIds( 
            $condit_code, 
            $address_filter, 
            $search, 
            $tag, 
            $alarm_code_arr, 
            $net_arr, 
            $stage_arr, 
            $strictMount, 
            $strictStatus );
    }

    /**
     * NOTE: replace displayerList method
     *
     * @param int $mode output different kinds of list via different modes.
     * @param int $cond_flg
     * @param int $no_loc_flg
     * @param DsAddressObj $address_filter
     * @param int $pg
     * @param int $per
     * @param array $order
     * @param string $search
     * @param string $tag_txt Searching only tag string.
     * @param array $alarm_code_arr
     * @param array $net_arr
     * @param array $model_arr
     * @param array $stage_arr
     * @return array|int
     */
    public function displayerList_v2 ( 
        int $mode, 
        int $cond_flg, 
        int $no_loc_flg, 
        DsAddressObj $address_filter, 
        int $pg, 
        int $per, 
        array $order = [], 
        string $search = '', 
        string $tag_txt = '', 
        array $alarm_code_arr = [], 
        array $net_arr = [], 
        array $model_arr = [], 
        array $stage_arr = [] )
    {
        return $this->sqlDisplayer->displayMainList_v2( 
            $mode, 
            $cond_flg, 
            $no_loc_flg, 
            $address_filter,
            $pg, 
            $per, 
            $order, 
            $search, 
            $tag_txt, 
            $alarm_code_arr, 
            $net_arr, 
            $model_arr, 
            $stage_arr );
    }

    /**
     * List all model name in a table for the company.
     *
     * @param int $pageNum
     * @param int $per
     * @param array $orders
     * @param string|null $search
     * @param array|null $net_arr
     * @param array|null $stage_arr
     * @return array|int
     */
    public function getDisplayerModelNameTable_v2 (
        int $pageNum,
        int $per,
        array $orders = [],
        string $search = NULL,
        array $net_arr = [],
        array $stage_arr = []
    ) {
        return $this->sqlDisplayer->getDisplayerModelList_v2( $pageNum, $per, $orders, $search, $net_arr, $stage_arr );
    }

    /**
     * Output devices' list via GPS distance from camera view.
     *
     * NOTE: it replaces self::displayerGpsIndices() method.
     *
     * @param float $lat
     * @param float $lng
     * @param float $radius
     * @param bool $loc_promise
     * @param int $condition
     * @param DsAddressObj $address_condition_arr
     * @param string|null $search
     * @param array $alarm_code_arr
     * @param array $net_arr
     * @param array $stage_arr
     * @return array|int
     * @author Nick
     */
    public function displayerGpsIndices_v2 ( 
        float $lat, 
        float $lng, 
        float $radius, 
        bool  $loc_promise, 
        int   $condition, 
        DsAddressObj $address_condition_arr, 
        string $search = NULL, 
        array  $alarm_code_arr = [], 
        array  $net_arr = [], 
        array  $stage_arr = [] )
    {
        return $this->sqlDisplayer->getDisplayGpsList_v2( 
            $lat, 
            $lng, 
            $radius, 
            $loc_promise, 
            $condition, 
            $address_condition_arr, 
            $search,
            $alarm_code_arr,
            $net_arr,
            $stage_arr );
    }

    /**
     *
     * @return array|int success return array. otherwise, return integer error code.
     * @author Nick
     */
    public function displayerNoLocationNum_v2 ()
    {
        return $this->sqlDisplayer->getNoLocationDisplay_v2();
    }

    /**
     * IMPORTANT: 目前給 remote & software update 做使用，並不給display management 做使用。
     *            因為前兩者需要針對真正個別的 mcb account 做操作，不可以用全機序號來論！！
     *
     * Show all display account are connecting to server; Similarly, client must register their product,
     * and it makes a first connection to server, and the account is success.
     *
     * @param int $pg
     * @param int $per
     * @param array $orders E.g. [{column: 0, dir: "asc"}, {column: 0, dir: "desc"}.....]
     * @param string $search
     * @param array $models
     * @param int $cond_flg
     * @param DsAddressObj|null $address_filter
     * @param array $alarm_code_arr
     * @param array $net_arr
     * @param array $lifecycle_arr
     * @return array|int
     * @author Nick
     */
    public function getMcbRemoteTable (
        int   $pg,
        int   $per,
        array  $orders = [],
        string $search = '',
        array $models = [],
        int   $cond_flg = -1,
        DsAddressObj $address_filter = NULL,
        array  $alarm_code_arr = [],
        array  $net_arr = [],
        array  $lifecycle_arr = [] )
    {
        return $this->sqlDisplayer->mcbRemoteTable( 
            $pg, 
            $per, 
            $orders,
            $search, 
            $models, 
            $cond_flg, 
            $address_filter, 
            $alarm_code_arr, 
            $net_arr, 
            $lifecycle_arr  );
    }

    /**
     *
     * @param int $pg
     * @param int $per
     * @param array $orders
     * @param string $search
     * @param array $mcb_arr
     * @param array $net_arr
     * @param array $stage_arr
     * @return array|int
     */
    public function getDisplayerAlarmList (
        int $pg,
        int $per = 25,
        array $orders = [],
        string $search = '',
        array $mcb_arr = [],
        array $net_arr = [],
        array $stage_arr = [] )
    {
        return $this->sqlDisplayer->displayerAlarmTables( $pg, $per, $orders, $search, $mcb_arr, $net_arr, $stage_arr );
    }
}

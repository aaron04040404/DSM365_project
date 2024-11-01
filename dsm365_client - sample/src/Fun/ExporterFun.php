<?php

namespace Gn\Fun;

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

use Gn\Sql\SqlExporter;

class ExporterFun
{
    //protected $container;
    protected $sqlExporter = NULL;
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
    public function __construct( array $db_settings, array $requester_auth)
    {
        //$this->container = $container;
        $this->sqlExporter = new SqlExporter($db_settings, $requester_auth);
    }


    /**
     * 
     * @param int $mode
     * @param int $cond_flg
     * @param int $no_loc_flg
     * @param int $pg
     * @param int $per
     * @param array $net_arr
     * @param array $stage_arr
     * @param array $mcb_ids
     * @return array|int
     * 
     */
    public function ExternalDisplayFun(
        int $mode, 
        int $cond_flg, 
        int $no_loc_flg, 
        int $pg, 
        int $per,
        array $net_arr = [],
        array $stage_arr = [],
        array $mcb_ids = []
        )
    {
        return $this->sqlExporter->sqlexternalDisplay( 
        $mode, 
        $cond_flg, 
        $no_loc_flg, 
        $pg, 
        $per,
        $net_arr,
        $stage_arr,
        $mcb_ids
        );  
    }

    public function DisplayerVersionFun()
    {
        return $this->sqlExporter->sqldisplayerVersion();
    }

    /*public function DownloadDisplayerVersionFun(Request $request, Response $response, array $args, array $result)
    {
        
       $this->sqlExporter->downloadCSV($request, $response, $args, $result);
    }*/

}
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

    public function __construct( array $db_settings, array $requester_auth)
    {
        //$this->container = $container;
        $this->sqlExporter = new SqlExporter($db_settings, $requester_auth);
    }

    /**
     * 
     * @param int $mcb_id
     * @param string $start_date
     * @param string $end_date
     * @return array
     * 
     */

    public function DisplayManagementFun(int $mcb_id, string $start_date, string $end_date)
    {   
        
        return $this->sqlExporter->sqldisplayManagement($mcb_id, $start_date, $end_date);
    }

    /**
     * 
     * @param int $mem_id
     * @return array
     * 
     */
    public function InternalOverviewFun(int $mem_id)
    {
        return $this->sqlExporter->sqlinternalOverview($mem_id);
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
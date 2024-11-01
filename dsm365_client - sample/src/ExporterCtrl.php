<?php

namespace Gn;

use Gn\Ctl\ApiBasicCtl;
use Gn\Ctl\McbBasicCtl;
use Gn\Fun\ExporterFun;

use Gn\Interfaces\BaseRespCodesInterface;
use Gn\Interfaces\DisplayAlarmInterface;
use Gn\Interfaces\NotificationRespCodesInterface;

use Gn\Lib\ValidDateTime;

use DateTime;
// from Slim
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use Slim\Http\Stream;

/**
 * 
 * These Api function for download the data what is internal
 * 
 * @author 
 */
class ExporterCtrl extends ApiBasicCtl
{
    //protected $container;
    protected $exporter = NULL;

    public function __construct( Container $container )
    {
        //$this->container = $container;
        parent::__construct( $container );
        $this->exporter = new ExporterFun( $this->settings['db']['main'], $this->memData);
    }

    /**
     * function for dump ExternalDisplay CSV file
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function ExternalDisplay(Request $request, Response $response, array $args)
    {   
        $mcb_ids = $request->getQueryParam('ids');
        if($mcb_ids === NULL){
            $mcb_ids = [];
        }
        $net_arr = $this->memData['network'];
        $stage_arr = $this->memData['stage'];
        $result = $this->exporter->ExternalDisplayFun(3, -1, 1, 0, 50, $net_arr, $stage_arr, $mcb_ids);
        if(is_int($result)){
            self::resp_decoder( $result, '');
        }else{
        self::resp_decoder( static::PROC_OK, '', $result );
        $this->respCode = StatusCode::HTTP_OK;
        $filename = 'userData.csv';
        $response = self::downloadCSV($response, $result, $filename);
        //error_log(print_r(ob_get_level() + '321', true));
        return $response; 
        }

        //return $response->withJson($this->respJson, $this->respCode);
    }

    /**
     * function for download CSV file
     * 
     * @param Response $response
     * @param array $result
     * @param string $filename
     * @return Response
     */

    public static function downloadCSV(Response $response, array $result, string $filename)
    {
        $output = fopen('php://temp', 'r+');
                
            $header = array_keys($result[0]);
            fputcsv($output, $header);
    
            foreach($result as $row)
            {
                fputcsv($output, $row);
            }
    
            //流(Stream)指向開頭
            rewind($output);
    
            $stream = new Stream($output);
            //echo $file_stream;
            //error_log(print_r(ob_get_level() + '123', true));
            //error_log($stream);
    
            return $response->withHeader( 'Content-Type', 'text/csv' )
                            ->withHeader( 'Content-Description', 'File Transfer' )
                            ->withHeader( 'Content-Transfer-Encoding', 'binary' )
                            ->withHeader( 'Content-Disposition', 'attachment; filename="' . $filename . '"')
                            ->withHeader( 'Filename', $filename)
                            ->withHeader( 'Access-Control-Expose-Headers', 'Content-Length, Content-Disposition' ) // for Access-Allow-Control, you have to use it to expose
                            ->withHeader( 'Cache-Control', 'must-revalidate, post-check=0, pre-check=0' )
                            ->withHeader( 'Pragma', 'public' )
                            ->withHeader( 'Expires', '0' )
                            ->withBody($stream);
    }


}
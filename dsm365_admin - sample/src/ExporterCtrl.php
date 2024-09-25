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
     * 
     * Download the DisplayManagement_v2 data
     * Note:這個版本有限制時間區間要在一天以內
     * @param Request $request
     * @param Response $response
     * @param array $args   
     * @method GET
     * 
     * @return Response
     */
    public function DisplayManagement_v2(Request $request, Response $response, array $args)//這個DisplayManagement版本限制時間只能在一天之內
    {

        $mcb_id = $request->getQueryParam('id');
        $start_date = $request->getQueryParam('s');
        $end_date = $request->getQueryParam('e');

        // 檢查 mcb_id 是否為全數字，因為從URL拔下來的值會是string
        if( !ctype_digit($mcb_id) | ((int)$end_date - (int)$start_date >= 86400) | ((int)$end_date < (int)$start_date) ){//一天的timestamp是86400秒
            return $response->withJson($this->respJson, $this->respCode); 
        }
        $mcb_id = (int)$mcb_id;
        $start_date = date('Y-m-d H:i:s', (int)$start_date);
        $end_date = date('Y-m-d H:i:s', (int)$end_date);

        $dateTimeFormat = 'Y-m-d H:i:s';

        // 檢查 start_date, end__date 格式是否正確並且是有效日期
        if (!ValidDateTime::isvalidDateTime($start_date, $dateTimeFormat) | !ValidDateTime::isvalidDateTime($end_date, $dateTimeFormat) ) {
            return $response->withJson($this->respJson, $this->respCode);
        }

        // after all checking
        $result= $this->exporter->DisplayManagementFun($mcb_id, $start_date, $end_date);

        //var_dump($result);
        if(is_int($result)){
            self::resp_decoder( $result, '');
            return $response->withJson($this->respJson, $this->respCode);
        }elseif($result == 'No Result!!!'){
            $this->respJson['data'] = $result;
            $this->respCode = StatusCode::HTTP_OK;
            return $response->withJson($this->respJson, $this->respCode);
        }else {
            self::resp_decoder( static::PROC_OK, '', $result );
            $this->respCode = StatusCode::HTTP_OK;
            
            $sd = new DateTime($start_date);
            $ed = new DateTime($end_date);

            $filename = $result[0]['Kiosk/Display_SN'] . '_' . $sd->format('YmdHis') . '-' . $ed->format('YmdHis') . '.csv';

            $response = self::downloadCSV($response, $result, $filename);
            return $response; 
        }
    }
    /**
     * 
     * Download the DisplayManagement data
     *  
     * @param Request $request
     * @param Response $response
     * @param array $args   
     * @method GET
     * 
     * @return Response
     */
    public function DisplayManagement(Request $request, Response $response, array $args)
    {

        $mcb_id = $request->getQueryParam('id');
        $start_date = $request->getQueryParam('s');
        $end_date = $request->getQueryParam('e');

        // 檢查 mcb_id 是否為全數字，因為從URL拔下來的值會是string
        if( !ctype_digit($mcb_id) | ((int)$end_date < (int)$start_date) ){
            return $response->withJson($this->respJson, $this->respCode); 
        }
        $mcb_id = (int)$mcb_id;
        $start_date = date('Y-m-d H:i:s', (int)$start_date);
        $end_date = date('Y-m-d H:i:s', (int)$end_date);

        $dateTimeFormat = 'Y-m-d H:i:s';

        // 檢查 start_date, end__date 格式是否正確並且是有效日期
        if (!ValidDateTime::isvalidDateTime($start_date, $dateTimeFormat) | !ValidDateTime::isvalidDateTime($end_date, $dateTimeFormat) ) {
            return $response->withJson($this->respJson, $this->respCode);
        }

        // after all checking
        $result= $this->exporter->DisplayManagementFun($mcb_id, $start_date, $end_date);

        
        if(is_int($result)){
            self::resp_decoder( $result, '');
            return $response->withJson($this->respJson, $this->respCode);
        }elseif($result == 'No Result!!!'){
            $this->respJson['data'] = $result;
            $this->respCode = StatusCode::HTTP_OK;
        }else {
            self::resp_decoder( static::PROC_OK, '', $result );
            $this->respCode = StatusCode::HTTP_OK;
            
            $sd = new DateTime($start_date);
            $ed = new DateTime($end_date);

            $filename = $result[0]['Kiosk/Display_SN'] . '_' . $sd->format('YmdHis') . '-' . $ed->format('YmdHis') . '.csv';

            $response = self::downloadCSV($response, $result, $filename);
            return $response; 
        }
    }

    /**
     * 
     * Download the Interlnal Overview data
     *  
     * @param Request $request
     * @param Response $response
     * @param array $args   
     * @method GET
     * 
     * @return Response
     */
    public function InternalOverview(Request $request, Response $response, array $args)
    {
        $mem_id = $request->getQueryParam('id');
        if( !ctype_digit($mem_id) ){
            return $response->withJson($this->respJson, $this->respCode); 
        }
        $mem_id = (int)$mem_id;

        $result = $this->exporter->InternalOverviewFun($mem_id);

        //var_dump($result);
        if(is_int($result)){
            self::resp_decoder( $result, '');
            return $response->withJson($this->respJson, $this->respCode);
        }elseif($result == 'No Result!!!'){
            $this->respJson['data'] = $result;
            $this->respCode = StatusCode::HTTP_OK;
            return $response->withJson($this->respJson, $this->respCode);
        }else {
            self::resp_decoder( static::PROC_OK, '', $result );
            $this->respCode = StatusCode::HTTP_OK;
            date_default_timezone_set('Asia/Taipei');
            $filename = 'OVERVIEW_' . date('Y-m-d H:i:s') . '.csv';
            
            $response = self::downloadCSV($response, $result, $filename);
            //print_r($this->memData);
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
    
            //流指向開頭
            rewind($output);
    
            $stream = new Stream($output);
            //echo $file_stream;
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
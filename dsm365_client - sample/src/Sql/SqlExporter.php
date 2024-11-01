<?php
namespace Gn\Sql;

use RuntimeException;
use Exception;
use PDOException;
use PDO;

//use Lib
use Gn\Lib\Uuid;
use Gn\Sql\Syntaxes\SyntaxAlarm;
use Gn\Lib\DsParamProc;

//use Slim
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use Slim\Http\Stream;
//use Slim\Psr7\Stream;


/**
 * 
 * All Sql SELECT function for export
 * 
 * Note: 
 * Sql SELECT no error return the result of search
 * Sql SELECT no error but no result return "No Result!!!"
 * Sql SELECT error then result is empty
 * 
 */
class SqlExporter extends SqlMemViewer//請記得檔名跟class名要一模一樣然後第一個字要大寫
{ 
    protected $container;
    protected $sql_vals = array();
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

        parent::__construct( $db_settings, $requester_auth);
        //$this->sql_vals = array();
    }

    /**
     * get external display
     * 
     * Note:
     * $mode, $cond_flg, $no_loc_flg, $pg, $per都先寫死預設值，之後有特別需求再修改
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

    public function sqlexternalDisplay(
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

        $mode = 3;
        $type = $this->requester_auth['type'];
        $company_id = $this->requester_auth['company'];
        if($company_id <= 0){
            return self::PROC_INVALID;
        }
        $user_id = $this->requester_auth['id'];
        if($user_id < 0){
            return self::PROC_INVALID;
        }
        $search = '';
        $alarm_code_arr = [];
        $search_sql = parent::fulltextSearchSQL(
            $search,
            [ 'a.sn', 'a.model', 'a.descp' ],
            [ 'ad.zip_code', 'ad.state', 'ad.city', 'ad.address_1', 'ad.address_2' ],
            [ 'b.bonding' ],
            [ 'c.name', 'c.alpha_2', 'c.alpha_3' ],
            [ 't.tag' ],
            [ 'dk.main_descp' ]
        );
        //for alarm filter
        $alarm_code_arr = DsParamProc::uniqueArray($alarm_code_arr);
        $sql_alarm = SyntaxAlarm::sqlRealtimeAlarmFilter( $alarm_code_arr, 'b' );
        if ( $sql_alarm === false ) {
            return self::PROC_INVALID;
        }
        $sql_alarm = strlen( $sql_alarm ) === 0 ? $sql_alarm : ( ' AND ' . $sql_alarm );
        
        if(!empty($search_sql)){
            $search_sql = 'AND ' . $search_sql;
        }

        $_id_arr = $this->mem2mcbView( $net_arr, $stage_arr, $mcb_ids, true );
        if ( is_int( $_id_arr ) ) {
            return $_id_arr;
        } else if ( count( $_id_arr ) === 0 ) {
            return parent::datatableProp( 0 );  // 都已經不在 view 之中了，理所當然 table 是空的
        }
        //print_r($_id_arr);

        $kiosk = $this->KioskotherSide($company_id, $_id_arr);
        if($kiosk == false){
            $kiosk = [];
        }
        //error_log(print_r($kiosk, true));

        $_id_arr = array_unique(array_merge($_id_arr, $kiosk));
        sort($_id_arr);
        //error_log(print_r($_id_arr, true));
        //$_id_arr = array_intersect($mcb_ids, $_id_arr);//找出傳入$mcb_ids的數組，存在在可視範圍$_id_arr的機台序號
        $sql_where_base = 'WHERE a.id IN(' . implode( ',', $_id_arr ) . ') ';

        try{
            /**
             * MySQL中Procedure參數
             * `out_display`(
	         *   IN type INT,
             *   IN company_id INT,
             *   IN user_id INT,
             *   IN mode INT,
             *   IN cond_flg INT,
             *   IN no_loc_flg INT,
             *   IN pg INT,
             *   IN per INT,
             *   IN sql_where_base VARCHAR(1000),
             *   IN search_sql VARCHAR(1000),
             *   IN sql_alarm VARCHAR(1000),
             *   IN tag_sql VARCHAR(1000),
             *   IN sql_address VARCHAR(1000),
             *   IN model_sql VARCHAR(1000),
             *   OUT result JSON
             *  )
             */
            $sql = 'call dynascan365_client.out_display(' . $type .', '. $company_id .', ' . $user_id .', 3, -1, 1, 0, 0, "' . $sql_where_base . '", "' . $search_sql . '", "' . $sql_alarm . '", \'\', \'\', \'\', @result);';
            error_log($sql);
            $data = self::selectTransact($sql, $this->sql_vals);

            //如果查詢出來結果是空的，則去查詢@result有沒有東西
            $err_sql = 'SELECT @result';
            $err_data = self::selectTransact($err_sql, $this->sql_vals);
            if (empty($data)) {
                
                // Procedure處理完畢時，如果強制跳出則會在@result中存入錯誤信息，如果是空的但是查詢是成功則會為null
                if($err_data[0]['@result'] === null){
                    $result = 'No Result!!!';
                }else{
                   return self::PROC_INVALID;
                }

            }else{
                $result = $data;
            }
        }catch(Exception $e){
           
    }
        return $result;
    }



    /**
     * test export displayerVersion
     * 
     * @return array
     */
    public function sqldisplayerVersion()
    {
        try{    
            //$pdo = $this->container->get('pdo');
            $sql = 'SELECT 
                    IF(a.sn != b.sn, 1, 0) AS sn_error, IF(a.model != b.model, 1, 0) AS model_error,
                    b.update_on, IF( DATE_ADD( b.update_on, INTERVAL 5 MINUTE ) < NOW(), 1, 0 ) AS off_line,
                    a.sn AS a_sn, a.model AS a_model, a.belong_to_name, b.bonding, b.sn AS b_sn, b.model AS b_model, b.android_v, b.dsservice_v, b.dsm365_v,
                    JSON_UNQUOTE( JSON_EXTRACT(b.status_tags, \'$.ttc_ver\') ) AS tcc_ver
                    
                    FROM displayer AS a
                    INNER JOIN displayer_realtime AS b ON b.id = a.id
                    WHERE a.status != \'D\' AND b.mount = 1';
            $stat = $this->pdo->prepare($sql);
            $stat->execute();
            $data = $stat->fetchAll();
            if (empty($data)) {
                // 若結果為空，返回提示訊息
                $result = 'No Result!!!';
                
            }else{
                $result = $data;
            }
            //$pdo = null;
            $stat->closeCursor();
        }catch(Exception $e){
                //$result = 'SQL Error';
        }

        return $result;
        
    }


    /*public function downloadCSV(Request $request, Response $response, array $args, array $result)
    {
        $filename = 'userData.csv';
        
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
        ob_end_clean();
        //error_log($stream);

        return $response->withHeader( 'Content-Type', 'application/csv' )
                        ->withHeader( 'Content-Description', 'File Transfer' )
                        ->withHeader( 'Content-Transfer-Encoding', 'binary' )
                        ->withHeader( 'Content-Disposition', 'attachment; filename="' . $filename . '"')
                        ->withHeader( 'Access-Control-Expose-Headers', 'Content-Length, Content-Disposition' ) // for Access-Allow-Control, you have to use it to expose
                        ->withHeader( 'Cache-Control', 'must-revalidate, post-check=0, pre-check=0' )
                        ->withHeader( 'Pragma', 'public' )
                        ->withHeader( 'Expires', '0' )
                        ->withBody($stream);
    }*/

}
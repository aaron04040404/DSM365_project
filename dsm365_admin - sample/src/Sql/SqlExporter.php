<?php
namespace Gn\Sql;

use RuntimeException;
use Exception;
use PDOException;
use PDO;

//use Lib
use Gn\Lib\Uuid;
use Gn\Lib\ValidDateTime;

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

    public function __construct( array $db_settings, array $requester_auth)
    {

        parent::__construct( $db_settings, $requester_auth);
        //$this->sql_vals = array();
    }


    /**
     * get internally displayManagement
     * 
     * @param int $mcb_id
     * @param string $start_date
     * @param string $end_date
     * @return array
     * 
     */
    
    public function sqldisplayManagement(int $mcb_id, string $start_date, string $end_date)
    {
        //$sql_vals = array();
        // 檢查 start_date, end__date 格式是否正確並且是有效日期
        $dateTimeFormat = 'Y-m-d H:i:s';
        if (!ValidDateTime::isvalidDateTime($start_date, $dateTimeFormat) | !ValidDateTime::isvalidDateTime($end_date, $dateTimeFormat) ) {
            
            return self::PROC_INVALID;
        }
        try{
            /**
             * MySQL中Procedure參數
             * `Display_Management`(
             *   IN mcb_idd INT,
             *   IN start_date VARCHAR(20),
             *   IN end_date VARCHAR(20),
             *   IN pg INT,
             *   IN per INT
             *   )
             * 
             */
            
            $sql = 'call dynascan365_main.Display_Management(' . $mcb_id . ', "' . $start_date . '", "' . $end_date . '", 0, 0);';
            //$result = NULL;
            $data = self::selectTransact($sql, $this->sql_vals);
            //$pdo = null;
            if (empty($data)) {
                // 若結果為空，返回提示訊息
                $result = 'No Result!!!';
                
            }else{
                $result = $data;
            }
            //$pdo = null;
            //$stat->closeCursor();
        }catch(Exception $e){
                //$result = 'SQL Error';
        }

        return $result;
    }

    /**
     * get internalOverview
     * 
     * @param int $mem_id
     * @return array
     * 
     */
    public function sqlinternalOverview(int $mem_id)
    {
        //$sql_vals = array();

        try{    
            $sql = 'SELECT company as Client, tab_2.model as Model, tab_1.Network, Count(*) as Total, 
                    COUNT(CASE 
                            WHEN a_lcm_pw = 0
                            AND a_kiosk_mcb = 0
                            AND a_failover = 0
                            AND a_lcm_pw = 0
                            AND a_fan = 0
                            AND a_thermal = 0
                            AND a_lan_switch_pw = 0
                            AND a_flood = 0
                            AND s_door = 0 
                            AND a_lcm_mount = 0
                            AND a_fan_1 = 0 THEN 1 END) AS Normal,
                    COUNT(CASE WHEN a_kiosk_mcb = 1 THEN 1 END) AS MCB_Non_paired,
                    COUNT(CASE WHEN a_lcm_pw = 1 THEN 1 END) AS LCM,
                    COUNT(CASE WHEN a_player_pw = 1 THEN 1 END) AS Player,
                    COUNT(CASE WHEN a_failover = 1 THEN 1 END) AS Input_Source_issue,
                    COUNT(CASE WHEN a_fan = 1 THEN 1 END) AS Fan,
                    COUNT(CASE WHEN a_thermal = 1 THEN 1 END) AS Thermal,
                    COUNT(CASE WHEN a_lan_switch_pw = 1 THEN 1 END) AS LAN_switch,
                    COUNT(CASE WHEN a_flood = 1 THEN 1 END) AS Flood,
                    COUNT(CASE WHEN s_door = 1 THEN 1 END) AS Door,
                    COUNT(CASE WHEN a_lcm_mount = 1 THEN 1 END) AS LCM_mount_error,
                    COUNT(CASE WHEN a_fan_1 = 1 THEN 1 END) AS `Fan(Adv.)`
                FROM
                (
                SELECT dnm.*, mcb_id, b.*, 
                        IFNULL(dn.name, \'\') as Network
                FROM displayer_network_mem as dnm
                INNER JOIN displayer_network_mcb as dnmc ON dnm.net_uuid = dnmc.net_uuid
                INNER JOIN displayer_network as dn ON dnm.net_uuid = dn.uuid
                INNER JOIN displayer_realtime as b ON dnmc.mcb_id = b.id
                WHERE mem_id = ' . $mem_id . ') as tab_1
                INNER JOIN 
                (
                SELECT a.id, model,
                        name AS company,
                        a.status
                    FROM displayer AS a
                    INNER JOIN dynascan365_client.company AS b ON a.belong_to = b.id
                    ) as tab_2 ON tab_1.mcb_id = tab_2.id
                    GROUP BY Client, Model, Network
                    ORDER BY Client';
            $data = self::selectTransact($sql, $this->sql_vals);
            if (empty($data)) {
                // 若結果為空，返回提示訊息
                $result = 'No Result!!!';
                
            }else{
                $result = $data;
            }
        }catch(Exception $e){

        }

        return $result;
    }


    /**
     * test export displayerVersion
     * Note:
     * 這是最原始的寫法，在沒有套用Nick任何的函數
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

}
<?php
/**
 * This class works for extending to API webpage controller.
 *
 * @author Nick Feng
 * @since 1.0
 */
namespace Gn\Ctl;

use ErrorException;
use Exception;
use Gn\Fun\DisplayerAdminFun;
use Gn\Fun\DsNetworkAdminFun;
use Gn\Fun\DisplayStageFun;
use Gn\Interfaces\BaseRespCodesInterface;
use Gn\Interfaces\DisplayNetworkInterface;

// from Slim
use OutOfBoundsException;
use Slim\Container;
use Slim\Http\StatusCode;

/**
 * Api basic controller functions for extending.
 * 
 * @author Nick
 */
abstract class ApiBasicCtl extends BasicCtl implements BaseRespCodesInterface, DisplayNetworkInterface
{
    /**
     * Constructor.
     *
     * @param Container $container
     * @throws ErrorException
     */
    public function __construct( Container $container )
    {
        if ( empty( $container->jwt ) ) {
            throw new ErrorException( 'Authorization is not existed in header' );
        } else {
            parent::__construct( $container );
            // you must work with user access token (not mcb token)
            // so if you cannot get user account data from the token,
            // you cannot start all things about all interaction.
            // if ( empty( (array)$this->memData ) ) {
            //     throw new \ErrorException( 'user token error(channel=' . $this->container->jwt->data->channel . ')' );
            // }
            if ( !is_array( $this->memData ) || empty( $this->memData ) ) {
                throw new ErrorException( 'user token error(channel=' . $this->container->jwt->data->channel . ')' );
            }
        }
    }
    
    /**
     * NOTE: 請跟著 Slim3 的定義去顯示 HTTP TEXT & HTTP CODE
     * 
     * @param int $code
     * @param string $header_txt
     * @param mixed $data
     * @return bool
     */
    public function resp_decoder ( int $code, string $header_txt = '', $data = NULL ): bool
    {
        $this->respCode = StatusCode::HTTP_OK;
        if ( isset( static::PROC_TXT[ $code ] ) ) {
            $_ok = false;
            switch ( $code ) {
                case static::PROC_OK:
                case static::PROC_CLIENT_COMPANY_OK_AND_PLAN_UPDATE:
                    $this->respJson['status'] = 1;
                    $_ok = true;
                    break;
                case static::PROC_INVALID:
                    $this->respCode = StatusCode::HTTP_BAD_REQUEST;
                    break;
                case static::PROC_SERIALIZATION_FAELURE:
                case static::PROC_SQL_ERROR:
                    $this->respCode = StatusCode::HTTP_INTERNAL_SERVER_ERROR;
                    break;
            }
            $this->respJson['data'] = is_null( $data ) ? ( $header_txt . static::PROC_TXT[ $code ] ) : $data;
            return $_ok;
        } else {
            throw new OutOfBoundsException( static::EXCEPTION_MSG_PROC_OUTOFBOUNDS );
        }
    }
    
    /**
     * Response for dataTable API
     *
     * @param int $drawCode
     * @param int $totalElem
     * @param array $jsonArray
     */
    public function resp_datatable ( int $drawCode, int $totalElem = 0, array $jsonArray = [] )
    {
        $this->respCode = StatusCode::HTTP_OK;
        $this->respJson = parent::dataTablesResp( $drawCode, $totalElem, $jsonArray );
    }
    
    /**
     * NOTE: if the request is not a company owner & admin, 這裡必須要檢查 display network，避免不相干的人來干涉
     *
     * @param array $display_arr    When you cal this function, you have to ensure the member data is no problem form token decoding
     * @throws ErrorException
     * @return bool
     */
    protected function inNetwork ( array $display_arr ): bool
    {
        try {
            if ( $this->memData['type'] > static::DETECT_THRESHOLD ) {
                $network = new DsNetworkAdminFun( $this->settings['db']['main'], $this->memData );
                $code = $network->isMemHasDisplayer( $display_arr );
                if ( $code !== static::PROC_OK ) {
                    // set a log if it is a server internal error
                    if ( $code >= static::PROC_FOREIGN_KEY_CONSTRAINT ) {
                        $this->container->logger->emergency( 'network access error: #' . $code );
                    }
                    return false;
                }
            }
            return true;
        } catch ( Exception $e ) {
            throw new ErrorException( 'network access error: ' . $e->getMessage() );
        }
    }
    
    /**
     * To check the view of stage
     * 
     * NOTE: if the request is not a company owner
     * 
     * @param array $display_arr
     * @throws ErrorException
     * @return bool
     */
    protected function inStage ( array $display_arr ): bool
    {
        try {
            // only the system owner doesn't need to check stage
            if ( $this->memData['type'] !== 1 ) {
                $stageFun = new DisplayStageFun( $this->settings['db']['main'], $this->memData );
                $code = $stageFun->inMemStages( $display_arr );
                if ( $code !== static::PROC_OK ) {
                    // set a log if it is a server internal error
                    if ( $code >= static::PROC_FOREIGN_KEY_CONSTRAINT ) {
                        $this->container->logger->emergency( 'stage access error: #' . $code );
                    }
                    return false;
                }
            }
            return true;
        } catch ( Exception $e ) {
            throw new ErrorException( 'stage access error: ' . $e->getMessage() );
        }
    }

    /**
     * To check the view of stage & network effect.
     *
     * @param array $display_arr
     * @param bool $is_sn FALSE => int to sn; TRUE => sn to int
     * @param array|null $output
     * @return bool
     * @throws ErrorException
     */
    protected function inMemView ( array $display_arr, bool $is_sn = false, array &$output = NULL ): bool
    {
        $mcb_admin = new DisplayerAdminFun( $this->settings['db']['main'] );
        if ( $is_sn ) {
            $display_arr = $mcb_admin->swapMcbSn( $display_arr, TRUE );
            if ( $display_arr === false ) {
                return false;
            }
            if ( is_array( $output ) ) {
                $output = $display_arr;
            }
        }
        if ( $mcb_admin->mcbExists( $display_arr ) ) {
            return self::inStage( $display_arr ) && self::inNetwork( $display_arr );
        }
        return false;
    }
}

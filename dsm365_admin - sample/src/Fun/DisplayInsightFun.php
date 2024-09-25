<?php
/**
 * All about dashboard information and navigation bar information
 * processing controller functions are here.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Fun;

use ErrorException;
use Gn\Sql\SqlDisplayInsight;

/**
 * All dashboard function of interaction.
 *
 * @author Nick Feng
 *
 */
class DisplayInsightFun
{
    /**
     * 
     * @var SqlDisplayInsight
     */
    protected $sql_bs = NULL;

    /**
     * Constructor, and look up jwt id automatically when it called.
     *
     * @param array $db_settings
     * @param array $requester_auth It is from SqlRegister::isApiJwt method output:
     * @throws ErrorException
     */
    public function __construct( array $db_settings, array $requester_auth )
    {
        $this->sql_bs = new SqlDisplayInsight( $db_settings, $requester_auth );
    }

    /**
     *
     * @param int $near_on
     * @param int $scope_code
     * @param array $display_arr
     * @return array|int
     */
    public function displayInsight_AcPower_kWh ( int $near_on, int $scope_code, array $display_arr )
    {
        return $this->sql_bs->displayAckWhSum( $near_on, $scope_code, $display_arr );
    }

    /**
     *
     * @param int $near_on
     * @param int $scope_code
     * @param array $display_arr
     * @return array|int
     * @throws ErrorException
     */
    public function displayInsight_AcPower_Chart ( int $near_on, int $scope_code, array $display_arr )
    {
        return $this->sql_bs->displayAcInputPowerChart( $near_on, $scope_code, $display_arr );
    }

    /**
     *
     * @param array $display_arr
     * @return array|int
     */
    public function displayInsight_ConditionSum ( array $display_arr )
    {
        return $this->sql_bs->displayConditionSum( $display_arr );
    }
    
    const DISP_INSIGHT_FACE_SUM_KIOSK_MODE = 1;
    const DISP_INSIGHT_FACE_SUM_LCM_MODE   = 2;

    /**
     *
     * @param int $mode
     * @param array $display_arr
     * @return array|int
     */
    public function displayInsight_FaceTypeSum ( int $mode, array $display_arr )
    {
        switch ( $mode ) {
            case self::DISP_INSIGHT_FACE_SUM_KIOSK_MODE:
                return $this->sql_bs->displayFaceTypeSum_Kiosk( $display_arr );
            case self::DISP_INSIGHT_FACE_SUM_LCM_MODE:
                return $this->sql_bs->displayFaceTypeSum_LCM( $display_arr );
            default:
                return $this->sql_bs::PROC_INVALID;
        }
    }

    /**
     *
     * @param array $display_arr
     * @return array|int
     * @author Cleo: 2021-05-05
     *
     */
    public function displayInsight_TccAlarm ( array $display_arr )
    {
        return $this->sql_bs->displayTccAlarmInsight( $display_arr );
    }
}

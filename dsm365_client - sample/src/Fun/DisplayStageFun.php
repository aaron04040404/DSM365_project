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
use Gn\Sql\SqlDisplayStage;

/**
 * All dashboard function of interaction.
 *
 * @author Nick Feng
 *
 */
class DisplayStageFun
{
    /**
     * asset database.
     * 
     * @var SqlDisplayStage
     */
    protected $sqlDisplayStage = null;

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
     *              'permission' => array()
     *          ];
     * @throws ErrorException
     */
    public function __construct( array $db_settings, array $requester_auth )
    {
        $this->sqlDisplayStage = new SqlDisplayStage( $db_settings, $requester_auth );
    }

    /**
     *
     * @param bool $is_system
     * @return array|int
     */
    public function getStageTab ( bool $is_system )
    {
        return $this->sqlDisplayStage->stageTab( $is_system );
    }
    
    /**
     * IMPORTANT: 基本上，不設計可以修改 displayer_situation 表單內容的函式。因為它要是固定的，而且絕對的
     *
     * @param array $data [
     *     {
     *         'id':     life code id,
     *         'status': 0 is removing, 1 is adding
     *     }
     * ]
     * @return int
     */
    public function setStages ( array $data ): int
    {
        return $this->sqlDisplayStage->stageEditor( $data );
    }
    
    /**
     * 
     * @param int $stage_id
     * @return array|int
     
    public function stageMembers ( int $stage_id )
    {
        return $this->sqlDisplayStage->getStageMembers( $stage_id );
    }*/

    /**
     *
     * @param int $member_id
     * @param bool $strictNum
     * @return array|int
     */
    public function memberStages ( int $member_id, bool $strictNum = false )
    {
        return $this->sqlDisplayStage->getMemberStages ( $member_id, $strictNum );
    }
    
    /**
     *
     * @param bool $del true is write into; otherwise, is deleted
     * @param int $mem_id
     * @param array $stage_arr
     * @return int
     */
    public function editMember2Stage ( bool $del, int $mem_id, array $stage_arr ): int
    {
        return $this->sqlDisplayStage->memStageMount( $del, $mem_id, $stage_arr );
    }
    
    /**
     *
     * @param int $company
     * @param array $life_arr
     * @return int
     */
    public function setDisplayerStage ( int $company, array $life_arr ): int
    {
        return $this->sqlDisplayStage->editDisplayerStageCode( $company, $life_arr );
    }

    /**
     *
     * @param array $mcb_arr
     * @return int
     */
    public function inMemStages( array $mcb_arr ): int
    {
        return $this->sqlDisplayStage->inMemberStages( $mcb_arr );
    }
}

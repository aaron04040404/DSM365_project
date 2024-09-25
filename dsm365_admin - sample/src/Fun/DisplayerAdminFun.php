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
use Gn\Sql\SqlDisplayerAdmin;

/**
 * All dashboard function of interaction.
 *
 * @author Nick Feng
 *
 */
class DisplayerAdminFun
{
    /**
     * notification database.
     * 
     * @var SqlDisplayerAdmin
     */
    protected $sqlDpAdmin = NULL;
    
    /**
     * Constructor, and look up jwt id automatically when it called.
     *
     * @param array $db_settings
     */
    public function __construct( array $db_settings )
    {
        $this->sqlDpAdmin = new SqlDisplayerAdmin( $db_settings );
    }

    /**
     *
     * @param array $arr
     * @param bool $from_sn FALSE => int to sn; TRUE => sn to int
     * @return array|bool
     */
    public function swapMcbSn( array $arr, bool $from_sn = false ): bool
    {
        return $this->sqlDpAdmin->convertMcbSn( $arr, $from_sn );
    }

    /**
     * To ensure the MCB ID is existed.
     *
     * @param array $mcb_arr
     * @return boolean
     */
    public function mcbExists ( array $mcb_arr ): bool
    {
        return $this->sqlDpAdmin->isMcbExisted( $mcb_arr );
    }

    /**
     * Get display account information.
     *
     * @param int $company
     * @param mixed $displayer
     * @param bool $is_series_num If $is_series_num is true, $displayer is a string.
     * @return array|int
     * @author Nick
     */
    public function displayerInfo ( int $company, $displayer, bool $is_series_num = false )
    {
        return $this->sqlDpAdmin->displayerDetails( $company, $displayer, $is_series_num );
    }

    /**
     * Working for kiosk devices to find out other LCM on the same kiosk machine.
     *
     * IMPORTANT: "WITHOUT NETWORK & STAGE CONFINE"
     *
     * @param int $company
     * @param int $mcb_id
     * @param bool $hasSelf
     * @param bool $hasUnmount
     * @param bool $hasDeleted
     * @return array|int
     */
    public function getKioskGroupInfo( int $company, int $mcb_id, bool $hasSelf = false, bool $hasUnmount = false, bool $hasDeleted = false )
    {
        return $this->sqlDpAdmin->getKioskGroup( $company, $mcb_id, $hasSelf, $hasUnmount, $hasDeleted );
    }
    
    /**
     * Sync displayer client address hash code from address table data to the admin database.
     *
     * @author Nick
     * @param int $company
     * @param int $display
     * @param string $country_code
     * @param string $zip_code
     * @param string $state
     * @param string $city
     * @param string $addr_01
     * @param string $addr_02
     * @param array $gps
     * @return int Status code in integer
     */
    public function setDisplayAddress ( int $company, int $display, string $country_code, string $zip_code, string $state,
                                        string $city, string $addr_01, string $addr_02, array $gps ): int
    {
        return $this->sqlDpAdmin->editDisplayAddress( $company, $display, $country_code, $zip_code, $state, $city, $addr_01, $addr_02, $gps );
    }
    
    /**
     * NOTE: By Nick at 2021-06-19 決定把 mark, status description 與 地址相關的東西拆開來
     * 
     * @author Nick
     * @param int $company
     * @param int $id
     * @param int $status
     * @param string $desc_txt
     * @return int information code.
     */
    public function editDisplayerInfo ( int $company, int $id, int $status = 0, string $desc_txt = NULL ): int
    {
        return $this->sqlDpAdmin->editDisplayerDetails( $company, $id, $status, $desc_txt );
    }
    
    /**
     * make member customized mark to something the member want.
     * 
     * @param int $company
     * @param int $id
     * @param int $mark
     * @return int
     */
    public function setDisplayCustomizedComment ( int $company, int $id, int $mark ): int
    {
        return $this->sqlDpAdmin->setDisplayCustomizedMark( $company, $id, $mark );
    }

    /**
     * Return tag for each displayer id(array key)
     *
     * @param int $company
     * @param array $id_arr
     * @return int|array
     */
    public function getDisplayerTags ( int $company, array $id_arr )
    {
        return $this->sqlDpAdmin->getDisplayerTags( $company, $id_arr );
    }

    /**
     * Set tags for a displayer
     *
     * @param int $company
     * @param int $owner_id
     * @param array $tags
     * @return int
     * @throws ErrorException
     */
    public function editDisplayerTags ( int $company, int $owner_id, array $tags ): int
    {
        return $this->sqlDpAdmin->setDisplayerTags( $company, $owner_id, $tags );
    }

    /**
     * NOTE: 2024-03-21 因為 James 說要斷開 TCC 端的 displayer_realtime_sync 與 admin-side 的 displayer_realtime 於
     *       condition_flg 的條建異步。所以，要將原本在 BsDisplayProcFun 類別搬過來
     *
     * @param int $company_id
     * @param int $display_id
     * @param int $mode 0(default) = full information, 1 = simple mode for all realtime tags' status
     * @return int|array
     * @author Nick
     */
    public function displayCurrentStatus ( int $company_id, int $display_id, int $mode = 0 )
    {
        switch ( $mode ) {
            case 1:
                return $this->sqlDpAdmin->getDisplayRealtimeLite( $company_id, $display_id );
            default:
                return $this->sqlDpAdmin->getDisplayRealtime( $company_id, $display_id );
        }
    }
}

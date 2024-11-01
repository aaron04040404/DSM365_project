<?php
/**
 * All displayer management processing controller functions are here.
 * 
 * @author  Nick Feng
 *
 * @since 1.0
 * 
 */
namespace Gn\Fun;

use Gn\Sql\SqlClientDisplayKiosk;

/**
 * client-side kiosk data functions.
 * 
 * @author Nick Feng
 */
class ClientDisplayKioskFun
{
    /**
     * client-side kiosk data SQL process.
     * 
     * @var SqlClientDisplayKiosk
     */
    protected $sqlDsKiosk = NULL;
    
    /**
     * Constructor
     *
     * @param array $db_settings database settings from Slim 3 settings array
     */
    public function __construct( array $db_settings )
    {
        $this->sqlDsKiosk = new SqlClientDisplayKiosk( $db_settings );
    }
    
    /**
     *
     * @param int $company
     * @param string $main_sn
     * @param string $descp_txt
     * @return int
     */
    public function editKioskInformation ( int $company, string $main_sn, string $descp_txt ): int
    {
        return $this->sqlDsKiosk->editKioskInfo( $company, $main_sn, $descp_txt );
    }
}

<?php
namespace Gn\Obj;

use Gn\Lib\RegexConst;
use TypeError;

class DsAddressObj
{
    const ADDR_LOGIC_AND = 0;
    const ADDR_LOGIC_OR  = 1;

    /**
     * All properties default are NULL.
     * 
     * @var array
     */
    private $data;
    
    public function __construct()
    {
        $this->data = [
            'logic'   => NULL,
            'country' => NULL,
            'zip'     => NULL,
            'state'   => NULL,
            'city'    => NULL
        ];
    }
    
    /**
     * remove all data
     **/
    public function __destruct()
    {
        unset( $this->data );
    }
    
    /**
     * To detect the object is empty or not
     * 
     * @return boolean
     */
    public function isEmpty(): bool
    {
        return empty( array_filter( $this->data ) );
    }
    
    /**
     * To detect the object is NULL or not
     *
     * @return boolean
     */
    public function isNull(): bool
    {
        return empty( array_filter( $this->data, function ( $value ) {
            return !is_null( $value );
        } ) );
    }
    
    /**
     * 如果任何一個欄位的數值是 NULL，則代表這個欄位的數值是可以忽視的。
     * 但是，如果是空字串的話舊部可以忽視。因為那有可能是它想要清除字串，才用空字串來消除
     * 
     * @return boolean
     */
    public function isValid(): bool
    {
        foreach ($this->data as $name => $value) {
            if (!empty($value)) {
                switch ($name) {
                    case 'country':
                        if (!is_string($value) || !RegexConst::isCountryAlpha($value)) {
                            return false;
                        }
                        break;
                    case 'zip':
                        if (!is_string($value) || RegexConst::safeStrlen($value) > RegexConst::STR_ADDR_ZIPCODE_LEN) {
                            return false;
                        }
                        break;
                    case 'state':
                        if (!is_string($value) || RegexConst::safeStrlen($value) > RegexConst::STR_ADDR_STATE_LEN) {
                            return false;
                        }
                        break;
                    case 'city':
                        if (!is_string($value) || RegexConst::safeStrlen($value) > RegexConst::STR_ADDR_CITY_LEN) {
                            return false;
                        }
                        break;
                    case 'logic':
                        if ($value !== self::ADDR_LOGIC_AND && $value !== self::ADDR_LOGIC_OR) {
                            return false;
                        }
                        break;
                }
            }
        }
        return true;
    }
    
    /**
     * 
     * 
     * Example:
     *    $addr = new DsAddressObj();
     *    $addr->logic = 1;
     * 
     * 
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set( string $name, $value )
    {
        switch ($name) {
            case 'logic':
            case 'country':
            case 'zip':
            case 'state':
            case 'city':
                $this->data[$name] = $value;
                break;
            default:
                throw new TypeError( 'ds-address __set() name is invalid' );
        }
    }
    
    /**
     * 
     * @param string $name
     * @return mixed|NULL
     */
    public function __get( string $name )
    {
        return $this->data[$name] ?? NULL;
    }
    
    /**
     * 
     * @param string $name
     * @return boolean
     */
    public function __isset( string $name ) 
    {
        return isset( $this->data[ $name ] );
    }
    
    /**
     * 
     * @param string $name
     */
    public function __unset( string $name )
    {
        unset( $this->data[ $name ] );
    }
}
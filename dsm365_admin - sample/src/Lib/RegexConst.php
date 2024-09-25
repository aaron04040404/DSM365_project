<?php
/**
 * Regex constant only for DynaScan system.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Lib;

/**
 * All regex string for comparison.
 * @author Nick Feng
 */
class RegexConst extends StrProc {
    const REGEX_SSID_CHAR      = '/^[A-Za-z0-9_-]{10,25}$/';
    const REGEX_MODELNAME_CHAR = '/^[A-Za-z0-9_-]{6,16}$/';
    const REGEX_USER_TYPE      = '/^[0-9]{1,3}$/';
    const REGEX_MCB_STATUS     = '/^[aAdD]{1}$/';
    
    const STR_ADDR_ZIPCODE_LEN = 16;
    const STR_ADDR_STATE_LEN   = 100;
    const STR_ADDR_CITY_LEN    = 100;
    const STR_ADDR_01_LEN      = 1024;
    const STR_ADDR_02_LEN      = 1024;
}
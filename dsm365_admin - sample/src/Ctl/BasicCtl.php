<?php
/**
 * It works for general web pages or authorization checking pages.
 *
 * @author Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Ctl;

use DateTime;
use DateTimeZone;
use ErrorException;
use Exception;
use Gn\Interfaces\HttpMessageInterface;

// from Slim
use Slim\Container;
use Slim\Http\StatusCode;

/**
 * Basic controller functions for extending.
 * 
 * @author nick
 *
 */
abstract class BasicCtl implements HttpMessageInterface
{
    /**
     * name of JWT encoder.
     * 
     * @var string
     */
    const JWT_ALGORITHM = 'HS256';
    /**
     * Get container object from Slim.
     * @var Container
     */
    protected $container;
    
    /**
     * Slim framework settings
     * @var object
     */
    protected $settings;
    
    /**
     * user data from token jti.
     * @var array
     */
    protected $memData;
    
    /**
     * mcb data from token jti.
     * @var array
     */
    protected $mcbData;
    
    /**
     * API response code of header
     * @var int
     */
    protected $respCode = StatusCode::HTTP_BAD_REQUEST;
    
    /**
     * API response JSON content
     * @var array
     */
    protected $respJson = NULL;
    
    /**
     * Constructor.
     *
     * @param Container $container
     */
    public function __construct ( Container $container )
    {
        $this->container = $container;
        $this->settings  = $container->get( 'settings' );
        $this->memData   = $container->usrAuthData;
        $this->mcbData   = $container->mcbAuthData;
        /*
         HTTP Response Code 500 => For SQL error, php exception error.
         HTTP Response Code 401 => Only for token verification.
         HTTP Response Code 400 => Only for request variable fields and values are illegal.
         HTTP Response Code 200 => All without above two items, but there are some status code in response JSON structure:
             0 is on failure,
             1 is on success
         */
        $this->respJson = self::jsonResp( 0, (array)static::HTTP_RESP_MSG[ StatusCode::HTTP_BAD_REQUEST ] );
    }
    
    /**
     * check the request is from the specified host
     *
     * @return bool
     */
    protected function isReferred (): bool
    {
        if( !isset( $_SERVER['HTTP_REFERER'] ) || !isset( $_SERVER['HTTP_HOST'] ) || !isset( $_SERVER['SERVER_NAME'] ) ||
            strpos( $_SERVER['HTTP_REFERER'], $_SERVER['SERVER_NAME'] ) === false ||
            strpos( $_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME'] ) === false )
        {
            return false;
        }
        return true;
    }

    /**
     * Create a cookie for browser.
     *
     * @param string $name
     * @param string|null $content
     * @param int $exp
     * @param bool $isSecure
     * @return bool
     * @deprecated
     */
    protected function setWebCookie ( string $name, string $content = NULL, int $exp = 0, bool $isSecure = false ): bool
    {
        $cookie_params_arr = session_get_cookie_params();
        // read cookie set HttpOnly
        return setcookie( $name, $content, $exp, $cookie_params_arr['path'], $cookie_params_arr['domain'], $isSecure, true );
    }

    /**
     * Create/Remove a cookie for browser.
     *
     * @param string $name
     * @param string|null $content
     * @param string|null $domain
     * @param string $path
     * @param int $exp When value is 0, it means don't remember me on browser.
     * @param bool $isSecure
     * @return string cookie content string. key value will be urlencoded.
     * @throws ErrorException
     */
    protected function setWebCookie_v2 ( string $name, string $content = NULL, string $domain = NULL, 
                                         string $path = '/', int $exp = 0, bool $isSecure = false ): string
    {
        if ( !empty( $name ) ) {
            // Set-Cookie: <cookie-name>=<cookie-value>; Expires=<date>; Domain=<domain-value>; Secure; HttpOnly
            return urlencode( $name ) . '=' . ( is_null( $content ) ? '' : urlencode( $content ) )
                   . '; Domain=' . ( is_null( $domain ) ? $_SERVER['SERVER_NAME'] : $domain )
                   . '; Path=' . $path
                   . ( $exp !== 0 ? '; Expires=' . ( gmdate( 'l, d-M-Y H:i:s', $exp ) . ' GMT' ) : '' )
                   . ( $isSecure ? '; Secure' : '' ) . '; HttpOnly; SameSite=Lax'; // Strict
            // NOTE: Change SameSite=Strict to SameSite=Lax for email content leading link back to website can be working.
            //       Otherwise, you will lose the Cookies Header when redirecting from other URL or page
        }
        // IMPORTANT: don't let it be happened!!
        throw new ErrorException( 'access token error!' );
    }
    
    /**
     * Get web cookie contents.
     *
     * @deprecated
     * @param string $name
     * @return mixed
     */
    protected function getWebCookie ( string $name )
    {
        return filter_input( INPUT_COOKIE, $name, FILTER_SANITIZE_STRING );
    }
    
    /**
     * 
     * @deprecated
     * @param array $names
     * @param bool $isSecure
     */
    protected function clearAllWebCookie ( array $names = [], bool $isSecure = false )
    {
        if ( empty( $names ) ) {
            if ( isset( $_SERVER['HTTP_COOKIE'] ) ) {
                $cookies = explode( ';', $_SERVER['HTTP_COOKIE'] );
                foreach( $cookies as $cookie ) {
                    $parts = explode( '=', $cookie );
                    $name = trim( $parts[0] );
                    self::setWebCookie( $name, null, -1, $isSecure );
                }
            } else {
                foreach( $_COOKIE as $name => $cookie ) {
                    self::setWebCookie( $name, null, -1, $isSecure );
                }
            }
        } else {
            foreach ( $names as $name ) {
                self::setWebCookie( $name, null, -1, $isSecure );
            }
        }
    }

    /**
     * Generate a cookie lifetime. Default is 2 years(63072000 seconds)
     *
     * @param string $tzone
     * @return int
     * @throws Exception
     */
    protected function genCookieLifeTime ( string $tzone = 'UTC' ): int
    {
        $tz = new DateTimeZone($tzone);
        return (new DateTime(null, $tz))->getTimeStamp() + 63072000;    // limit is 2 years. 2 * 365 * 24 * 60 * 60
    }
    
    /**
     * Get the coming reference
     *
     * @return string
     */
    protected function getExitNodeIpAddress (): string
    {
        if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } else if( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * Before you save log string with token information in jwt->data, you can use the function to convert all to string.
     *
     * @param mixed $payloadData
     * @return string
     */
    protected function jsonLogStr ( $payloadData ): string
    {
        return json_encode($payloadData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Message JSON array
     *
     * @param int $status
     * @param mixed $data in json
     * @return array { 'status': [ false = 0| TRUE = 1 ], 'message': [ string | json structure array ] }
     */
    protected function jsonResp ( int $status, $data ): array
    {
        /*
         HTTP Response Code 500 => For SQL error, php exception error.
         HTTP Response Code 401 => Only for token verification.
         HTTP Response Code 400 => Only for request variable fields and values are illegal.
         HTTP Response Code 200 => All without above two items, but there are some status code in response JSON structure:
             0 is on failure,
             1 is on success,
         */
        return [
            'status' => $status,
            'data'   => $data
        ];
    }
    
    /**
     * This works for DataTables.net framework in front-end javascript in AJAX.
     * The function is working for detecting the URL query parameters is in need.
     * What system only needs are page, per, order, search, and draw(a random code)
     * 
     * @param array $q
     * @return bool
     */
    protected function is_dataTablesParams ( array $q ): bool
    {
        if ( isset( $q['draw'] ) && isset( $q['page'] ) && isset( $q['per'] ) 
            && isset( $q['order'] ) && isset( $q['search'] )
            && ( ctype_digit( $q['page'] ) || is_int( $q['page'] ) ) 
            && ( ctype_digit( $q['per'] ) || is_int( $q['per'] ) ) 
            && is_array( $q['order'] ) && is_string( $q['search'] ) )
        {
            return true;
        }
        return false;
    }
    
    /**
     * This works for DataTables.net framework in front-end javascript in AJAX.
     *
     * @param int $drawCode It prevents XXS for Datatables.
     * @param int $totalElem
     * @param array $jsonArray
     * @return array
     */
    protected function dataTablesResp ( int $drawCode, int $totalElem = 0, array $jsonArray = [] ): array
    {
        return array (
            'draw'            => $drawCode,     // It prevents XXS for Datatables, so it from front-side to give a start
            'recordsTotal'    => $totalElem,    // total number of elements for this category(a single table contents)
            'recordsFiltered' => $totalElem,    // limit for elements to show
            'data'            => $jsonArray     // json data array.
        );
    }
}
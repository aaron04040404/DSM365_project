<?php
/**
 * CURL for API connecting functions.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Lib;

/**
 * Class deals with CURL for API connecting.
 *
 * @author Nick Feng
 */
class Serv2Serv
{
    const HTTP_USER_AGENT_DEFAULT = 'DynaScan Service';
    
    
    // token default header name is : Authorization
    
    // Quividi token header name is : HTTP_AUTHORIZATION

    /**
     *
     * @param string $url
     * @param bool $isSSL
     * @param array $postJson
     * @param string|null $token
     * @param string $tokenHeader When $token is set, the header must set too.
     * @return boolean|array
     */
    public static function Curl_Json2Json( string $url, bool $isSSL = FALSE, array $postJson = [], 
                                           string $token = NULL, string $tokenHeader = 'X-Token' )
    {
        $out = FALSE;
        if (function_exists('curl_version')) {
            $postVarStr = json_encode($postJson);   // convert to JSON string
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $isSSL);
            curl_setopt($ch, CURLOPT_USERAGENT, ($_SERVER['HTTP_USER_AGENT'] ?? self::HTTP_USER_AGENT_DEFAULT)); // it must be
            //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_POST, 1);
            //curl_setopt($ch, CURLOPT_HEADER, 1); // no need to include header in the response.
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',                         // response in json
                'Content-Type: application/json; charset=utf-8',    // request in json
                'Content-Length: ' . strlen($postVarStr),
                ($tokenHeader.': '.$token)
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postVarStr);
            $curlResult = json_decode(curl_exec($ch), TRUE);
            $respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);  // 200, 400, 500, .....
            curl_close ($ch);
            
            $out = [
                'httpcode' => $respCode,
                'resp' => $curlResult
            ];
        }
        return $out;
    }
}
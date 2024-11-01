<?php
/**
 * test terminal sample:
 *
 * phpunit --debug ./tests/Functional/DisplayApiTest.php(全域變數跟專案內的版本一樣才能這樣呼叫)
 * ./vendor/bin/phpunit --verbose tests/Functional/DisplayApiTest.php
 * phpunit -v ./tests/Functional/DisplayApiTest.php
 *
 */
namespace Tests\Functional;

use Firebase\JWT\JWT;
use Gn\Lib\JwtPayload;

class DisplayApiTest extends TestBaseCtrl
{
    public function testAccessToken ()
    {
        $_secret = require __DIR__ . '/../../security/jwt/secret-register.php';
        /*
        $postVal = [
            'email'    => 'sean.he@dynascan.com.tw',
            'pw'       => hash( 'sha512', 'Dyna190411' ),
            'remember' => 0,
            'token'    => JWT::encode( JwtPayload::genPayload(), $_secret, 'HS256' )
        ]; 
        //*/
        /*
        $postVal = [
            'email'    => 'fgnickbanana@gmail.com',
            'pw'       => hash( 'sha512', '1qaz2wsX' ),
            'remember' => 0,
            'token'    => JWT::encode( JwtPayload::genPayload(), $_secret, 'HS256' )
        ];
        //*/
        //*
        $postVal = [
            'email'    => 'aaron.lcw@dynascan.com.tw',
            'pw'       => hash( 'sha512', 'Louis122511' ),
            'remember' => 0,
            'token'    => JWT::encode( JwtPayload::genPayload(), $_secret, 'HS256' )
        ];
        //*/
        /*
        $postVal = [
            'email'    => 'nickfeng@dynascan365.com',
            'pw'       => hash( 'sha512', '1qaz2wsX' ),
            'remember' => 0,
            'token'    => JWT::encode( JwtPayload::genPayload(), $_secret, 'HS256' )
        ];
        //*/
        /*
        $postVal = [
            'email'    => 'frank.hung@dynascan.com.tw',
            'pw'       => hash( 'sha512', 'Fgnick1111' ),
            'remember' => 0,
            'token'    => JWT::encode( JwtPayload::genPayload(), $_secret, 'HS256' )
        ];
        //*/
        
        // for login post protection checking.
        if ( !isset( $_SERVER['HTTP_REFERER'] ) || empty( $_SERVER['HTTP_REFERER'] ) ) {
            $_SERVER['HTTP_REFERER'] = parent::SERV_PROTOCOL . parent::SERV_HOST;
        }
        if ( !isset( $_SERVER['HTTP_HOST'] ) || empty( $_SERVER['HTTP_HOST'] ) ) {
            $_SERVER['HTTP_HOST'] = parent::SERV_HOST;
        }
        
        $response = $this->runApp( 'POST', '/login', NULL, NULL, $postVal );
        $this->assertEquals( 302, $response->getStatusCode(),
            'access token error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        $cookies = $response->getHeader( 'Set-Cookie' );
        $_c = array();
        foreach ( $cookies as $c ) {
            $content      = explode( ';', $c );
            $elem         = trim( $content[0] );
            $kv           = explode( '=', $elem );
            $_c[ $kv[0] ] = stripslashes( $kv[1] );
        }
        $this->assertArrayHasKey( '_l', $_c, 'access token _l missing.' );
        
        return $_c; // return access token for other function.
    }
    
    /**
     * @depends testAccessToken
     */
    public function testApiToken ( array $cookies )
    {
        $response = $this->runApp( 'GET', '/api-token', NULL, $cookies, NULL );
        $this->assertEquals( 200, $response->getStatusCode(),
            'api token error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        $x_token = $response->getHeader( 'X-Token' )[0];
        //$request = getallheaders();
        //$header = $response->getHeaders();
        //var_dump($header);
        //var_dump($request);
        //var_dump($x_token);
        return $x_token;
    }
    /**
     * 
     * @depends testApiToken
     */
    public function testExternalDisplay(string $token)
    {
        $x_token = array( 'X-Token' => $token );
        $url_query = [
            'ids' => [2331, 2332, 2335, 2261, 2271, 2268, 2334, 2354]
        ];
        $response = $this->runApi( 'GET', '/exporter/externalDisplay', $x_token, $url_query );

        //var_dump( json_decode( $response->getBody(), true ) );//回傳json的話用這個var_dump()
        $content = $response->getBody()->getContents();//是用stream回傳的話要用這個var_dump()
        $request = getallheaders();//取得request的headers
        //var_dump($request);
        //var_dump($response->getHeader('Filename'));
        //$lines = explode("\n", $content);
        //print_r($lines);
        var_dump($response->getHeader('Content-Type'));
        $this->assertEquals( 200, $response->getStatusCode(),
        'no export' );

    }
    
    
}


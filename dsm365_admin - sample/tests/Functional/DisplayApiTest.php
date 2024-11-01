<?php
/**
 * test terminal sample:
 *
 * phpunit --debug ./tests/Functional/DisplayApiTest.php
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
        //*
        $postVal = [
            'email'    => 'fgnickbanana@gmail.com',
            'pw'       => hash( 'sha512', '1qaz2wsX' ),
            'remember' => 0,
            'token'    => JWT::encode( JwtPayload::genPayload(), $_secret, 'HS256' )
        ];
        //*/
        /*
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
        var_dump($postVal['token']);
        $this->assertEquals( 302, $response->getStatusCode(),
            'access token error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        $cookies = $response->getHeader( 'Set-Cookie' );
        //var_dump($response);
        $_c = array();
        foreach ( $cookies as $c ) {
            $content      = explode( ';', $c );
            $elem         = trim( $content[0] );
            $kv           = explode( '=', $elem );
            $_c[ $kv[0] ] = stripslashes( $kv[1] );
        }
        //var_dump($_c);
        $this->assertArrayHasKey( '_l', $_c, 'access token _l missing.' );
        //var_dump($_c['_l']);
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
        //var_dump($x_token);
        return $x_token;
    }
    /**
     * Dump display management
     * 
     * @depends testApiToken
     * 
     */
    public function testDisplayerManagement_v2(string $token)
    {
        $x_token = array( 'X-Token' => $token );
        $url_query = [
            'id' => 2330,
            's' => 1723075200,
            'e' => 1723075260
        ];
        $response = $this->runApi( 'GET', '/exporter/displayManagement_v2', $x_token, $url_query );
        
        //var_dump( json_decode( $response->getBody(), true ) );
        $this->assertEquals( 200, $response->getStatusCode(),
        'no export' );

        $content = $response->getBody()->getContents();
        //var_dump($response->getHeader('Content-Type'));
        //var_dump($response->getHeader('Filename'));
        //var_dump($content);
        //$lines = explode("\n", $content);
        //print_r($lines);
        
    }

    /**
     * Dump internalOverview
     * 
     * @depends testApiToken
     * 
     */
    public function testInternalOverview(string $token)
    {
        $x_token = array( 'X-Token' => $token );
        $url_query = [
            'id' => 5053,
        ];
        $response = $this->runApi( 'GET', '/exporter/internalOverview', $x_token, $url_query );

        var_dump( json_decode( $response->getBody(), true ) );
        $this->assertEquals( 200, $response->getStatusCode(),
        'no export' );

        //$content = $response->getBody()->getContents();
        //var_dump($response->getHeader('Content-Type'));
        //var_dump($response->getHeader('Filename'));
        //var_dump($content);
        //$lines = explode("\n", $content);
        //print_r($lines);
        
    }


    /**
     * Get display lcm warning table
     *
     * @depends testApiToken
     */
    /*public function testDisplayerAlarmeList ( string $token )
    {
        $x_token = array( 'X-Token' => $token );
        // table url querys.
        $url_query = [
            'draw'   => 1,
            'page'   => 0,
            'per'    => 5,
            'order'  => [
                [
                    'column' => 0,
                    'dir'    => 'asc'
                ]
            ],
            'search' => '',
            '_'      => time()
        ];
        
        $response = $this->runApi( 'GET', '/v1/display/warning/table', $x_token, $url_query );
        $this->assertEquals( 200, $response->getStatusCode(),
            'display warning list error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        var_dump( json_decode( $response->getBody(), true ) );
    }*/
    
    
}


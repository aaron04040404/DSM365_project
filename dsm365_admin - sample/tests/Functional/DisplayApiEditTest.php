<?php
/**
 * test terminal sample:
 *
 * phpunit --debug ./tests/Functional/DisplayApiTest.php
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
        
        $postVal = [
            'email'    => 'nickfeng@dynascan.com.tw',
            'pw'       => hash( 'sha512', '1qaz2wsX' ),
            'remember' => 0,
            'token'    => JWT::encode( JwtPayload::genPayload(), $_secret, 'HS256' )
        ];
        
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
        return $x_token;
    }
    
    
    
    
    
    /**
     * POST display information
     *
     * @depends testApiToken
     
    public function testEditDisplayAddress ( string $token )
    {
        $x_token = array( 'X-Token' => $token );
        $form = [
            'id'      => 444,
            'country' => 'KR',
            'zipcode' => '110-125',
            'state'   => 'Seoul',
            'city'    => '',
            'addr1'   => '460-5 Jongno 5(o)-ga, Jongno-gu, Seoul, 南韓',
            'addr2'   => '',
            'gps'     => [37.5700033, 127.0057316],
            '_'       => time()
        ];
        
        $response = $this->runApi( 'POST', '/v1/display/address', $x_token, $form );
        $this->assertEquals( 200, $response->getStatusCode(), 'edit display address error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        var_dump( json_decode( $response->getBody(), true ) );
    }
    
    /**
     * POST display information
     *
     * @depends testApiToken
     */
    public function testEditDisplayStatus ( string $token )
    {
        $x_token = array( 'X-Token' => $token );
        $form = [
            'id'         => 444,
            'descp'      => '!@#!$#%#AA!!!!Aa%^&*^&*@#$%@!@#',
            'main_descp' => 'Hi !!****!......',
            '_'          => time()
        ];
        
        $response = $this->runApi( 'POST', '/v1/display/status', $x_token, $form );
        $this->assertEquals( 200, $response->getStatusCode(), 'edit display status & description error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        //var_dump( json_decode( $response->getBody(), true ) );
    }
    
    /**
     * POST display information
     *
     * @depends testApiToken
     
    public function testEditDisplayMarkers ( string $token )
    {
        $x_token = array( 'X-Token' => $token );
        $form = [
            'id'   => 445,
            'mark' => 0,
            '_'    => time()
        ];
        
        $response = $this->runApi( 'POST', '/v1/display/user/mark', $x_token, $form );
        $this->assertEquals( 200, $response->getStatusCode(), 'edit display status & description error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        //var_dump( json_decode( $response->getBody(), true ) );
    }
    */
    
    /**
     * Get display list by mode
     *
     * @depends testApiToken
     
    public function testCompanyDisplayerList ( string $token )
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
                    'dir'    => 'desc'
                ]
            ],
            'search' => '',
            'mode'   => 1,
            '_'      => time()
        ];
        
        // var_dump( '/v1/display/insight?' . http_build_query( $url_query ) );
        
        $response = $this->runApi( 'GET', '/v2/display/list', $x_token, $url_query );
        $this->assertEquals( 200, $response->getStatusCode(),
            'display list error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        var_dump( json_decode( $response->getBody(), true ) );
    }
    
    
    
    
    
    
    /**
     * Get display insight data for dashboard.
     *
     * @depends testApiToken
     
    public function testCompanyDisplayerInsight ( string $token )
    {
        $x_token = array( 'X-Token' => $token );
        // table url querys.
        $url_query = [
            's'     => ( new \DateTime('2021-03-03T07:47:50+00:00') )->getTimeStamp(),
            'tag'   => '',
            'panel' => [
                'condition'   => 1,
                'bri_lv'      => 1, // clouding sum, avg. of high level, avg. of low level.
                'temp_avg'    => 1,
                'runtime_avg' => 3, // including avg of days, charts
                'input_pw'    => 3, // including sum of days, avg of days, chart points.
                'tcc_alarm_sum' => 1
                
                // add more flags in the future to explore.
            ],
            '_' => time()      // for request in no cache.
        ];
        
        // var_dump( '/v1/display/insight?' . http_build_query( $url_query ) );
        
        $response = $this->runApi( 'GET', '/v1/display/insight/1', $x_token, $url_query );
        $this->assertEquals( 200, $response->getStatusCode(),
            'display map index error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        //var_dump( json_decode( $response->getBody(), true ) );
    }
    
    /**
     * Get dashboard map index.  淘汰舊的 /v1/display/map/index
     * 
     * @depends testApiToken
     *
    public function testDisplayMapIndex ( string $token )
    {
        $x_token = array( 'X-Token' => $token );
        // table url querys.
        $url_query = [
            'lat' => 0.0,
            'lng' => 0.0,
            'rad' => 207.03879096873,
            'lp'  => 1,
            '_'   => time()      // for request in no cache.
        ];
        
        $response = $this->runApi( 'GET', '/v2/display/map/index', $x_token, $url_query );
        $this->assertEquals( 200, $response->getStatusCode(),
            'display map index error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        // var_dump( '/v1/display/map/index?' . http_build_query( $url_query ) );
        // var_dump( json_decode( $response->getBody(), true ) );
    }
    
    /**
     * 
     * @depends testApiToken
     *
    public function testDisplayerOSD ( string $token )
    {
        $x_token = array( 'X-Token' => $token );
        // table url querys.
        $url_query = [
            'id' => 321,
            't'  => time(),
            '_'  => time()
        ];
        // list query params:
        // ?draw=&page=&per=&order[]=&search&author=&status=&category=&displayer=[specified displayer id]
        // &tag=[string saparated by comma]&assignee=[member id saparated by comma]
        $response = $this->runApi( 'GET', '/v1/display/osd', $x_token, $url_query );
        $this->assertEquals( 200, $response->getStatusCode(),
            'display table error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        //$table = json_decode( $response->getBody(), true );
        
        //var_dump('/v1/display/osd?' . http_build_query( $url_query ) );
        //var_dump( json_decode( $response->getBody(), true ) );
    }
    
    /**
     *
     * @depends testApiToken
     *
    public function testDisplayerUninitialized ( string $token )
    {
        $x_token = array( 'X-Token' => $token );
        // table url querys.
        $url_query = NULL;
        // list query params:
        // ?draw=&page=&per=&order[]=&search&author=&status=&category=&displayer=[specified displayer id]
        // &tag=[string saparated by comma]&assignee=[member id saparated by comma]
        $response = $this->runApi( 'GET', '/v2/display/init/loc/num', $x_token, $url_query );
        $this->assertEquals( 200, $response->getStatusCode(),
            'display location uninitialized error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        var_dump( json_decode( $response->getBody(), true ) );
        
        $response = $this->runApi( 'GET', '/v2/display/init/loc/list', $x_token, $url_query );
        $this->assertEquals( 200, $response->getStatusCode(),
            'display location uninitialized error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        var_dump( json_decode( $response->getBody(), true ) );
    }
    
    /**
     *
     * @depends testApiToken
     *
    public function testDisplayerRemoteAttrib ( string $token )
    {
        $x_token = array( 'X-Token' => $token );
        // table url querys.
        $url_query = NULL;
        
        $response = $this->runApi( 'GET', '/v1/display/remote/attr/CJO861DR4', $x_token, $url_query );
        $this->assertEquals( 200, $response->getStatusCode(),
            'display remote attribute error: (' . $response->getStatusCode() . ') ' . (string)$response->getBody() );
        
        //var_dump( json_decode( $response->getBody(), true ) );
    }
    
    */
    
    
    
    
    
}


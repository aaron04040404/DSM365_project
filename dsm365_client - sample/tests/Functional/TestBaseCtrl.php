<?php

namespace Tests\Functional;

use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Environment;
use Throwable;

/**
 * This is an example class that shows how you could set up a method that
 * runs the application. Note that it doesn't cover all use-cases and is
 * tuned to the specifics of this skeleton app, so if your needs are
 * different, you'll need to change it.
 */
class TestBaseCtrl extends \PHPUnit\Framework\TestCase       // \PHPUnit_Framework_TestCase
{
    protected const SERV_PROTOCOL   = 'http://';
    protected const SERV_HOST       = '127.0.0.1:377';
    protected const SERV_USER_AGENT = 'PHPUnit Test Agent';
    
    
    /**
     * Use middleware when running application?
     *
     * @var bool
     */
    protected $withMiddleware = true;
    
    protected function generateRandomString ( int $length = 10 ) 
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    private function genEnv ( string $requestMethod, string $requestUri, array $requestData = NULL )
    {
        if ( !isset( $_SERVER['SERVER_NAME'] ) ) {
            $_SERVER['SERVER_NAME'] = self::SERV_HOST;
        }
        if ( !isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $_SERVER['HTTP_USER_AGENT'] = self::SERV_USER_AGENT;
        }
        
        // Create a mock environment for testing with
        return Environment::mock(
            [
                'REQUEST_METHOD' => strtoupper( $requestMethod ),
                'REQUEST_URI'    => $requestUri,
                'QUERY_STRING'   => ( $requestMethod === 'GET' && !empty( $requestData ) ) ? http_build_query( $requestData ) : ''
            ]
        );
    }

    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param array|object|null $requestData the request data
     * @return ResponseInterface
     * @throws Throwable
     */
    public function runApp ( string $requestMethod, string $requestUri, 
                             array $headers = null, array $cookies = null, 
                             array $requestData = null )
    {
        // Create a mock environment for testing with
        $environment = self::genEnv( $requestMethod, $requestUri, $requestData );
        // Set up a request object based on the environment
        $request = Request::createFromEnvironment( $environment );
        // Add headers
        if ( !empty( $headers ) ) {
            foreach ( $headers as $name => $value ) {
                $request = $request->withHeader( $name, $value );
            }
        }
        // Add cookies
        if ( !empty( $cookies ) ) {
            $request = $request->withCookieParams( $cookies );
        }
        // Add request data, if it exists
        if ( $requestMethod !== 'GET' && !empty( $requestData ) ) {
            $request = $request->withParsedBody( $requestData );
        }
        
        // Set up a response object
        $response = new Response();

        // Use the application settings
        $settings = require __DIR__ . '/../../app/settings.php';

        // Instantiate the application
        $app = new App( $settings );

        // Set up dependencies
        require __DIR__ . '/../../app/dependencies.php';

        // Register middleware
        if ( $this->withMiddleware ) {
            require __DIR__ . '/../../app/middleware.php';
        }

        // Register routes
        require __DIR__ . '/../../app/routes.php';

        // Process the application
        $response = $app->process( $request, $response );

        // Return the response
        return $response;
    }

    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param array|object|null $requestData the request data
     * @return ResponseInterface
     * @throws Throwable
     */
    public function runApi ( string $requestMethod, string $requestUri, array $headers = null, 
                             array $requestData = null )
    {
        // Create a mock environment for testing with
        $environment = self::genEnv( $requestMethod, $requestUri, $requestData );
        // Set up a request object based on the environment
        $request = Request::createFromEnvironment( $environment );
        // Add header
        if ( !empty( $headers ) ) {
            foreach ( $headers as $name => $value ) {
                $request = $request->withHeader( $name, $value );
            }
        }
        // Add request data, if it exists
        if ( $requestMethod !== 'GET' && !empty( $requestData ) ) {
            $request = $request->withParsedBody( $requestData );
        }
        $request = $request->withHeader( 'Accept', 'application/json' );
        $request = $request->withHeader( 'Content-Type', 'application/json; charset=utf-8' );
        $request = $request->withHeader( 'Content-Length', $request->getContentLength() );
        
        // Set up a response object
        $response = new Response();
        
        // Use the application settings
        $settings = require __DIR__ . '/../../api/settings.php';
        
        // Instantiate the application
        $app = new App( $settings );
        
        // Set up dependencies
        require __DIR__ . '/../../api/dependencies.php';
        
        // Register middleware
        if ( $this->withMiddleware ) {
            require __DIR__ . '/../../api/middleware.php';
        }
        
        // Register routes
        require __DIR__ . '/../../api/routes.php';
        
        // Process the application
        $response = $app->process( $request, $response );
        
        // Return the response
        return $response;
    }
}

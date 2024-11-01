<?php

namespace Tests\Functional;

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Environment;

/**
 * This is an example class that shows how you could set up a method that
 * runs the application. Note that it doesn't cover all use-cases and is
 * tuned to the specifics of this skeleton app, so if your needs are
 * different, you'll need to change it.
 */
class ApiBaseTestCase extends \PHPUnit\Framework\TestCase       // \PHPUnit_Framework_TestCase
{
    /**
     * Use middleware when running application?
     *
     * @var bool
     */
    protected $withMiddleware = true;

    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param array|object|null $requestData the request data
     * @return \Slim\Http\Response
     */
    public function runApp ( string $requestMethod, string $requestUri, array $headers = null, array $requestData = null )
    {
        if ( !isset( $_SERVER['SERVER_NAME'] ) ) {
            $_SERVER['SERVER_NAME'] = '127.0.0.1';
        }
        if ( !isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test Agent';
        }
        
        // Create a mock environment for testing with
        $environment = Environment::mock(
            [
                'REQUEST_METHOD' => strtoupper( $requestMethod ),
                'REQUEST_URI'    => $requestUri,
                'QUERY_STRING'   => ( $requestMethod === 'GET' && !empty( $requestData ) ) ? http_build_query( $requestData ) : ''
            ]
        );

        // Set up a request object based on the environment
        $request = Request::createFromEnvironment( $environment );
        // Add request data, if it exists
        if ( isset( $requestData ) ) {
            $request = $request->withParsedBody( $requestData );
        }
        // Add header
        if ( isset( $headers ) ) {
            foreach ( $headers as $name => $value ) {
                $request = $request->withHeader( $name, $value );
            }
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

<?php
/**
 * This class works for extending to application webpage controller.
 *
 * @author Nick Feng
 * @since 1.0
 */
namespace Gn\Ctl;

use ErrorException;
use Gn\Interfaces\BaseRespCodesInterface;

// from Slim
use Slim\Container;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

/**
 * Api basic controller functions for extending.
 *
 * @author nick
 *
 */
abstract class AppBasicCtl extends BasicCtl implements BaseRespCodesInterface
{
    /**
     * variable name for *.phtml
     * 
     * @var string
     */
    const ARGS_OUTPUT_NAME = 'template_var';
    
    /**
     * file extension name for renderer.
     * 
     * @var string
     */
    const TEMPLATE_EXTENSION = '.phtml';
    
    /**
     * Get renderer object from Slim.
     * @var PhpRenderer
     */
    protected $renderer = NULL;

    /**
     * Constructor.
     *
     * @param Container $container
     * @throws ErrorException
     */
    public function __construct( Container $container )
    {
        if ( !isset( $container['renderer'] ) ) {
            throw new ErrorException( 'renderer is not existed!' );
        }
        parent::__construct( $container );
        $this->renderer = $container->renderer;
    }

    /**
     * Render a template with customer arguments.
     *
     * @2023-06-05 Nick 進行簡化的新版本
     *
     * @param Response $resp
     * @param array $args
     * @param array|boolean $user user data.
     * @param string|null $template
     * @return Response|bool
     * @author Nick Feng
     */
    protected function appRenderer ( Response $resp, array $args, $user, string $template = NULL )
    {
        if ( is_array( $user ) && !empty( $user ) ) {
            $args[ self::ARGS_OUTPUT_NAME ] = [
                'app'  => $this->settings[ 'app' ],
                'user' => $user,
                // admin & client web are in difference
                'firestore' => [
                    'collections' => [
                        'notification' => $this->settings['firebase']['collections']['tcc_notification_main']['name'],
                        'screenshot'   => $this->settings['firebase']['collections']['screenshot']['name'],
                        'mcb_raw'      => $this->settings['firebase']['collections']['mcb_raw']['name'],
                    ]
                ]
            ];
            if ( empty( $template ) ) {
                $template = '404';
            }
            return $this->renderer->render( $resp, ( $template . self::TEMPLATE_EXTENSION ), $args );
        }
        return false;
    }
}
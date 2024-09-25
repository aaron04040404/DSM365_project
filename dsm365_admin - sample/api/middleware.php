<?php
/**
 * Application middleware of Slim
 *
 * @author Nick Feng
 * @since 1.0
 */

/**
 * Filter the request without JWT Authentication. Return error message for not accessed request.
 *
 * JSON Web Tokens(JWT) are essentially passwords. You should treat them as such, and you should always use HTTPS.
 * If the middleware detects insecure usage over HTTP it will throw a RuntimeException.
 * This rule is relaxed for requests on local host.
 * To allow insecure usage you must enable it manually by setting secure to false.
 */
$app->add( new Slim\Middleware\JwtAuthentication([
    'rules' => [
        new Slim\Middleware\JwtAuthentication\RequestPathRule([
            'path' => '/',
            //'passthrough' => []
        ]),
        new Slim\Middleware\JwtAuthentication\RequestMethodRule([
            'passthrough' => ['OPTIONS']
        ])
    ],
    'header' => $container->get('settings')['oauth']['header'],
    'regexp' => '/(.*)/',
    'secure' => require __DIR__ . '/../security/ssl/ssl_secur.php', // default is false,  // 正式機運作的時候，最好轉成 true, 並且搭配 https
    //'relaxed'  => [ 'localhost', '127.0.0.1', '127.0.0.1:377' ],  // 您可以列出多個開發服務器以放鬆安全性。 通過以下設置，localhost 和 dev.example.com 都允許傳入未加密(https -> http)的請求。
    'secret' => $container->get('settings')['oauth']['api_secret'],
    'callback' => function ($request, $response, $arguments) use ( $container ) {
        $container->jwt = $arguments['decoded']; // change StdClass object to decoded jwt contents Object if success.
        // dispatch jwt to different way
        // IMPORTANT: every api token must have channel name in jwt data parameter. if no, please fix it.
        if ( isset( $container->jwt->data->channel ) ) {
            switch ( $container->jwt->data->channel ) {
                case Gn\Lib\JwtPayload::PAYLOAD_AUTH_CHANNEL_USR: // get the member data via api token
                    $reg = new Gn\Fun\RegisterFun( $container->get('settings')['db']['main'] );
                    $container['usrAuthData'] = $reg->isApiAuth( $container->jwt->jti );
                    if ( $container['usrAuthData'] === false ) {
                        $this->setMessage('access denied');
                        return false; // it will call error method with status 401
                    }
                    break;
                case Gn\Lib\JwtPayload::PAYLOAD_AUTH_CHANNEL_OUTSOURCE: // IMPORTANT: for outsourcing controlling data
                    // 這邊必須 payload 之中包含著使用的 outsourcing company name, access uid, channel, API URL path。
                    // 如果沒有，則 return false。401 un-authority
                    // 反之，才可以進入真正的 API URL routing 的部份
                    // payload: {
                    //     iat:
                    //     jti:	(string) a random code to identify the outsourcing --> 應該會是一個 PHP 檔案，裡面如同 config 界定，哪個 outsourcing 只允許去使用哪些 API
                    //     iss:	(string) outsourcing company name.
                    //     nbf:
                    //     exp:
                    //     data: {
                    //         channel:	(string) “outsource” (please use it)
                    //     }
                    // }
                    // 因為來到這邊的使用者，他們都是走 outsourcing 的 token 格式過來的。
                    // 所以必須檢查相關 token 之下 data 欄位之中的所帶過來的資料是否符合需求
                    $outsource_access_map = $container->get('settings')['oauth']['outsource_access'];
                    if ( isset( $container->jwt->iss ) && isset( $outsource_access_map[ $container->jwt->iss ] ) ) {
                        if ( isset( $container->jwt->jti ) && $outsource_access_map[ $container->jwt->iss ]['uid'] === $container->jwt->jti ) {
                            return true;
                        }
                    }
                    $this->setMessage('access denied');
                    return false;
                default:
                    $this->setMessage('channel not existed');
                    return false;   // 401 response
            }
        } else {
            $this->setMessage('channel not existed');
            //throw new \ErrorException( 'api token without channel' );
            return false;   // 401 response
        }
        return true;
    },
    'error' => function ($request, $response, $arguments) use ( $container ) {
        $data = [
            'status' => 'authorization deny',
            'message' => $arguments['message']
        ];
        return $response->withStatus( Slim\Http\StatusCode::HTTP_UNAUTHORIZED )
                        ->withHeader('Content-Type', 'application/json')
                        ->write( json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
    }
]));

// @2024-03-25: 經過測試，這是因為同源策略（Same-Origin Policy）通常只適用於在網絡上通過 HTTP 或 HTTPS 協議加載的資源。
//              對於本地文件系統中的文件，瀏覽器不會強制執行同源策略，因此可能會省略 Origin 標頭。
//              所以這個功能只需要在正式機上營運的時候啟動，demo的時候，由於 Vue.js 的 proxy 問題，所以可能會部份被阻擋。
//              因此demo的時候可以先暫時註解不使用此功能
$app->add( new Gn\AddSlimMiddleware\CorsMiddleware( $container->get('settings')['app']['dns']['self'] ) );

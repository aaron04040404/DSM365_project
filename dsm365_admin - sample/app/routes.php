<?php
/**
 * Application route of Slim
 *
 * @author Nick Feng
 * @since 1.0
 */

/**
 * 絕對不可以使用 /open-api/[*] 的 URL 路徑。
 * 因為這個路徑，在 loading balance 的區段，就會轉給另一個，由外包公司(Chroma)開發的 VM group 執行群體去運作了。
 * 因此，不會進來到 TCC 原本的 PHP 設計之中
 */

// for login
$app->group( '/login', function () { // [?code=[error code in integer]]
    $this->get( '',          Gn\AppRegister::class . ':GET_Login' );
    $this->post( '',         Gn\AppRegister::class . ':POST_Login' );
    $this->group( '/confirm', function () {
        $this->get( '',         Gn\AppRegister::class . ':GET_OneTimePassword' );       // OTP confirming
        $this->post( '',        Gn\AppRegister::class . ':POST_OneTimePassword' );
        $this->post( '/resent', Gn\AppRegister::class . ':POST_ResentOneTimePassword' );   // will redirect to the same page if it is success
    });

    // ===================== edge device auto register ===================== [Start]
    $this->get( '/auto',  Gn\AppEdge::class . ':GET_AutoLoginEdge' );
    $this->post( '/auto', Gn\AppEdge::class . ':POST_AutoLoginEdge' );
    // ===================== edge device auto register ===================== [End]
});
// for forget-password
$app->group( '/password', function () {
    $this->group( '/forget', function () {   // [?code=[error code in integer]]
        $this->get( '',  Gn\AppRegister::class . ':GET_ForgetPassword' );
        $this->post( '', Gn\AppRegister::class . ':POST_ForgetPassword' );
    });
    $this->group( '/new', function () {
        $this->get( '',  Gn\AppRegister::class . ':GET_NewPassword' ); // [?v={token}]
        $this->post( '', Gn\AppRegister::class . ':POST_NewPassword' );
    });
});
// use access token to get a new api token for client side
$app->get( '/api-token', Gn\AppRegister::class . ':GET_GetApiToken' );    // use ajax

// use access token to get a new token for Firebase Oauth
$app->get( '/firebase/token', Gn\AppRegister::class . ':GET_GetGoogleFirebaseOauth' );    // use ajax

// for logout
$app->get( '/logout', Gn\AppRegister::class . ':GET_Logout' );

// for api test page in development mode. It works only for system creator.
$app->get( '/api-test', Gn\AppTest::class );

/**
 * for vue.js.
 * NOTE: it must be after all routes.
 */
$app->get( '/[{path:.*}]', Gn\AppVue::class );
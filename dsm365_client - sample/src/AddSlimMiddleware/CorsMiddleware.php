<?php
// control HTTP CORS for adding to middleware.php

namespace Gn\AddSlimMiddleware;

use Slim\Http\Request;
use Slim\Http\Response;

class CorsMiddleware
{
    protected $allowedDomain;

    public function __construct($allowedDomain)
    {
        $this->allowedDomain = $allowedDomain;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $origin = $request->getHeaderLine('Origin');
        if ( !empty( $origin ) ) {
            if ($this->isValidDomain($origin)) {
                // 如果來源域是有效的，則允許請求通過
                return $next($request, $response);
            } else {
                // 如果來源域不是有效的，返回 403 Forbidden 狀態碼
                return $response->withStatus(403)->withJson(['error' => 'Invalid domain']);
            }
        } else {
            // $origin = '' empty string時，這是因為同源策略（Same-Origin Policy）通常只適用於在網絡上通過 HTTP 或 HTTPS 協議加載的資源。
            // 對於本地文件系統中的文件，瀏覽器不會強制執行同源策略，因此可能會省略 Origin 標頭。
            // method => POST 會嚴格檢查 Origin 字串。所以，就算是同源也不會是空字串。因此，demo 測試的時候因為開放給 Vue.js
            // 的假 proxy 也會無法突破這個檢視。故在 demo 的時候，還是要關閉這個檢查的機制
            return $next($request, $response);
        }
    }

    protected function isValidDomain( $origin ): bool
    {
        // 檢查來源域是否在允許的域清單中
        return $origin === $this->allowedDomain;
    }
}
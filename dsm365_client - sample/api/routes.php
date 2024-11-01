<?php
/**
 * Application route of Slim 3
 * 
 * @author Nick Feng
 * @since 1.0
 */
$app->group('/v1', function () use ($app) {
    // ---------------- system member management (system framework) ---------------- [START]
    $app->group('/account', function () {
        $this->get('/profile',        Gn\MemberCtrl::class . ':get_AccountProfile');
        $this->post('/profile/edit',  Gn\MemberCtrl::class . ':post_EditAccountProfile');
        $this->post('/change/pw',     Gn\MemberCtrl::class . ':post_AccountChangePassword');
        $this->post('/pw/protection', Gn\MemberCtrl::class . ':POST_PwProtection');  // response a token like a CSRF in 1 minute waiting after password identified.

        $this->group('/ui/property', function () {
            $this->get('/{name:[-0-9a-zA-Z_]+}',  Gn\MemberUiCtrl::class . ':GET_MemberUiProperties');
            $this->post('', Gn\MemberUiCtrl::class . ':POST_MemberUiProperties');
        });
    });
    
    $app->group('/role', function () {
        $this->post('/list',       Gn\MemberCtrl::class . ':post_RoleList');
        $this->post('/generate',   Gn\MemberCtrl::class . ':post_CreateRole');
        $this->post('/delete',     Gn\MemberCtrl::class . ':post_RoleRecycle');
        $this->post('/properties', Gn\MemberCtrl::class . ':post_GetRolePermission');
        $this->post('/edit',       Gn\MemberCtrl::class . ':post_SetRolePermission');
    });
    
    $app->group('/member', function () {
        $this->post('/invite',     Gn\MemberCtrl::class . ':post_InviteMember'); // like account register.
        $this->post('/re-invite',  Gn\MemberCtrl::class . ':post_ReinviteMember'); // like account register.
        $this->post('/list',       Gn\MemberCtrl::class . ':post_GetMemberList');
        $this->post('/view',       Gn\MemberCtrl::class . ':post_GetMemberData');
        $this->post('/edit/state', Gn\MemberCtrl::class . ':post_SetMemberStatus');
    });
    
    
    // params for address: ?country=&zip=&state=&city=&addr=
    $app->get('/asset/{type:[-0-9a-zA-Z_]+}/{filename}',  Gn\AssetCtrl::class . ':get_sysAsset');
    
    // ---------------- system member management (system framework) ---------------- [END]
    
    
    // --------------------------------- Customized API for DSM365 ------------------------------------------
    
    $app->group('/stage', function () use ($app) {
        $app->get('/list',      Gn\StageCtrl::class . ':GET_StageList');       // ?s=
        // $app->post('/list',     Gn\StageCtrl::class . ':POST_StageList');
        
        // IMPORTANT: this api is merged to /member/list with stage array in request parameters
        //$app->get('/users',     Gn\StageCtrl::class . ':GET_StageMembers');    // ?id=
        
        $app->get('/workspace', Gn\StageCtrl::class . ':GET_MemberStages');    // ?id=
        $app->map(['POST', 'DELETE'], '/user', Gn\StageCtrl::class . ':POST_Member2Stages');
        $app->post('/mcb', Gn\StageCtrl::class . ':POST_SetDisplayerStage');
    });
    
    // for web app notification
    $app->group('/notification', function () use ($app) {
        $app->get('/count',                  Gn\NotificationCtrl::class . ':GET_NotificationCount');
        $app->get('/list/{start_on:[0-9]+}', Gn\NotificationCtrl::class . ':GET_NotificationList'); // ?per=&c=
        $app->post('/set/read',              Gn\NotificationCtrl::class . ':POST_NotificationIsRead');
        $app->get('/settings',               Gn\NotificationCtrl::class . ':GET_NotificationSettings');
        $app->post('/settings',              Gn\NotificationCtrl::class . ':POST_NotificationSettings');
        
        // deprecated
        $app->get('/analytics',              Gn\NotificationCtrl::class . ':GET_NotifAnalytics');
    });
    
    /*
    // for wev app internal message
    $app->group('/message', function () use ($app) {
        $app->get('/count',                  Gn\MessageCtrl::class . ':GET_MessageCount');
        $app->get('/list/{start_on:[0-9]+}', Gn\MessageCtrl::class . ':GET_MessageList'); // ?per=&t=&r=
        $app->post('/set/read',              Gn\MessageCtrl::class . ':POST_MessageIsRead');
    });
    */
    
    // for contact card
    $app->group('/contact', function () use ($app) {
        $app->get('/list',     Gn\ContactCardCtrl::class . ':GET_ContactCardList');        // ?draw=&models[]=&page=&per=&order[]=&search
        $app->post('/add',     Gn\ContactCardCtrl::class . ':POST_AddViewContactCard');
        $app->post('/edit',    Gn\ContactCardCtrl::class . ':POST_EditViewContactCard');
        $app->post('/existed', Gn\ContactCardCtrl::class . ':POST_ContactCardExisted');    // Because of privacy, you have to use POST method.
        $app->get('/data/{uuid:[-0-9a-fA-F]{36}+}',    Gn\ContactCardCtrl::class . ':GET_ContactCard');
        $app->delete('/data/{uuid:[-0-9a-fA-F]{36}+}', Gn\ContactCardCtrl::class . ':DEL_ContactCard');
        
    });
    
    $app->group('/display', function () use ($app) {
        // it can group members only to see the displays in the specified network.
        $app->group('/network', function() {
            $this->get('',       Gn\DsServNetworkCtrl::class . ':GET_DisplayerNetworkTab');   // ?draw=&page=&per=&order[]=&search
            $this->post('',      Gn\DsServNetworkCtrl::class . ':POST_DisplayerNetwork');
            $this->post('/edit', Gn\DsServNetworkCtrl::class . ':POST_EditDisplayerNetwork');
            $this->group('/list', function () {
                $this->get('',  Gn\DsServNetworkCtrl::class . ':GET_SelfNetworkTab');    // ?draw=&page=&per=&order[]=&search
                // 反查 user 被歸類給哪些 network，以及 display 被歸類給哪些 network
                $this->get('/user/{id:[-0-9]+}', Gn\DsServNetworkCtrl::class . ':GET_User2NetworkTab');  // ?draw=&page=&per=&order[]=&search
                $this->get('/mcb/{id:[-0-9]+}',  Gn\DsServNetworkCtrl::class . ':GET_Displayer2NetworkTab'); // ?id=&draw=&page=&per=&order[]=&search
            });
            $this->get('/{uuid:[-0-9a-fA-F]{36}+}',        Gn\DsServNetworkCtrl::class . ':GET_DisplayerNetwork');
            $this->get('/{uuid:[-0-9a-fA-F]{36}+}/user',   Gn\DsServNetworkCtrl::class . ':GET_DisplayNetworkUserTab');  // ?draw=&page=&per=&order[]=&search
            $this->get('/{uuid:[-0-9a-fA-F]{36}+}/device', Gn\DsServNetworkCtrl::class . ':GET_DisplayerNetworkDisplayerTab'); // ?draw=&page=&per=&order[]=&search
            $this->delete('/{uuid:[-0-9a-fA-F]{36}+}',   Gn\DsServNetworkCtrl::class . ':DEL_DisplayerNetwork');
            $this->map(['POST', 'DELETE'], '/user', Gn\DsServNetworkCtrl::class . ':POST_User2DisplayNetwork');
            $this->map(['POST', 'DELETE'], '/mcb',  Gn\DsServNetworkCtrl::class . ':POST_Mcb2DisplayNetwork');
        });
        
        $app->get('/list/pro', Gn\DisplayerCtrl::class . ':GET_DisplayerRemoteTable'); // LCM list for remote & update
        
        $app->get('/insight[/{id:[0-9]+}]', Gn\DisplayInsightCtrl::class . ':GET_DisplayerInsight');  //?panel=[]&q=&tag=&s=[unix timestamp]
        
        // NOTE: 這邊勢必需要遮蔽不少不可以給客戶看到的資料，請以後要注意
        $app->get('/mcb/raw', Gn\BackSideCtrl::class . ':GET_McbRawData'); // ?id=&start=[unix timestamp]&end=[unix timestamp]&cmd[]=&mode=
        
        $app->get('/profile',   Gn\DisplayerAdminCtrl::class . ':GET_DisplayerProfile');     // ?id=[display id] or ?sn=[series number string]
        // @deprecated $app->post('/profile',    Gn\DisplayerCtrl::class . ':POST_DisplayerProfile');
        $app->post('/address',   Gn\DisplayerAdminCtrl::class . ':POST_DisplayerAddress');
        $app->post('/status',    Gn\DisplayerAdminCtrl::class . ':POST_DisplayerStatus');
        $app->post('/user/mark', Gn\DisplayerAdminCtrl::class . ':POST_DisplayerUserCustomizedMark');
        
        $app->get('/realtime',    Gn\DisplayerAdminCtrl::class . ':GET_DisplayRealtime');    // ?id=[display id]
        $app->get('/osd',         Gn\BackSideCtrl::class . ':GET_DisplayOSD');         // ?id=[display id]&t=[unix timestamp]
        
        $app->group('/warning', function () {
            $this->get('/table',     Gn\DisplayerCtrl::class . ':GET_DisplayerDiagnose');
            $this->get('/attribute', Gn\Mem2McbAlarmCtrl::class . ':GET_DisplayerWarnAttribute');
            $this->group('/block', function () {
                $this->get('',                          Gn\Mem2McbAlarmCtrl::class . ':GET_ListBannedMcbNotification');
                $this->delete('/cleanup',               Gn\Mem2McbAlarmCtrl::class . ':DEL_BannedMcbNotification_All');
                $this->map(['POST', 'DELETE'], '',      Gn\Mem2McbAlarmCtrl::class . ':POST_BannedMcbNotification_One');
                $this->map(['POST', 'DELETE'], '/type', Gn\Mem2McbAlarmCtrl::class . ':POST_BannedMcbNotification_Type');
            });
            $this->group('/mail', function () {
                $this->get('',                          Gn\Mem2McbAlarmCtrl::class . ':GET_ListDeniedMcbEmailing');
            });
        });
        
        $app->group('/remote', function () use ($app) {
            $app->get('/attr/{model_name:[-0-9a-zA-Z_]+}', Gn\BackSideCtrl::class . ':GET_DisplayerRemoteAttribute');
            $app->post('/cmd' , Gn\BackSideCtrl::class . ':POST_DisplayerRemoteCommand');

            $this->get('/screenshot/{sn:[-0-9a-zA-Z]+}/{filename}', Gn\McbAdminCtrl::class . ':GET_AskMcbScreenshotImage');
            $this->post('/screenshot', Gn\McbAdminCtrl::class . ':POST_AskMcbScreenshotImage');
        });
        
            // for tag
        $app->get('/tag',  Gn\DisplayerAdminCtrl::class . ':GET_DisplayTag');    // ?id=[]
        $app->post('/tag', Gn\DisplayerAdminCtrl::class . ':POST_DisplayTag');
        
        // for contact card
        $app->get('/contact/{display_id:[0-9]+}', Gn\ContactCardCtrl::class . ':GET_ContactCard2Display');
        $app->map(['POST', 'DELETE'], '/contact', Gn\ContactCardCtrl::class . ':POST_ContactCard2Display');
    });


    /**
     * 專門給外包商使用的通道：
     * 這邊的前提，如果有非要原本TCC完全進行掌控，或是 device remote與 Ellery 的 edge device 做界面溝通的部份，通通都需要收錄再這底下
     */
    $app->group('/outsource' , function () use ($app) {
        $app->group('/open-api' , function () use ($app) {
            $app->group('/remote' , function () use ($app) {
                $app->post('/cmd', Gn\OutSourceChromaCtrl::class . ':POST_RemoteEdgeDevice');
            });
        });
    });
});


// API version 1:
$app->group('/v2', function () use ($app) {
    $app->get('/asset/{type:[-0-9a-zA-Z_]+}/{filename}',  Gn\AssetCtrl::class . ':GET_SysAsset_V2');

    $app->group('/display', function () use ($app) {
        $app->get('/list',         Gn\DisplayerCtrl::class . ':GET_DisplayerList_v2');         // ?draw=&mode=&models=[]&page=&per=&order[]=&search
        $app->get('/list/model',   Gn\DisplayerCtrl::class . ':GET_DisplayerModelList_v2');
        $app->get('/map/index',    Gn\DisplayerCtrl::class . ':GET_DisplayerGpsIndices_v2' );  // ?lat=&lng=&rad=&q=&lp=
        $app->get('/init/loc/num', Gn\DisplayerCtrl::class . ':GET_DisplayerNoLocation_v2');
        $app->post('/tag', Gn\DisplayerAdminCtrl::class . ':POST_DisplayTag_v2');
        $app->group('/network', function() {
            $this->get('/{uuid:[-0-9a-fA-F]{36}+}/device', Gn\DsServNetworkCtrl::class . ':GET_NetworkDisplayerTab'); // ?draw=&page=&per=&order[]=&search
        });
    });
});

$app->group('/exporter', function() use ($app){
    $app->get('/displayManagement', Gn\ExporterCtrl::class . ':DisplayManagement');
    $app->get('/internalOverview', Gn\ExporterCtrl::class . ':InternalOverview');
    $app->get('/externalDisplay', Gn\ExporterCtrl::class . ':ExternalDisplay');
    $app->get('/externalDisplay_test', Gn\Sql\SqlExporter::class . 'sqlexternalDisplay');
});


/////////////////////// Product Auto Register /////////////////////// [START]
$app->group('/auto-cloud', function () use ($app) {
    $app->post('/product/register',       Gn\EdgeCtrl::class . ':POST_productRegister');
    $app->post('/product/register/reset', Gn\EdgeCtrl::class . ':POST_productReset');

    // NOTE: 在未來就要位每個api加入版本號


});
    /////////////////////// Product Auto Register /////////////////////// [END]
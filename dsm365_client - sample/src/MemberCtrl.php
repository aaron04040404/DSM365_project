<?php
/**
 * Copyright Nick Feng 2017
 * This PHP file is a controller for the routes.php file.
 * It follows the Slim framework structure, and you will link to a part of custom module.
 *
 * @author  Nick Feng
 * @since 1.0
 */
namespace Gn;

use ErrorException;
use Exception;
use Gn\Lib\JwtPayload;
use Gn\Lib\DsParamProc;
use Gn\Ctl\ApiBasicCtl;
use Gn\Fun\RegisterFun;
use Gn\Interfaces\MailerInterface;

// from Slim
use Firebase\JWT\JWT;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * member controller of system
 *
 * @author  Nick Feng
 * @since 1.0
 */
class MemberCtrl extends ApiBasicCtl implements MailerInterface
{
    /**
     * member management class
     *
     * @var RegisterFun
     */
    protected $register = NULL;

    /**
     * Constructor.
     *
     * @param Container $container
     * @throws ErrorException
     */
    public function __construct( Container $container )
    {
        parent::__construct( $container );
        $this->register = new RegisterFun ( $this->settings['db']['main'] );
    }
    
    /**
     * Invite a member via system. B2B By this way to create a new member.
     * 
     * @author Nick
     * @param array $payload
     * @param string $email
     * @return bool If success, return URL string; Otherwise, false in boolean.
     */
    private function mailMemberInvitation ( array $payload, string $email ): bool
    {
        if ( empty( $email ) || empty( $payload ) ) {
            return false;
        }
        // generate a url for user confirming.
        $token = rawurlencode( JWT::encode( $payload, $this->settings['oauth']['pw_reset_secret'], parent::JWT_ALGORITHM ) );
        $resetURL = $this->settings['app']['dns']['self'] . $this->settings['app']['url']['reset_pw'] . $token;
        // send mail to member.
        $mailer = $this->container->get('mailer');
        $is_sent = $mailer->sendMail( 
            [ $email ], 
            static::MAIL_TITLE_INVITATION, 
            static::MAIL_HTMLBODY_INVITATION, 
            [ 'url' => $resetURL ] 
        );
        if ( !$is_sent ) {
            // NOTE: Just record reset URL into log for testing. If released, PLEASE REMOVE it in log recording.
            $this->container->logger->emergency( $email . ' invitation mail error: ' . $resetURL );
            return false;
        }
        return true;
    }
    
    /**
     * Inviting a new member.
     * 
     * @author Nick
     * @method POST
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function post_InviteMember ( Request $request, Response $response, array $args ): Response
    {
        $email = $request->getParsedBodyParam( 'email' );
        $type  = $request->getParsedBodyParam( 'type' );
        $role  = $request->getParsedBodyParam( 'role' );
        if ( is_string( $email ) && !empty( $email )
            && is_int( $type ) && $type > 0 
            && is_int( $role ) && $role > 0 ) 
        {
            // you cannot invite a member level is higher than/equal to the inviter.
            if ( $type > $this->memData['type'] && $this->memData['permission']['member'] >= 2 ) {
                // $member_perms = $this->memData['permission'];
                // IMPORTANT: check the role id is legal and from the same company.
                //            database must have the permissions of role, if not, it means data error!!
                $perms = $this->register->getRolePerms( $this->memData['company'], $role ); 
                if ( $perms !== false ) {
                    // IMPORTANT: role的創建不能高於super admin( company/group owner ), 也不能高過本次賦予權限的編輯者
                    //            暫時這邊不檢查，因為可能只是代理邀請。但是實質創造role的人並不是他人。
                    $data = $this->register->InitMember( $email, $this->memData['company'], $type, $role );
                    if ( is_array( $data ) ) {
                        self::resp_decoder( static::PROC_OK, '', [ 'id' => $data['mem_id'] ] );
                        // set a log
                        $this->container->logger->notice( $email . ' invited by member ' . $this->memData['id'] );
                        // send mail to member.
                        self::mailMemberInvitation( $data['payload'], $email );
                    } else {
                        self::resp_decoder( $data );
                        // set a log
                        $this->container->logger->warn(
                            $email . ' invited error by member(' . $this->memData['id'] . '): ' . $this->respJson['data'] );
                    }
                } else {
                    self::resp_decoder( static::PROC_SQL_ERROR );
                    // set a log
                    $this->container->logger->emergency(
                        'Member invitation database role permissions finding error!' );
                }
            } else {
                self::resp_decoder( static::PROC_NO_ACCESS );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }
    
    /**
     * Invite an existed member again.
     *
     * @author Nick
     * @method POST
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function post_ReinviteMember ( Request $request, Response $response, array $args ): Response
    {
        $email = $request->getParsedBodyParam( 'email' );
        if ( is_string( $email ) && !empty( $email ) ) {
            if ( $this->memData['permission']['member'] >= 2 ) {
                // you cannot to re-modify the original invitation, the only one you can do just reset password and refresh token.
                $payload = $this->register->resetPassword( $email, 3 );
                if ( is_array( $payload ) ) {
                    self::resp_decoder( static::PROC_OK );
                    // set a log
                    $this->container->logger->notice( $email . ' has invited by member ' . $this->memData['id'] );
                    // send mail to member.
                    self::mailMemberInvitation( $payload, $email );
                } else {
                    self::resp_decoder( $payload );
                    // set a log
                    $this->container->logger->warn(
                        $email . ' reinvited fail by member(' . $this->memData['id'] . '): ' . $this->respJson['data'] );
                }
            } else {
                self::resp_decoder( static::PROC_NO_ACCESS );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }

    /**
     * Edit SELF profile( not others' profile ).
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @author Nick
     * @method GET
     */
    public function get_AccountProfile (Request $request, Response $response, array $args): Response
    {
        // TO Sean: 這邊 network & stage 之所以不完整輸出，是因為在看 profile 的時候以及檢查每個 member 的資料之際，
        //          network & stage 很有可能過多到需要翻頁的狀態，因此鼓勵都呼叫專門的 api 去取得專屬的詳細資訊
        $data = $this->register->getMemberData( $this->memData['company'], $this->memData['id'], true );
        if ( is_array( $data ) ) {
            self::resp_decoder( static::PROC_OK, '', $data );
        } else {
            self::resp_decoder( static::PROC_FAIL );
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }

    /**
     * Change self information of profile by self.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws ErrorException
     * @author Nick
     * @method POST
     */
    public function post_EditAccountProfile (Request $request, Response $response, array $args): Response
    {
        $name = $request->getParsedBodyParam( 'name' );
        $msg  = $request->getParsedBodyParam( 'msg' );
        $otp  = $request->getParsedBodyParam( 'otp', false );   // 之後改成新版的界面的時候，這個 false 要移除
        if ( is_string( $name ) && is_string( $msg ) && is_bool( $otp ) ) {
            $out = $this->register->editMemberProfile( $this->memData['company'], $this->memData['id'], $name, $msg, $otp );
            self::resp_decoder( $out );
            // set a log
            if ( $this->respJson['status'] === 1 ) {
                $this->container->logger->notice( 'account profile changed: member ' . $this->memData['id'] );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }
    
    /**
     * Change the self password.
     *
     * @author Nick
     * @method POST
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function post_AccountChangePassword (Request $request, Response $response, array $args): Response
    {
        $pw    = $request->getParsedBodyParam( 'pw' );
        $ex_pw = $request->getParsedBodyParam( 'ex_pw' );
        if ( is_string( $pw ) && is_string( $ex_pw ) && !empty( $pw ) && !empty( $ex_pw ) ) {
            if ( $email = $this->register->modifyPw( $this->memData['id'], $pw, $ex_pw ) ) {
                self::resp_decoder( static::PROC_OK );  // complete in perfect
                // set a log
                $this->container->logger->notice( $email . ' password has been changed' );
                // send mail to member for confirming.
                $mailer = $this->container->get('mailer');
                $sent_mail = $mailer->sendMail(
                    array( $email ),
                    static::MAIL_TILTE_PW_CHANGED,
                    static::MAIL_HTMLBODY_PW_CHANGED 
                );
                if ( !$sent_mail ) {
                    // set a log
                    $this->container->logger->emergency( $email . ' password-changed mail error.' );
                }
            } else {
                self::resp_decoder( static::PROC_FAIL );
                // set a log
                $this->container->logger->emergency( 'member password change fail(member #' . $this->memData['id'] . ')' );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }

    /**
     * Via the API with user account password, it will return a token like a CSRF to set up the remote command protection code.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws Exception
     * @author Nick
     * @method POST
     */
    public function POST_PwProtection( Request $request, Response $response, array $args ): Response
    {
        $pw = $request->getParsedBodyParam( 'pw' );
        if ( is_string( $pw ) && strlen( $pw ) > 0 ) {
            $code = $this->register->confirmPassword( $this->memData['id'], $pw );
            if ( $code === static::PROC_OK ) {
                // generate a token for remote command to bring back, system will check the token.
                $token = JWT::encode( 
                    JwtPayload::genPayload(), 
                    $this->settings['oauth']['register_secret'],
                    parent::JWT_ALGORITHM );
                // output a token string
                self::resp_decoder( static::PROC_OK, '', $token );
            } else {
                self::resp_decoder( $code );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }
    
    /**
     * Create a new member role ( it will check name duplication ).
     * @2024-04-15 Sean 因為 James 不願意修正 UI 設計的問題，所以需要我這邊的 ID 進行回傳，因此改回傳陣列
     *
     * @author Nick
     * @method POST
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function post_CreateRole (Request $request, Response $response, array $args): Response
    {
        $name  = $request->getParsedBodyParam( 'name' );
        $perms = $request->getParsedBodyParam( 'perms' );
        if ( is_string( $name ) && is_array( $perms ) && !empty( $name ) && !empty( $perms ) ) {
            if ( $this->memData['permission']['role'] >= 2 ) {
                // IMPORTANT: check keys(permission names) are equal to legal role properties' columns 
                if ( !array_diff_key( $perms, $this->memData['permission'] ) 
                    && !array_diff_key( $this->memData['permission'], $perms ) ) 
                {
                    // IMPORTANT: role的創建不能高於super admin( company/group owner ), 也不能高過本次賦予權限的編輯者
                    foreach ( $perms as $k => $v ) {
                        if ( !is_int( $v ) || $v > $this->memData['permission'][ $k ] || $v < 0 ) {
                            self::resp_decoder( static::PROC_NO_ACCESS );
                            return $response->withJson( $this->respJson, $this->respCode );
                        }
                    }
                    
                    $code = $this->register->createRole( $this->memData['company'], $name, $perms );
                    if ( is_array( $code ) ) {
                        self::resp_decoder( static::PROC_OK, '', $code );

                        // set a log
                        $this->container->logger->notice(
                            'member ' . $this->memData['id'] . ' create a new role: ' . $name . ', ' .
                            parent::jsonLogStr( $perms ) );

                    } else {
                        self::resp_decoder( $code );

                        // set a log
                        $this->container->logger->error(
                            'member ' . $this->memData['id'] . ' new role error: ' . $name . ', ' .
                            parent::jsonLogStr( $perms ) );
                    }
                }
            } else {
                self::resp_decoder( static::PROC_NO_ACCESS );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }

    /**
     * Remove and recycle role id.
     *
     * NOTE: If there is any role in using, you cannot remove it.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws ErrorException
     * @author Nick
     * @method POST
     */
    public function post_RoleRecycle ( Request $request, Response $response, array $args ): Response
    {
        $role_id = $request->getParsedBodyParam( 'id' );
        if ( is_int( $role_id ) && $role_id > 0 ) {
            if ( $this->memData['permission']['role'] >= 3 ) {
                $code = $this->register->recycleRole( $this->memData['company'], $role_id );
                self::resp_decoder( $code );
                // set a log
                if ( $this->respJson['status'] === 1 ) {
                    $this->container->logger->notice( 'member ' . $this->memData['id'] . ' recycles a role ' . $role_id );
                }
            } else {
                self::resp_decoder( static::PROC_NO_ACCESS );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }

    /**
     * Get member role list.
     *
     * @author Nick
     * @method POST
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function post_RoleList (Request $request, Response $response, array $args): Response
    {
        $vars = $request->getParsedBody();
        if ( parent::is_dataTablesParams( $vars ) ) {
            if ( $this->memData['permission']['role'] >= 1 || $this->memData['permission']['member'] >= 2 ) {
                $table = $this->register->getRoleList(
                    $this->memData['company'],
                    (int)$vars['page'],
                    (int)$vars['per'],
                    $vars['order'],
                    $vars['search']
                );
                if ( is_array( $table ) ) {
                    parent::resp_datatable( $vars['draw'], $table['total'], $table['data'] );
                } else {
                    self::resp_decoder( $table );
                }
            } else {
                self::resp_decoder( static::PROC_NO_ACCESS );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }

    /**
     * edit a type of role permission.
     *
     * @author Nick
     * @method POST
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function post_SetRolePermission (Request $request, Response $response, array $args): Response
    {
        $role_id = $request->getParsedBodyParam( 'id' );
        $perms   = $request->getParsedBodyParam( 'perms' );
        if ( is_int( $role_id ) && $role_id > 0 && is_array( $perms ) && !empty( $perms ) ) {
            // 請檢查 role_id 是否與 requester 使用的是同一個？如果是，則不可以給它修改！
            $requester_data = $this->register->getMemberData( $this->memData['company'], $this->memData['id'] );
            if ( $this->memData['permission']['role'] >= 2 && $role_id != $requester_data['role_id'] ) {
                // IMPORTANT: check keys(permission names) are equal to legal role properties' columns
                if ( DsParamProc::isArrayKeysEqual( $this->memData['permission'], $perms ) ) {
                    // IMPORTANT: role 的創建不能高於 super admin ( company/group owner ), 也不能高過本次賦予權限的編輯者
                    foreach ( $perms as $k => $v ) {
                        if ( !is_int( $v ) || $v > $this->memData['permission'][ $k ] || $v < 0 ) {
                            self::resp_decoder( static::PROC_NO_ACCESS );
                            return $response->withJson( $this->respJson, $this->respCode );
                        }
                    }
                    // you can do next steps if all perms are legal.
                    $code = $this->register->setRolePerms( $this->memData['company'], $role_id, $perms );
                    self::resp_decoder( $code );
                    // set a log
                    if ( $this->respJson['status'] === 1 ) {
                        $this->container->logger->notice(
                            'member ' . $this->memData['id'] . ' edit the role(' .
                            $role_id . ') permission: ' . parent::jsonLogStr( $perms ) );
                    }
                }
            } else {
                self::resp_decoder( static::PROC_NO_ACCESS );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }
    
    /**
     * View a type of role permission.
     * 
     * @author Nick
     * @method POST
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function post_GetRolePermission ( Request $request, Response $response, array $args ): Response
    {
        $role_id = $request->getParsedBodyParam( 'id' );
        if ( is_int( $role_id ) && $role_id > 0 ) {
            if ( $this->memData['permission']['role'] >= 1 ) {
                $data = $this->register->getRolePerms( $this->memData['company'], $role_id );
                if ( is_array( $data ) ) {
                    self::resp_decoder( static::PROC_OK, '', $data );
                } else {
                    self::resp_decoder( static::PROC_FAIL );
                }
            } else {
                self::resp_decoder( static::PROC_NO_ACCESS );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }
    
    /**
     * Get member list of company, member, or guest
     * 
     * NOTE: Because ISSUE needs to use member list for any one,
     *       Change to be anyone can see it without any permission by FGN at 4th Des. 2020.
     *
     * @author Nick
     * @method POST
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function post_GetMemberList (Request $request, Response $response, array $args): Response
    {
        $vars        = $request->getParsedBody();
        $usr_type    = $request->getParsedBodyParam( 'usr_type', [] );
        $stage_arr   = $request->getParsedBodyParam( 'stage', [] );
        $network_arr = $request->getParsedBodyParam( 'net', [] );
        if ( parent::is_dataTablesParams( $vars )
            && is_array( $usr_type )
            && is_array( $stage_arr )
            && is_array( $network_arr ) )
        {
            if ( $this->memData['permission']['member'] >= 1 || $this->memData['permission']['displayer_network'] >= 1 ) {
                // NOTE: FGN take the exception person off at 4th Des. 2020,
                //       so you can see yourself in the list.
                $table = $this->register->getMemberList(
                    $this->memData['company'],
                    0,
                    (int)$vars['page'],
                    (int)$vars['per'],
                    $vars['order'],
                    $vars['search'],
                    $usr_type,
                    $network_arr,
                    $stage_arr
                );
                
                if ( is_array( $table ) ) {
                    parent::resp_datatable( $vars['draw'], $table['total'], $table['data'] );
                } else {
                    self::resp_decoder( $table );
                }
            } else {
                self::resp_decoder( static::PROC_NO_ACCESS );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }

    /**
     * Get member data, not self.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws ErrorException
     * @author Nick
     * @method POST
     */
    public function post_GetMemberData (Request $request, Response $response, array $args): Response
    {
        $mem_id = $request->getParsedBodyParam( 'id' );
        if ( is_int( $mem_id ) && $mem_id > 0 ) {
            $is_access = 0;
            if ( $this->memData['id'] === $mem_id ) { // get the member information for requester self.
                $is_access = 1;
            } else if ( $this->memData['permission']['member'] >= 1 ) { // looking for other member account information.
                $is_access = 2;
            }
            if ( $is_access > 0 ) {
                $data = $this->register->getMemberData( $this->memData['company'], $mem_id );
                if ( is_array( $data ) ) {
                    switch ( $is_access ) {
                        case 1:
                            self::resp_decoder( static::PROC_OK, '', $data );
                            break;
                        case 2:
                            // NOTE: if who(not self) you want to look for is higher(/equal) than you in member type,
                            //       you can not review it.
                            //       E.g. 1 ~ N, 1 is the highest level, and others are lower and lower with number increasing.
                            if ( $data['type'] > $this->memData['type'] ) {
                                self::resp_decoder( static::PROC_OK, '', $data );
                            } else {
                                self::resp_decoder( static::PROC_NO_ACCESS );
                            }
                            break;
                        default:
                            self::resp_decoder( static::PROC_FAIL );
                    }
                } else {
                    self::resp_decoder( static::PROC_FAIL );
                }
            } else {
                self::resp_decoder( static::PROC_NO_ACCESS );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }

    /**
     * For manager to edit member status, role, type (not self).
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws ErrorException
     * @author Nick
     * @method POST
     */
    public function post_SetMemberStatus (Request $request, Response $response, array $args): Response
    {
        $mem_id = $request->getParsedBodyParam( 'id' );
        $type   = $request->getParsedBodyParam( 'type', 0 );    // 0 means un-modify it
        $role   = $request->getParsedBodyParam( 'role', 0 );    // 0 means un-modify it
        $status = $request->getParsedBodyParam( 'status', 0 );  // 0 means un-modify it
        if ( is_int( $mem_id ) && $mem_id > 0
            && is_int( $type ) && $type >= 0
            && is_int( $role ) && $role >= 0
            && is_int( $status ) && $status >= 0 )
        {
            // Check api permission
            if ( $this->memData['permission']['member'] >= 2 && ( $type === 0 || $type > $this->memData['type'] ) ) {
                // IMPORTANT: you cannot modify a person has any permission higher than you!
                if ( $role > 0 ) {
                    $perms = $this->register->getRolePerms( $this->memData['company'], $role ); // another legal permission form
                    // IMPORTANT: role 的創建不能高於 super admin( company/group owner ), 也不能高過本次賦予權限的編輯者
                    if ( is_array( $perms ) ) {
                        // NOTE: because the both permission array are from system returning, so no need to check array keys are equal or not.
                        foreach( $perms['perms'] as $k => $v ) {
                            if ( !is_int( $v ) || $v > $this->memData['permission'][ $k ] || $v < 0 ) {
                                self::resp_decoder( static::PROC_NO_ACCESS );
                                return $response->withJson( $this->respJson, $this->respCode );
                            }
                        }
                    } else {
                        self::resp_decoder( static::PROC_SQL_ERROR );
                        return $response->withJson( $this->respJson, $this->respCode );
                    }
                }
                // if it is legal, you can go to the next.
                $data = $this->register->getMemberData( $this->memData['company'], $mem_id );
                // 1. Can not change the member type higher than requester.
                // 2. Can not allow requester to change member type to be equal/higher with self
                // 3. Can not change member status to 1|2 from 3 (initializing).
                if ( is_array( $data ) && $data['type'] > $this->memData['type'] ) {
                    // 如果這個要被修改的 member 是正在被邀請的人員，則不可以修改他的 status。如果有值送來，也直接改成忽略它
                    if ( $data['status'] === 3 ) {
                        $status = 0;
                    }
                    $out = $this->register->editMemberState( $this->memData['company'], $mem_id, $type, $role, $status );
                    self::resp_decoder( $out );
                    // set a log
                    if ( $out === static::PROC_OK ) {
                        $this->container->logger->notice(
                            'member(' . $mem_id . ') state changed by member(' . $this->memData['id'] .
                            '): type=' . $type . ', role=' . $role . ', status=' . $status );
                    }
                } else {
                    self::resp_decoder( static::PROC_NO_ACCESS );
                }
            } else {
                self::resp_decoder( static::PROC_NO_ACCESS );
            }
        }
        return $response->withJson( $this->respJson, $this->respCode );
    }
}

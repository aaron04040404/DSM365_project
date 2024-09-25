<?php
/**
 * All register of account processing controller functions are here.
 *
 * @author Nick Feng
 * @since 1.0
 */
namespace Gn\Fun;

use Exception;
use Gn\Sql\SqlRegister;

/**
 * User register functions.
 *
 * @author Nick
 */
class RegisterFun
{
    /**
     * Member db of register side.
     * 
     * @var SqlRegister
     */
    protected $sqlRegister = NULL;
    
    /**
     * Constructor
     *
     * @param array $db_settings database settings from Slim 3 settings array
     */
    public function __construct( array $db_settings )
    {
        $this->sqlRegister = new SqlRegister( $db_settings );
    }
    
    /**
     * Invite new member by admin.
     * 
     * @param string $email
     * @param int $company
     * @param int $type
     * @param int $role_id
     * @return array|int
     */
    public function InitMember ( string $email, int $company, int $type, int $role_id )
    {
        $status = $this->sqlRegister->initMember( $company, $email, $type, $role_id );
        if ( is_array( $status ) ) {
            $new_payload = $this->sqlRegister->resetPw( $email, 3 );
            if ( is_int( $new_payload ) ) {
                return $new_payload;
            }
            $status['payload'] = $new_payload;
            return $status;
        }
        return $status; // other error code
    }
    
    /**
     * Reset user password.
     * NOTE: <b>It called for forget password page, new invited member, or
     * it works for users management page to reset some specified member account to make them reset password.</b>
     * 
     * @param string     $email the account you're modifying.
     * @param int        $scope
     * @return array|int Return a new payload, or integer error code
     */
    public function resetPassword ( string $email, int $scope = 1 )
    {
        return $this->sqlRegister->resetPw( $email, $scope );
    }
    
    /**
     * For the password setting up after password reset.
     * 
     * @param string $newPW
     * @param string $verify_code
     * @param int $scope
     * @return boolean|string
     */
    public function initPassword ( string $newPW, string $verify_code, int $scope = 1 )
    {
        return $this->sqlRegister->initPw ( $newPW, $verify_code, $scope );
    }
    
    /**
     * Modify user password from old one to a new one.
     *
     * @param int $userID
     * @param string $newPW String in SHA512
     * @param string $verificationCode Default is NULL. It works for member password reset only, so when
     * it is assigned, it doesn't need to check jti in jwt object.
     * @return boolean|string Return an email for success, or FALSE on failure.
     */
    public function modifyPw ( int $userID, string $newPW, string $verificationCode )
    {
        return $this->sqlRegister->changePw( $userID, $newPW, $verificationCode );
    }
    
    /**
     * Login user account
     *
     * @param string $email    User account.
     * @param string $pw       User password in sha512.
     * @param bool $isRemember True is to do, and FALSE is not to do.
     * @return array|int       If it is success it will return a token payload array, or you will get error number code.
     */
    public function Login ( string $email, string $pw, bool $isRemember )
    {
        return $this->sqlRegister->genAccessJwt( $email, $pw, $isRemember );
    }

    /**
     *
     * @param string $email
     * @return array|int
     * @throws Exception
     */
    public function email4OTP( string $email )
    {
        return $this->sqlRegister->OTP_generator( $email );
    }

    /**
     *
     * @param string $uuid
     * @param string $otp
     * @return int|array
     * @throws Exception
     */
    public function verifyLoginOtp ( string $uuid, string $otp )
    {
        return $this->sqlRegister->verifyOTP( $uuid, $otp );
    }

    /**
     *
     * @param string $uuid
     * @return array|int
     */
    public function updateOTP ( string $uuid )
    {
        return $this->sqlRegister->resentOTP( $uuid );
    }

    /**
     * After Login detection, you can call the method to get member profile and permission flags.
     *
     * @param string|null $access_jti
     * @param bool $withPerms
     * @param bool $withNet
     * @param bool $withStage
     * @return array|boolean
     */
    public function isLogged( string $access_jti = NULL, bool $withPerms = false, bool $withNet = false, bool $withStage = false )
    {
        if ( !empty( $access_jti ) ) {
            $user = $this->sqlRegister->isAccessJwt( $access_jti );
            if ( $user !== false ) {
                return $this->sqlRegister->getMemInfo( $user['company'], $user['id'], $withPerms, $withNet, $withStage );
            }
        }
        return false;
    }

    /**
     * If you need to do something with user account password protection checking, you should use the method to get a protection token as CSRF.
     *
     * @param int|string $user
     * @param string     $pw_sha512
     * @param bool       $is_email
     * @return int
     */
    public function confirmPassword( $user, string $pw_sha512, bool $is_email = false ): int
    {
        return $this->sqlRegister->checkUserPw( $user, $pw_sha512, $is_email );
    }

    /**
     * user account logout and remove login cookie session.
     * NOTE: clean application api token must be the first step.
     *
     * @param string $access_jti
     * @return boolean
     */
    public function Logout( string $access_jti ): bool
    {
        if ( !$this->sqlRegister->removeApiJwtByAccess( $access_jti ) ) {
            return false;
        }
        return $this->sqlRegister->removeAccessByJti( $access_jti );
    }
    
    /**
     * You can get a new web api JWT token via this method.
     * <p><b>** Here will check the login status too. **</b></p>
     *
     * @param string $access_jti
     * @return boolean|array
     */
    public function genApiAuth( string $access_jti )
    {
        $user = $this->sqlRegister->isAccessJwt( $access_jti );
        if ( $user !== false ) {
            return $this->sqlRegister->genApiJwt( $user['id'], $access_jti );
        }
        return false;
    }

    /**
     * Check API is accessed and return info of the jwt owner.
     *
     * @param string $jti
     * @param array|null $oauthFilter
     * @return boolean|array
     */
    public function isApiAuth ( string $jti, array $oauthFilter = NULL )
    {
        return $this->sqlRegister->isApiJwt( $jti, $oauthFilter );
    }

    /**
     *
     * @param int $company
     * @param int $except_mem
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string|null $search
     * @param array $usr_type
     * @param array $network
     * @param array $stages
     * @return int|array
     */
    public function getMemberList (
        int $company,
        int $except_mem,
        int $page,
        int $per,
        array $order = [],
        string $search = NULL,
        array $usr_type = [],
        array $network = [],
        array $stages = [] )
    {
        return $this->sqlRegister->getMemberTable(
            $company,
            $except_mem,
            $page,
            $per,
            $order,
            $search,
            $usr_type,
            $network,
            $stages );
    }

    /**
     * Get the specified user account public information, "Not Self Account Public Information Data"
     * These may be user profile, message, title, slogan, and so on.
     *
     * @param int $company
     * @param string $userID Who you want to modify. It also can be yourself.
     * @param bool $withPerms
     * @param bool $withNet
     * @param bool $withStage
     * @return boolean|array
     */
    public function getMemberData( int $company, string $userID, bool $withPerms = false, bool $withNet = false, bool $withStage = false )
    {
        return $this->sqlRegister->getMemInfo( $company, $userID, $withPerms, $withNet, $withStage );
    }

    /**
     * Edit member profile like name, message, phone number.
     *
     * @param int $company_id
     * @param int $user_id
     * @param string $name
     * @param string $msg
     * @param bool $otp
     * @return int
     */
    public function editMemberProfile ( int $company_id, int $user_id, string $name, string $msg, bool $otp ): int
    {
        return $this->sqlRegister->setMemberProfile( $company_id, $user_id, $name, $msg, $otp );
    }

    /**
     * Edit member state like statue, type, group of permission.
     *
     * @param int $company_id
     * @param int $user_id
     * @param int $type
     * @param int $role
     * @param int $status
     * @return int
     */
    public function editMemberState ( int $company_id, int $user_id, int $type = 0, int $role = 0, int $status = 0 ): int
    {
        $out = $this->sqlRegister::PROC_INVALID;
        if ( $type > 0 ) {
            $out = $this->sqlRegister->setMemberType( $company_id, $user_id, $type );
            if ( $out !== $this->sqlRegister::PROC_OK ) {
                return $out;
            }
        }
        if ( $role > 0 ) {
            $out = $this->sqlRegister->setMemberRole( $company_id, $user_id, $role );
            if ( $out !== $this->sqlRegister::PROC_OK ) {
                return $out;
            }
        }
        if ( $status > 0 ) {
            if ( $status >= 1 && $status <= 2 ) {
                // 0: remove member by the look of it. (you cannot change status to 0 via the function)
                // 1: activate a member
                // 2: block a member
                // 3: you cannot change status to 3(initializing) via the function.
                $out = $this->sqlRegister->modifyMemberStatus( $company_id, $user_id, $status );
            } else {
                return $this->sqlRegister::PROC_INVALID;
            }
        }
        return $out;
    }
    
    // ================================= Roles =================================

    /**
     *
     * @param int $company_id
     * @param string $role_name
     * @param array $perms
     * @return array|int
     */
    public function createRole ( int $company_id, string $role_name, array $perms )
    {
        return $this->sqlRegister->newRole( $company_id, $role_name, $perms );
    }

    /**
     * Disable a role
     *
     * @param int $company
     * @param int $role_id
     * @return int
     */
    public function recycleRole ( int $company, int $role_id ): int
    {
        return $this->sqlRegister->removeRole( $company, $role_id );
    }

    /**
     * List member group table.
     *
     * @param int $company_id
     * @param int $page
     * @param int $per
     * @param array $order
     * @param string|null $search
     * @return array|int
     */
    public function getRoleList ( int $company_id, int $page, int $per, array $order = [], string $search = NULL )
    {
        return $this->sqlRegister->getRolesTab( $company_id, $page, $per, $order, $search );
    }

    /**
     * List access items for the group.
     *
     * @param int $company_id
     * @param int $role_id
     * @return boolean|array
     */
    public function getRolePerms ( int $company_id, int $role_id )
    {
        return $this->sqlRegister->rolePermissions( $company_id, $role_id );
    }
    
    /**
     *
     * @param int $company_id
     * @param int $role_id
     * @param array $perms
     * @return int
     */
    public function setRolePerms (int $company_id, int $role_id, array $perms): int
    {
        return $this->sqlRegister->editRolePerms( $company_id, $role_id, $perms );
    }
}

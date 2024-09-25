<?php
namespace Gn\Interfaces;
/**
 * All http response default message string are collected in this interface for implementing
 * @author nickfeng 2019-08-30
 *
 */
interface MailerInterface {
    const MAIL_TITLE_INVITATION    = 'DynaScan365 Invitation';
    const MAIL_TILTE_PW_CHANGED    = 'DynaScan365 Account Password Changed';
    const MAIL_TITLE_PW_RESET      = 'DynaScan365 Account Password Reset';
    const MAIL_TITLE_ACCESS_OTP    = 'DynaScan365 Access OTP';
    const MAIL_HTMLBODY_INVITATION = 'invitee-confirm.phtml';
    const MAIL_HTMLBODY_PW_CHANGED = 'account-pw-changed.phtml';
    const MAIL_HTMLBODY_PW_RESET   = 'account-pw-reset.phtml';
    const MAIL_HTMLBODY_ACCESS_OTP = 'access-otp.phtml';

    const MAIL_TITLE_DEVICE_AUTO_REGISTER    = 'DynaScan Display Product Register | DynaScan365 Cloud';
    const MAIL_HTMLBODY_DEVICE_AUTO_REGISTER = 'product-register.phtml';
    const MAIL_TITLE_DEVICE_AUTO_RESET       = 'DynaScan Display Product Unmounted | DynaScan365 Cloud';
    const MAIL_HTMLBODY_DEVICE_AUTO_RESET    = 'product-reset.phtml';
}

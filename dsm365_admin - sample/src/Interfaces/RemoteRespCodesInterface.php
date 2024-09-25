<?php
namespace Gn\Interfaces;

/**
 * All http response default message string are collected in this interface for implementing
 * @author nickfeng 2019-08-30
 *
 */
interface RemoteRespCodesInterface extends NotificationRespCodesInterface
{
    /**
     * Database uses 3-char size for the channel name saving.
     *
     * channel code of remoting from
     * @var string
     */
    const DISPLAY_REMOTE_ADMIN_CHANNEL  = 'adm';    // belong to admin main
    const DISPLAY_REMOTE_CLIENT_CHANNEL = 'cm';     // belong to client main
    const DISPLAY_REMOTE_OPENAPI_CHANNEL = 'op';    // belong to open api using
    
    /**
     * Default remote json file name.
     * @var string
     */
    const REMOTE_ATTRIBUTE_DEF_NAME = 'BASE';
    
    /**
     * The preg_match detection is working for:
     * 1. remote command status
     * 2. soc software queue status.
     * 3. soc recovery queue status.
     * 
     * H: It means waiting for update now.
     * T: It means it has been watched.
     * D: It means update is done.
     * C: It means update canceled.
     * X: It means time out from watched.
     * 
     * @var string
     */
    const SOC_FEEDBACK_STATUS_PREG = '/[HTDCX]{1}/';
}





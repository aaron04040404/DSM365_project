<?php
namespace Gn\Interfaces;

/**
 * All notification attribute code inside here!
 * 
 * @author nickfeng 2019-08-30
 *
 */
interface NotificationRespCodesInterface
{
    /**
     * notification action type code.
     * 
     * @var string
     */
    const SOC_NOTIF_REMOTE_OSD_ACTION      = 'rmt';   // OSD remote
    const SOC_NOTIF_UPDATE_APK_ACTION      = 'upt';   // update dsservice apk
    const SOC_NOTIF_SYSTEM_RECOVERY_ACTION = 'sys';   // recovery SoC system settings
    
    /**
     * notification category codes
     * 
     * @var integer
     */
    const NOTIF_CATEGORY_SYS_ALERT    = 1;
    const NOTIF_CATEGORY_MCB_ALERT    = 101;
    const NOTIF_CATEGORY_USR_ALERT    = 201;
    const NOTIF_CATEGORY_UPDATE_ALERT = 301;
    const NOTIF_CATEGORY_REMOTE_ALERT = 401;
}

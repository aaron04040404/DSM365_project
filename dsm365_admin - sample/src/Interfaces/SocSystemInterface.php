<?php
namespace Gn\Interfaces;

/**
 * All http response default message string are collected in this interface for implementing
 * @author nickfeng 2019-08-30
 *
 */
interface SocSystemInterface
{
    /**
     * All elements of SoC settings to show on OSD menu
     * 
     * IMPORTANT: 以後這個陣列的資料，要依照 company_id 與 model name 進行差異畫的顯示儲存在資料庫之中
     * 
     * @var array
     */
    const SOC_SETTING_OSD_ITEMS = [
        56, 64, 66, 68, 70, 74, 76, 78, 80, 88, 92, 94, 96, 
        104, 134, 140, 160, 162, 178, 184, 186, 188, 190, 204
    ];
    
    /**
     * All elements of SoC settings in a SoC for recovery
     * 
     * @2023-08-16除役
     * @var array
     */
    const SOC_SETTING_RECOVERY_CMD_ARR_orig = [
        56, 64, 66, 68, 70, 74, 76, 78, 80, 82, 84, 88, 92, 94, 96,
        102, 104, 106, 108, 110, 112, 114, 134, 138, 140,
        160, 162, 164, 176, 178, 184, 186, 188, 190, 204, 214
    ];
    
    /**
     * All elements of SoC settings in a SoC for recovery
     *
     * @2023-08-16服役
     * @var array
     */
    const SOC_SETTING_RECOVERY_CMD_ARR = [
        56, 64, 66, 68, 70, 74, 76, 78, 80, 82, 84, 88, 92, 94, 96,
        102, 104, 106, 108, 110, 112, 114, 134, 138, 140,
        160, 162, 164, 166, 176, 178, 184, 186, 188, 190, 204, 214, 216, 218
    ];
}

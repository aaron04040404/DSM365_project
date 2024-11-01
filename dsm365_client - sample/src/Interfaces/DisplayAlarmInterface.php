<?php
/**
 * Copyright Nick Feng 2021
 * SQL function basic method to extend.
 *
 * Display alarm syntax extensions.
 *
 * @author  Nick Feng
 * @since 1.0
 */
namespace Gn\Interfaces;

/**
 * Basic functions for SQL
 *
 * @author Nick
 */
interface DisplayAlarmInterface
{
    // you must ensure all alarm/statue flags are like boolean between 0 and 1, and 1 means something is happening.
    
    // IMPORTANT: you have to maintain all values in array are equal to 
    //            the columns names of alarm flag of mcb realtime table.
    const DISPLAY_ALARM_FILTER_MAP = [
        '101' => 'a_fan_1',             // Fan Alarm for David changing. it is different to admin side now @2022-12-12
        '102' => 'a_overheat',          // Overheat
        '103' => 'a_nosignal',          // No Signal
        '104' => 'a_time_confused',     // *** Time in wrong zone *** Python to generate, not PHP =>  @2024-03-27 將它與 a_time_confused 做配對。
        '105' => 'a_powerstate',        // Power State  @2024-03 David又說要使用了 XD
        '106' => 'a_bright_h',          // Brightness Protection
        '107' => 'a_failover',          // Fail over
        '108' => 'a_lcm_stick',         // Jammed LC
        '109' => 's_door',              // Door Opening
        '110' => 's_lightbox',          // *** Lightbox Opening *** @2023-12-25 merge to 109, triggered with door opening alarm
        '111' => 'a_pw_supply',         // Power Supply
        '112' => 'a_lan_switch_pw',     // LAN Switch
        '113' => 'a_lcm_pw',            // LCM Power
        '114' => 'a_player_pw',         // Player power
        '115' => 'a_thermal',           // Thermal Critical
        '116' => 'a_flood',             // Flood Alarm
        '117' => 'a_fan',               // Advanced Fan Alarm. it is only for admin-side web side to manage raw-fan-alarm from raw data.
        '201' => 'condition_flg',       // *** MCB Lost *** Python to generate, not PHP
        '202' => 'a_sn_changed',        // *** Watch the SN changing point *** => @2024-03-27 將它與 a_sn_changed 做配對。
        '203' => 's_reboot',            // DSService Booting
        '204' => 'a_lcm_mount',         // LCM mounting quantity is incorrect
        '205' => 'a_kiosk_mcb',          // Another side MCB board connection lost
        // @2024-06-28: 新增以下 alarm code
        '206' => 'a_company',            // ，kiosk的兩個面體，是否都歸屬同一個公司群體，如果為否，則發出警告
        '207' => 'a_freq'                // request frequency is too fast => less than 30s
    ];
    
    const KIOSK_MCB_LOST_TIME_LIMIT = 300; // 5 min of update time
    const KIOSK_MCB_FREQ_TIME_LIMIT = 25;
    
    const DISPLAY_CONDITION_FLAG_NONE  = 0;
    const DISPLAY_CONDITION_FLAG_GOOD  = 1;
    const DISPLAY_CONDITION_FLAG_ISSUE = 2;
    const DISPLAY_CONDITION_FLAG_LOST  = 3;
}
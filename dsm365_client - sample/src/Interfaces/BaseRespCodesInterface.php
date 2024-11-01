<?php
namespace Gn\Interfaces;

/**
 * All http response default message string are collected in this interface for implementing
 * @author nickfeng 2019-08-30
 *
 */
interface BaseRespCodesInterface {
    const EXCEPTION_MSG_PROC_OUTOFBOUNDS = 'process code is out of bounds';
    const EXCEPTION_MSG_AUTH_EMPTY = 'DSService user authority data is not existed!';
    
    // status code for returning.
    const PROC_FAIL           = 0x00;
    const PROC_OK             = 0x01;    // 0x01 ~ 0x0F 可子針對 success 回應做自由發揮
    const PROC_INVALID        = 0x10;
    const PROC_NO_ACCESS      = 0x11;
    const PROC_DATA_FULL      = 0x12;
    const PROC_INVALID_USER   = 0x13;
    const PROC_INVALID_PW     = 0x14;
    const PROC_NOT_EXISTED    = 0x15;
    const PROC_BLOCKED        = 0x16;
    const PROC_UNINITIALIZED  = 0x17; //uninitialized
    const PROC_TOKEN_ERROR    = 0x18;
    const PROC_MEM_VIEW_ERROR = 0x19;
    const PROC_FILE_INVALID   = 0x1A;
    const PROC_WAITING        = 0x1B;
    const PROC_EXCEEDED_ATTEMPT = 0x1C;
    // for device remote
    const PROC_REMOTE_ID_INVALID       = 0x21;
    const PROC_REMOTE_CMD_INVALID      = 0x22;
    const PROC_REMOTE_LV_INVALID       = 0x23;
    const PROC_REMOTE_NO_OSD_LOCK      = 0x24;
    const PROC_REMOTE_OSD_LOCK_TWICE   = 0x25;
    const PROC_REMOTE_OSD_LOCK_INVALID = 0x26;
    const PROC_REMOTE_APPLICABILITY    = 0x27;
    // for MCB registering process
    const PROC_MCB_ID_INVALID             = 0x31;
    const PROC_MCB_SN_INVALID             = 0x32;
    const PROC_MCB_MODEL_INVALID          = 0x33;
    const PROC_MCB_COMPANY_INVALID        = 0x34;
    const PROC_MCB_PW_INVALID             = 0x35;
    const PROC_MCB_MEM_INVALID            = 0x36;
    const PROC_MCB_KIOSK_SN_INVALID       = 0x37;
    const PROC_MCB_KIOSK_LCM_ID_INVALID   = 0x38;
    const PROC_MCB_KIOSK_LCM_FULLY_LOADED = 0x39;
    const PROC_MCB_KIOSK_GROUP_ERROR      = 0x3A;
    // for MCB raw data process
    const PROC_MCB_RAW_INVALID      = 0x41;
    const PROC_MCB_RAW_CMD_INVALID  = 0x42;
    const PROC_MCB_RAW_TIME_INVALID = 0x43;
    const PROC_MCB_RAW_DATA_INVALID = 0x44;
    const PROC_MCB_RAW_DATA_EMPTY   = 0x45;
    // for company registering
    const PROC_CLIENT_COMPANY_OK_AND_PLAN_UPDATE = 0x51;   // 如同 process ok 一樣，所以記得 JSON status code 在 response 的時候要 = 1
    const PROC_CLIENT_COMPANY_NAME_INVALID       = 0x52;
    const PROC_CLIENT_COMPANY_PHONE_ZONE_INVALID = 0x53;
    const PROC_CLIENT_COMPANY_PHONE_INVALID      = 0x54;
    const PROC_CLIENT_COMPANY_COUNTRY_INVALID    = 0x55;
    const PROC_CLIENT_COMPANY_ADDRESS_INVALID    = 0x56;
    const PROC_CLIENT_COMPANY_GPS_INVALID        = 0x57;
    const PROC_CLIENT_COMPANY_STATUS_INVALID     = 0x58;
    const PROC_CLIENT_COMPANY_PLAN_INVALID       = 0x59;
    const PROC_CLIENT_COMPANY_ROLE_INVALID       = 0x5A;
    // for file I/O
    const PROC_GCP_CLOUD_STORAGE_FAIL = 0x61;
    // for SQL error
    const PROC_FOREIGN_KEY_CONSTRAINT = 0xFC;   // foreign key constraint fails
    const PROC_SERIALIZATION_FAELURE  = 0xFD;   // deadlock table
    const PROC_DUPLICATE              = 0xFE;
    const PROC_SQL_ERROR              = 0xFF;
    
    /**
     * Convert processing code to text.
     * 
     * @var array
     */
    const PROC_TXT = [
        self::PROC_FAIL          => 'fail',
        self::PROC_OK            => 'ok',   // 0x01 ~ 0x0F 可子針對 success 回應做自由發揮
        self::PROC_INVALID       => 'invalid input',
        self::PROC_NO_ACCESS     => 'permission denied',
        self::PROC_DATA_FULL     => 'data full',
        self::PROC_INVALID_USER  => 'invalid ID',
        self::PROC_INVALID_PW    => 'invalid password',
        self::PROC_NOT_EXISTED   => 'not existed',
        self::PROC_BLOCKED       => 'blocked',
        self::PROC_UNINITIALIZED => 'uninitialized',
        self::PROC_TOKEN_ERROR   => 'token error',
        self::PROC_MEM_VIEW_ERROR=> 'user view error',
        self::PROC_FILE_INVALID  => 'file invalid',
        self::PROC_WAITING       => 'process waiting',
        self::PROC_EXCEEDED_ATTEMPT => 'You\'ve exceeded the maximum number of attempts',
        // for device remote
        self::PROC_REMOTE_ID_INVALID       => 'invalid remote MCB ID',
        self::PROC_REMOTE_CMD_INVALID      => 'invalid remote command',
        self::PROC_REMOTE_LV_INVALID       => 'invalid remote command level',
        self::PROC_REMOTE_NO_OSD_LOCK      => 'no OSD lock function',
        self::PROC_REMOTE_OSD_LOCK_TWICE   => 'OSD lock has turned ON',
        self::PROC_REMOTE_OSD_LOCK_INVALID => 'OSD lock invalid',
        self::PROC_REMOTE_APPLICABILITY    => 'remote command applicability uncertainty',
        // for MCB registering process
        self::PROC_MCB_ID_INVALID             => 'invalid MCB ID number',
        self::PROC_MCB_SN_INVALID             => 'invalid MCB series number',
        self::PROC_MCB_MODEL_INVALID          => 'invalid MCB model name',
        self::PROC_MCB_COMPANY_INVALID        => 'invalid MCB company id',
        self::PROC_MCB_PW_INVALID             => 'invalid MCB password',
        self::PROC_MCB_MEM_INVALID            => 'invalid member ID of MCB creator',
        self::PROC_MCB_KIOSK_SN_INVALID       => 'invalid kiosk series number',
        self::PROC_MCB_KIOSK_LCM_ID_INVALID   => 'invalid kiosk LCM ID', 
        self::PROC_MCB_KIOSK_LCM_FULLY_LOADED => 'invalid kiosk LCM full loading',
        self::PROC_MCB_KIOSK_GROUP_ERROR      => 'kiosk group info error',
        // for MCB raw data process
        self::PROC_MCB_RAW_INVALID      => 'invalid MCB raw element',
        self::PROC_MCB_RAW_CMD_INVALID  => 'invalid MCB raw command code',
        self::PROC_MCB_RAW_TIME_INVALID => 'invalid MCB raw time format',
        self::PROC_MCB_RAW_DATA_INVALID => 'invalid MCB raw data structure',
        self::PROC_MCB_RAW_DATA_EMPTY   => 'empty MCB raw data',
        // for company registering
        self::PROC_CLIENT_COMPANY_OK_AND_PLAN_UPDATE => 'ok and plan updated',   // 如同 process ok 一樣，所以記得 JSON status code 在 response 的時候要 => 1
        self::PROC_CLIENT_COMPANY_NAME_INVALID       => 'name invalid',
        self::PROC_CLIENT_COMPANY_PHONE_ZONE_INVALID => 'phone zone invalid',
        self::PROC_CLIENT_COMPANY_PHONE_INVALID      => 'phone number invalid',
        self::PROC_CLIENT_COMPANY_COUNTRY_INVALID    => 'country invalid',
        self::PROC_CLIENT_COMPANY_ADDRESS_INVALID    => 'address invalid',
        self::PROC_CLIENT_COMPANY_GPS_INVALID        => 'GPS invalid',
        self::PROC_CLIENT_COMPANY_STATUS_INVALID     => 'status invalid',
        self::PROC_CLIENT_COMPANY_PLAN_INVALID       => 'plan invalid',
        self::PROC_CLIENT_COMPANY_ROLE_INVALID       => 'role invalid',
        // for file I/O
        self::PROC_GCP_CLOUD_STORAGE_FAIL => 'cloud storage I\/O fail',
        // for SQL error
        self::PROC_FOREIGN_KEY_CONSTRAINT => 'data with key constraint',   // foreign key constraint fails
        self::PROC_SERIALIZATION_FAELURE  => 'data serialization failure',   // deadlock table or timeout
        self::PROC_DUPLICATE              => 'data duplicate',
        self::PROC_SQL_ERROR              => 'server internal error'
    ];
}

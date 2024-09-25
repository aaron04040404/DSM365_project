<?php
/**
 * Check whether the time format is correct 
 * 
 */

namespace Gn\Lib;

use DateTime;

class ValidDateTime
{
    public static function isValidDateTime($dateTime, $format) {
        $d = DateTime::createFromFormat($format, $dateTime);
        $errors = DateTime::getLastErrors();
    
        // 如果有錯誤或警告，表示日期無效
        if ($errors['warning_count'] > 0 || $errors['error_count'] > 0) {
            error_log("Warnings: " . print_r($errors['warnings'], true));
            error_log("Errors: " . print_r($errors['errors'], true));
            return false;
        }
    
        // 確認日期是否有效並且符合格式
        return true;
    }
}
<?php
/**
 * Copyright Nick Feng 2021
 * SQL function basic method to extend.
 *
 * Display alarm syntax extensions.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Interfaces;

/**
 * Basic functions for Google Cloud Storage file saving.
 * @author nick
 *
 */
interface DisplayScreenshotInterface
{
    // length = 11
    const GCS_SCREENSHOT_FILE_HEADER_STR = 'mcb-screen';  // E.g. mcb-screen-YMnT8XHLg3U5pRA.jpg
    
    const GCS_SCREENSHOT_FILE_RAND_STR_LEN = 15;

    const GCS_SCREENSHOT_FILE_EXT_STR = 'jpg';
        
    const GCS_SCREENSHOT_FILE_EXT_PREG = '/^.*\.(jpg)$/i';
    
    const GCS_SCREENSHOT_FILE_HEADER_PREG = '/^mcb\-screen\-([a-zA-Z0-9]){15}\.(jpg)$/';
    
    /**
     * 目前暫時不考慮使用
    const ADMIN_CHANNEL  = 'admin';
    const CLIENT_CHANNEL = 'client';
    */
}
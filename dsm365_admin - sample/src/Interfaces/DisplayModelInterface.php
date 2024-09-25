<?php
/**
 * Copyright Nick Feng 2021
 * SQL function basic method to extend.
 *
 * Display alarm syntax extentions.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Interfaces;

/**
 * Basic functions for SQL
 * @author nick
 *
 */
interface DisplayModelInterface
{
    /**
     * 結構請參照資料庫中的 displayer_model table 去做
     * @var array
     */
    const DISPLAY_MODLE_FACE_INFO_DEFAULT = [
        'total_num' => 1,
        'normal'    => [1],
        'touch'     => [],
        'poster'    => []
    ];
}
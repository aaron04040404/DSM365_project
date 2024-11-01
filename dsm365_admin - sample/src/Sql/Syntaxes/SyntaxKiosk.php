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
namespace Gn\Sql\Syntaxes;

/**
 * 這邊整理了很多有關於 kiosk 合併性質的結果輸出
 * 包含了：
 * 
 *  main id, main sn,..... 
 * 
 * @author Nick 2024-01-30
 */
class SyntaxKiosk
{

    /**
     * out put a string to SQL to find out the main S/N for a kiosk machine if what you want to fine in SQL list with it.
     *
     * @param string $mcb_main_tab
     * @param string $mcb_realtime_tab
     * @param string $output_alias
     * @return string
     */
    public static function sqlBondingMainSn( string $mcb_main_tab = 'a', string $mcb_realtime_tab = 'b', string $output_alias = 'main_sn' ): string
    {
        return 'IF( '.$mcb_realtime_tab.'.bonding != \'\' AND '.$mcb_realtime_tab.'.bonding IS NOT NULL, '.$mcb_realtime_tab.'.bonding, '.$mcb_main_tab.'.sn ) AS ' . $output_alias;
    }

    /**
     * out put a string to SQL to find out the main mcb ID for a kiosk machine if what you want to fine in SQL list with it.
     *
     * @param string $mcb_main_tab
     * @param string $mcb_realtime_tab
     * @param string $output_alias
     * @return string
     */
    public static function sqlBondingMainId( string $mcb_main_tab = 'a', string $mcb_realtime_tab = 'b', string $output_alias = 'main_id' ): string
    {
        return 'CONVERT( CASE
                    WHEN (
                        MAX( CASE
                            WHEN '.$mcb_realtime_tab.'.lcm_id = 1 AND '.$mcb_realtime_tab.'.condition_flg < 3 AND '.$mcb_realtime_tab.'.condition_flg > 0 THEN '.$mcb_main_tab.'.id
                            ELSE 0
                         END ) = 0
                    ) THEN (
                        CASE
                            WHEN (
                                 MAX( CASE
                                    WHEN '.$mcb_realtime_tab.'.condition_flg < 3 AND '.$mcb_realtime_tab.'.condition_flg > 0 THEN '.$mcb_main_tab.'.id
                                    ELSE ( CASE WHEN '.$mcb_realtime_tab.'.lcm_id = 1 THEN '.$mcb_main_tab.'.id ELSE 0 END )
                                 END ) = 0
                            ) THEN (
                                MAX( '.$mcb_main_tab.'.id )
                            ) ELSE (
                                 MAX( CASE
                                    WHEN '.$mcb_realtime_tab.'.condition_flg < 3 AND '.$mcb_realtime_tab.'.condition_flg > 0 THEN '.$mcb_main_tab.'.id
                                    ELSE ( CASE WHEN '.$mcb_realtime_tab.'.lcm_id = 1 THEN '.$mcb_main_tab.'.id ELSE 0 END )
                                 END )
                            )
                        END
                    ) ELSE (
                        MAX( CASE
                            WHEN '.$mcb_realtime_tab.'.lcm_id = 1 AND '.$mcb_realtime_tab.'.condition_flg < 3 AND '.$mcb_realtime_tab.'.condition_flg > 0 THEN '.$mcb_main_tab.'.id
                            ELSE 0
                        END )
                    )
                END, UNSIGNED INTEGER ) AS ' . $output_alias;
    }

    /**
     * Kiosk machine with two or more LCM monitor.
     *
     * IMPORTANT: 這裡最後使用在沒有做任何條件過濾下為前提，先將所有kiosk的condition_flg的重太座統合後，先做第一次的表單輸出之際使用。
     *
     * @param string $mcb_model_tab
     * @param string $output_alias
     * @return string
     */
    public static function sqlIsDualSide( string $mcb_model_tab = 'dm', string $output_alias = 'is_dual' ): string
    {
        return 'CONVERT( CASE
                    WHEN MAX( '.$mcb_model_tab.'.face_total_num ) IS NULL OR MAX( '.$mcb_model_tab.'.face_total_num ) = 1 THEN 0
                    WHEN MAX( '.$mcb_model_tab.'.face_normal_num + '.$mcb_model_tab.'.face_touch_num ) = MAX( '.$mcb_model_tab.'.face_total_num ) AND MAX( '.$mcb_model_tab.'.face_total_num ) >= 2 THEN 1
                    ELSE 0
                END, UNSIGNED INTEGER ) AS ' . $output_alias;
    }

    /**
     * IMPORTANT: 這裡最後使用在沒有做任何條件過濾下為前提，先將所有kiosk的condition_flg的重太座統合後，先做第一次的表單輸出之際使用。
     *
     * @2024-03-20: James 「又」說：offline 要回覆成為凌駕於所有issue之上!!
     *
     * @param string $mcb_model_tab
     * @param string $mcb_realtime_tab
     * @param string $output_alias
     * @return string
     * @author Nick
     */
    public static function sqlBondingConditionFlg( string $mcb_model_tab = 'dm', string $mcb_realtime_tab = 'b', string $output_alias = 'condition_flg' ): string
    {
        return 'CONVERT( CASE
                    WHEN MAX( '.$mcb_model_tab.'.face_total_num ) IS NULL OR MAX( '.$mcb_model_tab.'.face_total_num ) = 1 THEN MAX( '.$mcb_realtime_tab.'.condition_flg )
                    WHEN SUM( '.$mcb_realtime_tab.'.mount ) = MAX( '.$mcb_model_tab.'.face_normal_num + '.$mcb_model_tab.'.face_touch_num ) THEN (
                        CASE
                            WHEN MAX( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 1 AND
                                 MIN( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 1
                            THEN 1
                            WHEN MAX( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 3 AND
                                 MIN( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 3
                            THEN 3
                            WHEN MAX( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 0 AND
                                 MIN( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 0
                            THEN 0
                            ELSE 2
                        END )
                    ELSE (
                        MAX( '.$mcb_realtime_tab.'.condition_flg )
                    )
                END, UNSIGNED INTEGER ) AS ' . $output_alias;
    }

    /**
     * IMPORTANT: 這裡最後使用在沒有做任何條件過濾下為前提，先將所有kiosk的condition_flg的重太座統合後，先做第一次的表單輸出之際使用。
     *
     * @2024-03-20: James 「又」說：offline 要回覆成為凌駕於所有issue之上，而分原本他說要LCM Mount有出現，不管是不是離線，都要顯示ISSUE的這個特例
     *
     * @deprecated 請勿刪除，免得他們又要改回來 XD
     * @param string $mcb_model_tab
     * @param string $mcb_realtime_tab
     * @param string $output_alias
     * @return string
     */
    public static function sqlBondingConditionFlg_org( string $mcb_model_tab = 'dm', string $mcb_realtime_tab = 'b', string $output_alias = 'condition_flg' ): string
    {
        return 'CONVERT( CASE
                    WHEN MAX( '.$mcb_model_tab.'.face_total_num ) IS NULL OR MAX( '.$mcb_model_tab.'.face_total_num ) = 1 THEN MAX( '.$mcb_realtime_tab.'.condition_flg )
                    WHEN SUM( '.$mcb_realtime_tab.'.mount ) = MAX( '.$mcb_model_tab.'.face_normal_num + '.$mcb_model_tab.'.face_touch_num ) THEN (
                        CASE
                            WHEN MAX( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 1 AND
                                 MIN( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 1
                            THEN 1
                            WHEN MAX( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 3 AND
                                 MIN( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 3
                            THEN 3
                            WHEN MAX( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 0 AND
                                 MIN( ( IF( '.$mcb_realtime_tab.'.mount > 0, '.$mcb_realtime_tab.'.condition_flg, NULL ) ) ) = 0
                            THEN 0
                            ELSE 2
                        END )
                    ELSE 2
                END, UNSIGNED INTEGER ) AS ' . $output_alias;
    }

    /**
     * IMPORTANT: 這裡最後使用在沒有做任何條件過濾下為前提，先將所有kiosk的mounting number checking做檢查，先做第一次的表單輸出之際使用。
     *
     * @param string $mcb_model_tab
     * @param string $mcb_realtime_tab
     * @param string $output_alias
     * @return string
     */
    public static function sqlIsMountError( string $mcb_model_tab = 'dm', string $mcb_realtime_tab = 'b', string $output_alias = 'mount_err' ): string
    {
        return 'CONVERT( CASE
                    WHEN MAX( '.$mcb_model_tab.'.face_total_num ) IS NULL THEN 0
                    WHEN SUM( '.$mcb_realtime_tab.'.mount ) = 0 THEN 100
                    WHEN SUM( '.$mcb_realtime_tab.'.mount ) = MAX( '.$mcb_model_tab.'.face_normal_num + '.$mcb_model_tab.'.face_touch_num ) THEN 0
                    WHEN SUM( '.$mcb_realtime_tab.'.mount ) < MAX( '.$mcb_model_tab.'.face_normal_num + '.$mcb_model_tab.'.face_touch_num ) THEN -1
                    WHEN SUM( '.$mcb_realtime_tab.'.mount ) > MAX( '.$mcb_model_tab.'.face_normal_num + '.$mcb_model_tab.'.face_touch_num ) THEN 1
                END, SIGNED INTEGER ) AS ' . $output_alias;
    }
}

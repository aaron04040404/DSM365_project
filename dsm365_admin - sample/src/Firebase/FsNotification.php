<?php
/**
 * 請先依照以下網址的官方指示，再進行Firebase的php相關使用
 * https://firebase.google.com/docs/firestore/quickstart#php
 * 
 * 藉由這個 function 將所有要傳送給使用者端的 notification realtime 訊息，寫入 GCP 上的 Firestore 資料庫。
 * 而 Vue.js 端，會透過 js 的程式與 Firestore 的資料庫做資料的急更新，加強使用者體驗
 * 
 * All displayer management processing controller functions are here.
 * 
 * @author  Nick Feng
 * @since 1.0
 */
namespace Gn\Firebase;

use ErrorException;
use Exception;
use Gn\Interfaces\NotificationRespCodesInterface;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Firestore\FieldValue;

/**
 * user notification_main process from Firebase
 * 
 * @author Nick Feng
 */
class FsNotification extends FsBase implements NotificationRespCodesInterface
{
    const BATCH_MAX_NUM = 500;
    
    /**
     * 
     * @var array
     */
    const NOTI_MAIN_INCREASE_ARR = [
        NotificationRespCodesInterface::NOTIF_CATEGORY_SYS_ALERT    => 'increment_001',
        NotificationRespCodesInterface::NOTIF_CATEGORY_MCB_ALERT    => 'increment_101',
        NotificationRespCodesInterface::NOTIF_CATEGORY_USR_ALERT    => 'increment_201',
        NotificationRespCodesInterface::NOTIF_CATEGORY_UPDATE_ALERT => 'increment_301',
        NotificationRespCodesInterface::NOTIF_CATEGORY_REMOTE_ALERT => 'increment_401'
    ];

    /**
     * Constructor
     *
     * @param array $fs_settings settings of Firebase/Firestore
     * @throws ErrorException
     * @throws GoogleException
     */
    public function __construct( array $fs_settings )
    {
        parent::__construct( $fs_settings );
    }

    /**
     * IMPORTANT: Working for notification category 101 type.
     *
     * @param string $collection The root collection name
     * @param array $mem_arr
     * @param int $category_id
     * @param int $increment
     * @param Exception|null $exception
     * @return int
     */
    public function increaseNotificationNum( string $collection, array $mem_arr, int $category_id, int $increment = 1, Exception &$exception = NULL ): int
    {
        if ( empty( $collection ) || empty( $mem_arr ) || !isset( self::NOTI_MAIN_INCREASE_ARR[ $category_id ] ) ) {
            return self::PROC_INVALID;
        }
        $out = self::PROC_OK;
        $increment_col_name = self::NOTI_MAIN_INCREASE_ARR[ $category_id ];
        $batchMax = self::BATCH_MAX_NUM;
        // 如果數量超過200，則每200筆資料就進行一次總體的寫入動作
        $options = [
            'maxBatchSize'        => self::BATCH_MAX_NUM,
            'initialOpsPerSecond' => self::BATCH_MAX_NUM
        ];
        // 會選擇用這個方式是因為大量寫入的速度，遠快速於 Transaction 的方式 
        $batch = $this->fs->bulkWriter( $options ); 
        try {
            foreach ( $mem_arr as $mem_id ) {
                if ( !is_int( $mem_id ) || $mem_id <= 0 ) {
                    $out = self::PROC_INVALID;
                    break;
                } else {
                    // NOTE: 這邊不需要檢查是否路徑存在。因為它只有一層
                    $documentRef = $this->fs->collection( $collection )->document( $mem_id );
                    $batch->set( 
                        $documentRef, 
                        [ $increment_col_name => ( $increment === 0 ? 0 : FieldValue::increment( $increment ) ) ],
                        [ 'merge' => true ] );
                    $batchMax -= 1;
                    if ( $batchMax <= 0 ) {
                        $batch->flush();
                        $batchMax = self::BATCH_MAX_NUM;    // reset to the beginning
                    }
                }
            }
        } catch ( Exception $e ) {
            if ( !is_null( $exception ) ) {
                $exception = $e;
            }
            $out = self::PROC_SQL_ERROR;
        }
        if ( !$batch->isEmpty() ) {
            $batch->flush();
        }
        $batch->close();
        return $out;
    }
}

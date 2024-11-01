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
use Gn\interfaces\DisplayScreenshotInterface;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Firestore\FieldValue;

/**
 * user notification_main process from Firebase
 * 
 * @author Nick Feng
 */
class FsScreenshot extends FsBase implements DisplayScreenshotInterface
{
    /**
     * Constructor
     *
     * @param array $fs_settings settings of Firebase/Firestore
     * @throws ErrorException|GoogleException
     */
    public function __construct( array $fs_settings )
    {
        parent::__construct( $fs_settings );
    }

    /**
     * to let front-side to know the image is going to make edge device to prepare the image to upload back to the server.
     */
    const USER_FLG_WAITING = 'waiting';
    /**
     * to let front-side to know the image is ready to show on web.
     */
    const USER_FLG_OK = 'ok';
    /**
     * to let front-side to know that is nothing to do.
     */
    const USER_FLG_EMPTY = '';

    const BUSY_FLG_YES = 1;
    const BUSY_FLG_NO = 0;
    const BUSY_FLG_ERR = -1;
    /**
     * IMPORTANT: It will remove the file name is over 1 day ago automatically
     *
     * @param int $mcb_id
     * @param int $user
     * @param Exception|NULL $exception
     * @return int
     */
    public function isBusy( int $mcb_id, int $user, Exception &$exception = NULL ): int
    {
        $is_busy = self::BUSY_FLG_NO;
        try {
            $collection_path = $this->fs_settings['collections']['screenshot']['name'] . '/' . $mcb_id . '/usr';
            $collection = $this->fs->collection($collection_path);

            // 取得所有文檔的 create_on 值
            $fiveMinutesAgo = strtotime('-10 seconds');
            $oneDayAgo = strtotime('-10 minutes');
            $documents = $collection->documents();
            foreach ($documents as $document) {
                $data = $document->data();
                if (isset($data['create_on'])
                    && isset($data['flag'])
                    && isset($data['usr_id'])
                    && $data['create_on']->get()->getTimestamp() > $fiveMinutesAgo
                    && $data['usr_id'] == $user) {
                    $is_busy = self::BUSY_FLG_YES;    // 同一個人，在上一次呼叫截圖的時間，還尚未超過10秒鐘的間隔之時，就拒絕截圖步驟
                } else {
                    // 如果當一個檔案名稱已經存在超過一天，就把它順便清除，節省空間
                    if ( $data['create_on']->get()->getTimestamp() <= $oneDayAgo ) {
                        $documentId = $document->id();  // get file name of document ID
                        $this->deleteData( $collection_path, $documentId, $exception );
                        if (!is_null($exception)) {
                            $is_busy = self::BUSY_FLG_ERR;
                        }
                    }
                }
            }
        } catch ( Exception $e ) {
            $is_busy = self::BUSY_FLG_ERR;
            if ( !is_null( $exception ) ) {
                $exception = $e;
            }
        }
        return $is_busy;
    }

    /**
     *
     * @param int $mcb_id
     * @param string $rand_filename
     * @param Exception|null $exception
     * @return int
     */
    public function setMcbFlag( int $mcb_id, string $rand_filename, Exception &$exception = NULL ): int
    {
        if ( $mcb_id <= 0 || !preg_match( DisplayScreenshotInterface::GCS_SCREENSHOT_FILE_HEADER_PREG , $rand_filename ) ) {
            return self::PROC_INVALID;
        }
        $collection_path = $this->fs_settings['collections']['screenshot']['name'] . '/' . $mcb_id . '/mcb';
        return $this->addData( $collection_path, 'ready', [ 'flag' => $rand_filename ], TRUE, $exception );
    }

    /**
     *
     * @param int $mcb_id
     * @param string $rand_filename for the second collection name
     * @param string $flag
     * @param int $user_id
     * @param Exception|null $exception
     * @return int
     */
    public function setUsrFlag(
        int $mcb_id,
        string $rand_filename,
        string $flag,
        int $user_id = 0,
        Exception &$exception = NULL ): int
    {
        if( $mcb_id <= 0 || $user_id < 0
            || !preg_match( DisplayScreenshotInterface::GCS_SCREENSHOT_FILE_HEADER_PREG , $rand_filename ) )
        {
            return static::PROC_INVALID;
        }
        switch( $flag ) {
            case self::USER_FLG_WAITING :
            case self::USER_FLG_OK:
            case self::USER_FLG_EMPTY:
                break;  // let it keep going
            default:
                return static::PROC_INVALID;
        }

        $data = [
            'flag' => $flag,
            'create_on' => FieldValue::serverTimestamp() // time()
        ];
        if( $user_id > 0 ) {
            $data['usr_id'] = $user_id;
        }
        $collection_path = $this->fs_settings['collections']['screenshot']['name'] . '/' . $mcb_id . '/usr';
        return $this->addData( $collection_path, $rand_filename, $data, TRUE, $exception );
    }

    /**
     * @param int $mcb_id
     * @param string $rand_filename
     * @param int $user_id
     * @param Exception|NULL $exception
     * @return int
     */
    public function removeUsrTrigger( int $mcb_id, string $rand_filename, int $user_id, Exception &$exception = NULL ): int
    {
        if( $mcb_id <= 0 || $user_id < 0
            || !preg_match( DisplayScreenshotInterface::GCS_SCREENSHOT_FILE_HEADER_PREG , $rand_filename ) )
        {
            return static::PROC_INVALID;
        }
        $collection_path = $this->fs_settings['collections']['screenshot']['name'] . '/' . $mcb_id . '/usr';
        return $this->deleteData( $collection_path, $rand_filename, $exception );
    }
}

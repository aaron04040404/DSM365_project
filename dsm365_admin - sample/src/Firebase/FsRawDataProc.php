<?php
/**
 * 請先依照以下網址的官方指示，再進行Firebase的php相關使用
 * https://firebase.google.com/docs/firestore/quickstart#php
 * 
 * 藉由這個 function 將所有要傳送給使用者端的 realtime 訊息，寫入 GCP 上的 Firestore 資料庫。
 * 而 Vue.js 端，會透過 js 的程式與 Firestore 的資料庫做資料的急更新，加強使用者體驗
 * 
 * All raw data update processing controller functions are here.
 * 
 * @author Nick Feng
 * @since 1.0
 */
namespace Gn\Firebase;

use ErrorException;
use Exception;
use Gn\Lib\StrProc;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Firestore\FieldValue;

/**
 *
 * @author Nick Feng
 */
class FsRawDataProc extends FsBase
{
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
     *
     * @param int $mcb_id
     * @param string $hash_str
     * @param Exception|NULL $exc
     * @return int
     */
    public function updateRawFlag( int $mcb_id, string $hash_str, Exception &$exc = NULL ):int
    {
        if ( !preg_match( StrProc::MD5_HASH_PREG, $hash_str ) || $mcb_id <= 0 ) {
            return self::PROC_INVALID;
        }
        return $this->addData(
            $this->fs_settings['collections']['mcb_raw']['name'],
            $mcb_id, [
                'hash_id' => $hash_str,
                'last_on' => FieldValue::serverTimestamp() // time()
            ],
            TRUE, $exc
        );
    }
}

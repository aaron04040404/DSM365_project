<?php
/**
 * 請先依照以下網址的官方指示，再進行Firebase的php相關使用
 * https://firebase.google.com/docs/firestore/quickstart#php
 *
 * All displayer management processing controller functions are here.
 * 
 * @author Nick Feng
 * @since 1.0
 */
namespace Gn\Firebase;

use ErrorException;
use Exception;
use Gn\Interfaces\BaseRespCodesInterface;

use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Firestore\FirestoreClient;

/**
 * user notification process from Firebase
 * 
 * @author Nick Feng
 */
abstract class FsBase implements BaseRespCodesInterface
{
    protected $fs = NULL;
    
    protected $fs_settings = NULL;

    /**
     * Constructor
     *
     * @param array $fs_settings
     * @throws ErrorException
     * @throws GoogleException
     * @author Nick, Cleo
     */
    protected function __construct( array $fs_settings )
    {
        if ( empty( $fs_settings['projectId'] ) || !is_string( $fs_settings['projectId'] ) ) {
            throw new ErrorException( 'Firebase settings protect ID error!' );
        } else if ( empty( $fs_settings['service_account_key'] ) || !is_string( $fs_settings['service_account_key'] ) ) {
            throw new ErrorException( 'Firebase service key error!' );
        }
        $service_account_key_txt = file_get_contents( $fs_settings['service_account_key'] );
        if ( $service_account_key_txt === false ) {
            throw new ErrorException( 'Firebase service key error!' );
        } 
        
        $this->fs = new FirestoreClient( [
            'projectId' => $fs_settings['projectId'],
            'keyFile'   => json_decode( $service_account_key_txt, true )
        ] );
        
        $this->fs_settings = $fs_settings;
    }

    /**
     * 關閉 Firestore 客戶端
     */
    public function __destruct()
    {
        $this->fs = NULL;
    }

    /**
     * 確認add的資料，所有parent是否健全並且存在。如果不存在，會把路徑補齊
     * 
     * @param string $collection
     * @param string $documentId
     */
    protected function checkPath ( string $collection, string $documentId )
    {
        $documentRef = $this->fs->collection( $collection )->document( $documentId );
        $snapshot    = $documentRef->snapshot();
        if ( !$snapshot->exists() ) {
            $documentRef->set([]);
            
            $path_arr    = explode( '/', $collection );
            // must pop twice
            $parent_doc  = array_pop( $path_arr );
            $parent_doc  = array_pop( $path_arr );
            $parent_path = implode( '/', $path_arr );
            if ( !is_null( $parent_doc ) ) {
                $this->checkPath( $parent_path, $parent_doc );
            }
        }
    }

    /**
     *
     * @param string $collection
     * @param string $documentId
     * @param array $data
     * @param bool $checkPath
     * @param Exception|null $exception
     * @return int
     * @author Nick, Cleo
     */
    protected function addData( string $collection, string $documentId, array $data, bool $checkPath = FALSE, Exception &$exception = NULL ): int
    {
        $collection = trim( $collection );
        if ( empty( $collection ) || empty( $documentId ) || $collection === '/' ) {
            return self::PROC_INVALID;
        }
        
        try {
            if ( $checkPath ) {
                $this->checkPath( $collection, $documentId );
            }
            $documentRef = $this->fs->collection( $collection )->document( $documentId );
            $documentRef->set( $data, [ 'merge' => true ] );
            return self::PROC_OK;
        } catch ( Exception $e ) {
            if ( !is_null( $exception ) ) {
                $exception = $e;
            }
            return self::PROC_SQL_ERROR;
        }
    }

    /**
     *
     * @param string $collection
     * @param string $documentId
     * @param Exception|null $exception
     * @return int
     * @author Nick, Cleo
     */
    protected function deleteData( string $collection, string $documentId, Exception &$exception = NULL ): int
    {
        if ( empty( $collection ) || empty( $documentId ) ) {
            return self::PROC_INVALID;
        }
        try {
            $documentRef = $this->fs->collection( $collection )->document( $documentId );
            $snapshot = $documentRef->snapshot();
            if ( $snapshot->exists() ) {
                $documentRef->delete();
            }
            return self::PROC_OK;
        } catch ( Exception $e ) {
            if ( !is_null( $exception ) ) {
                $exception = $e;
            }
            return self::PROC_SQL_ERROR;
        }
    }

    /**
     *
     * @param string $collection
     * @param string $documentId
     * @param Exception|null $exception
     * @return array|int
     * @author Nick, Cleo
     */
    protected function getData( string $collection, string $documentId, Exception &$exception = NULL )
    {
        if ( empty( $collection ) || empty( $documentId ) ) {
            return self::PROC_INVALID;
        }
        try {
            $documentRef = $this->fs->collection( $collection )->document( $documentId );
            $snapshot = $documentRef->snapshot();
            return $snapshot->exists() ? $snapshot->data() : self::PROC_INVALID;
        } catch ( Exception $e ) {
            if ( !is_null( $exception ) ) {
                $exception = $e;
            }
            return self::PROC_SQL_ERROR;
        }
    }
}

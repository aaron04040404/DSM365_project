<?php
/**
 * File io functions.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Lib;

/**
 * Class deals with file io preparation.
 * @author Nick Feng
 */
class FileIO
{
    /**
     * check string format in Linux file name rule.
     * @var string
     */
    const LINUX_FILENAME_SPECIAL_CHAR = '/^[^*&%\s]+$/';
    
    /**
     * 
     * @param string $dir
     * @param string $filename
     * @param string $data
     * @param int $chmodCode
     * @return bool|int
     */
    public static function saveFile (string $dir, string $filename, string $data, int $chmodCode = 0775)
    {
        // If the folder is not existed with nested directories
        if( !is_dir( $dir ) ) {
            if (!mkdir( $dir, $chmodCode, true )) {
                return false;
            }
        }
        // after tmp folder is ready, save it.
        if( is_dir( $dir ) ) {
            $file = $dir . DIRECTORY_SEPARATOR . $filename;
            $tmpFile = fopen( $file, 'w');
            if ( $tmpFile !== false ) {
                $num_written = fwrite( $tmpFile, $data );
                if ( $num_written !== false ) {
                    if ( chmod( $file, $chmodCode ) !== false ) { // change permission
                        return $num_written;
                    }
                }
            }
            return false;
        }
    }
    
    /**
     * Jump the special character.
     * <p>+-.*?><;&!\[\]\|\\\'\"\`\(\)\{\}</p>
     * ^ $ * + ? { } [ ] \ | ( )
     *
     * @param mixed $files object|array
     * @param int $limitSize
     * @param string $type
     * @param string $extension
     * @param string $name_reg
     * @return string Empty string is for fail.
     */
    public static function checkUploadFile ( $files, int $limitSize, string $type, string $extension,
        string $name_reg = self::LINUX_FILENAME_SPECIAL_CHAR ): string
    {
        // for file upload error messages
        $FILE_UPLOAD_ERRORS = array (
            0 => 'There is no error, the file uploaded with success',
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk.',
            8 => 'A PHP extension stopped the file upload.',
        );
        
        foreach ( $files as $val ) {
            // check elements is in the same count
            $elemNum = count( $val['error'] ) & count( $val['name'] ) & count( $val['size'] ) & count( $val['tmp_name'] ) & count( $val['type'] );
            if( $elemNum == 0 ) {
                return 'data error';
            }
            
            for( $i = 0; $i < $elemNum; $i++ ) {
                // check error code
                if( $val['error'][$i] > UPLOAD_ERR_OK ) {
                    return $FILE_UPLOAD_ERRORS[ $val['error'][$i] ];
                }
                // check apk file size: less than 50MB ( 50000000 bytes)
                if( $val['size'][$i] > $limitSize || $val['size'][$i] == 0 ) {
                    return 'size error';
                }
                // check file type
                if( strcmp( $val['type'][$i], $type ) < 0 ) { //'application/octet-stream'
                    return 'file type error: ';
                }
                // check name and *.apk *.mp4....(extension)
                if( empty( $val['name'][$i] ) || !preg_match( $name_reg, $val['name'][$i] ) ) {
                    return 'file name error';
                }
                //check file extension
                if( $extension != '*' && strlen( $extension ) > 0 ) {
                    if( pathinfo( $val['name'][$i], PATHINFO_EXTENSION ) != $extension ) {
                        return 'file extension error';
                    }
                }
            }
        }
        return '';
    }
    
    /**
     * Give a file random name in md5 with extension.
     * 
     * @param string $fileName
     * @return string
     */
    public static function genRandomFileName( string $fileName ): string
    {
        $ext = pathinfo( $fileName, PATHINFO_EXTENSION );
        return md5( uniqid( $fileName, TRUE ) ).'.'.$ext;
    }
    
    /**
     * Return a calculate size string of file or directory.
     * 
     * @param float $bytes
     * @return string Return string is like 10 GB or FALSE for fail.
     */
    public static function convertFileToReadableSize(float $bytes): string
    {
        $si_prefix = array('B','KB','MB','GB','TB','EB','ZB','YB');
        $base = 1024;
        $class = min((int)log($bytes, $base), (count($si_prefix) - 1));
        return sprintf('%1.2f', ($bytes / pow($base, $class))).' '.$si_prefix[$class];
    }
    
    /**
     * Count all files in folder and sub-folder.
     * (Ensure that the path contains an ending slash)
     *
     * Notice: the Linux folder permission will make it fail.
     *         Please set right permission for project folder.
     *
     * @param string $path where you want to count.
     * @return int
     */
    public static function countFile(string $path): int
    {
        $file_count = 0;
        $dir_handle = @opendir($path);
        if (!$dir_handle) {
            return -1;
        }
        while ($file = readdir($dir_handle)) {
            if ($file == '.' || $file == '..') continue;
            if (is_dir($path . $file)){
                $file_count += self::countFile($path . $file . DIRECTORY_SEPARATOR);
            } else {
                $file_count++; // increase file count
            }
        }
        closedir($dir_handle);
        return $file_count;
    }
}
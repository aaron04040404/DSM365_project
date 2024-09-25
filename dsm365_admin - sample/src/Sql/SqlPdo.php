<?php
/**
 * MySQL connection.
 *
 * @author  Nick Feng
 *
 * @since 1.0
 */
namespace Gn\Sql;

use PDO;
use PDOException;

/**
 * A singleton class. You must use SqlPdo::getInstance('database connection required info in array.') 
 * to get connection instance.
 * @author Nick Feng
 *
 */
class SqlPdo
{
    /**
     * All connecting pdo are saved in the array. If requested one is existed inside, it won't open a new one.
     * 
     * @var array
     */
    private static $pdo_instances = [];
    
    /**
     * It is the true constructor for singleton.
     * 
     * NOTE: When using the method, you are in fact getting back an alias for SqlPdo::$pdo_instances 
     *       -- a different name by which you refer to the same variable. 
     *       So if you do anything with it (including modifying its contents) 
     *       you are in fact directly performing the same action on the value of the property.
     * 
     * @param array $db Such like this : [
     *     'host'     => 'mysql:host=127.0.0.1;port=3306;dbname=[MySQL database name];charset=[utf8mb4]',
	 *     'user'     => 'username',
	 *     'password' => 'user password',
	 *     'option'   => [
	 *          \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
	 *          \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
	 *          \PDO::ATTR_EMULATE_PREPARES   => false,
	 *          \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
	 *          \PDO::MYSQL_ATTR_SSL_KEY      => __DIR__ . '/../../security/ssl/client-key.pem',
	 *          \PDO::MYSQL_ATTR_SSL_CERT     => __DIR__ . '/../../security/ssl/client-cert.pem',
	 *          \PDO::MYSQL_ATTR_SSL_CA       => __DIR__ . '/../../security/ssl/server-ca.pem',
	 *          \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
	 *      ]
     * ]
     */
    public static function &getInstance(array $db)
    {
        $servIndex = md5( $db['host'] . '|' . $db['user'], false );
        if ( !array_key_exists( $servIndex, self::$pdo_instances ) ) {
            try {
                $pdo = new PDO( $db['host'], $db['user'], $db['password'], $db['option'] );
                self::$pdo_instances[ $servIndex ] = $pdo;
            } catch ( PDOException $error ) {
                die( 'App database process error[' . $error->getCode() . ']: ' . $error->getMessage() );
            }
        }
        return self::$pdo_instances[ $servIndex ];
    }
    
    /**
     * private and unused
     */
    private function __construct() {}
    
    /**
     * destructor: Close all PDO connections before instance deleted
     */
    public function __destruct()
    {
        foreach (self::$pdo_instances as $k => $v ) {
            unset( $v );
            unset( self::$pdo_instances[ $k ] );
        }
    }
}
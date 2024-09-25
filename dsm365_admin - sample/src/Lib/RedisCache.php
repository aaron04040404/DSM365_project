<?php
/**
 * First, ensure the Redis module for PHP is installed, and mount to apache already.
 * Second, set up the Redis config file for memory limit and user account and password.
 * 
 * Finally, use Composer to install these php frameworks
 * 1. Predis\Client
 * 2. Symfony\Component\Cache\Adapter\RedisAdapter
 *  
 * 
 * Please initialize a standard object for redis memory cache in Slim3 dependencies.php at last
 * .
 * $container['redis'] = function ($c) {
 *     $settings = $c->get('settings')['redis']['normal'];
 *     if ($settings['status'] === true) {
 *         try {
 *             $config = [
 *                 'scheme' => $settings['scheme'],
 *                 'host'   => $settings['host'],
 *                 'port'   => $settings['port']
 *             ];
 *             $options = [
 *                 //'replication' => 'sentinel',
 *                 //'service' => 'mymaster',
 *                 'parameters' => [
 *                     'password' => $settings['password'],
 *                     'database' => $settings['database']
 *                 ],
 *             ];
 *             $conn = new \Predis\Client( $config, $options );
 *             return new \Gn\Lib\RedisCache( new \Symfony\Component\Cache\Adapter\RedisAdapter( $conn ) );
 *         } catch (\Exception $e) {
 *             $c->logger->notice('redis cache inactivate: ' . $e->getMessage());
 *         }
 *     }
 *     return false;
 * };
 *
 */
namespace Gn\Lib;

use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Base on Redis
 * @author nick
 *
 */
class RedisCache
{
    /**
     * for redis module for php
     * @var RedisAdapter
     */
    protected $cache = NULL;
    
    /**
     * Default expired time.
     *
     * @var array
     */
    const CACHE_EXP_SEC = [
        '1s'  => 1,
        '5s'  => 5,
        '10s' => 10,
        '15s' => 15,
        '30s' => 30,
        '1m'  => 60,
        '3m'  => 180,       // 3 min
        '5m'  => 300,       // 5 min
        '30m' => 1800,
        '1h'  => 3600,
        'never' => 315360000// 10 years
    ];


    /**
     * Constructor, and look up jwt id automatically when it called.
     *
     * @param $cacheAdapter
     * @throws Exception
     */
    public function __construct ($cacheAdapter)
    {
        if ( $cacheAdapter instanceof RedisAdapter ) {
            $this->cache = $cacheAdapter;
        } else {
            throw new Exception('redis cache adapter is not \Symfony\Component\Cache\Adapter\RedisAdapter type');
        }
    }
    
    /**
     * Destructor, and commit all in redis before class destroyed.
     */
    public function __destruct()
    {
        if (self::isCaching()) {
            $this->cache->commit(); // commit all none-saved in cache
        }
    }
    
    /**
     * Return true is redis working now, or return false.
     * @return boolean
     */
    public function isCaching(): bool
    {
        return !is_null( $this->cache );
    }

    /**
     * check the key is existed or not.
     *
     * @param string $key
     * @return boolean
     * @throws InvalidArgumentException
     */
    public function hasKey (string $key): bool
    {
        if (self::isCaching()) {
            return $this->cache->hasItem( md5( $key ) );
        }
        return false;
    }
    
    /**
     * To check the pre-data is still existed in cache, if it is, return data, or return FALSE on failure.
     *
     * @param string $key
     * @return mixed If there is pre-values inside cache, return values in mixed structure, or return false.
     */
    public function detectCache ( string $key )
    {
        if (self::isCaching()) {
            try {
                $item = $this->cache->getItem( md5( $key ) );
            } catch (InvalidArgumentException $e ) {
                return false;
            }
            if ($item->isHit()) {
                return $item->get();
            }
        }
        return false;
    }

    /**
     * No matter what the new data you want to save is, it is no need to check it contents empty or not.
     *
     * @param string $key
     * @param mixed $new_data
     * @param int $expSec
     * @return boolean
     * @throws InvalidArgumentException
     */
    public function saveCache ( string $key, $new_data, int $expSec = RedisCache::CACHE_EXP_SEC['30s'] ): bool
    {
        if ( self::isCaching() ) {
            try {
                $item = $this->cache->getItem( md5( $key ) );
                $item->set( $new_data );
                $item->expiresAfter( $expSec );
                return $this->cache->save( $item );
            } catch ( Exception $e ) {}
        }
        return false;
    }

    /**
     *
     * @param string $key
     * @return boolean
     * @throws InvalidArgumentException
     */
    public function deleteCache ( string $key ): bool
    {
        if (self::isCaching()) {
            return $this->cache->deleteItem( md5( $key ) );
        }
        return false;
    }
    
    /**
     * Clear all in cache with Redis.
     *
     * @return boolean
     */
    public function clearAll (): bool
    {
        if (self::isCaching()) {
            return $this->cache->clear();
        }
        return false;
    }
}
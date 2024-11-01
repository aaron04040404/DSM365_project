<?php
namespace Gn\Obj;

/**
 * 這個object 主要是給 client 的用戶使用。client (TCC)端必須限制有哪些 raw data 下的指令的哪個欄位，是客戶可以看到的資料。
 * 每個資料底下都會帶個next name 圍下個路徑。
 * 
 * @author nickfeng
 *
 */
class DsRawAccessObj
{
    /**
     * raw command code in decimal
     * 
     * @var int
     */
    private $cmd;
    
    /**
     * how to go down the path in JSON structure.
     * 
     * @var array
     */
    private $jsonPath;

    /**
     *
     * @param int $cmd
     * @param array $properties_path
     */
    public function __construct( int $cmd, array $properties_path )
    {
        $this->cmd = $cmd;
        $this->jsonPath = $properties_path;
    }
    
    /**
     * remove all in memory.
     */
    public function __destruct()
    {
        unset( $this->cmd );
        unset( $this->jsonPath );
    }
    
    /**
     * 
     * @param string $name
     * @return boolean|int
     */
    public function pushPath( string $name )
    {
        $name = preg_replace( '/\s+/', '', $name );
        if ( empty( $name ) ) {
            return false;
        }
        return array_push( $this->jsonPath, $name );
    }
    
    /**
     * 
     * @param int $idx
     * @return mixed
     */
    public function getPath( int $idx )
    {
        return $this->jsonPath[$idx] ?? NULL;
    }
    
    /**
     * 
     * @return int
     */
    public function cmd(): int
    {
        return $this->cmd;
    }
    
    /**
     * 
     * @return array
     */
    public function path(): array
    {
        return $this->jsonPath;
    }
    
    /**
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->cmd < 0 || $this->cmd > 255 || empty($this->jsonPath)) {
            return false;
        }
        foreach ( $this->jsonPath as $value ) {
            $value = preg_replace( '/\s+/', '', $value );
            if (!is_string($value) || empty($value) || strlen($value) > 50) {
                return false;
            }
        }
        return true;
    }
}

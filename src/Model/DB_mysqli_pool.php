<?php
// Swoole Coroutine MySQL
namespace ePHP\Model;

use ePHP\Core\Config;
use ePHP\Model\DBPool;

class DB_mysqli_pool extends DB_mysqli
{
    public $db = false;

    /**
     * Default db config
     *
     * @var string
     */
    public $db_config = 'default';

    /**
     * Current db config
     *
     * @var array
     */
    protected $config = [];

    function __construct($db_config = 'default')
    {
        $this->db_config = $db_config;

        if (false == ($config = Config::get('dbconfig.' . $db_config))) {
            \throw_error('invalid database configuration', 12005);
        }

        // Set default value
        $this->config = $config + array(
            'host'      => '127.0.0.1',
            'user'      => '',
            'password'  => '',
            'database'  => '',
            'port'      => 3306,
            'charset'   => 'utf8',
            'tb_prefix' => '',
            'timeout'   => 3,

            'idle_pool_size'=> 1,
            'max_pool_size'=> 2
        );
    }

    /**
     * Get one MySQL connect from pool
     *
     * @return bool
     */
    private function prepareDB()
    {
        $dbpool = DBPool::init($this->db_config);
        // var_dump($dbpool->cap + $dbpool->activeCount, $dbpool->queue->isEmpty(), $dbpool->queue->count());

        if ($dbpool->queue->isEmpty() && ($dbpool->cap + $dbpool->activeCount >= $this->config['max_pool_size'])) {
            \throw_error('MySQL pool is empty...', 12009);
        }

        if ($dbpool->cap < $this->config['idle_pool_size']
            || ($dbpool->queue->isEmpty() && $dbpool->cap < $this->config['max_pool_size'])
        ) {
            // var_dump('........................reconnect........................');
            $this->reconnect();
            $dbpool->activeCount++;
            return false;
        } else {
            // var_dump('........................using pool........................');
            $this->db = $dbpool->out($this->config['idle_pool_size']);
            return true;
        }
    }

    /**
     * MySQL query
     *
     * @param string $sql
     * @return object
     */
    public function query($sql)
    {
        if (true == Config::get('sql_log')) {
            wlog('SQL-Log', $sql);
        }

        // Prepare one MySQL client connect
        $isFromPool = $this->prepareDB();

        try {
            $rs = $this->db->query($sql);
        } catch (\Exception $e) {
            // echo 'error:'. $e->getCode() . $e->getMessage();
            // 如果发生了mysqli::query(): MySQL server has gone away，直接抛出异常，
            // 需要捕获后，重新reconnect
        }

        if ( !empty($rs) ) {
            \Swoole\Coroutine::getContext()['__$DB_QUERY_COUNT']++;
        } else if($this->db->errno == 2006 || $this->db->errno == 2013) {
            // Catch database pool long running
            // 2013 Lost connection to MySQL server during query
            // 2006 MySQL server has gone away
            try {
                $this->reconnect();
            } catch (\Exception $e) {
                DBPool::init($this->db_config)->cap--;
                DBPool::init($this->db_config)->activeCount--;
                \throw_error('DB_ERROR: ' . $this->db->error . "\nRAW_SQL: " . $sql, $this->db->errno);
            }

            \Swoole\Coroutine::getContext()['__$DB_QUERY_COUNT']++;
            $rs = $this->db->query($sql);
        } else {
            DBPool::init($this->db_config)->cap--;
            DBPool::init($this->db_config)->activeCount--;
            \throw_error('DB_ERROR: ' . $this->db->error . "\nRAW_SQL: " . $sql, $this->db->errno);
        }

        // Put db connction to pool
        // If it is new client, queue in, else put pool client back
        if ($isFromPool) {
            DBPool::init($this->db_config)->back($this->db);
        } else {
            DBPool::init($this->db_config)->in($this->db);
        }

        // echo 'db_config='. $this->db_config;
        // echo 'activeCount='. (DBPool::init($this->db_config)->activeCount);
        // echo ',cap='. (DBPool::init($this->db_config)->cap);
        // var_dump(DBPool::init($this->db_config)->queue);

        return $rs;
    }

    /**
     * 过滤SQL中的不安全字符
     *
     * @param string $str
     */
    public function escape_string($str)
    {
        // Prepare one MySQL client connect
        $isFromPool = $this->prepareDB();

        $str = $this->db->real_escape_string($str);

        if ($isFromPool) {
            DBPool::init($this->db_config)->back($this->db);
        } else {
            DBPool::init($this->db_config)->in($this->db);
        }

        return $str;
    }

    /*
     * Set auto commit
     *
     * @param bool $f
     * @return bool
     */
    public function autocommit($f)
    {
        \throw_error('DB_mysqli_pool not support transaction', 12021);
    }

    /*
     * Commit transaction
     *
     * @return bool
     */
    public function commit()
    {
        \throw_error('DB_mysqli_pool not support transaction', 12022);
    }

    /*
     * Roollback transaction
     *
     * @return bool
     */
    public function rollback()
    {
        \throw_error('DB_mysqli_pool not support transaction', 12023);
    }

    function __destruct()
    {
    }
}

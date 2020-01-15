<?php
// Swoole Coroutine MySQL
namespace ePHP\Model;

use ePHP\Core\Config;
use ePHP\Model\DBPool;

class DB_mysqlco
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
    private $config = [];

    /**
     * Last insert id
     *
     * @var integer
     */
    private $insert_id = 0;

    /**
     * Affected rows
     *
     * @var integer
     */
    private $affected_rows = 0;

    function __construct($db_config = 'default')
    {
        $this->db_config = $db_config;

        if (false == ($config = Config::get('dbconfig.' . $db_config))) {
            \throw_error('invalid database configuration', 12005);
        }

        // Rename key dbname->database
        if (isset($config['dbname'])) {
            $config['database'] = $config['dbname'];
            unset($config['dbname']);
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

    private function reconnect()
    {
        $db = new \Swoole\Coroutine\MySQL();

        if ( !$db->connect($this->config) ) {
            \throw_error('Can not connect to MySQL server', 12006);
        }

        $this->db = $db;
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

        // If disconnect, Must connection
        if (!$this->db->connected) {
            // var_dump('........................mysql disconnected........................');
            $this->reconnect();
        }

        $this->db->setDefer();
        $this->db->query($sql);
        $result = $this->db->recv();

        // Fix Lost connection to MySQL server during query
        // MySQL server has gone away
        if($this->db->errno == 2006 || $this->db->errno == 2013) {
            // var_dump('........................mysql go away........................');
            $this->reconnect();

            $this->db->setDefer();
            $this->db->query($sql);
            $result = $this->db->recv();
        }

        // Put db connction to pool
        // If it is new client, queue in, else put pool client back
        if ($isFromPool) {
            DBPool::init($this->db_config)->back($this->db);
        } else {
            DBPool::init($this->db_config)->in($this->db);
        }

        if ($this->db->errno == 0) {
            $GLOBALS['__$DB_QUERY_COUNT']++;
        } else {
            \throw_error('DB_ERROR: ' . $this->db->error . "\nRAW_SQL: " . $sql, 12045);
        }

        $this->insert_id = $this->db->insert_id;
        $this->affected_rows = $this->db->affected_rows;

        return $result;
    }

    /**
     * Return last insert id
     *
     * @return int $insert_id
     */
    public function insert_id()
    {
        return $this->insert_id;
    }

    /**
     * Affected_rows
     *
     * @return int $affected_rows
     */
    public function affected_rows()
    {
        return $this->affected_rows;
    }

    /**
     * fetch one row, return array
     * if empty return []
     *
     * @param string $sql
     * @return array
     */
    public function fetch_array($sql)
    {
        $result = $this->query($sql);
        if (!empty($result)) {
            return $result[0];
        } else {
            return [];
        }
    }

    /**
     * fetch many rows, return array
     *
     * @param string $sql
     * @return array
     */
    public function fetch_arrays($sql)
    {
        return $this->query($sql);
    }

    /**
     * fetch one row, return object
     *
     * @param string $sql
     * @return object
     */
    public function fetch_object($sql)
    {
        return (object)$this->fetch_array($sql);
    }

    /**
     * fetch many rows, return object
     * if empty return []
     *
     * @param string $sql
     * @return object
     */
    public function fetch_objects($sql)
    {
        $data = [];
        $result = $this->fetch_arrays($sql);
        foreach ($result as $v) {
            $data[] = (object)$v;
        }

        return $data;
    }

    /**
     * Escape SQL string
     *
     * @return string $str
     */
    public function escape_string($str)
    {
        return addslashes($str);
    }

    /*
     * Set auto commit transaction
     *
     * @param bool $f
     * @return bool
     */
    public function autocommit($f)
    {
        return true;
    }

    /*
     * Commit transaction
     *
     * @return bool
     */
    public function commit()
    {
        return true;
    }

    /*
     * Roollback transaction
     *
     * @return bool
     */
    public function rollback()
    {
        return true;
    }
}

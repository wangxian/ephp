<?php
// Swoole Coroutine MySQL
namespace ePHP\Model;

use ePHP\Core\Config;
use ePHP\Model\DBPool;
use ePHP\Exception\ExitException;

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
    private $iconfig = [];

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
        $db_config = 'dbconfig.' . $db_config;
        if (false == ($iconfig = Config::get($db_config))) {
            \show_error('Invalid database configuration！');
        }

        if (empty($iconfig['port'])) {
            $iconfig['port'] = 3306;
        }

        if (empty($iconfig['idle_pool_size'])) {
            $iconfig['idle_pool_size'] = 5;
        }

        if (empty($iconfig['max_pool_size'])) {
            $iconfig['max_pool_size'] = 10;
        }

        if (empty($iconfig['timeout'])) {
            $iconfig['timeout'] = 10;
        }

        if (empty($iconfig['charset'])) {
            $iconfig['charset'] = 'utf8';
        }

        $this->db_config = $db_config;
        $this->iconfig = $iconfig;
    }

    private function reconnect()
    {
        $this->db = new \Swoole\Coroutine\MySQL();
        $this->db->connect([
            'host' => $this->iconfig['host'],
            'port' => $this->iconfig['port'],
            'user' => $this->iconfig['user'],
            'password' => $this->iconfig['password'],
            'database' => $this->iconfig['dbname'],
            'timeout' => $this->iconfig['timeout'],
            'charset' => $this->iconfig['charset']
        ]);

        if (!$this->db->connected) {
            throw new ExitException('Can not connect to your MySQL Server. Then exit');
        }
    }

    /**
     * Get one MySQL connect from pool
     *
     * @return bool
     */
    private function prepareDB()
    {
        $dbpool = DBPool::init($this->db_config);
        // var_dump($dbpool->cap + $dbpool->acticeCount, $dbpool->queue->isEmpty(), $dbpool->queue->count());

        if ($dbpool->queue->isEmpty() && ($dbpool->cap + $dbpool->acticeCount >= $this->iconfig['max_pool_size'])) {
            throw new ExitException('MySQL pool is empty...');
        }

        if ($dbpool->cap < $this->iconfig['idle_pool_size']
            || ($dbpool->queue->isEmpty() && $dbpool->cap < $this->iconfig['max_pool_size'])
        ) {
            // var_dump('........................reconnect........................');
            $this->reconnect();
            $dbpool->acticeCount++;
            return false;
        } else {
            // var_dump('........................using pool........................');
            $this->db = $dbpool->out($this->iconfig['idle_pool_size']);
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

        // $this->db->setDefer();
        $this->db->query($sql);
        // $result = $this->db->recv();

        // Fix Lost connection to MySQL server during query
        // MySQL server has gone away
        // if($this->db->errno == 2006 || $this->db->errno == 2013)
        // {
        //     var_dump('........................mysql go away........................');
        //     $this->reconnect();

        //     $this->db->setDefer();
        //     $this->db->query($sql);
        //     $result = $this->db->recv();
        // }

        // Put db connction to pool
        // If it is new client, queue in, else put pool client back
        if ($isFromPool) {
            DBPool::init($this->db_config)->back($this->db);
        } else {
            DBPool::init($this->db_config)->in($this->db);
        }

        if ($this->db->errno == 0) {
            $_SERVER['__DB_QUERY_COUNT']++;
        } else {
            throw_error('DB_ERROR: ' . $this->db->error . "\nRAW_SQL: " . $sql, 2045);
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
        // if ($f)
        // {
        //     return $this->query('SET AUTOCOMMIT=1');
        // }
        // else
        // {
        //     // Disable auto commit
        //     $this->query('SET AUTOCOMMIT=0');
        //     return $this->query('START TRANSACTION');
        // }
    }

    /*
     * Commit transaction
     *
     * @return bool
     */
    public function commit()
    {
        return true;
        // return $this->query('COMMIT');
    }

    /*
     * Roollback transaction
     *
     * @return bool
     */
    public function rollback()
    {
        return true;
        // return $this->query('ROLLBACK');
    }
}

<?php
// Swoole Coroutine MySQL
namespace ePHP\Model;

use ePHP\Core\Config;

class DB_mysqlco
{
    public $db = false;

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
                'timeout'   => 3
            );

        $this->reconnect();
    }

    private function reconnect()
    {
        $db = new \Swoole\Coroutine\MySQL();

        if ( !$db->connect($this->config) ) {
            \throw_error('Can not connect to MySQL server, message: ' . $db->connect_error, 12006);
        }

        $this->db = $db;
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

        $this->db->setDefer();
        $this->db->query($sql);
        $result = $this->db->recv();

        // Fix Lost connection to MySQL server during query
        // MySQL server has gone away
        if($this->db->errno == 2006 || $this->db->errno == 2013) {
            $this->reconnect();

            $this->db->setDefer();
            $this->db->query($sql);
            $result = $this->db->recv();
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
        // Not support
        // return $this->db->escape($str);
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
        if ($f) {
            return $this->db->commit();
        } else {
            return $this->db->begin();
        }
    }

    /*
     * Commit transaction
     *
     * @return bool
     */
    public function commit()
    {
        return $this->db->commit();
    }

    /*
     * Roollback transaction
     *
     * @return bool
     */
    public function rollback()
    {
        return $this->db->rollback();
    }

    function __destruct()
    {
        $this->db->close();
    }
}

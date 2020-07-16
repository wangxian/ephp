<?php
namespace ePHP\Model;

use ePHP\Core\Config;

class DB_mysqli
{
    public $db = false;
    protected $config = [];

    function __construct($db_config = 'default')
    {
        $db_config = 'dbconfig.' . $db_config;
        if (false == ($config = Config::get($db_config))) {
            \throw_error('invalid database configuration', 12005);
        }

        // Set default value
        $this->config = $config + array(
            'host'     => '127.0.0.1',
            'user'     => '',
            'password' => '',
            'dbname'   => '',
            'port'     => 3306,
            'charset'  => 'utf8',
            'tb_prefix'=> '',
            'timeout'  => 3
        );

        $this->reconnect();
    }

    /**
     * Reconnect database
     *
     * @return null
     */
    function reconnect()
    {
        // Connect to MySQL
        // $this->db = new \mysqli($this->config['host'], $this->config['user'], $this->config['password'], $this->config['dbname'], $this->config['port']);
        $db = \mysqli_init();
        if ( !$db->options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->config['timeout']) ) {
            \throw_error('Setting MYSQLI_OPT_CONNECT_TIMEOUT failed', $db->errno);
        }

        if ( !$db->real_connect($this->config['host'], $this->config['user'], $this->config['password'], $this->config['dbname'], $this->config['port']) ) {
            \throw_error('Can not real_connect to MySQL server, message: ' . $db->connect_error);
        }

        if ($db->connect_errno) {
            \throw_error('Can not connect to MySQL server, message: ' . $db->connect_error, $db->errno);
        }

        // Set charset
        $db->set_charset(isset($this->config['charset']));

        $this->db = $db;
    }

    /**
     * MySQL query
     *
     * @param  string $sql
     * @return mixed
     */
    function query($sql)
    {
        if (true == Config::get('sql_log')) {
            wlog('SQL-Log', $sql);
        }

        if (true == ($rs = $this->db->query($sql))) {
            append_server('__$DB_QUERY_COUNT', serverv('__$DB_QUERY_COUNT', 0)+1);
            return $rs;
        } else if($this->db->errno == 2006 || $this->db->errno == 2013) {
            // Catch database pool long running
            // 2013 Lost connection to MySQL server during query
            // 2006 MySQL server has gone away
            $this->reconnect();

            append_server('__$DB_QUERY_COUNT', serverv('__$DB_QUERY_COUNT', 0)+1);
            return $this->db->query($sql);
        } else {
            \throw_error('DB_ERROR: ' . $this->db->error . "\nRAW_SQL: " . $sql, $this->db->errno);
        }
    }

    /**
     * fetch one row, return array
     *
     * @param string $sql
     * @return array
     */
    function fetch_array($sql)
    {
        $rs   = $this->query($sql);
        $data = $rs->fetch_assoc();
        if (empty($data)) {
            $data = [];
        }

        $rs->free();
        return $data;
    }

    /**
     * fetch many rows, return array
     *
     * @param string $sql
     * @return array
     */
    function fetch_arrays($sql)
    {
        $rs   = $this->query($sql);
        $data = [];
        while (true == ($row = $rs->fetch_assoc())) {
            $data[] = $row;
        }
        $rs->free();
        return $data;
    }

    /**
     * fetch one row, return object
     *
     * @param string $sql
     * @return object
     */
    function fetch_object($sql)
    {
        $rs   = $this->query($sql);
        $data = $rs->fetch_object();

        $rs->free(); //释放内存
        return $data;
    }

    /**
     * fetch many rows, return object
     *
     * @param string $sql
     * @return object
     */
    function fetch_objects($sql)
    {
        $rs   = $this->query($sql);
        $data = null;
        while (true == ($row = $rs->fetch_object())) {
            $data[] = $row;
        }
        $rs->free();
        return $data;
    }

    /**
     * Return last insert id
     *
     * @return int insert_id
     */
    public function insert_id()
    {
        return $this->db->insert_id;
    }

    /**
     * Return last affected rows
     *
     * @return int
     */
    public function affected_rows()
    {
        return $this->db->affected_rows;
    }

    /**
     * 过滤SQL中的不安全字符
     *
     * @param string $str
     */
    public function escape_string($str)
    {
        return $this->db->real_escape_string($str);
    }

    /*
     * Set auto commit
     *
     * @param bool $f
     * @return bool
     */
    public function autocommit($f)
    {
        return $this->db->autocommit($f);
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

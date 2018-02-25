<?php
namespace ePHP\Model;

use ePHP\Core\Config;

class DB_mysql
{
    public $db = false;

    function __construct($db_config = 'default')
    {
        $db_config = 'dbconfig.' . $db_config;
        if (false == ($iconfig = Config::get($db_config))) {
            \show_error('Invalid database configuration！');
        }

        if (empty($iconfig['port'])) {
            $iconfig['port'] = 3306;
        }

        $this->db = \mysql_connect($iconfig['host'] . ':' . $iconfig['port'], $iconfig['user'], $iconfig['password']);
        if (empty($this->db)) {
            show_error('Can not connect to your MySQL Server.');
        }

        if (!mysql_select_db($iconfig['dbname'], $this->db)) {
            show_error(mysql_error($this->db));
        }

        // Set charset
        $this->query("set names " . (isset($iconfig['charset']) ? $iconfig['charset'] : 'utf8'));
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

        if (true == ($rs = mysql_query($sql, $this->db))) {
            $_SERVER['run_dbquery_count']++;
            return $rs;
        } else {
            throw_error('DB_ERROR: ' . mysql_error($this->db) . "\nRAW_SQL: " . $sql, 2045);
        }
    }

    /**
     * return last insert id
     *
     * @return int $insert_id
     */
    public function insert_id()
    {
        return mysql_insert_id($this->db);
    }

    /**
     * Affected rows
     *
     * @return int $affected_rows
     */
    public function affected_rows()
    {
        return mysql_affected_rows($this->db);
    }

    /**
     * fetch one row, return array
     *
     * @param string $sql
     * @return array
     */
    public function fetch_array($sql)
    {
        $rs   = $this->query($sql);
        $data = mysql_fetch_assoc($rs);
        if (empty($data)) {
            $data = [];
        }

        mysql_free_result($rs);
        return $data;
    }

    /**
     * fetch many rows, return array
     *
     * @param string $sql
     * @return array
     */
    public function fetch_arrays($sql)
    {
        $result = $this->query($sql);

        $data  = [];
        while (true == ($row = mysql_fetch_assoc($result))) {
            $data[] = $row;
        }
        mysql_free_result($result);
        return $data;
    }

    /**
     * fetch one row, return object
     *
     * @param string $sql
     * @return object
     */
    public function fetch_object($sql)
    {
        $rs   = $this->query($sql);
        $data = mysql_fetch_object($rs);

        mysql_free_result($rs);
        return $data;
    }

    /**
     * fetch many rows, return object
     *
     * @param string $sql
     * @return object
     */
    public function fetch_objects($sql)
    {
        $result = $this->query($sql);
        $array  = null;
        while (true == ($row = mysql_fetch_object($result))) {
            $array[] = $row;
        }
        mysql_free_result($result);
        return $array;
    }

    /**
     * Escape SQL
     *
     * @return string $str
     */
    public function escape_string($str)
    {
        return mysql_real_escape_string($str, $this->db);
    }

    /*
     * Set auto commit
     *
     * @param bool $f
     * @return bool
     */
    public function autocommit($f)
    {
        if ($f) {
            return $this->query('SET AUTOCOMMIT=1');
        } else {
            $this->query('SET AUTOCOMMIT=0');
            return $this->query('START TRANSACTION');
        }
    }

    /*
     * Commit transaction
     *
     * @return bool
     */
    public function commit()
    {
        return $this->query('COMMIT');
    }

    /*
     * Roolback transacton
     *
     * @return bool
     */
    public function rollback()
    {
        return $this->query('ROLLBACK');
    }

    function __destruct()
    {
        mysql_close($this->db);
    }
}

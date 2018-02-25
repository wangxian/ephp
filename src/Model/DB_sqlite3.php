<?php
namespace ePHP\Model;

use ePHP\Core\Config;

class DB_sqlite3
{
    public $db = false;

    function __construct($db_config = 'default')
    {
        $db_config = 'dbconfig.' . $db_config;
        if (false == ($iconfig = Config::get($db_config))) {
            \show_error('Invalid database configuration！');
        }

        $this->db = new \SQLite3($iconfig['host'], SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, $iconfig['password']);
        if (empty($this->db)) {
            show_error($this->db->lastErrorMsg());
        }
    }

    /**
     * db query
     *
     * @param string $sql
     * @return mixed
     */
    public function query($sql)
    {
        if (true == Config::get('sql_log')) {
            wlog('SQL-Log', $sql);
        }

        $_key = strtolower(substr($sql, 0, 6));
        if ($_key == 'select') {
            $qt = 'query';
        } else {
            $qt = 'exec';
        }

        if (true == ($rs = $this->db->$qt($sql))) {
            $_SERVER['run_dbquery_count']++;
            return $rs;
        } else {
            \show_error('DB_ERROR: ' . $this->db->lastErrorMsg() . "\nRAW_SQL: " . $sql);
        }
        //return false;
    }

    /**
     * last insert id

     * @return integer $insert_id
     */
    public function insert_id()
    {
        return $this->db->lastInsertRowID();
    }

    /**
     * Affected rows
     *
     * @return integer $affected_rows
     */
    public function affected_rows()
    {
        return $this->db->changes();
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
        $data = $rs->fetchArray(SQLITE3_ASSOC);
        $_SERVER['run_dbquery_count']++;

        $rs->finalize();
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
        $result = $this->db->query($sql);
        $array  = null;
        while (true == ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $array[] = $row;
        }
        $_SERVER['run_dbquery_count']++;

        $result->finalize();
        return $array;
    }

    /**
     * fetch one row, return object
     *
     * @param string $sql
     * @return object
     */
    public function fetch_object($sql)
    {
        $_SERVER['run_dbquery_count']++;
        return (object) $this->fetch_array($sql);
    }

    /**
     * fetch many rows, return object
     *
     * @param string $sql
     * @return object
     */
    public function fetch_objects($sql)
    {
        $arr = $this->fetch_arrays($sql);
        if (!empty($arr)) {
            foreach ($arr as $k => $v) {
                $arr[$k] = (object) $v;
            }
        }

        $_SERVER['run_dbquery_count']++;
        return $arr;
    }

    /**
     * Escape SQL
     *
     * @return string $str
     */
    public function escape_string($str)
    {
        return $this->db->escapeString($str);
    }

    function __destruct()
    {
        $this->db->close();
    }
}

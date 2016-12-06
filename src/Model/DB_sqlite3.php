<?php
/**
+------------------------------------------------------------------------------
 * sqlite3 driver for ePHP
+------------------------------------------------------------------------------
 * @version 3.0
 * @author  WangXian
 * @package  dbdrivers
 * @email    <wo#wangxian.me>
 * @creation date 2011-2-22 18:16:52
 * @last modified 2011.6.11
+------------------------------------------------------------------------------
 */
namespace ePHP\Model;

use ePHP\Core\Config;

class DB_sqlite3
{
    public $db = false;

    /**
     * 使用配置中的那个数据库, 如: default, master, slave
     *
     * @param string $db_config
     */
    function __construct($db_config = 'default')
    {
        $db_config = 'dbconfig.' . $db_config;
        if (false == ($iconfig = Config::get($db_config)))
        {
            show_error('无效数据库配制！');
        }

        $this->db = new \SQLite3($iconfig['host'], SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, $iconfig['password']);
        if (empty($this->db))
        {
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
        // 是否记录 SQL log
        if (true == Config::get('sql_log'))
        {
            wlog('SQL-Log', $sql);
        }

        $_key = strtolower(substr($sql, 0, 6));
        if ($_key == 'select')
        {
            $qt = 'query';
        }
        else
        {
            $qt = 'exec';
        }

        if (true == ($rs = $this->db->$qt($sql)))
        {
            $_SERVER['run_dbquery_count']++;
            return $rs;
        }
        else if (Config::get('show_errors'))
        {
            show_error('执行sqlite_query()出现错误: ' . $this->db->lastErrorMsg() . '<br />原SQL: ' . $sql);
        }
        else
        {
            exit('DB_sqlite3::query() error.');
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
     * affected_rows
     *
     * @return integer $affected_rows
     */
    public function affected_rows()
    {
        return $this->db->changes();
    }

    /**
     * 查询一条数据，返回数据格式array
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
     * 查询多条数据，返回数据格式array
     *
     * @param string $sql
     * @return array
     */
    public function fetch_arrays($sql)
    {
        $result = $this->db->query($sql);
        $array  = null;
        while (true == ($row = $result->fetchArray(SQLITE3_ASSOC)))
        {
            $array[] = $row;
        }
        $_SERVER['run_dbquery_count']++;

        $result->finalize();
        return $array;
    }

    /**
     * 查询一条数据，返回数据格式Object
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
     * 查询多条数据，返回数据格式Object
     *
     * @param string $sql
     * @return object
     */
    public function fetch_objects($sql)
    {
        $arr = $this->fetch_arrays($sql);
        if (!empty($arr))
        {
            foreach ($arr as $k => $v)
            {
                $arr[$k] = (object) $v;
            }
        }

        $_SERVER['run_dbquery_count']++;
        return $arr;
    }

    /**
     * 转义SQL中不安全的字符
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

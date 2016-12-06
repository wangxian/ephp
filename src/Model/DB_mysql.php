<?php
/**
+------------------------------------------------------------------------------
 * mysql类
 * 使用老的mysql链接，应用程序一般不会直接调用dbdrivers
+------------------------------------------------------------------------------
 * @version 3.0
 * @author  WangXian
 * @package dbdrivers
 * @creation date 2010-8-12
 * @Modified date 2011-6-11
+------------------------------------------------------------------------------
 */

namespace ePHP\Model;

use ePHP\Core\Config;

class DB_mysql
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

        if (empty($iconfig['port']))
        {
            $iconfig['port'] = 3306;
        }

        $this->db = \mysql_connect($iconfig['host'] . ':' . $iconfig['port'], $iconfig['user'], $iconfig['password']);
        if (empty($this->db))
        {
            show_error('Can not connect to your MySQL Server. Then exit');
        }

        // 打开数据库
        if (!mysql_select_db($iconfig['dbname'], $this->db))
        {
            show_error(mysql_error($this->db));
        }

        // 设置charset
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
        // 是否记录 SQL log
        if (true == Config::get('sql_log'))
        {
            wlog('SQL-Log', $sql);
        }

        if (true == ($rs = mysql_query($sql, $this->db)))
        {
            $_SERVER['run_dbquery_count']++;
            return $rs;
        }
        else if (Config::get('show_errors'))
        {
            throw new CommonException('执行mysql::query()出现错误: ' . mysql_error($this->db) . '<br />原SQL: ' . $sql, 2045);
        }
        else
        {
            exit('DB_mysql::query() error.');
        }
        //return false;
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
     * 影响的数据总行数
     *
     * @return int $affected_rows
     */
    public function affected_rows()
    {
        return mysql_affected_rows($this->db);
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
        $data = mysql_fetch_assoc($rs);

        mysql_free_result($rs);
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
        $result = $this->query($sql);
        $array  = null;
        while (true == ($row = mysql_fetch_assoc($result)))
        {
            $array[] = $row;
        }
        mysql_free_result($result);
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
        $rs   = $this->query($sql);
        $data = mysql_fetch_object($rs);

        mysql_free_result($rs);
        return $data;
    }

    /**
     * 查询多条数据，返回数据格式Object
     *
     * @param string $sql
     * @return object
     */
    public function fetch_objects($sql)
    {
        $result = $this->query($sql);
        $array  = null;
        while (true == ($row = mysql_fetch_object($result)))
        {
            $array[] = $row;
        }
        mysql_free_result($result);
        return $array;
    }

    /**
     * 转义SQL中不安全的字符
     *
     * @return string $str
     */
    public function escape_string($str)
    {
        return mysql_real_escape_string($str, $this->db);
    }

    /*
     * 设置事务是否自动提交
     *
     * @param bool $f
     * @return bool
     */
    public function autocommit($f)
    {
        if ($f)
        {
            return $this->query('SET AUTOCOMMIT=1');
        }
        else
        {
            // 不自动提交事务
            $this->query('SET AUTOCOMMIT=0');
            return $this->query('START TRANSACTION');
        }
    }

    /*
     * 提交事务
     *
     * @return bool
     */
    public function commit()
    {
        return $this->query('COMMIT');
    }

    /*
     * 回滚事务
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

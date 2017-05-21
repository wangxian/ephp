<?php
namespace ePHP\Model;

use ePHP\Core\Config;
use ePHP\Exception\CommonException;

class DB_mysqli
{
    public $db = false;

    function __construct($db_config = 'default')
    {
        $db_config = 'dbconfig.' . $db_config;
        if (false == ($iconfig = Config::get($db_config)))
        {
            \show_error('Invalid database configuration！');
        }

        if (empty($iconfig['port']))
        {
            $iconfig['port'] = 3306;
        }

        $this->db = new \mysqli($iconfig['host'], $iconfig['user'], $iconfig['password'], $iconfig['dbname'], $iconfig['port']);
        if (mysqli_connect_errno())
        {
            \show_error('Can not connect to MySQL, message: ' . mysqli_connect_error());
        }

        // Set charset
        $this->db->set_charset(isset($iconfig['charset']) ? $iconfig['charset'] : 'utf8');
    }

    /**
     * MySQL query
     *
     * @param  string $sql
     * @return mixed
     */
    function query($sql)
    {
        if (true == Config::get('sql_log'))
        {
            wlog('SQL-Log', $sql);
        }

        if (true == ($rs = $this->db->query($sql)))
        {
            $_SERVER['run_dbquery_count']++;
            return $rs;
        }
        else
        {
            throw new CommonException('DBError: ' . $this->db->error . '<br />RawSQL: ' . $sql, 2045);
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
        if (empty($data)) $data = [];

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
        while (true == ($row = $rs->fetch_assoc()))
        {
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
        while (true == ($row = $rs->fetch_object()))
        {
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

<?php
// Coroutine MySQL
namespace ePHP\Model;

use ePHP\Core\Config;

class DB_mysqlco
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
        $this->db = new \Swoole\Coroutine\MySQL();
        $this->db->connect([
            'host' => $iconfig['host'],
            'port' => $iconfig['port'],
            'user' => $iconfig['user'],
            'password' => $iconfig['password'],
            'database' => $iconfig['dbname']
        ]);

        if (!$this->db->connected)
        {
            show_error('Can not connect to your MySQL Server. Then exit');
        }
        else
        {
            // Set default charset
            $this->query("set names " . (isset($iconfig['charset']) ? $iconfig['charset'] : 'utf8'));
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
        if (!$this->db->connected)
        {
            echo 'MySQL not connected';
            return false;
        }

        if (true == Config::get('sql_log'))
        {
            wlog('SQL-Log', $sql);
        }

        $this->db->setDefer();
        $this->db->query($sql);
        $result = $this->db->recv();

        return $result;
    }

    /**
     * return last insert id
     *
     * @return int $insert_id
     */
    public function insert_id()
    {
        return $this->db->insert_id;
    }

    /**
     * 影响的数据总行数
     *
     * @return int $affected_rows
     */
    public function affected_rows()
    {
        return $this->db->affected_rows;
    }

    /**
     * 查询一条数据，返回数据格式array
     *
     * @param string $sql
     * @return array
     */
    public function fetch_array($sql)
    {
        $result = $this->query($sql);
        if (!empty($result)) return $result[0];
        else return [];
    }

    /**
     * 查询多条数据，返回数据格式array
     *
     * @param string $sql
     * @return array
     */
    public function fetch_arrays($sql)
    {
        return $this->query($sql);
    }

    /**
     * 查询一条数据，返回数据格式Object
     *
     * @param string $sql
     * @return object
     */
    public function fetch_object($sql)
    {
        return (object)$this->fetch_array($sql);
    }

    /**
     * 查询多条数据，返回数据格式Object
     *
     * @param string $sql
     * @return object
     */
    public function fetch_objects($sql)
    {
        $data = [];
        $result = $this->fetch_arrays($sql);
        foreach ($result as $v)
        {
            $data[] = (object)$v;
        }

        return $data;
    }

    /**
     * 转义SQL中不安全的字符
     *
     * @return string $str
     */
    public function escape_string($str)
    {
        return addslashes($str);
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
}

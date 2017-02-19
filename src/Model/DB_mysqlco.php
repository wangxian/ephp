<?php
// Coroutine MySQL
namespace ePHP\Model;

use ePHP\Core\Config;
use ePHP\Model\DBPool;
use ePHP\Exception\CommonException;
use ePHP\Exception\ExitException;

class DB_mysqlco
{
    public $db = false;

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
        if (false == ($iconfig = Config::get($db_config)))
        {
            show_error('无效数据库配制！');
        }

        if (empty($iconfig['port']))
        {
            $iconfig['port'] = 3306;
        }

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
            'database' => $this->iconfig['dbname']
        ]);

        if (!$this->db->connected)
        {
            throw new ExitException('Can not connect to your MySQL Server. Then exit');
        }
        else
        {
            // Set default charset
            $this->db->query("set names " . (isset($this->iconfig['charset']) ? $this->iconfig['charset'] : 'utf8'));
        }
    }

    /**
     * Get one MySQL connect from pool
     *
     * @return bool
     */
    private function prepareDB()
    {
        $dbpool = DBPool::init();
        // var_dump($dbpool->cap + $dbpool->acticeCount, $dbpool->queue->isEmpty(), $dbpool->queue->count());

        if ($dbpool->queue->isEmpty() && ($dbpool->cap + $dbpool->acticeCount >= $this->iconfig['max_pool_size']))
        {
            throw new ExitException('MySQL pool is empty...');
        }

        if ( $dbpool->cap < $this->iconfig['idle_pool_size']
            || ($dbpool->queue->isEmpty() && $dbpool->cap < $this->iconfig['max_pool_size'])
        )
        {
            // var_dump('........................reconnect........................');
            $this->reconnect();
            $dbpool->acticeCount++;
            return false;
        }
        else
        {
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
        if (true == Config::get('sql_log'))
        {
            wlog('SQL-Log', $sql);
        }

        // Prepare one MySQL client connect
        $isFromPool = $this->prepareDB();

        // If disconnect, Must connection
        if (!$this->db->connected)
        {
            var_dump('........................mysql disconnected........................');
            $this->reconnect();
        }

        $this->db->setDefer();
        $this->db->query($sql);
        $result = $this->db->recv();

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
        if($isFromPool) DBPool::init()->back($this->db);
        else DBPool::init()->in($this->db);

        if ($this->db->errno == 0)
        {
            $_SERVER['run_dbquery_count']++;
        }
        else if (Config::get('show_errors'))
        {
            throw new CommonException('执行出现错误: ' . $this->db->error . '<br />原SQL: ' . $sql, 2045);
        }
        else
        {
            throw new ExitException('DB_mysqlco::query() error.');
        }

        $this->insert_id = $this->db->insert_id;
        $this->affected_rows = $this->db->affected_rows;

        return $result;
    }

    /**
     * return last insert id
     *
     * @return int $insert_id
     */
    public function insert_id()
    {
        return $this->insert_id;
    }

    /**
     * affected_rows
     *
     * @return int $affected_rows
     */
    public function affected_rows()
    {
        return $this->affected_rows;
    }

    /**
     * fetch array
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

<?php /** @noinspection DuplicatedCode */
/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnused */

namespace ePHP\Model;

use ePHP\Core\Config;
use \ePHP\Cache\Cache;

class BaseModel
{
    // Current table name
    protected $table_name = '';

    // Raw SQL
    public $sql = '';

    // Default dbconfig name, AS default, master, slave
    protected $db_config_name = 'default';

    private $field = '*';
    private $orderBy = '';
    private $groupBy = '';
    private $having = '';
    private $limit = '';
    private $where = '';
    private $join = '';
    private $data = array();

    /**
     * Expire seconds
     * @var int
     */
    private $expire = -1;

    /**
     * Raw query SQL
     * @var string
     */
    private $query_sql = '';

    /**
     * Model Db handle
     * @var mixed
     */
    private $db = null;

    /**
     * Standard fpm db handle
     * @var array
     */
    static private $_db_handle = [];

    /**
     * Swoole mode db handle
     * @var array
     */
    private $_swoole_db_handle = [];

    /**
     * Connect to the database
     *
     * 该方法不需要直接调用, 在使用时每个数据库只创建一个链接，以后直接复用
     *
     * @access private
     * @return mixed $db
     * @noinspection PhpIncludeInspection
     * @noinspection SpellCheckingInspection
     */
    private function conn()
    {
        if (SERVER_MODE === 'swoole') {
            // swoole模式下，static property是全局变量，协程切换时会出现问题，这里使用成员变量处理
            // 当swoole协程context结束时，自动回收内存
            if (isset($this->_swoole_db_handle[$this->db_config_name])) {
                $this->db = $this->_swoole_db_handle[$this->db_config_name];
            } else {
                $dbdriver = 'DB_' . Config::get('dbdriver');
                include_once __DIR__ . '/' . $dbdriver . '.php';

                $dbdriver                                       = '\\ePHP\\Model\\' . $dbdriver;
                $this->_swoole_db_handle[$this->db_config_name] = $this->db = new $dbdriver($this->db_config_name);
            }
        } else {
            if (isset(self::$_db_handle[$this->db_config_name])) {
                $this->db = self::$_db_handle[$this->db_config_name];
            } else {
                $dbdriver = 'DB_' . Config::get('dbdriver');
                include_once __DIR__ . '/' . $dbdriver . '.php';

                $dbdriver                                = '\\ePHP\\Model\\' . $dbdriver;
                self::$_db_handle[$this->db_config_name] = $this->db = new $dbdriver($this->db_config_name);
            }
        }

        return $this->db;
    }

    /**
     * 生成SELECT类型的SQL语句
     * 不需要直接调用
     * @access private
     * @return string
     * @noinspection SpellCheckingInspection
     */
    private function _read_sql()
    {
        // 可能是model::query('select')，直接接受SQL的查询操作
        if (!$this->query_sql) {
            $_table_name = $this->_get_table_name();

            $_join    = $this->join;
            $_where   = $this->where != '' ? ' WHERE ' . $this->where : '';
            $_orderby = $this->orderBy != '' ? ' ORDER BY ' . $this->orderBy : '';
            $_groupby = $this->groupBy != '' ? ' GROUP BY ' . $this->groupBy : '';
            $_limit   = $this->limit != '' ? ' LIMIT ' . $this->limit : '';
            $_having  = $this->having != '' ? ' HAVING ' . $this->having : '';

            $this->sql = 'SELECT ' . $this->field . ' FROM ' . $_table_name . $_join . $_where . $_groupby . $_having . $_orderby . $_limit;

            $this->_clear_var(); //清理使用过的变量
            return $this->sql;
        } else {
            $this->sql       = $this->query_sql;
            $this->query_sql = '';
            return $this->sql;
        }
    }

    /**
     * 获取表名
     *
     * @access private
     * @return string
     */
    private function _get_table_name()
    {
        if ($this->table_name == '') {
            // 如果是在实例化后使用，则使用当前模型名称
            $current_class = get_class($this);
            if ($current_class != 'model') {
                $this->table_name = strtolower(substr($current_class, strrpos($current_class, '\\') + 1, -5));

                // 如果设置了表前缀
                if (true == ($tb_prefix = Config::get('dbconfig.' . $this->db_config_name . '.tb_prefix'))) {
                    $this->table_name = $tb_prefix . $this->table_name;
                }
            } else {
                throw_error('Table name can not automatically infer.');
            }
        }

        return $this->table_name;
    }

    /**
     * 清理使用过的变量
     *
     * @access private
     * @return void
     */
    private function _clear_var()
    {
        //$this->table_name = '';
        $this->field   = '*';
        $this->orderBy = '';
        $this->groupBy = '';
        $this->having  = '';
        $this->limit   = '';
        $this->where   = '';
        $this->join    = '';
        $this->expire  = -1;
    }

    /**
     * 查询的表名
     *
     * @param string $table_name 表名
     * @return $this
     */
    public function table($table_name)
    {
        $this->table_name = $table_name;
        return $this;
    }

    /**
     * model::table()方法的别名，查询的表名
     * @param string $table_name
     * @return $this
     */
    public function from($table_name)
    {
        return $this->table($table_name);
    }

    /**
     * 切换使用的数据库，例如default、master, slave
     * 和dbconfig配置一致
     *
     * @param string $db_config_name
     * @return $this
     */
    public function dbconfig($db_config_name)
    {
        $this->db_config_name = $db_config_name;
        return $this;
    }

    /**
     * 指定数据缓存时间，单位秒
     *
     * @param int $expire 缓存有效期。大于0：缓存时间，0：永久缓存，-1：不缓存
     * @return $this
     */
    public function cache($expire)
    {
        $this->expire = $expire;
        return $this;
    }

    /**
     * 要查询的数据库字段，也就是SQL中select后的字段列表
     *
     * @param string $field 要查询的字段列表
     * @return $this
     */
    public function select($field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * select方法的别名，要查询的数据库字段
     *
     * @param string $field 要查询的字段列表
     * @return $this
     */
    public function field($field)
    {
        return $this->select($field);
    }

    /**
     * SQL中limit，使用方法：model->limit(0, 10) 或 model->limit(100)
     *
     * @param int $offset 如果$limit=0则offset作为limit使用
     * @param int $limit 每次查询的数据量
     * @return $this
     */
    public function limit($offset, $limit = 0)
    {
        if ($limit > 0) {
            $this->limit = $offset . ',' . $limit;
        } else {
            $this->limit = $offset;
        }

        return $this;
    }

    /**
     * 写入数据库的内容(for insert|update)
     *
     * @param mixed $data 要写入数据库的内容
     * @param array $replacement 当data是字符串时，按照位置替换问号「?」
     * @return $this
     */
    public function set($data, $replacement = array())
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = "'" . $this->escape_string($v) . "'";
            }

            $this->data += $data;
        } else if (is_string($data) && is_array($replacement)) {
            // 支持model->set("cid=? and name=?", [12, "name"])
            $i = 0;

            $data = preg_replace_callback(["/(\?)/"], function () use (&$i, &$replacement) {
                $v = $replacement[$i++];
                return !is_string($v) ? $v : "'" . $this->escape_string($v) . "'";
            }, $data);

            $this->data = $data;
        } else {
            throw_error('BaseModel::set($data, $replacement)第二个参数$replacement只接收array类型');
        }

        return $this;
    }

    /**
     * set方法的别名
     *
     * @param mixed $data 要写入数据库的内容。
     * @param array $replacement 当data是字符串时，按照位置替换问号“？”
     * @return $this
     */
    public function data($data, $replacement = array())
    {
        return $this->set($data, $replacement);
    }

    /**
     * SQL中的where条件
     *
     * @param mixed $where 可以是一个字符串或数组。
     * @param array $replacement 当where是字符串时，按照位置替换问号“？”
     * @return $this
     */
    public function where($where, $replacement = array())
    {
        if (is_array($where)) {
            $tmp = array();
            foreach ($where as $k => $v) {
                $tmp[] = !is_string($v) ? $k . "=" . $v : $k . "='" . $this->escape_string($v) . "'";
            }
            $where = implode(' AND ', $tmp);
        } elseif (is_string($where) && !empty($replacement)) {
            // 支持model->where("id>? and name=?", [12, "name"])
            $i     = 0;
            $where = preg_replace_callback(["/(\?)/"], function () use (&$i, &$replacement) {
                $v = $replacement[$i++];
                return !is_string($v) ? (!is_null($v) ? $v : "''") : "'" . $this->escape_string($v) . "'";
            }, $where);
        }

        if (empty($this->where)) {
            $this->where = $where;
        } else {
            $this->where .= " AND " . $where;
        }

        // dd($this->where);

        return $this;
    }

    /*
     * SQL left join
     *
     * @param string $join_string
     * @param $this
     */
    public function leftjoin($join_string)
    {
        $this->join = ' LEFT JOIN ' . $join_string;
        return $this;
    }

    /*
     * SQL left join
     *
     * @param string $join
     * @param $this
     */
    public function rightjoin($join_string)
    {
        $this->join = ' RIGHT JOIN ' . $join_string;
        return $this;
    }

    /**
     * SQL order by
     *
     * @param string $orderby
     * @return $this
     * @noinspection SpellCheckingInspection
     */
    public function orderby($orderby)
    {
        $this->orderBy = $orderby;
        return $this;
    }

    /**
     * SQL group by
     *
     * @param string $groupby 分组
     * @return $this
     * @noinspection SpellCheckingInspection
     */
    public function groupby($groupby)
    {
        $this->groupBy = $groupby;
        return $this;
    }

    /**
     * SQL having
     *
     * @param string $having
     * @return $this
     */
    public function having($having)
    {
        $this->having = $having;
        return $this;
    }

    /**
     * 操作影响的行数
     *
     * @return int $affected_rows
     */
    public function affected_rows()
    {
        return $this->db ? $this->db->affected_rows() : 0;
    }

    /**
     * 插入返回的主键ID
     *
     * @return int
     */
    public function insert_id()
    {
        return $this->db ? $this->db->insert_id() : 0;
    }

    /**
     * 最后执行的SQL
     *
     * @return string $sql
     */
    public function getLastSql()
    {
        return $this->sql;
    }

    /**
     * 执行数据库query
     *
     * 如果是SELECT\show查询可以后续操作，如findAll
     *
     * @param string $sql 要查询的SQL或执行commit的SQL等
     * @param array $replacement 按照位置替换问号“？”
     * @return mixed
     */
    public function query($sql, $replacement = array())
    {
        $sql = trim($sql);
        if (!empty($replacement)) {
            // 支持model->query("cid=? and name=?", [12, "name"])
            $i   = 0;
            $sql = preg_replace_callback(["/(\?)/"], function () use (&$i, &$replacement) {
                $v = $replacement[$i++];
                return !is_string($v) ? $v : "'" . $this->escape_string($v) . "'";
            }, $sql);
        }

        // 鉴定是执行查询还是commit提交操作。如果是select、show，可以有后续操作。
        $_key = strtolower(substr($sql, 0, 4));
        if ($_key == 'sele' || $_key == 'show') {
            $this->query_sql = $sql;
            return $this;
        } else {
            $this->sql = $sql;
            return $this->conn()->query($sql);
        }
    }

    // ------------------------------------------------------------------------------ 查询操作

    /**
     * 以array的方式,返回查询到的一条数据
     *
     * @return array $data
     */
    public function find()
    {
        return $this->_find('fetch_array');
    }

    /**
     * 以array的方式,返回多条查询结果
     *
     * @return array $data
     */
    public function findAll()
    {
        return $this->_find('fetch_arrays');
    }

    /**
     * 以object的方式,返回查询到的一条数据
     *
     * @return object $data
     */
    public function findObj()
    {
        return $this->_find('fetch_object');
    }

    /**
     * 以object的方式,返回查询到的多条数据
     *
     * @return object $data
     */
    public function findObjs()
    {
        return $this->_find('fetch_objects');
    }

    /**
     * 查询基础方法
     *
     * @param string $type 查询类型,fetch_array fetch_arrays...
     * @return mixed
     */
    protected function _find($type)
    {
        $sql = $this->_read_sql();
        if ($this->expire < 0) {
            return $this->conn()->$type($sql);
        } else {
            $cache     = Cache::init();
            $cachename = 'db/' . $type . '_' . md5($sql);
            if (false == ($data = $cache->get($cachename))) {
                $data = $this->conn()->$type($sql);
                $cache->set($cachename, $data, $this->expire);
                $this->expire = -1;
            }
            return $data;
        }
    }

    /**
     * 和findAll()差不多
     *
     * 返回的数据结构：array('data'=>array(....), 'data_count'=>总数据数)
     *
     * @return array $data
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlDialectInspection
     */
    public function findPage()
    {
        $_where    = ($this->where != '') ? ' WHERE ' . $this->where : '';
        $this->sql = $this->_read_sql();
        if ($this->expire < 0) {
            $_table_name = $this->_get_table_name();
            $sql_count   = 'SELECT count(*) AS countrows FROM ' . $_table_name . $this->join . $_where;

            $data['data']       = $this->conn()->fetch_arrays($this->sql);
            $data['data_count'] = $this->db->fetch_object($sql_count)->countrows;
        } else {
            $cache     = Cache::init();
            $cacheName = 'db/findPage_' . md5($this->sql);
            if (false == ($data = $cache->get($cacheName))) {
                $_table_name = $this->_get_table_name();
                $sql_count   = 'SELECT count(*) AS countrows FROM ' . $_table_name . $this->join . $_where;

                $data['data']       = $this->conn()->fetch_arrays($this->sql);
                $data['data_count'] = $this->db->fetch_object($sql_count)->countrows;

                $cache->set($cacheName, $data, $this->expire);
                $this->expire = -1;
            }
        }
        return $data;
    }

    /**
     * 统计数据条数
     * 示例: $m->table('test')->select('id')->where('id<12')->count();
     *
     * @return int
     */
    public function count()
    {
        $_table_name = $this->_get_table_name();
        $_where      = ($this->where != '') ? ' WHERE ' . $this->where : '';
        $_field      = $this->field;
        $_join       = $this->join;

        // 清理使用过的变量
        $this->where = '';
        $this->field = '*';
        $this->join  = '';

        // 连接数据库
        $this->conn();
        $this->sql = 'SELECT count(' . $_field . ') count FROM ' . $_table_name . $_join . $_where;
        return $this->db->fetch_object($this->sql)->count;
    }

    // ------------------------------------------------------------------------------insert update delete操作

    /**
     * 删除一行或多行，如果希望删除所有行,使用delete(true)
     *
     * @param bool $f
     * @return bool
     * @noinspection SqlNoDataSourceInspection
     */
    public function delete($f = false)
    {
        $_where      = '';
        $_table_name = $this->_get_table_name();

        if ($this->where) {
            $_where = ' WHERE ' . $this->where;
        } elseif ($this->where == '' && $f == false) {
            throw_error('警告：您似乎漏掉了where条件。如果确认不使用where条件，请使用delete(true)');
        }

        // 清理使用过的变量
        $this->where = '';

        $this->sql = 'DELETE FROM ' . $_table_name . $_where;
        return $this->conn()->query($this->sql);
    }

    /**
     * 更新一行或多行，如果更新所有数据,使用update(true)
     *
     * @param bool $f
     * @return bool
     */
    public function update($f = false)
    {
        $_where      = '';
        $_table_name = $this->_get_table_name();

        $_set_string = ' SET ';
        $tmp         = array();

        if (is_array($this->data)) {
            foreach ($this->data as $k => $v) {
                $tmp[] = $k . '=' . $v;
            }
            $_set_string .= implode(',', $tmp);
        } elseif (is_string($this->data)) {
            $_set_string .= $this->data;
        }

        if ($this->where) {
            $_where = ' WHERE ' . $this->where;
        } elseif ($this->where == '' && $f == false) {
            throw_error('警告：您似乎漏掉了where条件。如果确认不使用where条件，请使用update(true)');
        }

        // 清理使用过的变量
        $this->data  = array();
        $this->where = '';

        $this->sql = 'UPDATE ' . $_table_name . $_set_string . $_where;
        return $this->conn()->query($this->sql);
    }

    /**
     * 把数据写入数据库
     *
     * @return int $insert_id
     */
    public function insert()
    {
        return $this->_insert('NORMAL');
    }

    /**
     * insert遇到unique或master约束，如果指定了update_string，则更新该条数据，相当于update
     * 如未指定update_string，则忽略插入。
     *
     * @param string $update_string 如有重复需，要更新的字段 default ''
     * @return int $insert_id 插入返回的id
     */
    public function insert_update($update_string = '')
    {
        if ($update_string) {
            return $this->_insert('UPDATE', $update_string);
        } else {
            return $this->_insert('IGNORE');
        }
    }

    /**
     * 如果有重复，则删除重复项，新增一条数据
     *
     * @return int $insert_id 插入返回的id
     */
    public function insert_replace()
    {
        return $this->_insert('REPLACE');
    }

    /**
     * 插入方式，ignore，replace，duplicate update
     *
     * @param string $type 类型
     * @param string $update_string 更新字段
     * @return int
     * @noinspection SqlDialectInspection
     * @noinspection SqlNoDataSourceInspection
     */
    protected function _insert($type, $update_string = '')
    {
        if (is_string($this->data)) {
            throw_error('错误：执行model::insert()时model::set/data()参数必须是array类型');
            return false;
        }
        $_table_name = $this->_get_table_name();
        $_fields     = implode(',', array_keys($this->data));
        $_values     = implode(',', array_values($this->data));

        // 清理使用过的变量
        $this->data = array();
        $this->conn();

        // 插入类型
        if ($type == 'IGNORE') {
            $this->sql = 'INSERT IGNORE INTO ' . $_table_name . " ({$_fields}) VALUES ({$_values})";
        } elseif ($type == 'REPLACE') {
            $this->sql = 'REPLACE INTO ' . $_table_name . " ({$_fields}) VALUES ({$_values})";
        } elseif ($type == 'UPDATE') {
            $this->sql = 'INSERT INTO ' . $_table_name . " ({$_fields}) VALUES ({$_values}) ON DUPLICATE KEY UPDATE " . $update_string;
        } else {
            $this->sql = 'INSERT INTO ' . $_table_name . " ({$_fields}) VALUES ({$_values})";
        }

        $this->db->query($this->sql);
        return $this->db->insert_id();
    }

    // ------------------------------------------------------------------------------事务

    /**
     * 开始事务
     *
     * @return bool
     */
    public function trans_start()
    {
        return $this->conn()->autocommit(false);
    }

    /**
     * 事务提交
     */
    public function trans_commit()
    {
        $this->conn();
        $this->db->commit();
        $this->db->autocommit(true);
    }

    /**
     * 事务回滚
     */
    public function trans_rollback()
    {
        $this->conn(); //connect server
        $this->db->rollback();
        $this->db->autocommit(true);
    }

    /**
     * 过滤SQL中的不安全字符
     *
     * @param string $str 要过滤的字符串
     */
    public function escape_string($str)
    {
        return $this->conn()->escape_string($str);
    }
}

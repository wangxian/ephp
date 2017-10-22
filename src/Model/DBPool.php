<?php
namespace ePHP\Model;

class DBPool
{
    /**
     * Spl Queue
     *
     * @var \SplQueue
     */
    public $queue;

    /**
     * Current pool size
     *
     * @var integer
     */
    public $cap = 0;

    /**
     * Current actice count(being count + out count)
     *
     * @var integer
     */
    public $acticeCount = 0;

    /**
     * The cap last update time
     *
     * @var integer
     */
    private $_capLastRiseTime = 0;

    /**
     * @var \ePHP\Model\DBPool
     */
    private static $instance = [];

    /**
     * Dynamically handle calls to the class.
     *
     * @return \ePHP\Model\Pool
     */
    public static function init($name)
    {
        if (empty(self::$instance[$name]) || !self::$instance[$name] instanceof self) {
            self::$instance[$name]        = new self();
            self::$instance[$name]->queue = new \SplQueue();
        }
        return self::$instance[$name];
    }


    /**
     * Queue in
     */
    public function in($db)
    {
        $this->acticeCount--;
        $this->cap++;
        $this->_capLastRiseTime = time();
        $this->queue->enqueue($db);
    }

    /**
     * Put back resource to pool
     */
    public function back($db)
    {
        $this->acticeCount--;
        $this->queue->enqueue($db);
    }

    /**
     * Queue out
     *
     * @return mixed
     */
    public function out($idle)
    {
        // When pool size is big,
        // then wait for 5 minutes reduce pool
        if ($this->queue->count() > $idle && (time() - $this->_capLastRiseTime > 300)) {
            // var_dump('........................reduce connctions........................');
            while ($this->queue->count() > $idle) {
                $this->queue->dequeue();
            }
            $this->cap = $idle;
            $this->_capLastRiseTime = time();
        }

        $this->acticeCount++;
        return $this->queue->dequeue();
    }
}

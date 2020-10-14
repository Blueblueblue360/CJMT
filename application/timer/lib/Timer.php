<?php
/**
 * 此类为0.0版本中所使用的计时器，因存在延时bug。此类已在1.0及以后的版本中，废弃不用。
 */
namespace app\timer\lib;
use Exception;
use think\Log;

class Timer
{
    public static $now_time;
    public static $tasks = array();

    public static function init()
    {
//        pcntl_signal(SIGALRM, array( __CLASS__, 'signalHandle'), false);
    }

    public static function signalHandle()
    {
        pcntl_alarm(1);
        static::$now_time++;
        //执行任务
        if (empty(self::$tasks)) {
            return;
        }
        foreach (self::$tasks as $run_time => $task) {
            if (time() >= $run_time) {
                call_user_func_array($task[0], $task[1]);
                unset(self::$tasks[$run_time]);
                if($task[3]){
                    Timer::add($task[2], $task[0], $task[1], $task[3]);
                }
            }
        }
    }

    /**
     * @param $interval
     * @param $func
     * @param array $args
     * @param bool $persistent
     * @return bool
     * 往自身$tasks数组里添加执行任务
     */
    public static function add($interval, $func, $args = array(),$persistent = true)
    {
        if ($interval <= 0) {
            echo new Exception('wrong interval');
            return false;
        }
        if (!is_callable($func)) {
            echo new Exception('not callable');
            return false;
        } else {
            $runtime = time() + $interval;
            self::$tasks[$runtime] = array($func, $args, $interval, $persistent);
            return true;
        }
    }

    public static function tick()
    {
        self::$now_time = time();
//        pcntl_alarm(1);
            swoole_timer_tick(1000, function(){
            self::$now_time++;
            Log::info('this here ****8:'.self::$now_time);
            //执行任务
            if (empty(self::$tasks)) {
                return;
            }
            foreach (self::$tasks as $run_time => $task) {
                if (time() >= $run_time) {
                    call_user_func_array($task[0], $task[1]);
                    unset(self::$tasks[$run_time]);
                    if($task[3]){
                        Timer::add($task[2], $task[0], $task[1], $task[3]);
                    }
                }
            }
        });
    }
}
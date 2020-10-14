<?php
namespace app\timer\command;

use app\common\cron\CronExpression;
use app\timer\lib\TaskManager;
use app\timer\lib\TimingWheel;
use app\timer\lib\Worker;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Log;
use app\admin\model\CronTaskLogModel;
use app\admin\model\CronTaskModel;

class JTimerCommand extends Command
{
    /**
     * task进程默认数量
     */
    const TASK_WORKER_COUNT = 8;

    protected function configure()
    {
        $this->setName('jtimer')->setDescription('usage: start | stop | status | restart');
        $this->addArgument('action',Argument::REQUIRED);
        $this->addOption('daemonize','-d',Option::VALUE_NONE);
        $this->addOption('task_count','-t',Option::VALUE_OPTIONAL);
    }

    protected function execute(Input $input, Output $output)
    {
        global $argv;
        $argv[0] = $input->getArgument('action');
        $argv[1] = $input->getOption('daemonize') ? '-d' : '';

        $worker = new Worker();
        $worker->task_worker_count = $input->getOption('task_count') ? $input->getOption('task_count') : self::TASK_WORKER_COUNT;
        //task执行任务 写入执行日志
        $worker->onTask = function($worker, $data){
            $start_time = microtime(true);
            $create_time = date('Y-m-d H:i:s');
            exec($data['cmd']);
            $insert_task_log = [
                'ct_id' => $data['id'],
                'cmd' => $data['cmd'],
                'create_time' => $create_time,
                'spend_time' => microtime(true) - $start_time
            ];
            $key_time = CronTaskLogModel::CRONTAB_LOG.time();
            Cache::set($key_time,Cache::get($key_time,'').json_encode($insert_task_log).';', 3600);
        };

        // 清空缓存  巨型属性
        $worker->onWorkerStart = function(Worker $worker){
            TaskManager::clear();
            if($worker->worker_name == 'db-worker'){

                pcntl_signal(SIGINT, function (){
                    \Swoole\Timer::clearAll();
                }, false);
                echo "db worker timer set\n";
                // 替代timer方案
                swoole_timer_tick(30000, function() use ($worker) {
                    //将数据库中的任务写到缓存中
                    $list = CronTaskModel::getById('status',1);
                    if(TaskManager::loadTask($list)){
                        TaskManager::isChange(true);
                    }
                    //清除之前的旧日志
                    $day = getSetting('cron_task_log_save_day');
                    $key = 'has_delete_log_'.date('Y-m-d') . '_'.$day;
                    if ($day > 0 && cache($key) != true) { //清除日志一天只需执行一次即可
                        CronTaskLogModel::deletCache($day);
                        cache($key,true,24*60*60);
                    }
                    // 执行日志缓存持久化
                    cachePersist();

                    pcntl_signal_dispatch();
                });
            } elseif ($worker->worker_name == 'timer-worker'){
                pcntl_signal(SIGINT, function (){
                    \Swoole\Timer::clearAll();
                }, false);
                echo "time worker set\n";
                $worker->wheel = new TimingWheel();
                $worker->wheel::$now_time = time();
//                TaskManager::isChange(true); // 目前感觉这句没有意义
                swoole_timer_tick(1000, function() use ($worker) {
                    $worker->wheel::$now_time++;
                    $now_time = $worker->wheel::$now_time;
                    if(TaskManager::isChange()){ // 如果任务有变化，取出任务添加到时间轮片
                        $tasks = TaskManager::getTasks();
                        TaskManager::isChange(false);
//                        Log::info('is change:' . print_r($tasks,true));
                        $worker->wheel->clear();
                        if(! empty($tasks)){
                            foreach ($tasks as $task){
                                $interval = $this->getInterval($task['cron_expression'], $now_time);
                                echo "距离时间",$interval,"\n";
                                $worker->wheel->add($interval, $task, $now_time);
                            }
                        }
                    }

                    // 从文件中读取要执行的任务
                    $list = $worker->wheel->popSlots($now_time);
                    if(! empty($list)){
                        foreach ($list as $task){
                            Log::write($now_time.$task,'notice');
                            $interval = $this->getInterval($task['cron_expression'], $now_time);
                            $worker->wheel->add($interval, $task, $now_time);
                            $worker->task($task);
                        }
                    }

                    pcntl_signal_dispatch();
                });

            }

        };

        Worker::runAll();
    }

    public function getInterval($cron_expression, $now_time){
        $next_run_time = CronExpression::getNextRunTime($cron_expression, $now_time);
        return strtotime($next_run_time) - $now_time;
    }
}
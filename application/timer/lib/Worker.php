<?php
namespace app\timer\lib;

use Exception;
use think\Log;

class Worker
{

    public static $daemonize = false;

    public static $pid_file = '';

    public static $log_file = '';

    public static $status_file = '';

    public static $master_pid = 0;

    public static $stdoutFile = '/dev/null';

    public static $workers = array();

    /**
     * @var array
     * 创建2个worker，在此处配上worker名称
     */
    public static $worker_names = array('timer-worker','db-worker');

    public $worker_name = '';

    public static $status = 0;

    public static $runner = null;

    public $task_worker_count = 8;

    public static $task_workers = array();

    public $onWorkerStart = null;
    public $onTask = null;

    public $timer_id; // 设置的定时器id

    const STATUS_RUNNING = 1;
    const STATUS_SHUTDOWN = 2;

    const QUEUE_KEY = 32134;

    public function __construct()
    {
        self::$runner = $this;
    }

    public static function runAll()
    {
        self::checkEnv();

        self::init();

        self::parseCommand();

        self::daemonize();

        self::installSignal();

        self::saveMasterPid();

        self::resetStd();

        self::forkWorkers();

        self::monitorWorkers();
    }

    /**
     * check整个框架运行环境是否符合
     */
    protected static function checkEnv()
    {
        if(!function_exists('exec')){
            exit('请修改php.ini文件，开放exec方法'."\n");
        }

        if (php_sapi_name() != 'cli') {
            exit('only run in command line mode!');
        }

        if(!function_exists('posix_kill')){
            exit('请先安装posix扩展'."\n");
        }

        if(!function_exists('pcntl_fork')){
            exit('请先安装pcntl扩展'."\n");
        }

        if(!function_exists('msg_get_queue')){
            exit('请先安装sysvmsg扩展'."\n");
        }
    }

    /**
     * worker初始化 (Timer的初始化已被替代)
     */
    protected static function init()
    {
        $temp_dir = TEMP_PATH;

        if (!is_dir($temp_dir) && !mkdir($temp_dir)) {
            exit('mkdir runtime fail');
        }
        $test_file = $temp_dir . 'test';
        if(touch($test_file)){
            @unlink($test_file);
        }else{
            exit('permission denied: dir('.$temp_dir.')');
        }

        if (empty(self::$status_file)) {
            self::$status_file = $temp_dir . 'status_file.status';
        }

        if (empty(self::$pid_file)) {
            self::$pid_file = $temp_dir . 'worker.pid';
        }

        if (empty(self::$log_file)) {
            self::$log_file = LOG_PATH . 'worker.log';
        }

    }

    /**
     * 处理启动参数
     */
    protected static function parseCommand()
    {
        global $argv;

        if (!isset($argv[0]) || !in_array($argv[0],['start','stop','restart','status'])) {
            exit("Usage: php yourfile.php {start|stop|restart|status}\n");
        }
        //检测master进程是否存货
        $master_id = @file_get_contents(self::$pid_file);
        $master_is_alive = $master_id && posix_kill($master_id, 0);

        if ($master_is_alive) {
            if ($argv[0] == 'start' && posix_getpid() != $master_id) {
                exit('jtimer worker is already running!' . PHP_EOL);
            }
        } else {
            if ($argv[0] != 'start') {
                exit('jtimer worker not run!' . PHP_EOL);
            }
        }
        switch ($argv[0]) {
            case 'start':
                if($argv[1] == '-d'){
                    static::$daemonize = true;
                }
                break;
            case 'status':
                if (is_file(self::$status_file)) {
                    @unlink(self::$status_file);
                }
                posix_kill($master_id, SIGUSR2);
                usleep(300000);
                @readfile(self::$status_file);
                exit(0);
            case 'restart':
                static::$daemonize = true;
            case 'stop':
                //向主进程发出stop的信号
                self::log('jtimer worker[' . $master_id . '] stopping....');
                $master_id && $flag = posix_kill($master_id, SIGINT);
                while ($master_id && posix_kill($master_id, 0)) {
                    usleep(300000);
                }
                self::log('jtimer worker[' . $master_id . '] stop success');
                if($argv[0] == 'stop'){
                    exit(0);
                }
                break;
            default:
                exit("Usage: php yourfile.php {start|stop|reload|status}\n");
                break;
        }

    }

    /**
     * @throws Exception
     * 设置守护进程
     */
    protected static function daemonize()
    {
        if(static::$daemonize == false){
            return;
        }
        umask(0);
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new Exception("fork fail");
        } elseif ($pid > 0) {
            exit(0);
        } else {
            if (-1 === posix_setsid()) {
                throw new Exception("setsid fail");
            }
            self::setProcessTitle('jtimer : master');
        }

    }

    protected static function saveMasterPid()
    {
        self::$master_pid = posix_getpid();
        if (false === @file_put_contents(self::$pid_file, self::$master_pid)) {
            throw new Exception('fail to save master pid: ' . self::$master_pid);
        }
    }

    protected static function forkWorkers()
    {
        // fork task 进程
        while(count(static::$task_workers) < self::$runner->task_worker_count){
            static::forkOneWorker(self::$runner,'task');
        }
        // fork time,db 进程
        while (count(self::$workers) < count(self::$worker_names)) {
            $curr_name = current(self::$worker_names);
            if ($curr_name == '' || $curr_name == false){
                break;
            }
            if (! in_array($curr_name, array_values(self::$workers))) {
                self::forkOneWorker(self::$runner, $curr_name);
                self::$workers[] = $curr_name;
                next(self::$worker_names);
            }
        }
    }

    protected static function installSignal()
    {
        pcntl_signal(SIGINT, array(__CLASS__, 'signalHandler'), false);
        pcntl_signal(SIGUSR2, array(__CLASS__, 'signalHandler'), false);
        pcntl_signal(SIGPIPE, SIG_IGN, false);
        pcntl_signal(SIGHUP, SIG_IGN, false);
    }

    public static function signalHandler($signal)
    {
        switch ($signal) {
            case SIGINT: // Stop.
                self::stopAll();
                break;
            case SIGUSR1:
                break;
            case SIGUSR2: // Show status.
                self::writeStatus();
                break;
        }
    }

    protected static function writeStatus()
    {
        $pid = posix_getpid();
        if (self::$master_pid == $pid) {
            $master_alive = self::$master_pid && posix_kill(self::$master_pid, 0);
            $master_alive = $master_alive ? 'is running' : 'die';
            $result = file_put_contents(self::$status_file, 'master[' . self::$master_pid . '] ' . $master_alive . PHP_EOL, FILE_APPEND | LOCK_EX);
            self::log('status:'.$result);
            foreach (self::$workers as $pid => $worker_name) {
                posix_kill($pid, SIGUSR2);
            }
            foreach (self::$task_workers as $pid => $worker_name) {
                posix_kill($pid, SIGUSR2);
            }
        } else {
            $name = 'worker[' . $pid . ']';
            $alive = $pid && posix_kill($pid, 0);
            $alive = $alive ? 'is running' : 'die';
            file_put_contents(self::$status_file, $name . ' ' . $alive . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    protected static function forkOneWorker(Worker $runner, $worker_name)
    {
        $pid = pcntl_fork();
        if ($pid > 0) {
            if($worker_name == 'task'){
                static::$task_workers[$pid] = $pid;
            } else {
                self::$workers[$pid] = $worker_name;
            }
        } elseif ($pid == 0) {
            if (!is_null($runner->timer_id))
                swoole_timer_clear($runner->timer_id);
            $runner->worker_name = $worker_name;
            if ($worker_name != 'task')
                echo 'jtimer worker start ', $worker_name, "\n";
            self::log($worker_name . ' jtimer worker start');
            self::setProcessTitle('jtimer : ' . $worker_name);
            $runner->run();
        } else {
            throw new Exception('fork one worker fail');
        }
    }

    protected static function resetStd()
    {
        if(static::$daemonize === false){
            return;
        }
        global $STDOUT, $STDERR;
        $handle = fopen(self::$stdoutFile, "a");
        if ($handle) {
            unset($handle);
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDOUT = fopen(self::$stdoutFile, "a");
            $STDERR = fopen(self::$stdoutFile, "a");
        } else {
            throw new Exception('can not open stdoutFile ' . self::$stdoutFile);
        }
    }

    /**
     * @throws Exception
     * 监控worker工作状况
     */
    protected static function monitorWorkers()
    {
        self::$status = self::STATUS_RUNNING;// 1
        if(count(array_flip(static::$workers)) > 1){
//            print_r(static::$workers);
            return;
        }
        while (1) {
            pcntl_signal_dispatch();
            $pid = pcntl_wait($status, WUNTRACED);
            self::log("worker[ $pid ] exit with signal:".pcntl_wstopsig($status));
            pcntl_signal_dispatch();
            //child exit
            echo "pcntl wait pid: ". $pid,"\n";
            if ($pid > 0) {
                echo "当前 status: ". self::$status, ' shut down : ', self::STATUS_SHUTDOWN, "\n";
                if (self::$status != self::STATUS_SHUTDOWN) {
                    if(isset(static::$workers[$pid])){
                        $worker_name = self::$workers[$pid];
                        unset(self::$workers[$pid]);
                    }else{
                        $worker_name = 'task';
                        unset(self::$task_workers[$pid]);
                    }
                    self::forkOneWorker(self::$runner, $worker_name);
                }
            }
        }

    }

    /**
     * 执行进程里的任务方法
     * 多进程抢队列消息
     */
    protected function run()
    {
        if($this->worker_name == 'task'){
            $queue = msg_get_queue(static::QUEUE_KEY);
            while(1){
                msg_receive($queue,0,$msg_type,2024,$msg);
                print_r('---------------------');
                print_r($msg);
                print_r('***********************');
                echo "\n";
                if($msg && $this->onTask)
                    call_user_func($this->onTask, $this, $msg);
                pcntl_signal_dispatch();
            }
        } else {
            //worker进程
            if($this->onWorkerStart){
                try {
                    call_user_func($this->onWorkerStart, $this);
                } catch (\Exception $e) {
                    echo 'this here 1****:'.$this->worker_name."\n";
                    static::log($e);
                    // Avoid rapid infinite loop exit.
                    sleep(1);
                    exit(250);
                } catch (\Error $e) {
                    echo 'this here 2****:'.$this->worker_name."\n";
                    static::log($e);
                    // Avoid rapid infinite loop exit.
                    sleep(1);
                    exit(250);
                }
            }
        }

    }

    public function task($data){
        $queue = msg_get_queue(static::QUEUE_KEY);
        $flag = msg_send($queue,1,$data);
    }

    /**
     * @param $title
     * 给此进程设置标题
     */
    protected static function setProcessTitle($title)
    {
        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title($title);
        }
    }

    protected static function stopAll()
    {
        $pid = posix_getpid();
        if (self::$master_pid == $pid) { //master
            self::$status = self::STATUS_SHUTDOWN;
            foreach (self::$workers as $pid => $worker_name) {
                //停止worker进程
                posix_kill($pid, SIGINT);
            }
            foreach (self::$task_workers as $pid => $worker_name) {
                //停止task进程
                posix_kill($pid, SIGINT);
            }
            //停止master进程
            @unlink(self::$pid_file);
            exit(0);
        } else { //child
            var_dump($pid);
            self::log('push worker pid: ' . $pid . ' stop');
            exit(0);
        }
    }

    protected static function log($message)
    {
        $message = '['.date('Y-m-d H:i:s,') .']['. $message . "]\n";
        file_put_contents((string)self::$log_file, $message, FILE_APPEND | LOCK_EX);
    }

}

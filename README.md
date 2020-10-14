# CJMT

*linux 计划任务可视化管理工具(cron jobs management tool)*

**jtimer使用了master-worker进程模型，能够实现无阻塞执行任务。**

**时间表达使用了cron表达式，可精确到秒级，方便好用(比crontab多一位)**

## 声明
此项目在https://gitee.com/itzhoujun/JTimer 基础上进行了升级，修复了原php版本的bug(包括定时器延时误差)，目前在单一服务器上测试数月，稳定精准运行。
感谢原作者https://gitee.com/itzhoujun 的创造

## 安装与使用

### 项目要求：
1. php.ini开放exec方法
2. 安装pcntl扩展
3. 安装posix扩展
4. 安装sysvmsg扩展
5. 安装swoole扩展

ps:仅支持Linux

## 后台部署

项目后台基于thinkphp5+layui实现，部署方法参考thinkphp5官方文档，此处不再阐述。

数据库文件位于项目根目录 jtimer.sql，请自行导入

默认用户名密码：admin/admin


## 任务进程管理
所有命令均在项目根目录下执行

启动进程：（守护进程模式）
> php think jtimer start -d

启动进程：（调试模式）
> php think jtimer start

设置进程池进程数
> php think jtimer start -t 10

重启进程：
> php think jtimer restart

停止进程：
> php think jtimer stop

查看进程状态：
> php think jtimer status 或 ps aux | grep jtimer

# 架构介绍

## cron表达式
```
* * * * * *
| | | | | |
| | | | | ---- 星期（0-6）  
| | | | ------ 月份（1-12）
| | | -------- 日  （1-31）
| | ---------- 时  （0-23）
| ------------ 分  （0-59）
|------------- 秒  （0-59） 
 ```

## 进程模型

![输入图片说明](https://gitee.com/uploads/images/2018/0516/134824_90577c77_369962.png "未命名文件 (1).png")

简单来说，就是两个worker进程，1个负责数据的读写（读任务），1个负责任务的管理（传递任务给task进程执行）。

task进程池接收任务并执行

两者之间通过tp框架自带的文件缓存作为沟通的桥梁。

Q1：cron任务定时执行是如何实现的？

> 先解析任务的cron表达式得到该任务下次要执行的具体时间，然后将该任务置于时间轮片（TimingWheel）中，worker进程每秒查看一次时间轮片，发现有要执行的任务就取出来执行。执行完毕后再重复执行上面的步骤。（关于TimingWheel，请自行百度）

# 演示

![输入图片说明](https://gitee.com/uploads/images/2018/0403/114238_09c5b565_369962.png "TIM截图20180403113947.png")

![输入图片说明](https://gitee.com/uploads/images/2018/0403/114247_fed9251f_369962.png "TIM截图20180403114002.png")

![输入图片说明](https://gitee.com/uploads/images/2018/0403/114256_3f9a3561_369962.png "TIM截图20180403114154.png")

![输入图片说明](https://gitee.com/uploads/images/2018/0403/114305_dd1f5c3b_369962.png "TIM截图20180403114207.png")

![输入图片说明](https://gitee.com/uploads/images/2018/0403/114312_49078c1a_369962.png "TIM截图20180403114215.png")

# 注意
1. 由于任务的执行使用了task进程池的方式，请根据同一时刻任务最多数量来自行决定task进程数（默认8个），例如你一共50个任务，在某个时间点，可能有10个任务同时运行，那么可以将task进程数设置为10。否则可能导致任务排队。如果任务即使排队也不影响你的业务，那么无视这一条。修改task默认进程数在application/timer/command/JTimerCommand.php，也可以在启动时指定，如php think jtimer start -d -t 10

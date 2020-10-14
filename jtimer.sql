/*
Navicat MySQL Data Transfer

Source Server         : vagrant
Source Server Version : 50711
Source Host           : 192.168.33.10:3306
Source Database       : jtimer

Target Server Type    : MYSQL
Target Server Version : 50711
File Encoding         : 65001

Date: 2018-04-03 10:49:14
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for jt_cate
-- ----------------------------
DROP TABLE IF EXISTS `jt_cate`;
CREATE TABLE `jt_cate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL COMMENT '类别名称',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of jt_cate
-- ----------------------------
INSERT INTO `jt_cate` VALUES ('1', '外卖项目');
INSERT INTO `jt_cate` VALUES ('2', '电商项目');

-- ----------------------------
-- Table structure for jt_cron_task
-- ----------------------------
DROP TABLE IF EXISTS `jt_cron_task`;
CREATE TABLE `jt_cron_task` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cate_id` int(11) DEFAULT NULL COMMENT '任务类别id',
  `cron_expression` varchar(30) DEFAULT NULL COMMENT 'cron表达式',
  `cmd` varchar(255) NOT NULL COMMENT '任务要执行的命令',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '任务状态 0-禁用 1-正常',
  `remark` varchar(50) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '任务创建时间',
  `update_time` timestamp NULL DEFAULT NULL COMMENT '任务修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of jt_cron_task
-- ----------------------------
INSERT INTO `jt_cron_task` VALUES ('1', '1', '0 20 15 * * *', 'echo 12312323', '1', '', null, null);
INSERT INTO `jt_cron_task` VALUES ('2', '1', '0 0,40 18-23 * * *', 'echo 123 >> /root/123.log', '1', '更新test.log文件', null, null);

-- ----------------------------
-- Table structure for jt_cron_task_log
-- ----------------------------
DROP TABLE IF EXISTS `jt_cron_task_log`;
CREATE TABLE `jt_cron_task_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ct_id` int(11) NOT NULL COMMENT '任务id',
  `cmd` varchar(255) DEFAULT NULL COMMENT '执行的命令',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '开始执行时间',
  `spend_time` float DEFAULT NULL COMMENT '耗时，单位秒',
  PRIMARY KEY (`id`),
  KEY `ct_id` (`ct_id`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB AUTO_INCREMENT=1513 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of jt_cron_task_log
-- ----------------------------

-- ----------------------------
-- Table structure for jt_setting
-- ----------------------------
DROP TABLE IF EXISTS `jt_setting`;
CREATE TABLE `jt_setting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '配置项名称',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题',
  `value` varchar(255) NOT NULL DEFAULT '' COMMENT '配置项值',
  `group` tinyint(1) NOT NULL COMMENT '分组 1-系统设置 2-日志设置',
  `type` tinyint(1) NOT NULL COMMENT '类型：1-text 2-textarea 3-select',
  `remark` varchar(100) NOT NULL DEFAULT '' COMMENT '备注',
  `options` varchar(255) NOT NULL DEFAULT '' COMMENT 'type为3时的选项，格式为:\r\n1:选项1\r\n2:选项2',
  `sort` smallint(3) NOT NULL DEFAULT '0' COMMENT '排序',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of jt_setting
-- ----------------------------
INSERT INTO `jt_setting` VALUES ('1', 'show_validate_code', '登录验证码', '1', '1', '3', '登录时是否启用验证码', '1:启用\r\n0:不启用', '0');
INSERT INTO `jt_setting` VALUES ('2', 'cron_task_log_save_day', '保留天数', '1', '2', '1', 'cron任务执行日志保留天数（N天之前的日志自动删除，0表示不删除）', '', '0');
INSERT INTO `jt_setting` VALUES ('4', 'admin_limit_ip', '限制ip', '', '1', '2', '后台限制ip访问，不填表示不限制（多个ip使用英文逗号隔开）', '', '0');

-- ----------------------------
-- Table structure for jt_user
-- ----------------------------
DROP TABLE IF EXISTS `jt_user`;
CREATE TABLE `jt_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(30) DEFAULT NULL,
  `password` varchar(32) DEFAULT NULL,
  `salt` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of jt_user
-- ----------------------------
INSERT INTO `jt_user` VALUES ('1', 'admin', '9836c6e9ea4b0140a0a6a6ccdfb141ca', 'dfh38h');

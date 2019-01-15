-- phpMyAdmin SQL Dump
-- version 4.1.14
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: 2019-01-15 07:41:14
-- 服务器版本： 5.6.17
-- PHP Version: 5.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `ny-iot-hub`
--

-- --------------------------------------------------------

--
-- 表的结构 `iot_device_info`
--

CREATE TABLE IF NOT EXISTS `iot_device_info` (
  `devinfo_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '信息条目id',
  `devinfo_mac` char(16) NOT NULL COMMENT '硬件mac地址',
  `devinfo_mac_token` char(100) NOT NULL COMMENT '硬件mac地址对应的token',
  `devinfo_create_ip` char(16) NOT NULL COMMENT '创建者ip',
  `devinfo_create_time` datetime NOT NULL COMMENT '创建时间',
  `devinfo_update_time` datetime NOT NULL COMMENT '更新时间',
  `devinfo_is_dele` int(2) NOT NULL COMMENT '是否删除',
  `devinfo_dele_time` datetime NOT NULL COMMENT '删除时间',
  `devinfo_comment` varchar(512) NOT NULL COMMENT '硬件备注信息',
  PRIMARY KEY (`devinfo_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='硬件设备信息' AUTO_INCREMENT=196 ;

-- --------------------------------------------------------

--
-- 表的结构 `iot_device_msg`
--

CREATE TABLE IF NOT EXISTS `iot_device_msg` (
  `devmsg_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '信息条目id',
  `devmsg_mac` char(16) NOT NULL COMMENT '硬件mac地址',
  `devmsg_len` int(11) NOT NULL COMMENT '数据长度',
  `devmsg_msg` varchar(1024) NOT NULL COMMENT '交互的具体内容',
  `devmsg_orient` tinyint(4) NOT NULL COMMENT '数据流方向,1上行;2下行',
  `devmsg_status` tinyint(4) NOT NULL COMMENT '状态,10上行待接收;11上行接收成功;12上行接收失败;20下行待接收;21下行接收成功;22下行接收失败',
  `devmsg_time` datetime NOT NULL COMMENT '接收数据时间',
  PRIMARY KEY (`devmsg_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='硬件数据交互' AUTO_INCREMENT=25 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

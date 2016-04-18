<?php 
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
use \Workerman\Worker;
use \GatewayWorker\ThriftGateway;
use \Workerman\Autoloader;
use Services\GateWay\Ping;

// 自动加载类
require_once __DIR__ . '/../../Workerman/Autoloader.php';
Autoloader::setRootPath(__DIR__);

// gateway 进程
$gateway = new \GatewayWorker\ThriftGateway("ThriftWebsocket://0.0.0.0:8189");
// gateway名称，status方便查看
$gateway->name = 'Gateway-ws';
// gateway进程数
$gateway->count = 2;
// 本机ip，分布式部署时使用内网ip
$gateway->lanIp = '127.0.0.1';
// 内部通讯起始端口，假如$gateway->count=4，起始端口为4000
// 则一般会使用4001 4002 4003 4004 4个端口作为内部通讯端口 
$gateway->startPort = 2800;
// 心跳间隔
$gateway->pingInterval = 100;
$gateway->pingNotResponseLimit = 2;
$gateway->pingData = '';
// 服务注册地址
$gateway->registerAddress = '127.0.0.1:1838';

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}


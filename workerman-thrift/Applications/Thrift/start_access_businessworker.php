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
use \Workerman\Autoloader;
use Thrift\ClassLoader\ThriftClassLoader;
use GatewayWorker\ThriftBusinessWorker;
use GatewayWorker\BusinessWorker;
// 自动加载类
require_once __DIR__ . '/../../Workerman/Autoloader.php';
Autoloader::setRootPath(__DIR__);
// bussinessWorker 进程
$worker = new ThriftBusinessWorker();
// worker名称
$worker->name = 'Access_BusinessWorker';
$worker->class = 'Access';
$worker->eventHandler = 'AccessEvent';
$worker->servicesDir = __DIR__;
// bussinessWorker进程数量
$worker->count = 2;
// 服务注册地址
$worker->registerAddress = '127.0.0.1:1838';
// loger地址
$worker->loggerAddress = '127.0.0.1:2207';
// 统计地址
$worker->report_address = '127.0.0.1:55656';
// Channel地址
$worker->channelAddress = '127.0.0.1:2206';
$worker->thriftProtocol = 'TBinaryProtocol';
// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}


<?php

namespace GatewayWorker;

use GatewayWorker\Protocols\GatewayProtocol;
use Thrift\ClassLoader\ThriftClassLoader;

require_once __DIR__ . '/Lib/Thrift/ClassLoader/ThriftClassLoader.php';
$loader = new ThriftClassLoader ();
$loader->registerNamespace ( 'Thrift', __DIR__ . '/Lib' );
$loader->register ();
/**
 *
 * ThriftGateway，基于Gateway开发
 * 用于转发客户端的数据给Worker处理，以及转发Worker的数据给客户端
 *
 * @author 不再迟疑
 */
class ThriftGateway extends Gateway {
	private $_thriftWorkerConnections = array ();
	/**
	 * 构造函数
	 *
	 * @param string $socket_name        	
	 * @param array $context_option        	
	 */
	public function __construct($socket_name, $context_option = array()) {
		parent::__construct ( $socket_name, $context_option );
		$this->router = array (
				$this,
				'thriftRouterBind' 
		);
	}
	/**
	 * client_id与worker绑定
	 *
	 * @param array $worker_connections        	
	 * @param TcpConnection $client_connection        	
	 * @param int $cmd        	
	 * @param mixed $buffer        	
	 * @return TcpConnection
	 */
	public function thriftRouterBind($worker_connections, $client_connection, $cmd, $buffer) {
		if (! isset ( $client_connection->businessworker_address )) { // 用户连上来，初始化下
			$client_connection->businessworker_address = array ();
		}
		if (! isset ( $buffer ['class_name'] )) { // 系统消息进行广播
			$allWorkerName = array_keys ( $client_connection->businessworker_address );
			$allWorkerConnections = array ();
			foreach ( $allWorkerName as $value ) {
				$thriftworker_connections = $this->_thriftWorkerConnections [$value];
				if (! isset ( $thriftworker_connections [$client_connection->businessworker_address [$value]] )) { // 由于reolad导致的失效
					$client_connection->businessworker_address [$value] = array_rand ( $thriftworker_connections );
				}
				array_push ( $allWorkerConnections, $thriftworker_connections [$client_connection->businessworker_address [$value]] );
			}
			return $allWorkerConnections;
		}
		// 普通消息
		$class_name = $buffer ['class_name'];
		$thriftworker_connections = $this->_thriftWorkerConnections [$class_name];
		$needSet = false;
		if (! isset ( $client_connection->businessworker_address [$class_name] )) { // 代表与该worker第一次连接,动态新增的worker类型才会导致
			$needSet = true;
		} elseif (! isset ( $thriftworker_connections [$client_connection->businessworker_address [$class_name]] )) { // 由于reolad导致的失效
			$needSet = true;
		}
		if ($needSet) {
			if (count ( $thriftworker_connections ) > 0) {
				$client_connection->businessworker_address [$class_name] = array_rand ( $thriftworker_connections );
			} else {
				$this->log ( '[' . $class_name . ']-businessworker do not start!' );
			}
		}
		return array (
				$thriftworker_connections [$client_connection->businessworker_address [$class_name]] 
		);
	}
	
	/**
	 * 发送数据给worker进程
	 *
	 * @param int $cmd        	
	 * @param TcpConnection $connection        	
	 * @param mixed $body        	
	 */
	protected function sendToWorker($cmd, $connection, $body = '') {
		$gateway_data = $connection->gatewayHeader;
		$gateway_data ['cmd'] = $cmd;
		if (isset ( $body ['body'] )) {
			$gateway_data ['body'] = $body ['body'];
		} else {
			$gateway_data ['body'] = $body;
		}
		$gateway_data ['ext_data'] = $connection->session;
		if ($this->_workerConnections) {
			// 调用路由函数，选择一个worker把请求转发给它
			$worker_connections = call_user_func ( $this->router, $this->_workerConnections, $connection, $cmd, $body );
			$flag = false;
			foreach ( $worker_connections as $value ) {
				if (false === $value->send ( $gateway_data )) {
					$msg = "SendBufferToWorker fail. May be the send buffer are overflow";
					$this->log ( $msg );
					$flag = true;
				}
			}
			if ($flag)
				return false;
		} else { // 没有可用的worker
		       // gateway启动后1-2秒内SendBufferToWorker fail是正常现象，因为与worker的连接还没建立起来，所以不记录日志，只是关闭连接
			$time_diff = 2;
			if (time () - $this->_startTime >= $time_diff) {
				$msg = "SendBufferToWorker fail. The connections between Gateway and BusinessWorker are not ready";
				$this->log ( $msg );
			}
			$connection->destroy ();
			return false;
		}
		return true;
	}
	/**
	 * 当worker发来数据时
	 *
	 * @param TcpConnection $connection        	
	 * @param mixed $data        	
	 * @throws \Exception
	 */
	public function onWorkerMessage($connection, $data) {
		parent::onWorkerMessage ( $connection, $data );
		$cmd = $data ['cmd'];
		if ($cmd == GatewayProtocol::CMD_WORKER_CONNECT) {
			$class_name = $data ['body'];
			$connection->class_name = $class_name;
			if (! array_key_exists ( $class_name, $this->_thriftWorkerConnections )) {
				$this->_thriftWorkerConnections [$class_name] = array ();
			}
			$this->_thriftWorkerConnections [$class_name] [$connection->remoteAddress] = $connection;
		}
	}
	/**
	 * 当worker连接关闭时
	 *
	 * @param TcpConnection $connection        	
	 */
	public function onWorkerClose($connection) {
		parent::onWorkerClose ( $connection );
		if (isset ( $connection->class_name )) {
			unset ( $this->_thriftWorkerConnections [$connection->class_name] );
		}
	}
}

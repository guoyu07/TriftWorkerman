<?php

namespace GatewayWorker;

use \GatewayWorker\Lib\Context;
use \GatewayWorker\Protocols\GatewayProtocol;
use GatewayWorker\Lib\ChannelEventDispatcher;
use GatewayWorker\Lib\EventDispatcher;
use GatewayWorker\Lib\FrameChild;
use GatewayWorker\Protocols\ThriftProtocol;
use Logger\Client;
use Thrift\ClassLoader\ThriftClassLoader;
use Thrift\Transport\TMemoryBuffer;
use Thrift\Type\TMessageType;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;

require_once __DIR__ . '/Lib/Thrift/ClassLoader/ThriftClassLoader.php';
require_once __DIR__ . '../../Channel/Client.php';
require_once __DIR__ . '../../Applications/Statistics/Clients/StatisticClient.php';
$loader = new ThriftClassLoader ();
$loader->registerNamespace ( 'Thrift', __DIR__ . '/Lib' );
$loader->register ();
/**
 *
 * ThriftBusinessWorker 用于处理Gateway转发来的数据
 *
 * @author 不再迟疑
 *        
 */
class ThriftBusinessWorker extends BusinessWorker {
	public static $EVENT_CLIENT_CONNECT_CLOSED = 'event_client_connect_closed';
	public static $EVENT_CLIENT_CONNECT_OPENED = 'event_client_connect_opened';
	/**
	 * 会自动保存数据，reload的时候会自动恢复
	 *
	 * @var array $saveData
	 */
	public $saveData = array ();
	/**
	 * Thrift $servicesDir
	 *
	 * @var object
	 */
	public $servicesDir = '';
	
	/**
	 * 日志服务器地址
	 *
	 * @var unknown
	 */
	public $loggerAddress = '';
	/**
	 * 统计服务器地址
	 */
	public $report_address = '';
	/**
	 * 使用的协议,默认TBinaryProtocol,可更改
	 *
	 * @var string
	 */
	public $thriftProtocol = 'TBinaryProtocol';
	
	/**
	 * 设置类名称
	 *
	 * @var string
	 */
	public $class = '';
	
	/**
	 * 帧频
	 *
	 * @var int
	 */
	public $frameRate = 0;
	
	/**
	 * 参与帧循环的root原件
	 *
	 * @var FrameChild
	 */
	public $frameRoot = null;
	/**
	 * 事件派发器
	 *
	 * @var EventDispatcher
	 */
	public $eventDisparcher;
	/**
	 * channel地址
	 *
	 * @var unknown
	 */
	public $channelAddress;
	/**
	 * 处理类名称
	 *
	 * @var unknown
	 */
	protected $handler_class = '';
	/**
	 * Thrift processor
	 *
	 * @var object
	 */
	protected $processor = null;
	
	/**
	 *
	 * @var TBuffer
	 */
	protected $tBuffer = null;
	
	/**
	 *
	 * @var TProtocol
	 */
	protected $tProtocol = null;
	
	/**
	 *
	 * @var TProtocolHandler
	 */
	protected $tProtocolHandler = null;
	
	/**
	 * Event启动的回调
	 *
	 * @var function
	 */
	protected $_eventOnWorkerStart;
	/**
	 * 保存数据的文件名
	 *
	 * @var string
	 */
	protected $saveDateName;
	
	/**
	 * 当进程启动时一些初始化工作,加载saveData
	 *
	 * @return void
	 */
	protected function onWorkerStart() {
		parent::onWorkerStart ();		
		// 统计初始化
		if (empty ( $this->report_address )) {
			echo "Warning : report_address not set!\n";
		}else{
			$this->report_address = 'udp://'.$this->report_address;
		}
		// Logger初始化
		if (empty ( $this->loggerAddress )) {
			echo "Warning : logger address not set!\n";
		} else {
			\Logger\Client::init( $this->loggerAddress, $this->name );
		}
		// Channel初始化地址
		ChannelEventDispatcher::$channelAddress = $this->channelAddress;
		// 初始化时间派发器
		$this->eventDisparcher = new EventDispatcher ();
		// 检查类是否设置
		if (! $this->class) {
			throw new \Exception ( 'ThriftGatewayWorker->class not set' );
		}
		// 设置处理类的名称，去掉？后面的字符
		$this->handler_class = explode ( "?", $this->class, 2 ) [0];
		
		if (! $this->servicesDir) {
			throw new \Exception ( 'ThriftGatewayWorker->servicesDir not set' );
		}
		// 载入该服务下的所有文件
		foreach ( glob ( $this->servicesDir . '/Services/' . $this->handler_class . '/*.php' ) as $php_file ) {
			require_once $php_file;
		}
		
		// 检查类是否存在
		$processor_class_name = "\\Services\\" . $this->handler_class . "\\" . $this->handler_class . 'Processor';
		if (! class_exists ( $processor_class_name )) {
			$this->log ( "Class $processor_class_name not found" );
			return;
		}
		
		// 检查类是否存在
		$client_class_name = "\\Services\\" . $this->handler_class . "\\" . $this->handler_class . 'Client';
		if (! class_exists ( $client_class_name )) {
			$this->log ( "Class $client_class_name not found" );
			return;
		}
		
		// 检查类是否存在
		$handler_class_name = "\\Services\\" . $this->handler_class . "\\" . $this->handler_class . 'Handler';
		if (! class_exists ( $handler_class_name )) {
			$this->log ( "Class $handler_class_name not found" );
			return;
		}
		$this->tProtocolHandler = new $handler_class_name ();
		$this->tProtocolHandler->worker = $this;
		$this->processor = new $processor_class_name ( $this->tProtocolHandler );
		$this->tBuffer = new TMemoryBuffer ();
		$protocol_name = '\\Thrift\\Protocol\\' . $this->thriftProtocol;
		$this->tProtocol = new $protocol_name ( $this->tBuffer );
		// frameRoot
		$this->frameRoot = new FrameChild ();
		$this->frameRoot->__onAdded ( $this, false );
		// 检查onWorkerStart并调用接口
		if (is_callable ( $this->eventHandler . '::onWorkerStart' )) {
			$this->_eventOnWorkerStart = $this->eventHandler . '::onWorkerStart';
			call_user_func ( $this->_eventOnWorkerStart, $this );
		} else {
			echo "Waring: {$this->eventHandler}::onWorkerStart is not callable\n";
		}
		// 加载saveData
		$this->saveDateName = "$this->class[$this->registerAddress]:$this->id@store";
		if (file_exists ( $this->saveDateName )) {
			$s = file_get_contents ( $this->saveDateName );
			$this->saveData = unserialize ( $s );
			unlink ( $this->saveDateName );
		}
		// 如果使能了EnterFrame就启动Time
		if ($this->frameRate > 0) {
			$this->startFrameTimer ();
			if (isset ( $this->saveData ['@frameAutoSaveData'] )) {
				$this->frameRoot->loadData ( $this->saveData ['@frameAutoSaveData'] );
			}
		}
	}
	/**
	 * 启动frame定时器
	 */
	public function startFrameTimer() {
		Timer::add ( 1 / $this->frameRate, array (
				$this,
				'onEnterFrame' 
		) );
	}
	public function onEnterFrame() {
		$this->frameRoot->__onEnterFrame ();
		if ($this->frameRoot->getUseTime () > 1 / $this->frameRate * 1000) {
			$logger_message = "Frame Time Out :\n".implode("\n", $this->frameRoot->getFrameUseTimeStack());
			Client::log(Client::WARNING, $logger_message);
		}
	}
	/**
	 * onWorkerReload回调,保存saveData
	 *
	 * @param Worker $worker        	
	 */
	protected function onWorkerReload($worker) {
		parent::onWorkerReload ( $worker );
		if ($this->frameRoot) {
			$this->saveData ['@frameAutoSaveData'] = array ();
			$this->frameRoot->saveData ( $this->saveData ['@frameAutoSaveData'] );
		}
		$data = serialize ( $this->saveData );
		file_put_contents ( $this->saveDateName, $data );
	}
	/**
	 * 创建一个消息
	 *
	 * @param object $successData        	
	 * @param string $fname        	
	 * @param TMessageType::REPLY $type        	
	 * @param number $seqid        	
	 */
	public function createThriftMessage($successData, $fname, $type = TMessageType::REPLY, $seqid = 0) {
		// 检查类是否存在
		$result_class_name = "\\Services\\" . $this->handler_class . "\\" . $this->handler_class . '_' . $fname . '_result';
		if (! class_exists ( $result_class_name )) {
			$this->log ( "Class $result_class_name not found" );
			return;
		}
		$result = new $result_class_name ();
		$result->success = $successData;
		
		$seqid = 0;
		$this->tBuffer->clear ();
		$this->tProtocol->writeMessageBegin ( $fname, $type, $seqid );
		$result->write ( $this->tProtocol );
		$this->tProtocol->writeMessageEnd ();
		$message_body = $this->tBuffer->read ( $this->tBuffer->available () );
		$message = ThriftProtocol::$empty;
		$message ['body'] = $message_body;
		$message ['class_name'] = $this->class;
		return $message;
	}
	
	/**
	 * 当gateway转发来数据时
	 *
	 * @param TcpConnection $connection        	
	 * @param mixed $data        	
	 */
	public function onGatewayMessage($connection, $data) {
		$cmd = $data ['cmd'];
		if ($cmd === GatewayProtocol::CMD_PING) {
			return;
		}
		
		// 上下文数据
		Context::$client_ip = $data ['client_ip'];
		Context::$client_port = $data ['client_port'];
		Context::$local_ip = $data ['local_ip'];
		Context::$local_port = $data ['local_port'];
		Context::$connection_id = $data ['connection_id'];
		Context::$client_id = Context::addressToClientId ( $data ['local_ip'], $data ['local_port'], $data ['connection_id'] );
		// $_SERVER变量
		$_SERVER = array (
				'REMOTE_ADDR' => long2ip ( $data ['client_ip'] ),
				'REMOTE_PORT' => $data ['client_port'],
				'GATEWAY_ADDR' => long2ip ( $data ['local_ip'] ),
				'GATEWAY_PORT' => $data ['gateway_port'],
				'GATEWAY_CLIENT_ID' => Context::$client_id 
		);
		// 尝试解析session
		if ($data ['ext_data'] != '') {
			$_SESSION = Context::sessionDecode ( $data ['ext_data'] );
		} else {
			$_SESSION = null;
		}
		// 备份一次$data['ext_data']，请求处理完毕后判断session是否和备份相等，不相等就更新session
		$session_str_copy = $data ['ext_data'];
		
		if ($this->processTimeout) {
			pcntl_alarm ( $this->processTimeout );
		}
		// 尝试执行Event::onConnection、Event::onMessage、Event::onClose
		$fname = $mtype = $rseqid = null;
		switch ($cmd) {
			case GatewayProtocol::CMD_ON_CONNECTION :
				$this->eventDisparcher->dispatchEventWith ( ThriftBusinessWorker::$EVENT_CLIENT_CONNECT_OPENED );
				if ($this->_eventOnConnect) {
					call_user_func ( $this->_eventOnConnect, Context::$client_id );
				}
				break;
			case GatewayProtocol::CMD_ON_MESSAGE :
				$this->tBuffer->clear ();
				$this->tBuffer->write ( $data ['body'] );
				try { // 协议解析出错踢掉客户端
					$this->tProtocol->readMessageBegin ( $fname, $mtype, $rseqid );
				} catch ( \Exception $e ) {
					\GatewayWorker\Lib\Gateway::closeCurrentClient ();
					break;
				}
				if ($this->_eventOnMessage) { // 验证客户端
					$auth = call_user_func ( $this->_eventOnMessage, $data, $fname );
					if (! $auth) { // 验证失败踢掉客户端
						\GatewayWorker\Lib\Gateway::closeCurrentClient ();
						break;
					}
				}
				\StatisticClient::tick($this->class, $fname);
				$methodname = 'send_' . $fname;
				$this->tBuffer->clear ();
				$this->tBuffer->write ( $data ['body'] );
				$this->tProtocol->reset ();
				$success = $this->processor->process ( $this->tProtocol, $this->tProtocol );
				if ($mtype == TMessageType::ONEWAY) { // 单向的不返回
					\StatisticClient::report($this->name, $fname, $success, '0', '单向消息', $this->report_address);
					break;
				}
				if ($success) { // 处理成功
					$message_body = $this->tBuffer->read ( $this->tBuffer->available () );
					$message = ThriftProtocol::$empty;
					$message ['body'] = $message_body;
					$message ['class_name'] = $this->class;
					if (method_exists ( $this->tProtocolHandler, $methodname )) { // 方法存在
						call_user_func ( array (
								$this->tProtocolHandler,
								$methodname 
						), $message );
					}
					\StatisticClient::report($this->name, $fname, $success, '1', '成功', $this->report_address);
				} else {
					\StatisticClient::report($this->name, $fname, $success, '-1', '消息处理失败', $this->report_address);
				}
				break;
			case GatewayProtocol::CMD_ON_CLOSE :
				if ($this->_eventOnClose) {
					call_user_func ( $this->_eventOnClose, $this, Context::$client_id );
				}
				break;
		}
		if ($this->processTimeout) {
			pcntl_alarm ( 0 );
		}
		
		// 判断session是否被更改
		$session_str_now = $_SESSION !== null ? Context::sessionEncode ( $_SESSION ) : '';
		if ($session_str_copy != $session_str_now) {
			\GatewayWorker\Lib\Gateway::updateSocketSession ( Context::$client_id, $session_str_now );
		}
		
		Context::clear ();
	}
	/**
	 * 尝试连接Gateway内部通讯地址
	 *
	 * @return void
	 */
	public function tryToConnectGateway($addr) {
		if (! isset ( $this->gatewayConnections [$addr] ) && ! isset ( $this->_connectingGatewayAddresses [$addr] ) && isset ( $this->_gatewayAddresses [$addr] )) {
			$gateway_connection = new AsyncTcpConnection ( "GatewayProtocol://$addr" );
			$gateway_connection->remoteAddress = $addr;
			$gateway_connection->onConnect = array (
					$this,
					'onConnectGateway' 
			);
			$gateway_connection->onMessage = array (
					$this,
					'onGatewayMessage' 
			);
			$gateway_connection->onClose = array (
					$this,
					'onGatewayClose' 
			);
			$gateway_connection->onError = array (
					$this,
					'onGatewayError' 
			);
			if (TcpConnection::$defaultMaxSendBufferSize == $gateway_connection->maxSendBufferSize) {
				$gateway_connection->maxSendBufferSize = 50 * 1024 * 1024;
			}
			$gateway_data = GatewayProtocol::$empty;
			$gateway_data ['cmd'] = GatewayProtocol::CMD_WORKER_CONNECT;
			$gateway_data ['body'] = $this->class;
			$gateway_connection->send ( $gateway_data );
			$gateway_connection->connect ();
			$this->_connectingGatewayAddresses [$addr] = $addr;
		}
		unset ( $this->_waitingConnectGatewayAddresses [$addr] );
	}
}

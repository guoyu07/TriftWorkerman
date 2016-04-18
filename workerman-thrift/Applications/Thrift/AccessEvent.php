<?php
use GatewayWorker\ThriftBusinessWorker;
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

/**
 * 主逻辑
 * 主要是处理 onMessage onClose 三个方法
 */
class AccessEvent {
	/**
	 * 启动时
	 * @param ThriftBusinessWorker $thriftBusinessWorker
	 */
	public static function onWorkerStart(ThriftBusinessWorker $thriftBusinessWorker){
		
	}
	/**
	 * 鉴权
	 *    	
	 * @param string $message        	
	 * @param string $fname        	
	 */
	public static function onMessage($message, $fname) {
		return true;
	}
	
	/**
	 * 当用户断开连接时
	 * 
	 * @param integer $client_id
	 *        	用户id
	 */
	public static function onClose(ThriftBusinessWorker $thriftBusinessWorker, $client_id) {
	}
	
}

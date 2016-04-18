<?php

namespace Services\Access;

use GatewayWorker\Lib\Context;
use GatewayWorker\Lib\Db;
use GatewayWorker\Lib\Gateway;
use GatewayWorker\Lib\RedisForDb;
use GatewayWorker\ThriftBusinessWorker;
use Services\Access\AccessIf;
use Services\Access\UserInfo;
use Frames\Game\TestFrame;

class AccessHandler implements AccessIf {
	/**
	 *
	 * @var \GatewayWorker\ThriftBusinessWorker
	 */
	public $worker;
	/**
	 * 登录
	 * 
	 * {@inheritDoc}
	 *
	 * @see \Services\Access\AccessIf::login()
	 */
	public function login($username, $password) {
		$db_r = Db::instance ( 'db1' )->select ( '*' )->from ( 'account' );
		$result = RedisForDb::getHashFromRedisAndDb ( 'redis1', 'account', 'user_name', array (
				$username 
		), $db_r );
		$login_result = new Login_Result ();
		if (empty ( $result [$username] )) {
			$login_result->code = AccessMacro::CMD_ERROR;
			$login_result->msg = '不存在此用户';
		} else {
			if ($result [$username] ['user_passwords'] == $password) {
				$login_result->code = AccessMacro::CMD_OK;
				$login_result->msg = '登录成功';
				$login_result->userInfo = new UserInfo ( $result [$username] );
				Gateway::bindUid ( Context::$client_id, $result [$username] ['user_id'] );
				$_SESSION ['user_id'] = $result [$username] ['user_id'];
			} else {
				$login_result->code = AccessMacro::CMD_ERROR;
				$login_result->msg = '用户密码不正确';
			}
		}
		return $login_result;
	}
	public function send_login($message) {
		Gateway::sendToClient ( Context::$client_id, $message );
	}
	/**
	 * 注册
	 * 
	 * {@inheritDoc}
	 *
	 * @see \Services\Access\AccessIf::regist()
	 */
	public function regist($username, $password) {
		$db_r = Db::instance ( 'db1' )->select ( '*' )->from ( 'account' );
		$result = RedisForDb::getHashFromRedisAndDb ( 'redis1', 'account', 'user_name', array (
				$username 
		), $db_r );
		$regist_result = new Regist_Result ();
		if (empty ( $result [$username] )) {
			$userInfoArray = array (
					'user_name' => $username,
					'user_passwords' => $password 
			);
			Db::instance ( 'db1' )->insert ( 'account' )->cols ( $userInfoArray )->query ();
			$userInfoArray ['user_id'] = Db::instance ( 'db1' )->lastInsertId ();
			$regist_result->code = AccessMacro::CMD_OK;
			$regist_result->msg = '注册成功';
			$regist_result->userInfo = new UserInfo ( $userInfoArray );
			RedisForDb::putToRedisHash ( 'redis1', 'account', array (
					$username => $userInfoArray 
			), 'user_name' );
			Gateway::bindUid ( Context::$client_id, $userInfoArray ['user_id'] );
			$_SESSION ['user_id'] = $userInfoArray ['user_id'];
		} else {
			$regist_result->code = AccessMacro::CMD_ERROR;
			$regist_result->msg = '用户名已存在';
		}
		return $regist_result;
	}
	public function send_regist($message) {
		Gateway::sendToClient ( Context::$client_id, $message );
	}
}

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
namespace Frames\Game;

use GatewayWorker\Lib\ChannelEventDispatcher;
use GatewayWorker\Lib\Event;
use GatewayWorker\Lib\FrameChild;

/**
 * 帧处理类
 */
class FightThink extends FrameChild {
	public static $EVENT_WANT_FIGHT = 'event_want_fight';
	public static $EVENT_ATTACK = 'event_attack';
	public static $EVENT_DEAD = 'event_dead';
	/**
	 * 帧循环
	 *
	 * {@inheritDoc}
	 *
	 * @see \GatewayWorker\Lib\FrameChild::onEnterFrame()
	 */
	public function onEnterFrame() {
		if (count ( $this->fighter ) > 0) {
			$rand = array_rand ( $this->fighter );
			$this->attack ( $this->fighter [$rand] );
		} elseif ($this->currentFrame > $this->time) {
			$this->destory ();
		}
	}
	private function attack($uid) {
		if ($this->currentFrame % 30 == 0) {
			$attack = rand ( 10, 20 );
			ChannelEventDispatcher::getChannelEventDispatcher ()->dispatchEventWith ( FightThink::$EVENT_ATTACK . '@' . $uid, array (
					'user_id' => $this->user_id,
					'attack' => $attack 
			) );
			$this->parent->pushToMessage ( '你对' . $uid . '造成' . $attack . '点伤害' );
		}
	}
	private function putToFighter($uid) {
		if (! in_array ( $uid, $this->saveData ['fighter'] )) {
			array_push ( $this->saveData ['fighter'], $uid );
		}
	}
	private function removeFromFighter($uid) {
		$index = array_search ( $uid, $this->saveData ['fighter'] );
		if ($index !== null) {
			unset ( $this->saveData ['fighter'] [$index] );
			$this->parent->pushToMessage ( '将' . $uid . '移除战斗列表' );
		}
	}
	public function onResume() {
		ChannelEventDispatcher::getChannelEventDispatcher ()->dispatchEventWith ( FightThink::$EVENT_WANT_FIGHT, array (
				'user_id' => $this->user_id 
		) );
		ChannelEventDispatcher::getChannelEventDispatcher ()->addEventListener ( FightThink::$EVENT_ATTACK . '@' . $this->user_id, array (
				$this,
				'onChannelListener' 
		) );
		ChannelEventDispatcher::getChannelEventDispatcher ()->addEventListener ( FightThink::$EVENT_WANT_FIGHT, array (
				$this,
				'onChannelListener' 
		) );
		ChannelEventDispatcher::getChannelEventDispatcher ()->addEventListener ( FightThink::$EVENT_DEAD, array (
				$this,
				'onChannelListener' 
		) );
	}
	/**
	 * 加入循环时
	 */
	public function onAdded() {
		$this->fighter = array ();
		$this->time = rand ( 300, 600 );
		$this->hp = 200;
		$this->parent->pushToMessage ( '该找人切磋切磋武艺了' );
	}
	public function onChannelListener(Event $event) {
		switch ($event->type) {
			case FightThink::$EVENT_WANT_FIGHT :
				if ($event->data ['user_id'] != $this->user_id) {
					$this->parent->pushToMessage ( '收到来自' . $event->data ['user_id'] . '的切磋请求' );
					$this->putToFighter ( $event->data ['user_id'] );
				}
				break;
			case FightThink::$EVENT_ATTACK . '@' . $this->user_id :
				$this->putToFighter ( $event->data ['user_id'] );
				$this->parent->pushToMessage ( $event->data ['user_id'] . '攻击我造成' . $event->data ['attack'] . '点伤害' );
				$this->hp -= $event->data ['attack'];
				if ($this->hp <= 0) {
					ChannelEventDispatcher::getChannelEventDispatcher ()->dispatchEventWith ( FightThink::$EVENT_DEAD, array (
							'user_id' => $this->user_id 
					) );
				}
				break;
			case FightThink::$EVENT_DEAD :
				$this->removeFromFighter ( $event->data ['user_id'] );
				if ($event->data ['user_id'] != $this->user_id) {
					$this->parent->pushToMessage ( $event->data ['user_id'] . '已死亡' );
				} else {
					$this->parent->pushToMessage ( '你已死亡' );
				}
				$this->destory ();
				break;
		}
	}
	/**
	 * 移除循环时
	 */
	public function onRemoved() {
	}
	/**
	 * 销毁时
	 */
	public function onDestory() {
		ChannelEventDispatcher::getChannelEventDispatcher ()->removeEventListener ( FightThink::$EVENT_WANT_FIGHT, array (
				$this,
				'onChannelListener' 
		) );
		ChannelEventDispatcher::getChannelEventDispatcher ()->removeEventListener ( FightThink::$EVENT_ATTACK . '@' . $this->user_id, array (
				$this,
				'onChannelListener' 
		) );
		ChannelEventDispatcher::getChannelEventDispatcher ()->removeEventListener ( FightThink::$EVENT_DEAD, array (
				$this,
				'onChannelListener' 
		) );
		$this->parent->unLock ();
	}
}

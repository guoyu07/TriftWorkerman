<?php

namespace Frames\Game;

use GatewayWorker\Lib\ChannelEventDispatcher;
use GatewayWorker\Lib\Event;
use GatewayWorker\Lib\FrameChild;
use GatewayWorker\Lib\Gateway;
use GatewayWorker\ThriftBusinessWorker;
use Services\Game\Fight_Result;

/**
 * 帧处理类
 */
class PeopleFrame extends FrameChild {
	public static function getPeopleFrameFromPool(): PeopleFrame {
		return FrameChild::getFromPool ( __CLASS__ );
	}
	public $fight_result;
	public function onResume() {
		$this->fight_result = new Fight_Result ();
		$this->thriftBusinessWorker->eventDisparcher->addEventListener ( ThriftBusinessWorker::$EVENT_CLIENT_CONNECT_CLOSED, array (
				$this,
				'onClientClosed' 
		) );
		$this->messageList = [ ];
		$this->hp = 1000;
		$this->lock = false;
	}
	public function onAdded() {
		echo '[peopleAddedID:]' . $this->user_id . "\n";
		$this->pushToMessage ( '初入江湖，欢迎来到文字武侠' );
		$this->fight_result->fight = $this->user_id . '进入了江湖，所在服务器id为：' . $this->thriftBusinessWorker->id;
		$message = $this->thriftBusinessWorker->createThriftMessage ( $this->fight_result, 'fight' );
		Gateway::sendToAll ( $message );
	}
	/**
	 * 帧循环
	 *
	 * {@inheritDoc}
	 *
	 * @see \GatewayWorker\Lib\FrameChild::onEnterFrame()
	 */
	public function onEnterFrame() {
		if ($this->currentFrame % 20 == 0) {
			$this->sendMessage ();
		}
		$this->think ();
	}
	private function think() {
		if ($this->lock) {
			return;
		}
		$random = rand ( 0, 100 );
		if ($random > 20) {
			$this->addChild ( FrameChild::getFromPool ( 'Frames\Game\FightThink' ), 'fight' );
		} else {
			$this->addChild ( FrameChild::getFromPool ( 'Frames\Game\IdleThink' ), 'idle' );
		}
		$this->lock ();
	}
	public function unLock() {
		$this->lock = false;
	}
	public function lock() {
		$this->lock = true;
	}
	private function sendMessage() {
		$message = $this->shiftAllFromMessage ();
		if (! empty ( $message )) {
			$this->fight_result->fight = implode ( "\n", $message );
			$message = $this->thriftBusinessWorker->createThriftMessage ( $this->fight_result, 'fight' );
			Gateway::sendToUid ( $this->user_id, $message );
		}
	}
	/**
	 * 获取战斗的对象
	 *
	 * @param string $targetKey        	
	 */
	public function setFightTarget($targetKey) {
		$this->targetKey = $targetKey;
	}
	
	/**
	 * 客户端关闭连接监听
	 *
	 * @param Event $event        	
	 */
	public function onClientClosed($event) {
		if ($event->data == $this->user_id) {
			$this->destory ();
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
		echo '[peopleRemovedID:]' . $this->user_id . "\n";
		ChannelEventDispatcher::getChannelEventDispatcher ()->dispatchEventWith ( FightThink::$EVENT_DEAD );
		$this->fight_result->fight = $this->user_id . '离开了江湖';
		$message = $this->thriftBusinessWorker->createThriftMessage ( $this->fight_result, 'fight' );
		Gateway::sendToAll ( $message );
		$this->thriftBusinessWorker->eventDisparcher->removeEventListener ( ThriftBusinessWorker::$EVENT_CLIENT_CONNECT_CLOSED, array (
				$this,
				'onClientClosed' 
		) );
	}
}

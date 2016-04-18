<?php

namespace Frames\Game;

use GatewayWorker\Lib\FrameChild;
use GatewayWorker\Lib\Utils;
use Logger\Client;

/**
 * 帧处理类
 */
class GroupFrame extends FrameChild {
	/**
	 * 帧循环
	 *
	 * {@inheritDoc}
	 *
	 * @see \GatewayWorker\Lib\FrameChild::onEnterFrame()
	 */
	public function onEnterFrame() {
	}
	public function onResume() {
	}
	/**
	 * 加入循环时
	 */
	public function onAdded() {
		$this->group_id = Utils::uuid ();
		Client::log(Client::DEBUG, 'This is a text');
	}
	
	/**
	 * 移除循环时
	 */
	public function onRemoved() {
	}
}

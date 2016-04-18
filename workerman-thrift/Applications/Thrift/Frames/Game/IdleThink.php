<?php

namespace Frames\Game;

use GatewayWorker\Lib\FrameChild;

/**
 * 帧处理类
 */
class IdleThink extends FrameChild {
	/**
	 * 帧循环
	 *
	 * {@inheritDoc}
	 *
	 * @see \GatewayWorker\Lib\FrameChild::onEnterFrame()
	 */
	public function onEnterFrame() {
		if ($this->currentFrame > $this->time) {
			$this->destory ();
		}
	}
	public function onResume() {
	}
	/**
	 * 加入循环时
	 */
	public function onAdded() {
		$this->parent->lock ();
		$this->time = rand ( 100, 200 );
		$this->parent->pushToMessage ( '闲的蛋疼，坐下来喝杯茶吧。' );
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
		$this->parent->unLock ();
	}
}

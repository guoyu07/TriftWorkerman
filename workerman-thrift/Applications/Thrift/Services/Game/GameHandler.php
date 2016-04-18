<?php

namespace Services\Game;

use Frames\Game\PeopleFrame;
use GatewayWorker\Lib\Context;
use GatewayWorker\ThriftBusinessWorker;
use Services\Game\GameIf;

class GameHandler implements GameIf {
	/**
	 *
	 * @var \GatewayWorker\ThriftBusinessWorker
	 */
	public $worker;
	
	/**
	 *
	 * @return \Services\Game\Fight_Result
	 */
	public function fight() {
		$peopleFrame = PeopleFrame::getPeopleFrameFromPool ();
		$peopleFrame->user_id = $_SESSION ['user_id'];
		$peopleFrame->client_id = Context::$client_id;
		$this->worker->frameRoot->getChild ( 'group' )->addChild ( $peopleFrame, 'user_id:' . $_SESSION ['user_id'] );
		return null;
	}
	public function send_fight() {
	}
}

?>
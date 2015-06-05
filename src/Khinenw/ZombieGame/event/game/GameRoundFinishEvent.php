<?php

namespace Khinenw\ZombieGame\event\game;

use Khinenw\ZombieGame\GameGenius;
use Khinenw\ZombieGame\event\GeniusGameEvent;

class GameRoundFinishEvent extends GeniusGameEvent{

	private $gameId;
	private $zombieCount;
	private $humanCount;

	public function __construct(GameGenius $plugin, $gameId, $zombieCount, $humanCount){
		parent::__construct($plugin);
		$this->gameId = $gameId;
		$this->zombieCount = $zombieCount;
		$this->humanCount = $humanCount;
	}

	public function getGameId(){
		return $this->gameId;
	}

	public function getZombieCount(){
		return $this->zombieCount;
	}

	public function getHumanCount(){
		return $this->humanCount;
	}
}

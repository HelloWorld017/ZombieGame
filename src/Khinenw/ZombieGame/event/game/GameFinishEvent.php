<?php

namespace Khinenw\ZombieGame\event\game;

use Khinenw\ZombieGame\event\GeniusGameEvent;
use Khinenw\ZombieGame\GameGenius;

class GameFinishEvent extends GeniusGameEvent{

	private $gameId;
	private $winner;
	private $winTeam;

	public function __construct(GameGenius $plugin, $gameId, array $winner, $winTeam){
		parent::__construct($plugin);
		$this->gameId = $gameId;
		$this->winner = $winner;
		$this->winTeam = $winTeam;
	}

	public function getGameId(){
		return $this->gameId;
	}

	public function getWinner(){
		return $this->winner;
	}

	public function getWinTeam(){
		return $this->winTeam;
	}
}

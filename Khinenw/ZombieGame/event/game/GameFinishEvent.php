<?php

namespace Khinenw\ZombieGame\event\game;

use Khinenw\ZombieGame\GameGenius;
use pocketmine\event\plugin\PluginEvent;

class GameFinishEvent extends PluginEvent{

	private $gameId;
	private $winner;

	public function __construct(GameGenius $plugin, $gameId, array $winner){
		parent::__construct($plugin);
		$this->gameId = $gameId;
		$this->winner = $winner;
	}

	public function getGameId(){
		return $this->gameId;
	}

	public function getWinner(){
		return $this->winner;
	}
}

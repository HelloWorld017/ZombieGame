<?php

namespace Khinenw\ZombieGame\event\game;

use Khinenw\ZombieGame\GameGenius;
use pocketmine\event\plugin\PluginEvent;

class GameFinishEvent extends PluginEvent{

	private $gameId;
	private $winner;
	private $winTeam;
	private $score;
	public static $handlerList;

	public function __construct(GameGenius $plugin, $gameId, array $winner, $winTeam, $score){
		parent::__construct($plugin);
		$this->gameId = $gameId;
		$this->winner = $winner;
		$this->winTeam = $winTeam;
		$this->score = $score;
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

	public function getScore(){
		return $this->score;
	}
}

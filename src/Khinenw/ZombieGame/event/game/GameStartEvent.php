<?php

namespace Khinenw\ZombieGame\event\game;

use Khinenw\ZombieGame\GameGenius;
use pocketmine\event\plugin\PluginEvent;

class GameStartEvent extends PluginEvent{

	private $gameId;
	public static $handlerList;

	public function __construct(GameGenius $plugin, $gameId){
		parent::__construct($plugin);
		$this->gameId = $gameId;
	}

	public function getGameId(){
		return $this->gameId;
	}
}

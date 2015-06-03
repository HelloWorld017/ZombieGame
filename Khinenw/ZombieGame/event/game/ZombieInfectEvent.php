<?php

namespace Khinenw\ZombieGame\event\game;

use Khinenw\ZombieGame\GameGenius;
use pocketmine\event\plugin\PluginEvent;

class ZombieInfectEvent extends PluginEvent{

	private $isInitialZombie = false;
	private $isNoTouchInfection = false;
	private $playerName = "";

	public function __construct(GameGenius $plugin, $isInitialZombie, $isNoTouchInfection, $playerName){
		parent::__construct($plugin);
		$this->isInitialZombie = $isInitialZombie;
		$this->isNoTouchInfection = $isNoTouchInfection;
		$this->playerName = $playerName;
	}

	public function isInitialZombie(){
		return $this->isInitialZombie;
	}

	public function getPlayerName(){
		return $this->playerName;
	}

	public function isNoTouchInfection(){
		return $this->isNoTouchInfection;
	}

}

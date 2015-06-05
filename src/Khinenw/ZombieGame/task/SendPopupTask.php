<?php

namespace Khinenw\ZombieGame\task;

use Khinenw\ZombieGame\GameGenius;
use pocketmine\scheduler\PluginTask;

class SendPopupTask extends PluginTask{
	public function __construct(GameGenius $plugin){
		parent::__construct($plugin);
	}

	public function onRun($tick){

		foreach($this->getOwner()->players as $playerName => $playerGameId){
			if($playerGameId === "NONE"){
				$this->getOwner()->getServer()->getPlayerExact($playerName)->sendPopup($this->getOwner()->getPopupTextWithPlayerCount($playerName));
			}else{
				$this->getOwner()->getServer()->getPlayerExact($playerName)->sendPopup($this->getOwner()->getPopupTextWithPlayerCount($playerName));
			}
		}
	}
}
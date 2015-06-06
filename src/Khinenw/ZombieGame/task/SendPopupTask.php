<?php

namespace Khinenw\ZombieGame\task;

use Khinenw\ZombieGame\GameGenius;
use pocketmine\scheduler\PluginTask;

class SendPopupTask extends PluginTask{
	public function __construct(GameGenius $plugin){
		parent::__construct($plugin);
	}

	public function onRun($tick){
		$popupText = array();

		foreach($this->getOwner()->players as $playerName => $playerGameId){
			if(isset($popupText[$playerGameId])){
				$this->getOwner()->getServer()->getPlayerExact($playerName)->sendPopup($popupText[$playerGameId]);
			}else{
				$popupText[$playerGameId] = $this->getOwner()->getPopupTextWithGameId($playerGameId);
				$this->getOwner()->getServer()->getPlayerExact($playerName)->sendPopup($popupText[$playerGameId]);
			}
		}
	}
}
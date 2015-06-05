<?php

namespace Khinenw\ZombieGame\task;

use Khinenw\ZombieGame\GameGenius;
use pocketmine\scheduler\PluginTask;

class GameTickTask extends  PluginTask{
	public function __construct(GameGenius $plugin){
		parent::__construct($plugin);
	}

	public function onRun($tick){
		$this->getOwner()->onTick();
	}
}
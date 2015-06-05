<?php

namespace Khinenw\ZombieGame\event;

use Khinenw\ZombieGame\GameGenius;
use pocketmine\event\plugin\PluginEvent;

class GeniusGameEvent extends PluginEvent{

	public static $handlerList;

	public function __construct(GameGenius $plugin){
		parent::__construct($plugin);
	}
}
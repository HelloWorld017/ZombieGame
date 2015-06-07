<?php

/*
* Zombie Game, The Genius S1E4 Main Match into PocketMine-MP
* Copyright (C) 2015  Khinenw <deu07115@gmail.com>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Khinenw\ZombieGame\event\game;

use Khinenw\ZombieGame\GameGenius;
use pocketmine\event\plugin\PluginEvent;

class GameRoundFinishEvent extends PluginEvent{

	private $gameId;
	private $zombieCount;
	private $humanCount;
	public static $handlerList;

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

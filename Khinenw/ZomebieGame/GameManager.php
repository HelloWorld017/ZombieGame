<?php

namespace Khinenw\ZombieGame;


use Khinenw\ZombieGame\event\game\GameFinishEvent;
use Khinenw\ZombieGame\event\game\GameRoundFinishEvent;
use Khinenw\ZombieGame\event\game\GameRoundStartEvent;
use Khinenw\ZombieGame\event\game\GameStartEvent;
use Khinenw\ZombieGame\event\game\ZombieInfectEvent;

class GameManager {
	private $innerTick = 0;
	private $roundTick = 0;
	private $playerData = array();
	private $gameId = "";
	private $gameStatus = GameManager::STATUS_PREPARATION;
	private $roundCount = 1;
	private $plugin;

	const HUMAN = 0;
	const ZOMBIE = 1;

	//Setting
	const INITIAL_ZOMBIE_COUNT = 2;
	const INFECTION_MEDICINE_TERM = 2400;
	const PREPARATION_TERM = 1200;
	const INGAME_REST_TERM = 600;
	const ROUND_TERM = 1800;

	const STATUS_PREPARATION = 0;
	const STATUS_INGAME = 1;
	const STATUS_INGAME_REST = 2;

	const RETURNTYPE_MEDICINE_NOT_ZOMBIE_SUCCEED = 0;
	const RETURNTYPE_MEDICINE_OVER_TIME_SUCCEED = 1;
	const RETURNTYPE_MEDICINE_SUCCEED = 2;

	const RETURNTYPE_TOUCH_IN_PREPARATION_OR_REST = 0;
	const RETURNTYPE_TOUCH_SUCCEED = 1;

	private function newGame(){
		$this->plugin->getServer()->getPluginManager()->callEvent(new GameStartEvent($this->plugin, $this->gameId));

		$initialZombieNames = array_rand($this->playerData, GameManager::INITIAL_ZOMBIE_COUNT);

		foreach($initialZombieNames as $zombie){
			$this->infectZombie($zombie, true);
		}
		$this->innerTick = 0;
		$this->roundTick = 0;
		$this->gameStatus = GameManager::STATUS_PREPARATION;
		$this->roundCount = 1;
	}

	public function startGame(array $playerList, $gameId, GameGenius &$plugin){
		$this->gameId = $gameId;

		foreach($playerList as $player){
			$this->playerData[$player->getName()] = array(
				"type" => GameManager::HUMAN,
				"player" => $player
			);
		}

		$this->plugin = $plugin;
		$this->newGame();
	}

	public function touch($touchingPlayerName, $touchedPlayerName){

	}

	public function disconnectedFromServer($disconnectedPlayerName){
		$is_initial_zombie = false;

		if(isset($this->playerData[$disconnectedPlayerName]["initial_zombie"])){
			$is_initial_zombie = $this->playerData[$disconnectedPlayerName]["initial_zombie"];
		}

		unset($this->playerData[$disconnectedPlayerName]);

		if($is_initial_zombie){
			$zombie = array_rand($this->playerData);
			$this->infectZombie($zombie, true);
		}
	}

	public function useMedicine($usingPlayerName){
		if($this->playerData[$usingPlayerName]["type"] === GameManager::HUMAN){
			return GameManager::RETURNTYPE_MEDICINE_NOT_ZOMBIE_SUCCEED;
		}elseif($this->playerData[$usingPlayerName]["infection_time"] + GameManager::INFECTION_MEDICINE_TERM < $this->innerTick){
			return GameManager::RETURNTYPE_MEDICINE_OVER_TIME_SUCCEED;
		}else{
			$this->playerData[$usingPlayerName]["type"] = GameManager::HUMAN;
			unset($this->playerData[$usingPlayerName]["infection_time"]);
			unset($this->playerData[$usingPlayerName]["initial_zombie"]);
			return GameManager::RETURNTYPE_MEDICINE_SUCCEED;
		}

	}

	public function tick(){
		$this->innerTick++;
		$this->roundTick++;
		switch($this->gameStatus){
			case GameManager::STATUS_PREPARATION:
				if($this->roundTick >= GameManager::PREPARATION_TERM){
					$this->gameStatus = GameManager::STATUS_INGAME;
					$this->plugin->getServer()->getPluginManager()->callEvent(new GameRoundStartEvent($this->plugin, $this->gameId));
					$this->roundTick = 0;
				}
				break;
			case GameManager::STATUS_INGAME_REST:
				if($this->roundTick >= GameManager::INGAME_REST_TERM){
					$this->gameStatus = GameManager::STATUS_INGAME;
					$this->plugin->getServer()->getPluginManager()->callEvent(new GameRoundStartEvent($this->plugin, $this->gameId));
					$this->roundTick = 0;
				}
				break;
			case GameManager::STATUS_INGAME:
				$this->plugin->getServer()->getPluginManager()->callEvent(new GameRoundFinishEvent($this->plugin, $this->gameId));
				if($this->roundTick >= GameManager::ROUND_TERM){
					$this->roundCount++;
					if($this->roundCount > 5){
						$this->finishGame();
					}
					$this->gameStatus = GameManager::STATUS_INGAME_REST;
					$this->roundTick = 0;
				}
				break;
		}
	}

	private function finishGame(){
		$this->plugin->getServer()->getPluginManager()->callEvent(new GameFinishEvent($this->plugin, $this->gameId));
	}

	private function infectZombie($playerName, $isInitialZombie){
		$this->playerData[$playerName]["type"] = GameManager::ZOMBIE;
		$this->playerData[$playerName]["infection_time"] = $this->innerTick;
		$this->playerData[$playerName]["initial_zombie"] = $isInitialZombie;
		$this->plugin->getServer()->getPluginManager()->callEvent(new ZombieInfectEvent($this->plugin, $isInitialZombie, $playerName));
	}
}
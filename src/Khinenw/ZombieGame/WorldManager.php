<?php

namespace Khinenw\ZombieGame;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class WorldManager {

	public $NEED_PLAYERS;
	public $worldName;
	public $notInGamePlayerCount = 0;
	public $plugin;

	public $INITIAL_ZOMBIE_COUNT = 2;
	public $ROUND_COUNT = 3;
	public $INFECTION_MEDICINE_TERM = 2400;
	public $PREPARATION_TERM = 1200;
	public $INGAME_REST_TERM = 600;
	public $ROUND_TERM = 1800;

	public function __construct($worldName, GameGenius $plugin){
		$this->worldName = $worldName;
		$configs = (new Config($plugin->getDataFolder()."config_$worldName.yml", Config::YAML))->getAll();
		$thisClass = new \ReflectionClass(self::class);
		foreach($configs as $config => $value){
			if($thisClass->hasProperty($config)){
				(new \ReflectionProperty($thisClass, $config))->setValue($this, $value);
			}
		}
		$this->plugin = $plugin;
	}

	public function onPlayerJoin(PlayerJoinEvent $event){

	}

	public function playerChange(){

		$this->notInGamePlayerCount = 0;
		$onlineNotInGamePlayers = array();

		foreach($this->plugin->players as $playerName => $playerGameId){
			if($playerGameId === "NONE"){
				array_push($onlineNotInGamePlayers, $playerName);
				$this->notInGamePlayerCount++;
			}
		}

		if(count($onlineNotInGamePlayers) >= $this->NEED_PLAYERS){
			$gameid = $this->plugin->getGameId();
			$gamers = array();

			for($i = 0; $i < $this->NEED_PLAYERS; $i++){
				$player = $this->plugin->getServer()->getPlayerExact($onlineNotInGamePlayers[$i]);
				$this->plugin->players[$onlineNotInGamePlayers[$i]] = $gameid;
				array_push($gamers, $player);
			}

			$newgame = new GameManager();

			$this->plugin->games[$gameid] = $newgame;
			$this->plugin->gameWorlds[$gameid] = $this->worldName;

			foreach($gamers as $gamePlayer){
				$gamePlayer->sendMessage(TextFormat::AQUA.$this->plugin->getTranslation("WELCOME_GAME_MESSAGE"));
			}

			$newgame->startGame($gamers, $gameid, $this->plugin, $this);
			$this->notInGamePlayerCount -= $this->NEED_PLAYERS;
		}

	}
	public function getPopupTextWithGameId($gameId){
		$popupText = "";

		if ($gameId === "NONE") {
			$popupText = TextFormat::BOLD . TextFormat::GREEN . $this->plugin->getTranslation("POPUP_WAITING_PLAYERS", $this->notInGamePlayerCount, $this->NEED_PLAYERS);
		} else {
			$popupText .= TextFormat::GREEN;
			$playerGame = $this->plugin->games[$gameId];
			switch ($this->plugin->games[$gameId]->getGameStatus()) {
				case GameManager::STATUS_INGAME:
					$popupText .= $this->plugin->getTranslation("POPUP_STATUS_ROUND", $playerGame->getRoundCount()) . "\n";
					$popupText .= $this->plugin->getTranslation("POPUP_STATUS_LEFT", floor(($this->ROUND_TERM - $playerGame->roundTick) / 1200), floor((($this->ROUND_TERM - $playerGame->roundTick) % 1200) / 20));
					break;
				case GameManager::STATUS_INGAME_REST:
					$popupText .= $this->plugin->getTranslation("POPUP_STATUS_ROUND_REST", $playerGame->getRoundCount()) . "\n";
					$popupText .= $this->plugin->getTranslation("POPUP_STATUS_LEFT", floor(($this->INGAME_REST_TERM - $playerGame->roundTick) / 1200), floor((($this->INGAME_REST_TERM - $playerGame->roundTick) % 1200) / 20));
					break;
				case GameManager::STATUS_PREPARATION:
					$popupText .= $this->plugin->getTranslation("POPUP_STATUS_PREPARATION", $playerGame->getRoundCount()) . "\n";
					$popupText .= $this->plugin->getTranslation("POPUP_STATUS_LEFT", floor(($this->PREPARATION_TERM - $playerGame->roundTick) / 1200), floor((($this->PREPARATION_TERM - $playerGame->roundTick) % 1200) / 20));
					break;
			}
		}

		return $popupText;
	}

}

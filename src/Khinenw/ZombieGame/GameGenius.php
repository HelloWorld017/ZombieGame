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

namespace Khinenw\ZombieGame;

use Khinenw\ZombieGame\event\game\GameFinishEvent;
use Khinenw\ZombieGame\event\game\GameRoundFinishEvent;
use Khinenw\ZombieGame\event\game\GameRoundStartEvent;
use Khinenw\ZombieGame\event\game\GameStartEvent;
use Khinenw\ZombieGame\event\game\ZombieInfectEvent;
use Khinenw\ZombieGame\task\GameTickTask;
use Khinenw\ZombieGame\task\SendPopupTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Effect;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\level\particle\HeartParticle;
use pocketmine\level\sound\DoorSound;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class GameGenius extends PluginBase implements Listener{

	public $translation;
	public $config;
	public $games = array();
	public $players = array();
	public $notInGamePlayerCount = 0;

	public static $NEED_PLAYERS = 10;
	public static $GIVE_JUMP_SPELL = true;

	public function onEnable(){
		$this->getLogger()->info(TextFormat::AQUA."Loading Zombie Game...");
		
		if(!file_exists($this->getDataFolder())){
			$this->getLogger()->info(TextFormat::YELLOW."Created Data Folder!");
			mkdir($this->getDataFolder());
		}

		if(!file_exists($this->getDataFolder()."config.yml")){
			(new Config($this->getDataFolder()."config.yml", Config::YAML, yaml_parse(stream_get_contents($this->getResource("config.yml")))))->save();
			$this->getLogger()->info(TextFormat::YELLOW."Extracted config.yml!");
		}

		if(!file_exists($this->getDataFolder()."translation_en.yml")){
			(new Config($this->getDataFolder()."translation_en.yml", Config::YAML, yaml_parse(stream_get_contents($this->getResource("translation_en.yml")))))->save();
			$this->getLogger()->info(TextFormat::YELLOW."Extracted translation_en.yml!");
		}

		if(!file_exists($this->getDataFolder()."translation_ko.yml")){
			(new Config($this->getDataFolder()."translation_ko.yml", Config::YAML, yaml_parse(stream_get_contents($this->getResource("translation_ko.yml")))))->save();
			$this->getLogger()->info(TextFormat::YELLOW."Extracted translation_ko.yml!");
		}
		
		$this->config = (new Config($this->getDataFolder()."config.yml", Config::YAML))->getAll();
		if(!isset($this->config["language"])){
			$this->getLogger()->error(TextFormat::RED."Language Not Found!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}

		if(!file_exists($this->getDataFolder()."translation_".$this->config["language"].".yml")){
			$this->getLogger()->error(TextFormat::RED."Language Not Found!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}

		$this->getLogger()->info(TextFormat::AQUA."Loading Translation Pack : ".$this->config["language"]);
		$this->translation = (new Config($this->getDataFolder()."translation_".$this->config["language"].".yml", Config::YAML))->getAll();
		$this->getLogger()->info(TextFormat::AQUA."Done Loading Translation");

		$managerClass = new \ReflectionClass(GameManager::class);
		$geniusClass = new \ReflectionClass(GameGenius::class);

		$this->getLogger()->info(TextFormat::AQUA."Loading Configuration...");
		foreach($this->config as $key => $value){

			if($managerClass->hasProperty($key)){
				$managerClass->setStaticPropertyValue($key, $value);
				$this->getLogger()->info("Config ".$key." found in GameManager class!");
			}

			if($geniusClass->hasProperty($key)){
				$geniusClass->setStaticPropertyValue($key, $value);
				$this->getLogger()->info("Config ".$key." found in GameGenius class!");
			}
		}
		$this->getLogger()->info(TextFormat::AQUA."Done Loading Configuration.");

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(TextFormat::AQUA."Zombie Game has been loaded!");
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new GameTickTask($this), 1);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new SendPopupTask($this), 1);
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $params){

		if(!($sender instanceof Player)){
			$sender->sendMessage(TextFormat::RED.$this->getTranslation("MUST_INGAME"));
			return true;
		}

		//$sender = (Player) $sender;

		switch($command->getName()){
			case "setspawnpos":
				$this->config["spawnpos"] = array(
				"x" => $sender->getX(),
				"y" => $sender->getY(),
				"z" => $sender->getZ());

				$sender->sendMessage(TextFormat::AQUA.$this->getTranslation("SPAWNPOS_SET"));

				$config = new Config($this->getDataFolder()."config.yml", Config::YAML);
				$config->set("spawnpos", $this->config["spawnpos"]);
				$config->save();

				break;
			case "explanation":
				if(count($params) <= 0){
					$sender->sendMessage(TextFormat::GREEN."Explanation (1/3)");
					$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_1", GameManager::$INITIAL_ZOMBIE_COUNT));
					$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_2"));
					$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_3", GameManager::$ROUND_COUNT));
					$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_4"));
					$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_5"));
					return true;
				}
				switch($params[0]){
					case "1":
						$sender->sendMessage(TextFormat::GREEN."Explanation (1/3)");
						$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_1", GameManager::$INITIAL_ZOMBIE_COUNT));
						$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_2"));
						$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_3", GameManager::$ROUND_COUNT));
						$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_4"));
						$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_5"));
						break;
					case "2":
						$sender->sendMessage(TextFormat::GREEN."Explanation (2/3)");
						$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_P2_1"));
						$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_P2_2"));
						$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_P2_3"));
						break;
					case "3":
						$sender->sendMessage(TextFormat::GREEN."Explanation (3/3)");
						$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_P3_1"));
						$sender->sendMessage(TextFormat::GREEN.$this->getTranslation("GAME_EXPLANATION_P3_2", floor(GameManager::$INFECTION_MEDICINE_TERM/20)));
						break;
					default:
						$sender->sendMessage(TextFormat::RED."/explanation [Page Number]");
						break;
				}
				break;
			default:
				$sender->sendMessage(TextFormat::RED.$this->getTranslation("UNKNOWN_COMMAND"));
				break;
		}

		return true;
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		if(!isset($this->players[$event->getPlayer()->getName()])){
			$this->players[$event->getPlayer()->getName()] = "NONE";
			$event->getPlayer()->sendMessage(TextFormat::AQUA.$this->getTranslation("WELCOME_MESSAGE", $event->getPlayer()->getName()));

			$event->getPlayer()->sendMessage(TextFormat::AQUA.$this->getTranslation("PLAYER_COUNT", count($this->getServer()->getOnlinePlayers())));
			$event->getPlayer()->sendMessage(TextFormat::AQUA.$this->getTranslation("WAIT_MESSAGE", GameGenius::$NEED_PLAYERS));
		}

		$this->playerChange();
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		if(!isset($this->players[$event->getPlayer()->getName()])){
			return;
		}

		$playerGameId = $this->players[$event->getPlayer()->getName()];

		if($playerGameId !== "NONE"){
			$this->stripItemAndEffect($event->getPlayer());

			$playerData = $this->games[$playerGameId]->playerData[$event->getPlayer()->getName()];

			$this->notifyForPlayers($playerGameId, TextFormat::DARK_RED.$this->getTranslation("PLAYER_LEAVE", $event->getPlayer()->getName()));

			if(isset($playerData["initial_zombie"]) && $playerData["initial_zombie"]){
				$this->notifyForPlayers($playerGameId, TextFormat::AQUA.$this->getTranslation("WAS_INITIAL_ZOMBIE", $event->getPlayer()->getName()));
			}

			$this->games[$playerGameId]->disconnectedFromServer($event->getPlayer()->getName());
		}
		$this->playerChange();

		unset($this->players[$event->getPlayer()->getName()]);
	}

	public function onGameFinish(GameFinishEvent $event){

		$winnerText = "";
		foreach($event->getWinner() as $winner){
			$winnerText .= ", ".$winner;
		}
		$winnerText = substr($winnerText, 2);

		if($event->getWinTeam() === GameManager::ZOMBIE){
			$translationText = $this->getTranslation("GAME_FINISHED_ZOMBIE", $winnerText);
		}else{
			$translationText = $this->getTranslation("GAME_FINISHED_HUMAN", $winnerText, $event->getScore());
		}

		foreach($this->games[$event->getGameId()]->playerData as $playerName=>$playerData){
			$this->stripItemAndEffect($playerData["player"]);
			$playerData["player"]->sendMessage(TextFormat::AQUA.$translationText);
			$this->players[$playerName] = "NONE";
		}

		unset($this->games[$event->getGameId()]);
		$this->playerChange();

	}

	public function stripItemAndEffect(Player $player){
		$player->getInventory()->removeItem(new Item(Item::MUSHROOM_STEW, 0, 1));
		$player->getInventory()->removeItem(new Item(Item::BOWL, 0, 1));
		$player->removeEffect(Effect::SPEED);

		if(GameGenius::$GIVE_JUMP_SPELL) {
			$player->removeEffect(Effect::JUMP);
		}
	}

	public function giveItemAndEffect(Player $player){
		$player->setGamemode(0);
		$player->getInventory()->addItem(new Item(Item::MUSHROOM_STEW, 0, 1));
		$player->addEffect(Effect::getEffect(Effect::SPEED)->setAmplifier(3)->setDuration(30000));

		if(GameGenius::$GIVE_JUMP_SPELL) {
			$player->addEffect(Effect::getEffect(Effect::JUMP)->setAmplifier(3)->setDuration(30000));
		}
	}

	public function onGameRoundStarted(GameRoundStartEvent $event){
		$this->notifyForPlayers($event->getGameId(), $this->getTranslation("ROUND_STARTED", $this->games[$event->getGameId()]->getRoundCount()));
	}

	public function onGameRoundFinished(GameRoundFinishEvent $event){
		$this->notifyForPlayers($event->getGameId(), TextFormat::AQUA.$this->getTranslation("ROUND_FINISHED", $this->games[$event->getGameId()]->getRoundCount() - 1)."\n"
			.TextFormat::GREEN.TextFormat::UNDERLINE.$this->getTranslation("ROUND_FINISHED_COUNT", $event->getZombieCount(), $event->getHumanCount()));
	}

	public function onTick(){
		foreach($this->games as $game){
			$game->tick();
		}

		foreach($this->players as $playerName => $playerGameId){
			if($playerGameId !== "NONE"){
				$playerInstance = $this->getServer()->getPlayerExact($playerName);
				if($playerInstance->getHealth() !== 19){
					$playerInstance->setHealth(19);
				}
			}
		}
	}

	public function onZombieInfection(ZombieInfectEvent $event){
		if($event->isNoTouchInfection()){
			$this->notifyForPlayers($this->players[$event->getPlayerName()], TextFormat::BLUE.$this->getTranslation("NO_TOUCH_INFECTION", $event->getPlayerName()));
		}

		if($event->isInitialZombie()){
			$player = $this->getServer()->getPlayerExact($event->getPlayerName());
			if($player !== null) $player->sendMessage(TextFormat::UNDERLINE.TextFormat::LIGHT_PURPLE.$this->getTranslation("ARE_INITIAL_ZOMBIE"));
		}
	}

	public function onGameStart(GameStartEvent $event){
		if(!isset($this->config["spawnpos"])){
			$this->getLogger()->error("Please enter spawnpos first!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}else{
			$vector3 = new Vector3($this->config["spawnpos"]["x"], $this->config["spawnpos"]["y"], $this->config["spawnpos"]["z"]);
			foreach($this->games[$event->getGameId()]->playerData as $playerName => $playerData){
				if($playerData["type"] === GameManager::HUMAN){
					$playerData["player"]->sendMessage(TextFormat::LIGHT_PURPLE.TextFormat::UNDERLINE.$this->getTranslation("ARE_INITIAL_HUMAN"));
				}
				$this->giveItemAndEffect($playerData["player"]);
				$playerData["player"]->teleport($vector3);
			}
		}
	}

	public function onPlayerItemConsume(PlayerItemConsumeEvent $event){
		if($event->getItem()->getId() === Item::MUSHROOM_STEW){
			$playerGameId = $this->players[$event->getPlayer()->getName()];
			if($playerGameId === "NONE") return true;

			$this->games[$playerGameId]->useMedicine($event->getPlayer()->getName());
			$event->getPlayer()->sendMessage(TextFormat::GREEN.$this->getTranslation("MEDICINE_USED"));
		}
		return true;
	}

	public function onPlayerDamageByEntity(EntityDamageEvent $event){

		if(!$event->getEntity() instanceof Player){
			return true;
		}

		$entityGameId = $this->players[$event->getEntity()->getName()];

		if($entityGameId !== "NONE"){
			$event->setCancelled(true);
		}

		if(!($event instanceof EntityDamageByEntityEvent)){
			return true;
		}

		if(!($event->getDamager() instanceof Player)){
			return true;
		}

		$damagerGameId = $this->players[$event->getDamager()->getName()];

		if(($damagerGameId === $entityGameId) && ($damagerGameId !== "NONE")){
			$returnVal = $this->games[$damagerGameId]->touch($event->getDamager()->getName(), $event->getEntity()->getName());
			switch($returnVal){
				case GameManager::RETURNTYPE_TOUCH_ALREADY_TOUCED_FAILED:
					$event->getDamager()->sendMessage(TextFormat::RED.$this->getTranslation("TOUCH_ALREADY_TOUCHED"));
					break;
				case GameManager::RETURNTYPE_TOUCH_IN_PREPARATION_OR_REST_FAILED:
					$event->getDamager()->sendMessage(TextFormat::RED.$this->getTranslation("PREPARATION_OR_REST"));
					break;
				case GameManager::RETURNTYPE_TOUCH_SUCCEED:
					$this->notifyTipForPlayers($damagerGameId, TextFormat::DARK_PURPLE.$event->getDamager()->getName()." TOUCHED ".$event->getEntity()->getName());
					$event->getDamager()->getLevel()->addSound(new DoorSound($event->getDamager()->getLocation()));
					for($i = 0; $i < 30; $i++){
						$event->getDamager()->getLevel()->addParticle(new HeartParticle($event->getDamager()->getLocation()->add(mt_rand(-1, 1), mt_rand(-1, 1), mt_rand(-1, 1))));
					}
					for($i = 0; $i < 30; $i++){
						$event->getDamager()->getLevel()->addParticle(new HeartParticle($event->getEntity()->getLocation()->add(mt_rand(-1, 1), mt_rand(-1, 1), mt_rand(-1, 1))));
					}
					break;
			}
			return false;
		}
		return true;
	}

	public function notifyForPlayers($gameId, $notification){
		if($gameId === "NONE") return;
		foreach($this->games[$gameId]->playerData as $playerName => $playerData){
			$playerData["player"]->sendMessage($notification);
		}
	}

	public function notifyTipForPlayers($gameId, $notification){
		if($gameId === "NONE") return;
		foreach($this->games[$gameId]->playerData as $playerName => $playerData){
			$playerData["player"]->sendTip($notification);
		}
	}

	public function getPopupTextWithPlayerCount($playerName){
		$popupText = "";

		if($this->players[$playerName] === "NONE"){
			$popupText = TextFormat::BOLD.TextFormat::GREEN.$this->getTranslation("POPUP_WAITING_PLAYERS", $this->notInGamePlayerCount, GameGenius::$NEED_PLAYERS);
		}else{
			$popupText .= TextFormat::GREEN;
			$playerGame = $this->games[$this->players[$playerName]];
			switch($playerGame->getGameStatus()){
				case GameManager::STATUS_INGAME:
					$popupText .= $this->getTranslation("POPUP_STATUS_ROUND", $playerGame->getRoundCount());
					break;
				case GameManager::STATUS_INGAME_REST:
					$popupText .= $this->getTranslation("POPUP_STATUS_ROUND_REST", $playerGame->getRoundCount());
					break;
				case GameManager::STATUS_PREPARATION:
					$popupText .= $this->getTranslation("POPUP_STATUS_PREPARATION", $playerGame->getRoundCount());
					break;
			}
			$popupText .= $this->getTranslation("POPUP_STATUS_LEFT", floor($playerGame->roundTick / 1200), floor($playerGame->roundTick / 20));
		}

		return $popupText;
	}

	public function getPopupTextWithGameId($gameId){
		$popupText = "";

		if($gameId === "NONE"){
			$popupText = TextFormat::BOLD.TextFormat::GREEN.$this->getTranslation("POPUP_WAITING_PLAYERS", $this->notInGamePlayerCount, GameGenius::$NEED_PLAYERS);
		}else{
			$popupText .= TextFormat::GREEN;
			$playerGame = $this->games[$gameId];
			switch($this->games[$gameId]->getGameStatus()){
				case GameManager::STATUS_INGAME:
					$popupText .= $this->getTranslation("POPUP_STATUS_ROUND", $playerGame->getRoundCount())."\n";
					$popupText .= $this->getTranslation("POPUP_STATUS_LEFT", floor((GameManager::$ROUND_TERM - $playerGame->roundTick) / 1200), floor(((GameManager::$ROUND_TERM - $playerGame->roundTick) % 1200) / 20));
					break;
				case GameManager::STATUS_INGAME_REST:
					$popupText .= $this->getTranslation("POPUP_STATUS_ROUND_REST", $playerGame->getRoundCount())."\n";
					$popupText .= $this->getTranslation("POPUP_STATUS_LEFT", floor((GameManager::$INGAME_REST_TERM - $playerGame->roundTick) / 1200), floor(((GameManager::$INGAME_REST_TERM - $playerGame->roundTick) % 1200) / 20));
					break;
				case GameManager::STATUS_PREPARATION:
					$popupText .= $this->getTranslation("POPUP_STATUS_PREPARATION", $playerGame->getRoundCount())."\n";
					$popupText .= $this->getTranslation("POPUP_STATUS_LEFT", floor((GameManager::$PREPARATION_TERM - $playerGame->roundTick) / 1200), floor(((GameManager::$PREPARATION_TERM - $playerGame->roundTick) % 1200)/ 20));
					break;
			}
		}

		return $popupText;
	}

	public function playerChange(){

		$this->notInGamePlayerCount = 0;
		$onlineNotInGamePlayers = array();

		foreach($this->players as $playerName => $playerGameId){
			if($playerGameId === "NONE"){
				array_push($onlineNotInGamePlayers, $playerName);
				$this->notInGamePlayerCount++;
			}
		}

		if(count($onlineNotInGamePlayers) >= GameGenius::$NEED_PLAYERS){
			$gameid = $this->getGameId();
			$gamers = array();

			for($i = 0; $i < GameGenius::$NEED_PLAYERS; $i++){
				$player = $this->getServer()->getPlayerExact($onlineNotInGamePlayers[$i]);
				$this->players[$onlineNotInGamePlayers[$i]] = $gameid;
				array_push($gamers, $player);
			}

			$newgame = new GameManager();

			$this->games[$gameid] = $newgame;

			foreach($gamers as $gamePlayer){
				$gamePlayer->sendMessage(TextFormat::AQUA.$this->getTranslation("WELCOME_GAME_MESSAGE"));
			}

			$newgame->startGame($gamers, $gameid, $this);
			$this->notInGamePlayerCount -= GameGenius::$NEED_PLAYERS;
		}

	}

	public function getGameId(){
		for($counter = 0; $counter < count($this->games); $counter++){
			if(isset($this->games[$counter])){
				return $counter;
			}
		}
		return $counter + 1;
	}

	public function getTranslation($translationKey, ...$args){
		if(!isset($this->translation[$translationKey])){
			return "UNDEFINED_TRANSLATION";
		}

		$translationText = $this->translation[$translationKey];

		foreach($args as $key => $value){
			$translationText = str_replace("%s".($key + 1), $value, $translationText);
		}

		return $translationText;
	}
}

<?php

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
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
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

	public function onEnable(){
		$this->getLogger()->info(TextFormat::AQUA."Loading Zombie Game...");
		
		if(!file_exists($this->getDataFolder())){
			$this->getLogger()->error(TextFormat::RED."Data Not Found!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
		
		$this->config = (new Config($this->getDataFolder()."config.yml", Config::YAML))->getAll();
		if(!isset($this->config["language"])){
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
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new GameTickTask($this), 20);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new SendPopupTask($this), 20);
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
				break;

			default:
				$sender->sendMessage(TextFormat::RED.$this->getTranslation("UNKNOWN_COMMAND"));
		}

		return true;
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		if(!isset($this->players[$event->getPlayer()->getName()])){
			$this->players[$event->getPlayer()->getName()] = "NONE";
			$event->getPlayer()->sendMessage(TextFormat::AQUA.$this->getTranslation("WELCOME_MESSAGE", $event->getPlayer()->getName()));

			$event->getPlayer()->sendMessage(TextFormat::AQUA.$this->getTranslation("PLAYER_COUNT", count($this->getServer()->getOnlinePlayers())));
			$event->getPlayer()->sendMessage(TextFormat::AQUA.$this->getTranslation("WAIT_MESSAGE"));
		}

		$this->playerChange();
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		$playerGameId = $this->players[$event->getPlayer()->getName()];

		if($playerGameId !== "NONE"){
			$event->getPlayer()->removeEffect(Effect::SPEED);
			$playerData = $this->games[$playerGameId]->playerData[$event->getPlayer()->getName()];
			$this->games[$playerGameId]->disconnectedFromServer($event->getPlayer()->getName());

			$this->notifyForPlayers($playerGameId, TextFormat::DARK_RED.$this->getTranslation("PLAYER_LEAVE", $event->getPlayer()->getName()));

			if(isset($playerData["initial_zombie"]) && $playerData["initial_zombie"]){
				$this->notifyForPlayers($playerGameId, TextFormat::AQUA.$this->getTranslation("WAS_INITIAL_ZOMBIE", $event->getPlayer()->getName()));
			}
		}
		$this->playerChange();

		unset($this->players[$event->getPlayer()->getName()]);
	}

	public function onGameFinish(GameFinishEvent $event){
		foreach($this->games[$event->getGameId()]->playerData as $playerName=>$playerData){
			$playerData["player"]->removeEffect(Effect::SPEED);
			$this->players[$playerName] = "NONE";
		}

		unset($this->games[$event->getGameId()]);
	}

	public function onGameRoundStarted(GameRoundStartEvent $event){

	}

	public function onGameRoundFinished(GameRoundFinishEvent $event){
		$this->notifyForPlayers($event->getGameId(), TextFormat::AQUA.$this->getTranslation("ROUND_FINISHED", $this->games[$event->getGameId()]->roundCount)."\r\n"
			.$this->getTranslation("ROUND_FINISHED_COUNT", $event->getZombieCount(), $event->getHumanCount()));
	}

	public function onTick(){
		foreach($this->games as $game){
			$game->tick();
		}
	}

	public function onZombieInfection(ZombieInfectEvent $event){
		if($event->isNoTouchInfection()){
			$this->notifyForPlayers(TextFormat::BLUE.$this->players[$event->getPlayerName()], $this->getTranslation("NO_TOUCH_INFECTION", $event->getPlayerName()));
		}

		if($event->isInitialZombie()){
			$this->getServer()->getPlayerExact($event->getPlayerName())->sendMessage(TextFormat::AQUA.$this->getTranslation("ARE_INITIAL_ZOMBIE"));
		}
	}

	public function onGameStart(GameStartEvent $event){
		if(!isset($this->config["spawnpos"])){
			$this->getLogger()->error("Please enter spawnpos first!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}else{
			foreach($event->getGameId()->playerData as $playerName => $playerData){
				$playerData["player"]->setX($this->config["spawnpos"]["x"]);
				$playerData["player"]->setY($this->config["spawnpos"]["y"]);
				$playerData["player"]->setZ($this->config["spawnpos"]["z"]);
				if($playerData["type"] === GameManager::HUMAN){
					$playerData["player"]->sendMessage(TextFormat::AQUA.$this->getTranslation("ARE_INITIAL_HUMAN"));
				}
			}
		}
	}

	public function onPlayerDamageByEntity(EntityDamageEvent $event){
		if(!($event instanceof EntityDamageByEntityEvent)){
			return true;
		}

		if(!(($event->getEntity() instanceof Player) && ($event->getDamager() instanceof Player))){
			return true;
		}

		$damagerGameId = $this->players[$event->getDamager()->getName()];
		$entityGameId = $this->players[$event->getEntity()->getName()];

		if(($damagerGameId === $entityGameId) && ($damagerGameId !== "NONE")){
			$this->games[$damagerGameId]->touch($event->getDamager()->getName(), $event->getEntity()->getName());
			$this->notifyTipForPlayers($damagerGameId, TextFormat::DARK_PURPLE.$damagerGameId." TOUCHED ".$entityGameId);
			return false;
		}
		return true;
	}

	public function notifyForPlayers($gameId, $notification){
		foreach($this->games[$gameId]->playerData as $playerName => $playerData){
			$playerData["player"]->sendMessage($notification);
		}
	}

	public function notifyTipForPlayers($gameId, $notification){
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
			switch($this->games[$this->players[$playerName]]->gameStatus){
				case GameManager::STATUS_INGAME:
					$popupText .= $this->getTranslation("POPUP_STATUS_ROUND", $playerGame->roundCount);
					break;
				case GameManager::STATUS_INGAME_REST:
					$popupText .= $this->getTranslation("POPUP_STATUS_ROUND_REST", $playerGame->roundCount);
					break;
				case GameManager::STATUS_PREPARATION:
					$popupText .= $this->getTranslation("POPUP_STATUS_PREPARATION", $playerGame->roundCount);
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
				$player->addEffect(Effect::getEffectByName("speed")->setAmplifier(4)->setDuration(30000));
				array_push($gamers, $player);
			}

			$newgame = new GameManager();
			$newgame->startGame($gamers, $gameid, $this);

			$this->games[$gameid] = $newgame;

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

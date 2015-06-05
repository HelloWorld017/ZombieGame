<?php

namespace Khinenw\ZombieGame;

use Khinenw\ZombieGame\event\game\GameFinishEvent;
use Khinenw\ZombieGame\event\game\GameRoundStartEvent;
use Khinenw\ZombieGame\event\game\GameStartEvent;
use Khinenw\ZombieGame\event\game\ZombieInfectEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Effect;
use pocketmine\event\entity\EntityDamageByEntityEvent;
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

	public function onEnable(){
		$this->config = (new Config($this->getDataFolder()."config.yml", Config::YAML))->getAll();
		if(isset($this->config["language"])){
			$this->getLogger()->error("Language Not Found!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
		$this->translation = (new Config($this->getDataFolder()."translation_".$this->config["language"].".yml", Config::YAML))->getAll();

		foreach($this->config as $key => $value){
			$class = new \ReflectionClass("Khinenw/ZombieGame/GameManager");

			if($class->hasProperty($key)){
				$class->setStaticPropertyValue($key, $value);
			}
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
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
	}

	public function onGameFinish(GameFinishEvent $event){
		foreach($this->games[$event->getGameId()]->playerData as $playerData){
			$playerData["player"]->removeEffect(Effect::SPEED);
		}
	}

	public function onGameRoundStarted(GameRoundStartEvent $event){

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

	public function onPlayerDamage(EntityDamageByEntityEvent $event){
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

	public function playerChange(){
		$online = $this->getServer()->getOnlinePlayers();
		if(count($online) > 10){
			$gameid = $this->getGameId();
			$gamers = array();

			for($i = 0; $i < 10; $i++){
				$this->players[$online[$i]->getName()] = $gameid;
				$online[$i]->addEffect(Effect::getEffectByName("speed")->setAmplifier(4)->setDuration(30000));
				array_push($gamers, $this->players);
			}

			$newgame = new GameManager();
			$newgame->startGame($gamers, $gameid, $this);

			$this->games[$gameid] = $newgame;
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
		$translationText = $this->translation[$translationKey];
		$pattern = '/%s\D/';
		preg_match($pattern, $translationKey, $matches);
		foreach($matches as $arg){
			$argNum = intval(str_replace("%s", "", $arg));
			$translationText = str_replace($pattern, $args[$argNum], $translationText);
		}

		return $translationText;
	}
}

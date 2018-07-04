<?php
namespace dellosleones\WarpMaster;

use pocketmine\level\Location;
use pocketmine\level\Level;
use pocketmine\{Server, Player};

class Warp extends Location {
	private $cmd;
	public function __construct($x, $y, $z, $yaw, $pitch, Level $level, $name, $isLocked = false){
		parent::__construct($x, $y, $z, $yaw, $pitch, $level);
		$this->name = $name;
		$this->isLocked = $isLocked;
	}
	public function removeShortcut(){
		Server::getInstance()->getCommandMap()->unregister($this->cmd);
	}
	public function makeShortcut(WarpMaster $plugin){
		$map = Server::getInstance()->getCommandMap();
		$cmd = new \pocketmine\command\PluginCommand($this->name, $plugin);
		$cmd->setDescription($this->name . "(으)로 이동합니다.");
		$cmd->setPermission("warpmaster." . $this->name);
		$map->register($this->name, $cmd);
		$this->cmd = $cmd;
	}
	public function lock($isLock = true){
		$this->isLocked = $isLock;
	}
	public function isWarpable(Player $p){
		return $p->isOp() ? true : ! $this->isLocked;
	}
	public function warp(Player $p){
		$p->teleport($this, $this->yaw, $this->pitch);
	}
	public function toArray(){
		$ret = [
			"x"=>$this->x,
			"y"=>$this->y,
			"z"=>$this->z,
			"level"=>$this->level->getName(),
			"yaw"=>$this->yaw,
			"pitch"=>$this->pitch,
			"name"=>$this->name,
			"isLocked"=>$this->isLocked
		];
		return $ret;
	}
	public static function fromArray($data){
		$x = $data["x"];
		$y = $data["y"];
		$z = $data["z"];
		$level = Server::getInstance()->getLevelByName($data["level"]);
		$yaw = $data["yaw"];
		$pitch = $data["pitch"];
		$name = $data["name"];
		$isLocked = $data["isLocked"];
		return new Warp($x, $y, $z, $yaw, $pitch, $level, $name, $isLocked);
	}
}
?>
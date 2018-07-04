<?php
namespace dellosleones\WarpMaster;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\{Config, TextFormat};
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\{ModalFormRequestPacket, ModalFormResponsePacket};
use pocketmine\Player;
use pocketmine\command\{CommandSender, Command};

class WarpMaster extends PluginBase implements Listener {
	private $warps = [ ];
	private $uiqueue = [ ];
	private $conf, $settings;
	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$this->settings = new Config($this->getDataFolder() . "settings.yml", Config::YAML, ["ui-item"=>"345:0"]);
		$this->conf = new Config($this->getDataFolder() . "warps.yml");
		foreach($this->conf->getAll() as $data){
			$warp = Warp::fromArray($data);
			$this->warps[$warp->name] = $warp;
			$warp->makeShortcut($this);
		}
	}
	public function onUiEvent(PlayerInteractEvent $event){
		$p = $event->getPlayer();
		$split = explode(":", $this->settings->get("ui-item"));
		$id = (int) $split[0];
		$dmg = (int) $split[1];
		if($event->getItem()->getId() === $id and $event->getItem()->getDamage() === $dmg){
			$this->sendUi($p);
		}
	}
	public function onResponse(DataPacketReceiveEvent $event){
		if(! ($pk = $event->getPacket()) instanceof ModalFormResponsePacket)
		return;
		if($pk->formId !== 24648989)
		return;
		$data = $pk->formData;
		if($data === null)
		return;
		$data = json_decode($data, true);
		$player = $event->getPlayer();
		if(isset($this->uiqueue[$player->getName()])){
			$warp = $this->uiqueue[$player->getName()][$data];
			$this->warp($player, $warp);
			unset($this->uiqueue[$player->getName()]);
		}
	}
	public function sendUi(Player $player){
		$ui = [
		"title"=>"워프",
		"content"=>"이동할 워프를 선택해주세요.",
		"type"=>"form",
		"buttons"=> [ ]
		];
		$this->uiqueue[$player->getName()] = [ ];
		foreach($this->warps as $warp){
			$color = $warp->isLocked ? TextFormat::RED . "[잠김]" : TextFormat::GREEN;
			$ui["buttons"][] = ["text"=>$color . $warp->name];
			$this->uiqueue[$player->getName()][] = $warp;
		}
		$pk = new ModalFormRequestPacket();
		$pk->formId = 24648989;
		$pk->formData = json_encode($ui);
		$player->dataPacket($pk);
	}
	public function onDisable(){
		foreach($this->warps as $warp){
			$array = $warp->toArray();
			$this->conf->set($warp->name, $array);
		}
		$this->conf->save();
	}
	public function warp(Player $p, Warp $warp){
		if($warp->isWarpable($p)){
			$warp->warp($p);
			$p->sendPopup(TextFormat::AQUA . ">> " . $warp->name . "(으)로 이동하셨습니다." . " <<");
			return true;
		}
		$p->sendMessage(TextFormat::RED . "이동 권한이 없습니다. 워프가 잠겨있는지 확인하신 후 다시 시도해주세요.");
		return false;
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) : bool {
		if($command->getName() === "워프"){
			if(! isset($args[0])){
				$sender->sendMessage(TextFormat::BLUE . "사용법: /워프 <워프명> | <목록> <생성> <삭제> <잠금>");
				return true;
			}
			switch($args[0]){
				case "잠금":
				if(! $sender->isOp()){
					$sender->sendMessage(TextFormat::RED . "워프 잠금 권한이 없습니다.");
					return true;
				}
				if(! isset($args[1]) or ! isset($this->warps[$args[1]])){
					$sender->sendMessage(TextFormat::RED . "존재하지 않는 워프명입니다. '/워프 목록' 명령어로 워프 목록을 확인하신 후 다시 시도해주세요.");
					return true;
				}
				$warp = $this->warps[$args[1]];
				$bool = ! $warp->isLocked;
				$warp->lock($bool);
				$result = $bool ? "잠금" : "잠금 해제";
				$sender->sendMessage(TextFormat::AQUA . "$args[1] 워프가 성공적으로 " . $result . "처리 되었습니다.");
				break;
				
				case "삭제":
				if(! $sender->isOp()){
					$sender->sendMessage(TextFormat::RED . "워프 삭제 권한이 없습니다.");
					return true;
				}
				if(! isset($args[1]) or ! isset($this->warps[$args[1]])){
					$sender->sendMessage(TextFormat::RED . "존재하지 않는 워프명입니다. '/워프 목록' 명령어로 워프 목록을 확인하신 후 다시 시도해주세요");
					return true;
				}
				$this->warps[$args[1]]->removeShortcut();
				unset($this->warps[$args[1]]);
				$sender->sendMessage(TextFormat::AQUA . "성공적으로 $args[1](이)가 제거되었습니다.");
				break;
				
				case "목록":
				$txt = TextFormat::BLUE . "이동 가능한 워프 목록 : ";
				 foreach($this->warps as $warp){
					$txt .= $warp->name . ", ";
				}
				$sender->sendMessage($txt);
				break;
				
				case "생성":
				if($sender instanceof Player){
					if(! $sender->isOp()){
						$sender->sendMessage(TextFormat::RED . "워프생성 권한이 없습니다.");
						return true;
					}
					if(!isset($args[1])){
						$sender->sendMessage(TextFormat::BLUE . "사용법: /워프 생성 <워프명>");
						return true;
					}
					$warp = new Warp($sender->getX(), $sender->getY(), $sender->getZ(), $sender->getYaw(), $sender->getPitch(), $sender->getLevel(), $args[1], false);
					$this->warps[$args[1]] = $warp;
					$warp->makeShortcut($this);
					$sender->sendMessage(TextFormat::AQUA . "$args[1] 워프를 생성했습니다.");
				} else {
					$sender->sendMessage(TextFormat::RED . "콘솔에서 사용하실 수 없습니다.");
					return true;
				}
				break;
				
				default:
					if(isset($this->warps[$args[0]])){
						if($sender instanceof Player)
						$this->warp($sender, $this->warps[$args[0]]);
					}
			}
			return true;
		}
		if(isset($this->warps[$command->getName()]) and $sender instanceof Player){
			$this->warp($sender, $this->warps[$command->getName()]);
			return true;
		}
		return true;
	}
}
?>
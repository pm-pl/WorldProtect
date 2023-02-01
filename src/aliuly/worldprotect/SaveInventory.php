<?php
//= module:gm-save-inv
//: Will save inventory contents when switching gamemodes.
//:
//: This is useful for when you have per world game modes so that
//: players going from a survival world to a creative world and back
//: do not lose their inventory.

namespace aliuly\worldprotect;

use aliuly\worldprotect\common\PluginCallbackTask;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase as Plugin;
use pocketmine\Server;

class SaveInventory extends BaseWp implements Listener {
	const TICKS = 10;
	const DEBUG = false;
	private $saveOnDeath = true;

	public function __construct(Plugin $plugin) {
		parent::__construct($plugin);
		$this->saveOnDeath = $plugin->getConfig()->getNested("features")["death-save-inv"] ?? true;
		Server::getInstance()->getPluginManager()->registerEvents($this, $this->owner);
	}

	public function loadInv(Player $player) {
		$inventoryTag = $player->getSaveData()->getListTag("SurvivalInventory");
		if(!isset($inventoryTag)) {
			if(self::DEBUG) Server::getInstance()->getLogger()->info("[WP Inventory] SurvivalInventory Not Found");
			return;
		}
		if($inventoryTag !== null){
			/** @var CompoundTag $item */
			foreach($inventoryTag as $i => $item){
				$slot = $item->getByte("Slot");
				if ($slot >= 0 and $slot < 9){ //Hotbar
					//Old hotbar saving stuff, ignore it
				}elseif($slot >= 100 and $slot < 104){ //Armor
					$player->getArmorInventory()->setItem($slot - 100, Item::nbtDeserialize($item));
				}else{
					$player->getInventory()->setItem($slot - 9, Item::nbtDeserialize($item));
				}
			}
			$player->save();
		}
	}

	public function saveInv(Player $player) {
		$player->save();
		$inventoryTag = $player->getSaveData()->getListTag("Inventory");
		$player->getSaveData()->setTag("SurvivalInventory", new ListTag($inventoryTag->getAllValues()));
		$player->save();
	}

	public function onGmChange(PlayerGameModeChangeEvent $ev) {
		if ($ev->isCancelled()) return;
		$player = $ev->getPlayer();
		$newgm = $ev->getNewGamemode();
		$oldgm = $player->getGamemode();
		if(self::DEBUG) Server::getInstance()->getLogger()->info("[WP Inventory] Changing GM from " . $oldgm->name() . " to " . $newgm->name() . "...");
		if(($newgm == GameMode::CREATIVE() || $newgm == GameMode::SPECTATOR()) && ($oldgm == GameMode::SURVIVAL() || $oldgm == GameMode::ADVENTURE())) {// We need to save inventory
			$this->saveInv($player);
			if(self::DEBUG) Server::getInstance()->getLogger()->info("[WP Inventory] Saved Inventory from GM  " . $oldgm->name() . " to " . $newgm->name() . ".");
		} elseif(($newgm == GameMode::SURVIVAL() || $newgm == GameMode::ADVENTURE()) && ($oldgm == GameMode::CREATIVE() || $oldgm == GameMode::SPECTATOR())) {
			if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] GM Change - Clear Player Inventory and load SurvivalInventory...");
			$player->getInventory()->clearAll();
			// Need to restore inventory (but later!)
			$this->owner->getScheduler()->scheduleDelayedTask(new PluginCallbackTask($this->owner, [$this, "loadInv"], [$player]), self::TICKS);
		}
	}

	public function PlayerDeath(PlayerDeathEvent $event) {
		if(!$this->saveOnDeath) return;
		$player = $event->getPlayer();
		// Need to restore inventory (but later!).
		$this->owner->getScheduler()->scheduleDelayedTask(new PluginCallbackTask($this->owner, [$this, "loadInv"], [$player]), self::TICKS);
		if(self::DEBUG) Server::getInstance()->getLogger()->info("[WP Inventory] Reloaded SurvivalInventory on death");
	}
}

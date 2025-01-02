<?php

namespace Lyndarx\Death;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use Lyndarx\Death\command\DeathCommand;
use Lyndarx\Death\menu\DeathMenu;
use Lyndarx\Death\utils\InventorySerializer;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class Main extends PluginBase implements Listener {
    private array $deaths = [];
    
    public function onEnable(): void {
        @mkdir($this->getDataFolder() . "players");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        if(!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }
        
        $this->saveDefaultConfig();
        $this->loadDeaths();
        $this->getServer()->getCommandMap()->register("deaths", new DeathCommand($this));
    }
    
    public function onDisable(): void {
        $this->saveDeaths();
    }
    
    private function loadDeaths(): void {
        foreach(glob($this->getDataFolder() . "players/*.json") as $file) {
            $username = basename($file, ".json");
            $this->deaths[$username] = json_decode(file_get_contents($file), true) ?? [];
        }
    }
    
    private function saveDeaths(): void {
        foreach($this->deaths as $username => $data) {
            file_put_contents(
                $this->getDataFolder() . "players/" . $username . ".json",
                json_encode($data)
            );
        }
    }
    
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        if(!isset($this->deaths[$player->getName()])) {
            $this->deaths[$player->getName()] = [];
        }
    }
    
    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $lastDamageCause = $player->getLastDamageCause();
        $killer = "Environment";
        $weapon = "None";
        
        if ($lastDamageCause instanceof EntityDamageByEntityEvent) {
            $damager = $lastDamageCause->getDamager();
            if ($damager instanceof Player) {
                $killer = $damager->getName();
                $weapon = $damager->getInventory()->getItemInHand()->getName();
            }
        }
        
        $deathData = [
            "timestamp" => time(),
            "killer" => $killer,
            "weapon" => $weapon,
            "inventory" => InventorySerializer::serializeFromPlayer($player)
        ];
        
        if (!isset($this->deaths[$player->getName()])) {
            $this->deaths[$player->getName()] = [];
        }
        
        array_unshift($this->deaths[$player->getName()], $deathData);
        $this->saveDeaths();
    }
    
    public function getDeaths(string $player): ?array {
        return $this->deaths[$player] ?? null;
    }
}
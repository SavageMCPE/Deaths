<?php

namespace Lyndarx\Death\menu;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\player\Player;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\world\sound\XpCollectSound;
use Lyndarx\Death\utils\InventorySerializer;
use pocketmine\Server;

class DeathMenu {
    public static function send(Player $player, array $deaths, string $target, int $page = 0): void {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName("Muertes de $target - Página " . ($page + 1));
        
        $inventory = $menu->getInventory();
        $maxPerPage = 18;
        $totalPages = ceil(count($deaths) / $maxPerPage);
        
        $start = $page * $maxPerPage;
        for($i = 0; $i < $maxPerPage && isset($deaths[$start + $i]); $i++) {
            $death = $deaths[$start + $i];
            $head = VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::PLAYER)->asItem();
            
            $dateTime = date("d/m/Y H:i:s", $death["timestamp"]);
            $iso = date("c", $death["timestamp"]);
            
            $head->setCustomName("§r§eMuerte #" . ($start + $i + 1));
            $head->setLore([
                "§7Fecha: §f" . date("d/m/Y", $death["timestamp"]),
                "§7Hora: §f" . date("H:i", $death["timestamp"]),
                "§7ISO: §f" . $iso,
                "§7Asesino: §f" . $death["killer"],
                "§7Arma: §f" . $death["weapon"]
            ]);
            
            $inventory->setItem($i, $head);
        }
        
        if($page > 0) {
            $prev = VanillaBlocks::CARPET()->setColor(DyeColor::GRAY())->asItem();
            $prev->setCustomName("§r§cPágina Anterior");
            $inventory->setItem(18, $prev);
        }
        
        if($page < $totalPages - 1) {
            $next = VanillaBlocks::CARPET()->setColor(DyeColor::GRAY())->asItem();
            $next->setCustomName("§r§aSiguiente Página");
            $inventory->setItem(26, $next);
        }
        
        $menu->setListener(function(InvMenuTransaction $transaction) use($player, $deaths, $target, $page, $totalPages) {
            $clickedItem = $transaction->getItemClicked();
            
            if($clickedItem->getName() === "§r§cPágina Anterior" && $page > 0) {
                $player->removeCurrentWindow();
                self::send($player, $deaths, $target, $page - 1);
                return $transaction->discard();
            } 
            
            if($clickedItem->getName() === "§r§aSiguiente Página" && $page < $totalPages - 1) {
                $player->removeCurrentWindow();
                self::send($player, $deaths, $target, $page + 1);
                return $transaction->discard();
            }
            
            if($clickedItem->getCustomName() !== "") {
                $deathIndex = $page * 18 + $transaction->getAction()->getSlot();
                if(isset($deaths[$deathIndex])) {
                    $player->removeCurrentWindow();
                    self::showItemsMenu($player, $deaths[$deathIndex], $target);
                }
            }
            
            return $transaction->discard();
        });
        
        $menu->send($player);
    }
    
    private static function showItemsMenu(Player $player, array $death, string $target): void {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName("Recuperar Items");
        
        $inventory = $menu->getInventory();
        
        for($i = 0; $i < 5; $i++) {
            for($j = 0; $j < 3; $j++) {
                $green = VanillaBlocks::WOOL()->setColor(DyeColor::GREEN())->asItem();
                $green->setCustomName("§r§aAceptar");
                $inventory->setItem($j * 9 + $i, $green);
            }
        }
        
        $head = VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::PLAYER)->asItem();
        $head->setCustomName("§r§eInformación");
        $head->setLore([
            "§7Fecha: §f" . date("d/m/Y", $death["timestamp"]),
            "§7Hora: §f" . date("H:i", $death["timestamp"]),
            "§7Asesino: §f" . $death["killer"],
            "§7Arma: §f" . $death["weapon"]
        ]);
        $inventory->setItem(13, $head);
        
        for($i = 5; $i < 9; $i++) {
            for($j = 0; $j < 3; $j++) {
                $red = VanillaBlocks::WOOL()->setColor(DyeColor::RED())->asItem();
                $red->setCustomName("§r§cCancelar");
                $inventory->setItem($j * 9 + $i, $red);
            }
        }
        
        $menu->setListener(function(InvMenuTransaction $transaction) use($player, $death, $target) {
            $clickedItem = $transaction->getItemClicked();
            
            if($clickedItem->getName() === "§r§aAceptar") {
                $targetPlayer = Server::getInstance()->getPlayerExact($target);
                if($targetPlayer !== null) {
                    self::returnItems($targetPlayer, $death["inventory"]);
                    $player->sendMessage("§aItems enviados a {$target} correctamente!");
                } else {
                    $player->sendMessage("§cEl jugador {$target} no está en línea!");
                }
                $player->removeCurrentWindow();
                return $transaction->discard();
            }
            
            if($clickedItem->getName() === "§r§cCancelar") {
                $player->removeCurrentWindow();
                return $transaction->discard();
            }
            
            return $transaction->discard();
        });
        
        $menu->send($player);
    }
    
    private static function returnItems(Player $player, string $serializedInventory): void {
        $items = InventorySerializer::deSerialize($serializedInventory);
        
        if(isset($items["contents"])) {
            foreach($items["contents"] as $item) {
                if(!$player->getInventory()->canAddItem($item)) {
                    $player->getWorld()->dropItem($player->getPosition(), $item);
                } else {
                    $player->getInventory()->addItem($item);
                }
            }
        }
        
        if(isset($items["armor"])) {
            foreach($items["armor"] as $slot => $item) {
                $player->getArmorInventory()->setItem($slot, $item);
            }
        }
        
        $player->sendMessage("§a¡Has recibido tus items!");
        $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());
    }
}
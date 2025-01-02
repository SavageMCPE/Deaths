<?php

namespace Lyndarx\Death\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use Lyndarx\Death\Main;
use Lyndarx\Death\menu\DeathMenu;

class DeathCommand extends Command {
    private Main $plugin;
    
    public function __construct(Main $plugin) {
        parent::__construct("deaths", "Ver historial de muertes", "/deaths [jugador]");
        $this->setPermission('deaths.permission');
        $this->plugin = $plugin;
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if(!$sender instanceof Player) {
            return false;
        }
        
        $target = $args[0] ?? $sender->getName();
        
        $targetPlayer = null;
        foreach (Server::getInstance()->getOnlinePlayers() as $onlinePlayer) {
            if (stripos($onlinePlayer->getName(), $target) === 0) {
                $targetPlayer = $onlinePlayer->getName();
                break;
            }
        }
        
        if ($targetPlayer === null) {
            $sender->sendMessage("§cJugador no encontrado.");
            return false;
        }
        
        $deaths = $this->plugin->getDeaths($targetPlayer);
        
        if ($deaths === null) {
            $sender->sendMessage("§cNo se encontraron datos de muertes para este jugador.");
            return false;
        }
        
        DeathMenu::send($sender, $deaths, $targetPlayer);
        return true;
    }
}

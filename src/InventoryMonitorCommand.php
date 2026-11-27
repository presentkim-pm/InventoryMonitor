<?php

/*
 *
 *  ____  _             _         _____
 * | __ )| |_   _  __ _(_)_ __   |_   _|__  __ _ _ __ ___
 * |  _ \| | | | |/ _` | | '_ \    | |/ _ \/ _` | '_ ` _ \
 * | |_) | | |_| | (_| | | | | |   | |  __/ (_| | | | | | |
 * |____/|_|\__,_|\__, |_|_| |_|   |_|\___|\__,_|_| |_| |_|
 *                |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  Blugin team
 * @link    https://github.com/Blugin
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 */

declare(strict_types=1);

namespace blugin\inventorymonitor;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

/** `/inventorymonitor <player>` */
final class InventoryMonitorCommand extends Command implements PluginOwned{
    /** @param string[] $aliases */
    public function __construct(
        private readonly InventoryMonitor $owner,
        string $name,
        string $description,
        string $usage,
        array $aliases = []
    ){
        parent::__construct($name, $description, $usage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if(!$this->testPermission($sender)){
            return;
        }
        $lang = $this->owner->getLanguage();
        if(!$sender instanceof Player){
            $sender->sendMessage($lang->translate("commands.generic.onlyPlayer"));
            return;
        }
        if(!isset($args[0])){
            $sender->sendMessage($this->getUsage());
            return;
        }

        //An online player may be given by the beginning of the name, an offline one only by its full name
        $name = $this->owner->getServer()->getPlayerByPrefix($args[0])?->getName() ?? $args[0];
        $monitor = MonitorInstance::load($this->owner, $name);
        if($monitor === null){
            $sender->sendMessage($lang->translate("commands.generic.player.notFound", [$args[0]]));
            return;
        }
        $monitor->send($sender, $lang->translate("monitor.title", [$monitor->getPlayerName()]));
    }

    public function getOwningPlugin() : Plugin{
        return $this->owner;
    }
}

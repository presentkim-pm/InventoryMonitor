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

use blugin\lib\invmenu\responsive\ResponsiveInvMenu;
use blugin\lib\invmenu\responsive\ResponsiveInvMenuInventory;
use blugin\lib\invmenu\responsive\slot\SyncSlot;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class InventoryMonitor extends PluginBase implements Listener{
    public function onEnable() : void{
        ResponsiveInvMenu::register($this);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /** @param string[] $args */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if(!$sender instanceof Player){
            $sender->sendMessage("Run the command only in-game");
            return true;
        }

        if(!isset($args[0]))
            return false;

        $monitor = MonitorInstance::load((string) $args[0]);
        if($monitor === null){
            $sender->sendMessage("$args[0] is invalid player name");
            return true;
        }

        //Show menu to player
        $menu = $monitor->getMenu();
        $menu->send($sender);
        return true;
    }

    /** @notHandler */
    public function handlePlayerEvent(PlayerEvent $event){
        $monitor = MonitorInstance::get(strtolower($event->getPlayer()->getName()));
        if($monitor !== null){
            $monitor->getMenu()->getInventory()->removeAllViewers();
        }
    }

    /** @priority MONITOR */
    public function onPlayerPreLoginEvent(PlayerPreLoginEvent $event){
        $this->handlePlayerEvent($event);
    }

    /** @priority MONITOR */
    public function onPlayerQuitEvent(PlayerQuitEvent $event){
        $this->handlePlayerEvent($event);
    }

    /** @priority MONITOR */
    public function onInventoryTransactionEvent(InventoryTransactionEvent $event) : void{
        foreach($event->getTransaction()->getActions() as $key => $action){
            if(!$action instanceof SlotChangeAction)
                continue;
            $inventory = $action->getInventory();
            if(!($inventory instanceof ArmorInventory || $inventory instanceof PlayerInventory))
                continue;

            $player = $inventory->getHolder();
            if(!$player instanceof Player)
                continue;

            $monitor = MonitorInstance::get(strtolower($player->getName()));
            if($monitor === null)
                continue;

            $menuInv = $monitor->getMenu()->getInventory();
            if(!$menuInv instanceof ResponsiveInvMenuInventory)
                continue;

            $slotIndex = $action->getSlot();
            foreach($menuInv->getAllSlot() as $index => $slot){
                if(!$slot instanceof SyncSlot)
                    continue;

                if($slot->getInventory() !== $inventory || $slot->getSlot() !== $slotIndex)
                    continue;

                $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $_) use ($menuInv, $slotIndex) : void{
                    $menuInv->sendSlot($slotIndex, $menuInv->getViewers());
                }), 1);
                break;
            }
        }
    }
}

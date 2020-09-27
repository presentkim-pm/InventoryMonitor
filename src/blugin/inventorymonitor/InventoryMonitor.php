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

use blugin\lib\command\BaseCommandTrait;
use blugin\lib\command\listener\AvaliableCommandListener;
use blugin\lib\command\listener\EnumUpdateListener;
use blugin\lib\command\overload\Overload;
use blugin\lib\command\parameter\defaults\PlayerParameter;
use blugin\lib\invmenu\plus\inventory\slot\defaults\SyncSlot;
use blugin\lib\invmenu\plus\inventory\SlotPagingInventory;
use blugin\lib\invmenu\plus\InvMenuPlusHandler;
use blugin\lib\translator\traits\TranslatorHolderTrait;
use blugin\lib\translator\TranslatorHolder;
use blugin\traits\singleton\SingletonTrait;
use pocketmine\command\CommandSender;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class InventoryMonitor extends PluginBase implements Listener, TranslatorHolder{
    use TranslatorHolderTrait, BaseCommandTrait, SingletonTrait;

    public function onLoad(){
        self::$instance = $this;

        $this->loadLanguage();
        $this->getBaseCommand();
    }

    public function onEnable() : void{
        InvMenuPlusHandler::register($this);
        AvaliableCommandListener::register($this);
        EnumUpdateListener::register($this);

        //Register main command
        $overload = (new Overload($command = $this->getBaseCommand()))
            ->addParamater((new PlayerParameter("player"))->setIncludeOffline(true)->setExact(false))
            ->setHandler(function(CommandSender $sender, array $args, Overload $overload) : bool{
                $command = $overload->getBaseCommand();
                if(!$sender instanceof Player){
                    $command->sendMessage($sender, "commands.generic.onlyPlayer");
                    return true;
                }

                $monitor = MonitorInstance::load($args["player"]);
                $monitor->send($sender, $command->getMessage($sender, "chest.name.main", [$monitor->getPlayerName()]));
                return true;
            });
        $this->getServer()->getCommandMap()->register($this->getName(), $command->addOverload($overload));

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable() : void{
        $this->getServer()->getCommandMap()->unregister($this->getBaseCommand());
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
            if(!$action instanceof SlotChangeAction ||
                !(
                    ($inventory = $action->getInventory()) instanceof PlayerInventory ||
                    $inventory instanceof ArmorInventory ||
                    $inventory instanceof PlayerCursorInventory
                ) ||
                !($player = $inventory->getHolder()) instanceof Player ||
                ($monitor = MonitorInstance::get(strtolower($player->getName()))) === null
            )
                continue;

            foreach($monitor->getMenus() as $menu){
                $menuInv = $menu->getInventory();

                if(!$menuInv instanceof SlotPagingInventory)
                    continue;

                $slotIndex = $action->getSlot();
                foreach($menuInv->getAllSlot() as $index => $slot){
                    if(!$slot instanceof SyncSlot || $slot->getInventory() !== $inventory || $slot->getSlot() !== $slotIndex)
                        continue;

                    $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $_) use ($menuInv, $index) : void{
                        $menuInv->sendSlot($index, $menuInv->getViewers());
                    }), 1);
                    break;
                }
            }
        }
    }
}

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

use blugin\inventorymonitor\inventory\OfflinePlayerInventory;
use blugin\lib\invmenu\responsive\ResponsiveInvMenuHandler;
use blugin\lib\invmenu\responsive\ResponsiveInvMenuInventory;
use blugin\lib\invmenu\responsive\slot\ImmutableSlot;
use blugin\lib\invmenu\responsive\slot\OneWaySyncSlot;
use blugin\lib\invmenu\responsive\slot\SyncSlot;
use blugin\lib\invmenu\responsive\slot\ToggleMenuSlot;
use blugin\traits\selffactory\SelfFactoryTrait;
use muqsit\invmenu\InvMenu;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Player;
use pocketmine\Server;

class MonitorInstance{
    use SelfFactoryTrait;

    /** @var string */
    private $playerName;

    /** @var BaseInventory */
    private $mainInventory;

    /** @var BaseInventory */
    private $armorInventory;

    /** @var BaseInventory */
    private $enderChestInventory;

    /** @var BaseInventory|null */
    private $cursorInventory;

    /** @var InvMenu */
    private $mainMenu;

    /** @var InvMenu */
    private $enderMenu;

    public function __construct(string $playerName, BaseInventory $mainInventory, BaseInventory $armorInventory, ?BaseInventory $enderChestInventory = null, ?BaseInventory $cursorInventory = null){
        $this->playerName = strtolower($playerName);
        $this->mainInventory = $mainInventory;
        $this->armorInventory = $armorInventory;
        $this->enderChestInventory = $enderChestInventory;
        $this->cursorInventory = $cursorInventory;

        //Create inv menu
        $this->mainMenu = InvMenu::create(ResponsiveInvMenuHandler::TYPE_DOUBLE_CHEST);
        $this->enderMenu = InvMenu::create(ResponsiveInvMenuHandler::TYPE_DOUBLE_CHEST);

        /**
         * @var $mainInv ResponsiveInvMenuInventory
         * @var $enderInv ResponsiveInvMenuInventory
         */
        $mainInv = $this->mainMenu->getInventory();
        $enderInv = $this->enderMenu->getInventory();

        //Fill the rest with iron bars
        static $immutableSlot;
        if(!isset($immutableSlot)){
            $immutableSlot = new ImmutableSlot(Item::get(ItemIds::IRON_BARS));
        }
        for($i = 0; $i < 54; ++$i){
            $mainInv->setSlot($i, clone $immutableSlot);
            $enderInv->setSlot($i, clone $immutableSlot);
        }

        //Synchronize with inventory
        for($i = 0; $i < 36; ++$i){
            $mainInv->setSlot($i, new SyncSlot($mainInventory, $i));
        }

        //Synchronize with armor inventory
        for($i = 46; $i < 50; ++$i){
            $mainInv->setSlot($i, new SyncSlot($armorInventory, $i % 46));
        }

        if($cursorInventory !== null){
            $mainInv->setSlot(51, new OneWaySyncSlot($cursorInventory, 0));
        }

        //Synchronize with ender inventory
        if($enderChestInventory !== null){
            for($i = 0; $i < 27; ++$i){
                $enderInv->setSlot($i, new SyncSlot($enderChestInventory, $i));
            }
        }

        $mainInv->setSlot(53, new ToggleMenuSlot(Item::get(ItemIds::ENDER_CHEST), $this->enderMenu));
        $enderInv->setSlot(53, new ToggleMenuSlot(Item::get(ItemIds::CHEST), $this->mainMenu));

        //Binding responsive inventory to inv menu
        $mainInv->bind($this->mainMenu);
        $enderInv->bind($this->enderMenu);
        $this->mainMenu->setListener($mainInv->getListener());
        $this->enderMenu->setListener($enderInv->getListener());
        $this->mainMenu->setInventoryCloseListener(\Closure::fromCallable([$this, "onInventoryClose"]));
        $this->enderMenu->setInventoryCloseListener(\Closure::fromCallable([$this, "onInventoryClose"]));
    }

    public static function load(string $playerName, bool $includeOffline = true) : ?MonitorInstance{
        $playerName = strtolower($playerName);
        if(($monitor = self::get($playerName)) !== null)
            return $monitor;

        $server = Server::getInstance();
        $player = $server->getPlayerExact($playerName);
        if($player instanceof Player)
            return self::$instances[$playerName] = new MonitorInstance($playerName, $player->getInventory(), $player->getArmorInventory(), $player->getEnderChestInventory(), $player->getCursorInventory());

        if(!$includeOffline || !$server->hasOfflinePlayerData($playerName))
            return null;

        [$mainInv, $armorInv, $enderInv] = OfflinePlayerInventory::load($server->getOfflinePlayerData($playerName));
        $monitor = self::$instances[$playerName] = new MonitorInstance($playerName, $mainInv, $armorInv, $enderInv);

        //If no one is using the monitor, remove the instance
        static $onInventoryClose;
        if(!isset($onInventoryClose)){
            $onInventoryClose = function(Player $player, Inventory $inventory) use ($monitor, $server, $mainInv, $armorInv, $enderInv) : void{
                if($monitor->onInventoryClose())
                    return;

                $inventoryTag = $mainInv->save(new ListTag("Inventory", [], NBT::TAG_Compound));
                $inventoryTag = $armorInv->save($inventoryTag);
                $enderChestTag = $enderInv->save(new ListTag("EnderChestInventory", [], NBT::TAG_Compound));

                $namedTag = $server->getOfflinePlayerData($monitor->playerName);
                $namedTag->setTag($inventoryTag);
                $namedTag->setTag($enderChestTag);
                $server->saveOfflinePlayerData($monitor->playerName, $namedTag);
            };
        }
        $monitor->getMainMenu()->setInventoryCloseListener($onInventoryClose);
        $monitor->getEnderMenu()->setInventoryCloseListener($onInventoryClose);
        return $monitor;
    }

    public function onInventoryClose() : bool{
        if(empty($this->getMainMenu()->getInventory()->getViewers()) && empty($this->getEnderMenu()->getInventory()->getViewers())){
            unset(self::$instances[$this->playerName]);
            return false;
        }
        return true;
    }

    public function getPlayerName() : string{
        return $this->playerName;
    }

    public function getMainInventory() : BaseInventory{
        return $this->mainInventory;
    }

    public function getArmorInventory() : BaseInventory{
        return $this->armorInventory;
    }

    public function getEnderChestInventory() : BaseInventory{
        return $this->enderChestInventory;
    }

    public function getCursorInventory() : BaseInventory{
        return $this->cursorInventory;
    }

    public function getMainMenu() : InvMenu{
        return $this->mainMenu;
    }

    public function getEnderMenu() : InvMenu{
        return $this->enderMenu;
    }
}

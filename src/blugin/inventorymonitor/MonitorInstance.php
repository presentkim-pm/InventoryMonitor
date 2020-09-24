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
use blugin\lib\invmenu\responsive\ResponsiveInvMenu;
use blugin\lib\invmenu\responsive\ResponsiveInvMenuInventory;
use blugin\lib\invmenu\responsive\slot\ImmutableSlot;
use blugin\lib\invmenu\responsive\slot\SyncSlot;
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
    private $inventory;

    /** @var BaseInventory */
    private $armorInventory;

    /** @var InvMenu */
    private $menu;

    public function __construct(string $playerName, BaseInventory $inventory, BaseInventory $armorInventory){
        $this->playerName = strtolower($playerName);
        $this->inventory = $inventory;
        $this->armorInventory = $armorInventory;

        //Create inv menu
        $this->menu = InvMenu::create(ResponsiveInvMenu::TYPE_DOUBLE_CHEST);
        /** @var $responeInv ResponsiveInvMenuInventory */
        $responeInv = $this->menu->getInventory();

        //Synchronize with inventory
        for($i = 0; $i < 36; ++$i){
            $responeInv->setSlot($i, new SyncSlot($inventory, $i));
        }

        //Fill the rest with iron bars
        static $immutableSlot;
        if(!isset($immutableSlot)){
            $immutableSlot = new ImmutableSlot(Item::get(ItemIds::IRON_BARS));
        }
        for($i = 36; $i < 54; ++$i){
            $responeInv->setSlot($i, clone $immutableSlot);
        }

        //Synchronize with armor inventory
        for($i = 46; $i < 50; ++$i){
            $responeInv->setSlot($i, new SyncSlot($armorInventory, $i % 46));
        }

        //Binding responsive inventory to inv menu
        $responeInv->bind($this->menu);
        $this->menu->setListener($responeInv->getListener());
        //If no one is using the monitor, remove the instance
        $this->menu->setInventoryCloseListener(function(Player $player, Inventory $inventory) : void{
            if(!empty($inventory->getViewers()))
                return;

            $server = Server::getInstance();
            if($server->getPlayerExact($this->playerName) === null){
                $inventoryTag = new ListTag("Inventory", [], NBT::TAG_Compound);
                //Inventory
                for($slot = 0; $slot < 36; ++$slot){
                    $item = $this->getInventory()->getItem($slot);
                    if(!$item->isNull()){
                        $inventoryTag->push($item->nbtSerialize($slot + 9));
                    }
                }

                //Armor
                for($slot = 0; $slot < 4; ++$slot){
                    $item = $this->getArmorInventory()->getItem($slot);
                    if(!$item->isNull()){
                        $inventoryTag->push($item->nbtSerialize($slot + 100));
                    }
                }

                $namedTag = $server->getOfflinePlayerData($this->playerName);
                $namedTag->setTag($inventoryTag);
                $server->saveOfflinePlayerData($this->playerName, $namedTag);
            }

            unset(self::$instances[$this->playerName]);
        });
    }

    /**
     * @param string $playerName
     * @param bool   $includeOffline = true
     *
     * @return null|MonitorInstance
     */
    public static function load(string $playerName, bool $includeOffline = true) : ?MonitorInstance{
        $playerName = strtolower($playerName);
        if(($monitor = self::get($playerName)) !== null)
            return $monitor;

        $server = Server::getInstance();
        $player = $server->getPlayerExact($playerName);
        if($player instanceof Player){
            return self::$instances[$playerName] = new MonitorInstance($playerName, $player->getInventory(), $player->getArmorInventory());
        }

        if(!$includeOffline || !file_exists($server->getDataPath() . "players/{$playerName}.dat"))
            return null;

        $nbt = $server->getOfflinePlayerData($playerName);
        $inventoryTag = $nbt->getListTag("Inventory");
        if($inventoryTag === null)
            return null;

        return self::$instances[$playerName] = new MonitorInstance(
            $playerName,
            new OfflinePlayerInventory($inventoryTag, OfflinePlayerInventory::TYPE_INVENTORY),
            new OfflinePlayerInventory($inventoryTag, OfflinePlayerInventory::TYPE_ARMOR)
        );
    }

    public function getPlayerName() : string{
        return $this->playerName;
    }

    public function getInventory() : BaseInventory{
        return $this->inventory;
    }

    public function getArmorInventory() : BaseInventory{
        return $this->armorInventory;
    }

    public function getMenu() : InvMenu{
        return $this->menu;
    }
}

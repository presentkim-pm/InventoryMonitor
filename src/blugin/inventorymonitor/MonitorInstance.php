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
use blugin\lib\invmenu\plus\inventory\slot\defaults\ImmutableSlot;
use blugin\lib\invmenu\plus\inventory\slot\defaults\OneWaySyncSlot;
use blugin\lib\invmenu\plus\inventory\slot\defaults\SwitcingPageSlot;
use blugin\lib\invmenu\plus\inventory\slot\defaults\SyncSlot;
use blugin\lib\invmenu\plus\inventory\SlotBasedItemArray;
use blugin\lib\invmenu\plus\inventory\SlotPagingInventory;
use blugin\lib\invmenu\plus\InvMenuPlus;
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

    public const TYPE_MAIN = 0;
    public const TYPE_ENDER = 1;

    public const NAME_MAP = [
        self::TYPE_MAIN => "main",
        self::TYPE_ENDER => "ender"
    ];

    /** @var string */
    protected $playerName;

    /** @var \Closure|null */
    protected $inventoryCloseListener = null;

    /** @var SlotBasedItemArray[] */
    protected $pages = [];

    /** @var InvMenuPlus[] */
    protected $menus = [];

    public function __construct(string $playerName, BaseInventory $mainInventory, BaseInventory $armorInventory, ?BaseInventory $enderChestInventory = null, ?BaseInventory $cursorInventory = null){
        $this->playerName = $playerName;

        //Create pages
        $mainPage = new SlotBasedItemArray(54);
        $enderPage = new SlotBasedItemArray(54);

        //Fill the rest with iron bars
        static $immutableSlot;
        if(!isset($immutableSlot)){
            $immutableSlot = new ImmutableSlot(Item::get(ItemIds::IRON_BARS)->setCustomName(InventoryMonitor::getInstance()->getTranslator()->translate("chest.slot.border")));
        }
        for($i = 0; $i < 54; ++$i){
            $mainPage[$i] = clone $immutableSlot;
            $enderPage[$i] = clone $immutableSlot;
        }

        //Synchronize with inventory
        for($i = 0; $i < 36; ++$i){
            $mainPage[$i] = new SyncSlot($mainInventory, $i);
        }

        //Synchronize with armor inventory
        for($i = 46; $i < 50; ++$i){
            $mainPage[$i] = new SyncSlot($armorInventory, $i - 46);
        }

        if($cursorInventory !== null){
            $mainPage[51] = new OneWaySyncSlot($cursorInventory, 0);
        }

        //Synchronize with ender inventory
        if($enderChestInventory !== null){
            for($i = 0; $i < 27; ++$i){
                $enderPage[$i] = new SyncSlot($enderChestInventory, $i);
            }
        }
        $tanslator = InventoryMonitor::getInstance()->getTranslator();
        $mainPage[53] = new SwitcingPageSlot(Item::get(ItemIds::ENDER_CHEST)->setCustomName($tanslator->translate("chest.slot.toggle." . self::NAME_MAP[self::TYPE_ENDER])), self::TYPE_ENDER);
        $enderPage[53] = new SwitcingPageSlot(Item::get(ItemIds::CHEST)->setCustomName($tanslator->translate("chest.slot.toggle." . self::NAME_MAP[self::TYPE_MAIN])), self::TYPE_MAIN);

        $this->pages[self::TYPE_MAIN] = $mainPage;
        $this->pages[self::TYPE_ENDER] = $enderPage;
    }

    public static function load(string $playerName, bool $includeOffline = true) : ?MonitorInstance{
        $playerName = strtolower($playerName);
        if(($monitor = self::get($playerName)) !== null)
            return $monitor;

        $server = Server::getInstance();
        $player = $server->getPlayerExact($playerName);
        if($player instanceof Player)
            return self::$instances[$playerName] = new MonitorInstance($player->getName(), $player->getInventory(), $player->getArmorInventory(), $player->getEnderChestInventory(), $player->getCursorInventory());

        if(!$includeOffline || !$server->hasOfflinePlayerData($playerName))
            return null;

        [$mainInv, $armorInv, $enderInv] = OfflinePlayerInventory::load($server->getOfflinePlayerData($playerName));
        $monitor = self::$instances[$playerName] = new MonitorInstance($playerName, $mainInv, $armorInv, $enderInv);
        //If no one is using the monitor, remove the instance
        $monitor->setInventoryCloseListener(function(Player $player, Inventory $inventory) use ($server, $monitor, $mainInv, $armorInv, $enderInv) : void{
            if($monitor->onInventoryClose($player)){
                $inventoryTag = $mainInv->save(new ListTag("Inventory", [], NBT::TAG_Compound));
                $inventoryTag = $armorInv->save($inventoryTag);
                $enderChestTag = $enderInv->save(new ListTag("EnderChestInventory", [], NBT::TAG_Compound));

                $namedTag = $server->getOfflinePlayerData($monitor->playerName);
                $namedTag->setTag($inventoryTag);
                $namedTag->setTag($enderChestTag);
                $server->saveOfflinePlayerData($monitor->playerName, $namedTag);
            }
        });

        return $monitor;
    }

    public function onInventoryClose(Player $player) : bool{
        if(isset($this->menus[$key = spl_object_hash($player)])){
            unset($this->menus[$key]);
        }
        foreach($this->menus as $menu){
            if(!empty($menu->getInventory()->getViewers()))
                return false;
        }
        unset(self::$instances[$this->playerName]);
        return true;
    }

    public function getPlayerName() : string{
        return $this->playerName;
    }

    public function getInventoryCloseListener() : \Closure{
        return $this->inventoryCloseListener ?? \Closure::fromCallable([$this, "onInventoryClose"]);
    }

    public function setInventoryCloseListener(?\Closure $listener) : void{
        $this->inventoryCloseListener = $listener;
    }

    /** @return SlotBasedItemArray[] */
    public function getPages() : array{
        return $this->pages;
    }

    public function addPage(SlotBasedItemArray $page) : int{
        $this->pages[] = $page;
        return count($this->pages) - 1;
    }

    public function removePage(int $pageNumber) : void{
        unset($this->pages[$pageNumber]);
    }

    public function getPage(int $pageNumber) : ?SlotBasedItemArray{
        return $this->pages[$pageNumber] ?? null;
    }

    public function setPage(int $pageNumber, SlotBasedItemArray $page) : void{
        if(!isset($this->pages[$pageNumber])){
            $this->addPage($page);
        }else{
            $this->pages[$pageNumber] = $page;
        }
    }

    /** @return InvMenuPlus[] */
    public function getMenus() : array{
        return $this->menus;
    }

    public function getMenu(Player $player) : ?InvMenuPlus{
        return $this->menus[spl_object_hash($player)] ?? null;
    }

    public function send(Player $player, string $name) : InvMenuPlus{
        if(!isset($this->menus[$key = spl_object_hash($player)])){
            $menu = $this->menus[$key] = InvMenuPlus::create(InvMenu::TYPE_DOUBLE_CHEST, SlotPagingInventory::class);

            /** @var $menuInv SlotPagingInventory */
            $menuInv = $menu->getInventory();
            $menuInv->setPages($this->pages, self::TYPE_MAIN);

            $menuInv->bind($menu);
            $menu->setListener($menuInv->getListener());
            $menu->setInventoryCloseListener($this->getInventoryCloseListener());
        }

        $this->menus[$key]->setName($name);
        $this->menus[$key]->send($player);
        return $this->menus[$key];
    }
}

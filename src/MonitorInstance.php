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
use kim\present\libcontainer\Click;
use kim\present\libcontainer\Menu;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryListener;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use function array_values;
use function count;
use function spl_object_id;
use function strtolower;

/**
 * A monitored player (online or offline) and the menus of the players who are watching it.
 *
 * The menu inventory of every viewer is kept in sync with the monitored inventories in both directions:
 *  - a change of the monitored inventory is shown in the menus,
 *  - an item the viewer moves in a synchronized slot of the menu is written to the monitored inventory.
 */
final class MonitorInstance{
    /** @var array<string, self> lower-cased player name => instance */
    private static array $instances = [];

    /** @return self[] */
    public static function all() : array{
        return array_values(self::$instances);
    }

    public static function get(string $playerName) : ?self{
        return self::$instances[strtolower($playerName)] ?? null;
    }

    /**
     * @param bool $includeOffline whether the saved data of an offline player may be used
     * @return self|null null if there is no such player
     */
    public static function load(InventoryMonitor $plugin, string $playerName, bool $includeOffline = true) : ?self{
        $key = strtolower($playerName);
        if(($monitor = self::get($key)) !== null){
            return $monitor;
        }

        $server = $plugin->getServer();
        $player = $server->getPlayerExact($playerName);
        if($player !== null){
            return self::$instances[$key] = new self($plugin, $player->getName(), [
                MonitorLayout::SOURCE_MAIN => $player->getInventory(),
                MonitorLayout::SOURCE_ARMOR => $player->getArmorInventory(),
                MonitorLayout::SOURCE_ENDER => $player->getEnderInventory(),
                MonitorLayout::SOURCE_CURSOR => $player->getCursorInventory(),
            ], null);
        }

        if(!$includeOffline || !$server->hasOfflinePlayerData($key)){
            return null;
        }
        $data = $server->getOfflinePlayerData($key);
        if($data === null){
            return null;
        }
        $offline = OfflinePlayerInventory::load($data);
        return self::$instances[$key] = new self($plugin, $playerName, [
            MonitorLayout::SOURCE_MAIN => $offline->main,
            MonitorLayout::SOURCE_ARMOR => $offline->armor,
            MonitorLayout::SOURCE_ENDER => $offline->ender,
        ], $offline);
    }

    /** @var array<int, MonitorViewer> player object id => viewer */
    private array $viewers = [];

    /** @var array<string, InventoryListener> */
    private array $sourceListeners = [];

    /** > 0 while the menus are being written by the monitor itself, so that the writes are not mistaken for the viewers' */
    private int $refreshing = 0;

    private bool $disposed = false;
    private bool $dirty = false;

    private readonly Item $borderItem;

    /**
     * @param array<string, Inventory> $sources monitored inventories by {@see MonitorLayout}::SOURCE_* key
     * @param OfflinePlayerInventory|null $offline set when the data comes from the player data file, it is saved when
     *                                             the last viewer closes the menu
     */
    private function __construct(
        private readonly InventoryMonitor $plugin,
        private readonly string $playerName,
        private readonly array $sources,
        private readonly ?OfflinePlayerInventory $offline
    ){
        $lang = $plugin->getLanguage();
        $this->borderItem = VanillaBlocks::IRON_BARS()->asItem()->setCustomName($lang->translate("chest.slot.border"));

        foreach($this->sources as $key => $inventory){
            $inventory->getListeners()->add($this->sourceListeners[$key] = new CallbackInventoryListener(
                function(Inventory $unused, int $slot, Item $unused2) use ($key) : void{
                    $this->onSourceSlotChange($key, $slot);
                },
                function(Inventory $unused, array $unused2) use ($key) : void{
                    $this->onSourceContentChange($key);
                }
            ));
        }
    }

    public function getPlayerName() : string{
        return $this->playerName;
    }

    /** @return MonitorViewer[] */
    public function getViewers() : array{
        return array_values($this->viewers);
    }

    public function isViewing(Player $player) : bool{
        return isset($this->viewers[spl_object_id($player)]);
    }

    /** Opens the monitor menu for the player */
    public function send(Player $player, string $menuName) : void{
        $previous = $this->viewers[spl_object_id($player)] ?? null;
        if($previous !== null){
            $this->removeViewer($previous, false);
        }

        $menu = Menu::chest($menuName);
        $viewer = new MonitorViewer($player, $menu, MonitorPage::Inventory1, isset($this->sources[MonitorLayout::SOURCE_CURSOR]));
        $this->viewers[spl_object_id($player)] = $viewer;

        $this->applyPage($viewer);
        $viewer->menuListener = new CallbackInventoryListener(
            function(Inventory $inventory, int $slot, Item $unused) use ($viewer) : void{
                $this->onMenuSlotChange($viewer, $slot);
            },
            function(Inventory $inventory, array $unused) use ($viewer) : void{
                $this->onMenuContentChange($viewer);
            }
        );
        $menu->getInventory()->getListeners()->add($viewer->menuListener);

        $menu->onClose(function(Player $player, Menu $menu) use ($viewer) : void{
            $this->removeViewer($viewer);
        });
        if(!$menu->open($player)){
            $this->removeViewer($viewer);
        }
    }

    /** Closes the menus of all viewers, e.g. when the monitored player joins or leaves */
    public function closeAll() : void{
        foreach($this->getViewers() as $viewer){
            $this->removeViewer($viewer);
            if($viewer->player->isOnline()){
                $viewer->player->removeCurrentWindow();
            }
        }
        $this->dispose();
    }

    /** @param bool $disposeIfLast whether the instance ends when this was the last viewer */
    private function removeViewer(MonitorViewer $viewer, bool $disposeIfLast = true) : void{
        $id = spl_object_id($viewer->player);
        if(($this->viewers[$id] ?? null) !== $viewer){
            return;
        }
        unset($this->viewers[$id]);
        if($viewer->menuListener !== null){
            $viewer->menu->getInventory()->getListeners()->remove($viewer->menuListener);
            $viewer->menuListener = null;
        }
        if($disposeIfLast && count($this->viewers) === 0){
            $this->dispose();
        }
    }

    /** Called when nobody is looking at the monitor any more */
    private function dispose() : void{
        if($this->disposed){
            return;
        }
        $this->disposed = true;

        foreach($this->sourceListeners as $key => $listener){
            $this->sources[$key]->getListeners()->remove($listener);
        }
        $this->sourceListeners = [];

        $key = strtolower($this->playerName);
        if((self::$instances[$key] ?? null) === $this){
            unset(self::$instances[$key]);
        }

        if($this->offline !== null && $this->dirty){
            $server = $this->plugin->getServer();
            $data = $server->getOfflinePlayerData($key);
            if($data === null){
                $this->plugin->getLogger()->error("Unable to save the changes of {$this->playerName}'s offline inventory");
                return;
            }
            $this->offline->save($data);
            $server->saveOfflinePlayerData($key, $data);
        }
    }

    /** The viewer changed a slot of the menu: write it to the monitored inventory */
    private function onMenuSlotChange(MonitorViewer $viewer, int $slot) : void{
        if($this->refreshing > 0){
            return;
        }
        $definition = $viewer->layout->slots[$slot] ?? null;
        if($definition === null || $definition->kind !== SlotKind::Sync || $definition->source === null){
            return;
        }
        $source = $this->sources[$definition->source] ?? null;
        $item = $viewer->menu->getInventory()->getItem($slot);
        if($source !== null && !$source->getItem($definition->sourceSlot)->equalsExact($item)){
            $source->setItem($definition->sourceSlot, $item);
        }
    }

    private function onMenuContentChange(MonitorViewer $viewer) : void{
        if($this->refreshing > 0){
            return;
        }
        foreach($viewer->layout->slots as $slot => $definition){
            $this->onMenuSlotChange($viewer, $slot);
        }
    }

    private function onSourceSlotChange(string $source, int $sourceSlot) : void{
        $this->dirty = true;
        foreach($this->viewers as $viewer){
            $slot = $viewer->layout->slotOf($source, $sourceSlot);
            if($slot !== null){
                $this->refreshSlot($viewer, $slot);
            }
        }
    }

    private function onSourceContentChange(string $source) : void{
        $this->dirty = true;
        foreach($this->viewers as $viewer){
            foreach($viewer->layout->slots as $slot => $definition){
                if($definition->source === $source){
                    $this->refreshSlot($viewer, $slot);
                }
            }
        }
    }

    private function switchPage(MonitorViewer $viewer, MonitorPage $page) : void{
        if(($this->viewers[spl_object_id($viewer->player)] ?? null) !== $viewer){
            return;
        }
        $viewer->setPage($page);
        $this->applyPage($viewer);
    }

    /**
     * Sets the slot rules of the menu for the page of the viewer (synchronized slots are free, armor slots only take
     * the matching armor piece, the page buttons are buttons, the rest is locked) and fills the slots.
     */
    private function applyPage(MonitorViewer $viewer) : void{
        $menu = $viewer->menu;
        ++$this->refreshing;
        try{
            foreach($viewer->layout->slots as $slot => $definition){
                $menu->removeButton($slot);
                $menu->lock($slot);
                switch($definition->kind){
                    case SlotKind::Sync:
                        if($definition->source === MonitorLayout::SOURCE_ARMOR){
                            $armorSlot = $definition->sourceSlot;
                            $menu->accept($slot, static fn(Item $item) : bool => ArmorSlotRule::accepts($item, $armorSlot));
                        }else{
                            $menu->unlock($slot);
                        }
                        break;
                    case SlotKind::Previous:
                    case SlotKind::Next:
                        $target = $definition->target;
                        if($target !== null){
                            $menu->setButton($slot, $this->itemFor($viewer, $definition), function(Click $click) use ($viewer, $target) : void{
                                $this->switchPage($viewer, $target);
                            });
                        }
                        break;
                }
            }
        }finally{
            --$this->refreshing;
        }
        $this->refreshAll($viewer);
    }

    private function refreshAll(MonitorViewer $viewer) : void{
        foreach($viewer->layout->slots as $slot => $definition){
            $this->refreshSlot($viewer, $slot);
        }
    }

    /** The item a menu slot shows for its definition */
    private function itemFor(MonitorViewer $viewer, SlotDefinition $definition) : Item{
        $lang = $this->plugin->getLanguage();
        return match($definition->kind){
            SlotKind::Border => $this->borderItem,
            SlotKind::Previous => VanillaItems::ARROW()->setCustomName($lang->translate("chest.slot.previous")),
            SlotKind::Next => VanillaItems::ARROW()->setCustomName($lang->translate("chest.slot.next")),
            SlotKind::Indicator => ($viewer->getPage() === MonitorPage::Armor ? VanillaItems::DIAMOND_CHESTPLATE() : (
                $viewer->getPage() === MonitorPage::Ender1 || $viewer->getPage() === MonitorPage::Ender2 ? VanillaBlocks::ENDER_CHEST()->asItem() : VanillaBlocks::CHEST()->asItem()
            ))->setCustomName($lang->translate("chest.slot.page", [
                $viewer->getPage()->number(),
                count(MonitorPage::cases()),
                $lang->translate("chest.page." . $viewer->getPage()->label())
            ])),
            default => ($this->sources[$definition->source ?? ""] ?? null)?->getItem($definition->sourceSlot) ?? $this->borderItem,
        };
    }

    /** Makes the menu slot show what its definition says */
    private function refreshSlot(MonitorViewer $viewer, int $slot) : void{
        $item = $this->itemFor($viewer, $viewer->layout->slots[$slot]);

        $inventory = $viewer->menu->getInventory();
        if($inventory->getItem($slot)->equalsExact($item)){
            return;
        }
        ++$this->refreshing;
        try{
            $inventory->setItem($slot, $item);
        }finally{
            --$this->refreshing;
        }
    }
}

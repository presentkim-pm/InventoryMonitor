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
use kim\present\libcontainer\Menu;
use kim\present\libcontainer\ui\ScreenMenu;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\entity\Human;
use pocketmine\entity\InvalidSkinException;
use pocketmine\entity\Skin;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryListener;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use function array_values;
use function count;
use function spl_object_id;
use function str_repeat;
use function strtolower;

/**
 * A monitored player (online or offline) and the screens of the players who are watching it.
 *
 * Every viewer has a {@see ScreenMenu} whose slots are bound to the monitored inventories (see {@see MonitorSlots}):
 * libcontainer keeps both sides in sync in real time. This class only wires the bindings, follows the lifecycle
 * (the last viewer closing ends the instance) and saves the data of an offline player.
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
                MonitorSlots::SOURCE_INVENTORY => $player->getInventory(),
                MonitorSlots::SOURCE_ARMOR => $player->getArmorInventory(),
                MonitorSlots::SOURCE_OFFHAND => $player->getOffHandInventory(),
                MonitorSlots::SOURCE_CURSOR => $player->getCursorInventory(),
                MonitorSlots::SOURCE_ENDER => $player->getEnderInventory(),
            ], null, $player->getSkin(), $player->getInventory(), 0);
        }

        if(!$includeOffline || !$server->hasOfflinePlayerData($key)){
            return null;
        }
        $data = $server->getOfflinePlayerData($key);
        if($data === null){
            return null;
        }
        $offline = OfflinePlayerInventory::load($data);
        //An offline player has no cursor item, so that slot of the screen stays empty
        try{
            $skin = Human::parseSkinNBT($data);
        }catch(SavedDataLoadingException | InvalidSkinException){
            $skin = self::defaultSkin();
        }
        return self::$instances[$key] = new self($plugin, $playerName, [
            MonitorSlots::SOURCE_INVENTORY => $offline->main,
            MonitorSlots::SOURCE_ARMOR => $offline->armor,
            MonitorSlots::SOURCE_OFFHAND => $offline->offHand,
            MonitorSlots::SOURCE_ENDER => $offline->ender,
        ], $offline, $skin, null, $data->getInt(self::TAG_SELECTED_INVENTORY_SLOT, 0));
    }

    /** Hotbar slot a player held when the data was saved, see Human::saveNBT() */
    private const TAG_SELECTED_INVENTORY_SLOT = "SelectedInventorySlot";

    /** A plain gray skin for an offline player whose data has no skin */
    private static function defaultSkin() : Skin{
        return new Skin("Standard_Custom", str_repeat("\x80\x80\x80\xff", 64 * 64));
    }

    /** @var array<int, MonitorViewer> player object id => viewer */
    private array $viewers = [];

    /** @var array<string, InventoryListener> */
    private array $sourceListeners = [];

    private ?\Closure $heldListener = null;

    private bool $disposed = false;
    private bool $dirty = false;

    /**
     * @param array<string, Inventory> $sources monitored inventories by {@see MonitorSlots}::SOURCE_* key
     * @param OfflinePlayerInventory|null $offline set when the data comes from the player data file, it is saved when
     *                                             the last viewer closes the screen
     */
    private function __construct(
        private readonly InventoryMonitor $plugin,
        private readonly string $playerName,
        private readonly array $sources,
        private readonly ?OfflinePlayerInventory $offline,
        private readonly Skin $skin,
        private readonly ?PlayerInventory $heldInventory,
        private readonly int $offlineHeldIndex
    ){
        //The screens follow the sources by themselves; this is for the offline data and the viewers' puppets
        foreach($this->sources as $key => $inventory){
            $inventory->getListeners()->add($this->sourceListeners[$key] = CallbackInventoryListener::onAnyChange(function() : void{
                $this->dirty = true;
                $this->updatePuppets();
            }));
        }
        $this->heldInventory?->getHeldItemIndexChangeListeners()->add($this->heldListener = function(int $oldIndex) : void{
            $this->updatePuppets();
            foreach($this->viewers as $viewer){
                $this->sendHeldIndex($viewer->screen->getMenu());
            }
        });
    }

    private function heldIndex() : int{
        return $this->heldInventory?->getHeldItemIndex() ?? $this->offlineHeldIndex;
    }

    /** "h" + index: a bare number would be read as a number by the screen's expression */
    private function sendHeldIndex(Menu $menu) : void{
        $menu->lock(MonitorSlots::HELD)->setItem(MonitorSlots::HELD, VanillaItems::PAPER()->setCustomName("h" . $this->heldIndex()));
    }

    /** @return list<Item> head, chest, legs, feet, main hand, off hand of the monitored player */
    private function equipment() : array{
        $armor = $this->sources[MonitorSlots::SOURCE_ARMOR];
        $inventory = $this->sources[MonitorSlots::SOURCE_INVENTORY];
        return [
            $armor->getItem(0),
            $armor->getItem(1),
            $armor->getItem(2),
            $armor->getItem(3),
            $inventory->getItem($this->heldIndex()),
            $this->sources[MonitorSlots::SOURCE_OFFHAND]->getItem(0),
        ];
    }

    private function updatePuppets() : void{
        $equipment = $this->equipment();
        foreach($this->viewers as $viewer){
            $viewer->puppet->update($equipment);
        }
    }

    public function getPlayerName() : string{
        return $this->playerName;
    }

    public function isOnline() : bool{
        return $this->offline === null;
    }

    /** @return MonitorViewer[] */
    public function getViewers() : array{
        return array_values($this->viewers);
    }

    public function isViewing(Player $player) : bool{
        return isset($this->viewers[spl_object_id($player)]);
    }

    /** Opens the monitor screen for the player */
    public function send(Player $player, string $title) : void{
        $previous = $this->viewers[spl_object_id($player)] ?? null;
        if($previous !== null){
            $this->removeViewer($previous, false);
        }

        $lang = $this->plugin->getLanguage();
        $screen = ScreenMenu::create(MonitorSlots::SCREEN_KEY, $title);
        $viewer = new MonitorViewer($player, $screen, new MonitorPuppet($player, $this->skin));
        $this->viewers[spl_object_id($player)] = $viewer;
        $viewer->puppet->spawn($this->equipment());
        $puppet = $viewer->puppet;
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(static fn() => $puppet->removeListEntry()), MonitorPuppet::LIST_ENTRY_TICKS);

        $menu = $screen->getMenu();
        $menu->batch(function() use ($menu, $screen, $lang, $viewer) : void{
            foreach(MonitorSlots::BINDINGS as $binding){
                $source = $this->sources[$binding["source"]] ?? null;
                if($source !== null){
                    $menu->bind($source, $binding["offset"], $binding["sourceStart"], $binding["count"], $binding["readOnly"]);
                }
            }
            //Armor slots only accept the matching armor piece
            for($i = 0; $i < MonitorSlots::ARMOR_COUNT; ++$i){
                $menu->accept(MonitorSlots::ARMOR_START + $i, static fn(Item $item) : bool => ArmorSlotRule::accepts($item, $i));
            }
            //Everything else (data slot, reserved slots, a missing cursor) stays locked
            $screen->data(MonitorSlots::DATA, $lang->translate("monitor.label", [
                $this->playerName,
                $lang->translate($this->isOnline() ? "monitor.status.online" : "monitor.status.offline")
            ]));
            //live_horse_renderer takes the id as a string; quoted, the screen's expression keeps it one. Not a DataSlot:
            //its NUL terminator would stay in the id and the renderer would draw nothing
            $menu->lock(MonitorSlots::MODEL)->setItem(
                MonitorSlots::MODEL,
                VanillaItems::PAPER()->setCustomName('"' . $viewer->puppet->getActorId() . '"')
            );
            $this->sendHeldIndex($menu);
            $menu->lock(MonitorSlots::TAB_INVENTORY)->setItem(MonitorSlots::TAB_INVENTORY, VanillaBlocks::CHEST()->asItem());
            $menu->lock(MonitorSlots::TAB_ENDER)->setItem(MonitorSlots::TAB_ENDER, VanillaBlocks::ENDER_CHEST()->asItem());
        });

        $menu->onClose(function(Player $player, Menu $menu) use ($viewer) : void{
            $this->removeViewer($viewer);
        });
        if(!$screen->open($player)){
            $this->removeViewer($viewer);
        }
    }

    /** Closes the screens of all viewers, e.g. when the monitored player joins or leaves */
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
        $viewer->puppet->despawn();

        //The bindings keep the monitored inventories referenced until they are released
        $menu = $viewer->screen->getMenu();
        foreach($this->sources as $source){
            $menu->unbind($source);
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
        if($this->heldListener !== null){
            $this->heldInventory?->getHeldItemIndexChangeListeners()->remove($this->heldListener);
            $this->heldListener = null;
        }

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
}

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

namespace blugin\inventorymonitor\inventory;

use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;

/**
 * The inventories of an offline player, read from and written to the player's saved NBT data
 * (the same layout as `Human::saveNBT()`).
 */
final class OfflinePlayerInventory{
    public const TAG_INVENTORY = "Inventory";
    public const TAG_ENDER_CHEST = "EnderChestInventory";
    public const TAG_OFF_HAND = "OffHandItem";
    public const TAG_SLOT = "Slot";

    public const MAIN_SIZE = 36;
    /** The saved slot of the first inventory slot (0-8 are the legacy hotbar slots) */
    public const MAIN_OFFSET = 9;
    public const ARMOR_SIZE = 4;
    public const ARMOR_OFFSET = 100;
    public const ENDER_SIZE = 27;
    public const OFF_HAND_SIZE = 1;

    public function __construct(
        public readonly SimpleInventory $main,
        public readonly SimpleInventory $armor,
        public readonly SimpleInventory $ender,
        public readonly SimpleInventory $offHand
    ){}

    public static function load(CompoundTag $nbt) : self{
        $main = [];
        $armor = [];
        foreach($nbt->getListTag(self::TAG_INVENTORY, CompoundTag::class) ?? [] as $itemTag){
            $slot = $itemTag->getByte(self::TAG_SLOT);
            if($slot >= self::ARMOR_OFFSET && $slot < self::ARMOR_OFFSET + self::ARMOR_SIZE){
                $armor[$slot - self::ARMOR_OFFSET] = Item::safeNbtDeserialize($itemTag, "Offline armor slot $slot");
            }elseif($slot >= self::MAIN_OFFSET && $slot < self::MAIN_OFFSET + self::MAIN_SIZE){
                $main[$slot - self::MAIN_OFFSET] = Item::safeNbtDeserialize($itemTag, "Offline inventory slot $slot");
            }
        }

        $ender = [];
        foreach($nbt->getListTag(self::TAG_ENDER_CHEST, CompoundTag::class) ?? [] as $itemTag){
            $slot = $itemTag->getByte(self::TAG_SLOT);
            if($slot >= 0 && $slot < self::ENDER_SIZE){
                $ender[$slot] = Item::safeNbtDeserialize($itemTag, "Offline ender chest slot $slot");
            }
        }

        $offHandTag = $nbt->getCompoundTag(self::TAG_OFF_HAND);
        $offHand = $offHandTag === null ? [] : [0 => Item::safeNbtDeserialize($offHandTag, "Offline off-hand item")];

        return new self(
            self::createInventory(self::MAIN_SIZE, $main),
            self::createInventory(self::ARMOR_SIZE, $armor),
            self::createInventory(self::ENDER_SIZE, $ender),
            self::createInventory(self::OFF_HAND_SIZE, $offHand)
        );
    }

    /**
     * @param array<int, Item> $items
     */
    private static function createInventory(int $size, array $items) : SimpleInventory{
        $inventory = new SimpleInventory($size);
        $inventory->setContents($items);
        return $inventory;
    }

    /** Writes the inventories into the saved data, the other tags (held slot, ...) are kept */
    public function save(CompoundTag $nbt) : void{
        $inventoryTag = new ListTag([], NBT::TAG_Compound);
        foreach($this->main->getContents() as $slot => $item){
            $inventoryTag->push($item->nbtSerialize($slot + self::MAIN_OFFSET));
        }
        foreach($this->armor->getContents() as $slot => $item){
            $inventoryTag->push($item->nbtSerialize($slot + self::ARMOR_OFFSET));
        }
        $nbt->setTag(self::TAG_INVENTORY, $inventoryTag);

        $enderTag = new ListTag([], NBT::TAG_Compound);
        foreach($this->ender->getContents() as $slot => $item){
            $enderTag->push($item->nbtSerialize($slot));
        }
        $nbt->setTag(self::TAG_ENDER_CHEST, $enderTag);

        //Like Human::saveNBT(), the tag is only there when the off-hand is not empty
        $offHand = $this->offHand->getItem(0);
        if($offHand->isNull()){
            $nbt->removeTag(self::TAG_OFF_HAND);
        }else{
            $nbt->setTag(self::TAG_OFF_HAND, $offHand->nbtSerialize());
        }
    }
}

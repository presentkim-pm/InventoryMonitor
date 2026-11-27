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

use pocketmine\block\BlockTypeIds;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;

/**
 * The server only validates armor slots for the online player's own armor inventory, but the monitor also edits offline
 * data and goes through a different inventory, so the same rule is applied here.
 */
final class ArmorSlotRule{
    private function __construct(){
    }

    /** @param int $slot one of the {@see ArmorInventory}::SLOT_* constants */
    public static function accepts(Item $item, int $slot) : bool{
        if($item->isNull()){
            return true;
        }
        if($item instanceof Armor){
            return $item->getArmorSlot() === $slot;
        }
        return $slot === ArmorInventory::SLOT_HEAD && $item instanceof ItemBlock && (
            $item->getBlock()->getTypeId() === BlockTypeIds::CARVED_PUMPKIN ||
            $item->getBlock()->getTypeId() === BlockTypeIds::MOB_HEAD
        );
    }
}

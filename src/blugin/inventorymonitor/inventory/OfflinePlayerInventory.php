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

use pocketmine\inventory\ContainerInventory;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\WindowTypes;

class OfflinePlayerInventory extends ContainerInventory{
    public const TYPE_INVENTORY = 36;
    public const TYPE_ARMOR = 4;

    public const SIZE = [
        self::TYPE_INVENTORY => 36,
        self::TYPE_ARMOR => 4
    ];

    public const INDEX = [
        self::TYPE_INVENTORY => 9,
        self::TYPE_ARMOR => 100
    ];

    /** @var int */
    private $type;

    public function __construct(ListTag $inventoryTag, int $type){
        $size = self::SIZE[$type];
        $index = self::INDEX[$type];

        $items = [];
        /** @var CompoundTag $itemTag */
        foreach($inventoryTag as $i => $itemTag){
            $slot = $itemTag->getByte("Slot");
            $item = Item::nbtDeserialize($itemTag);
            if($slot >= $index && $slot < ($index + $size)){
                $items[$slot - $index] = $item;
            }
        }
        parent::__construct(new Vector3(), $items, $size);
        $this->type = $type;
    }

    public function getName() : string{ return "OfflinePlayerInventory"; }

    public function getDefaultSize() : int{ return self::SIZE[$this->type]; }

    public function getNetworkType() : int{ return WindowTypes::CONTAINER; }
}
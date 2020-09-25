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
    public const TYPE_MAIN = 0;
    public const TYPE_ARMOR = 1;
    public const TYPE_ENDERCHEST = 2;

    public const SIZE = [
        self::TYPE_MAIN => 36,
        self::TYPE_ARMOR => 4,
        self::TYPE_ENDERCHEST => 27
    ];

    public const INDEX = [
        self::TYPE_MAIN => 9,
        self::TYPE_ARMOR => 100,
        self::TYPE_ENDERCHEST => 0
    ];

    public const TAG_NAME = [
        self::TYPE_MAIN => "Inventory",
        self::TYPE_ARMOR => "Inventory",
        self::TYPE_ENDERCHEST => "EnderChestInventory"
    ];

    /** @var int */
    private $type;

    public function __construct(CompoundTag $nbt, int $type){
        $size = self::SIZE[$type];
        $index = self::INDEX[$type];
        $tagName = self::TAG_NAME[$type];

        $items = [];
        $inventoryTag = $nbt->getListTag($tagName);
        if($inventoryTag !== null){
            /** @var CompoundTag $itemTag */
            foreach($inventoryTag as $i => $itemTag){
                $slot = $itemTag->getByte("Slot");
                $item = Item::nbtDeserialize($itemTag);
                if($slot >= $index && $slot < ($index + $size)){
                    $items[$slot - $index] = $item;
                }
            }
        }
        parent::__construct(new Vector3(), $items, $size);
        $this->type = $type;
    }

    /** @return OfflinePlayerInventory[] */
    public static function load(CompoundTag $nbt) : array{
        return [
            self::TYPE_MAIN => new OfflinePlayerInventory($nbt, self::TYPE_MAIN),
            self::TYPE_ARMOR => new OfflinePlayerInventory($nbt, self::TYPE_ARMOR),
            self::TYPE_ENDERCHEST => new OfflinePlayerInventory($nbt, self::TYPE_ENDERCHEST)
        ];
    }

    public function save(ListTag $tag) : ListTag{
        $size = $this->getSize();
        for($slot = 0; $slot < $size; ++$slot){
            $item = $this->getItem($slot);
            if(!$item->isNull()){
                $tag->push($item->nbtSerialize($slot + self::INDEX[$this->type]));
            }
        }
        return $tag;
    }

    public function getName() : string{ return "OfflinePlayerInventory"; }

    public function getDefaultSize() : int{ return self::SIZE[$this->type]; }

    public function getNetworkType() : int{ return WindowTypes::CONTAINER; }
}
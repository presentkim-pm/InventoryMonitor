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

namespace blugin\inventorymonitor\inventory\group;

use blugin\inventorymonitor\inventory\SyncInventory;
use pocketmine\item\Item;

abstract class SlotGroup{
    public const START = -1;
    public const END = -1;

    /** @var SyncInventory $syncInventory */
    protected $syncInventory;

    /**
     * SlotGroup constructor.
     *
     * @param SyncInventory $syncInventory
     */
    public function __construct(SyncInventory $syncInventory){
        $this->syncInventory = $syncInventory;
    }

    /**
     * @param int $slot
     *
     * @return bool
     */
    public function validate(int $slot) : bool{
        return $slot >= static::START && $slot <= static::END;
    }

    /**
     * @param int  $slot
     * @param Item $item
     */
    public function setItem(int $slot, Item $item) : void{
        $this->onUpdate($slot - static::START, $item);
    }

    /**
     * @param int  $index
     * @param Item $item
     */
    public abstract function onUpdate(int $index, Item $item) : void;
}

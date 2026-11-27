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

/**
 * The slot contract between the plugin and the bundled resource pack (`resources/pack`).
 *
 * The menu is one resource-pack screen of {@see SIZE} slots. The window title the client receives starts with
 * {@see SCREEN_KEY}, which the pack's JSON UI recognises; every cell of the pack reads the slot with the number below as
 * `collection_index` of `container_items`. Changing a number here means changing `resources/pack/ui` too.
 *
 *   0..8    hotbar            PlayerInventory 0..8      (two-way)
 *   9..35   main inventory    PlayerInventory 9..35     (two-way)
 *   36..39  armor             helmet, chestplate, leggings, boots, only the matching piece is accepted (two-way)
 *   40      offhand           PlayerOffHandInventory 0  (two-way)
 *   41      cursor            PlayerCursorInventory 0   (read-only, online players only)
 *   42      data slot         name and online / offline status of the target, drawn as a label (locked)
 *   43..44  reserved          locked and empty
 *   45..71  ender chest       EnderChest 0..26          (two-way)
 */
final class MonitorSlots{
    /** Title prefix the pack looks for, see {@see \kim\present\libcontainer\ui\Screen} */
    public const SCREEN_KEY = "§b§w§i§m";
    public const SIZE = 72;

    public const HOTBAR_START = 0;
    public const HOTBAR_COUNT = 9;
    public const MAIN_START = 9;
    public const MAIN_COUNT = 27;
    /** hotbar and main inventory are one bound range, as in PlayerInventory */
    public const INVENTORY_START = 0;
    public const INVENTORY_COUNT = 36;
    public const ARMOR_START = 36;
    public const ARMOR_COUNT = 4;
    public const OFFHAND = 40;
    public const CURSOR = 41;
    public const DATA = 42;
    public const RESERVED_START = 43;
    public const RESERVED_COUNT = 2;
    public const ENDER_START = 45;
    public const ENDER_COUNT = 27;

    public const SOURCE_INVENTORY = "inventory";
    public const SOURCE_ARMOR = "armor";
    public const SOURCE_OFFHAND = "offhand";
    public const SOURCE_CURSOR = "cursor";
    public const SOURCE_ENDER = "ender";

    /**
     * The mirrored ranges: which monitored inventory is bound to which menu slots.
     *
     * @var list<array{source: string, offset: int, sourceStart: int, count: int, readOnly: bool}>
     */
    public const BINDINGS = [
        ["source" => self::SOURCE_INVENTORY, "offset" => self::INVENTORY_START, "sourceStart" => 0, "count" => self::INVENTORY_COUNT, "readOnly" => false],
        ["source" => self::SOURCE_ARMOR, "offset" => self::ARMOR_START, "sourceStart" => 0, "count" => self::ARMOR_COUNT, "readOnly" => false],
        ["source" => self::SOURCE_OFFHAND, "offset" => self::OFFHAND, "sourceStart" => 0, "count" => 1, "readOnly" => false],
        ["source" => self::SOURCE_CURSOR, "offset" => self::CURSOR, "sourceStart" => 0, "count" => 1, "readOnly" => true],
        ["source" => self::SOURCE_ENDER, "offset" => self::ENDER_START, "sourceStart" => 0, "count" => self::ENDER_COUNT, "readOnly" => false],
    ];

    private function __construct(){
    }

    /** The armor inventory slot (see ArmorInventory::SLOT_*) of a menu slot, null if it is not an armor slot */
    public static function armorSlotOf(int $menuSlot) : ?int{
        return $menuSlot >= self::ARMOR_START && $menuSlot < self::ARMOR_START + self::ARMOR_COUNT ? $menuSlot - self::ARMOR_START : null;
    }

    /** The SOURCE_* key of the monitored inventory a menu slot mirrors; null for the data and reserved slots */
    public static function sourceOf(int $menuSlot) : ?string{
        foreach(self::BINDINGS as $binding){
            if($menuSlot >= $binding["offset"] && $menuSlot < $binding["offset"] + $binding["count"]){
                return $binding["source"];
            }
        }
        return null;
    }

    public static function isReserved(int $menuSlot) : bool{
        return $menuSlot >= self::RESERVED_START && $menuSlot < self::RESERVED_START + self::RESERVED_COUNT;
    }
}

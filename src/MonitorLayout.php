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
 * The layout of the double chest menu (54 slots), pure data without any server state.
 *
 * Main page: the 36 slots of the inventory (0-35), the 4 armor slots (46-49: helmet, chestplate, leggings, boots),
 * the cursor item (51), and the toggle to the ender chest page (53).
 * Ender page: the 27 slots of the ender chest (0-26) and the toggle to the main page (53).
 * Everything else is a border.
 */
final class MonitorLayout{
    public const SIZE = 54;

    public const SOURCE_MAIN = "main";
    public const SOURCE_ARMOR = "armor";
    public const SOURCE_ENDER = "ender";
    public const SOURCE_CURSOR = "cursor";

    public const INVENTORY_SLOTS = 36;
    public const ARMOR_SLOT_OFFSET = 46;
    public const ARMOR_SLOTS = 4;
    public const CURSOR_SLOT = 51;
    public const ENDER_SLOTS = 27;
    public const TOGGLE_SLOT = 53;

    private function __construct(){
    }

    /** @return array<int, SlotDefinition> menu slot => definition, for all the slots of the menu */
    public static function build(MonitorPage $page, bool $hasCursor = true) : array{
        $layout = [];
        for($i = 0; $i < self::SIZE; ++$i){
            $layout[$i] = new SlotDefinition(SlotKind::Border);
        }

        switch($page){
            case MonitorPage::Main:
                for($i = 0; $i < self::INVENTORY_SLOTS; ++$i){
                    $layout[$i] = new SlotDefinition(SlotKind::Sync, self::SOURCE_MAIN, $i);
                }
                for($i = 0; $i < self::ARMOR_SLOTS; ++$i){
                    $layout[self::ARMOR_SLOT_OFFSET + $i] = new SlotDefinition(SlotKind::Sync, self::SOURCE_ARMOR, $i);
                }
                if($hasCursor){
                    $layout[self::CURSOR_SLOT] = new SlotDefinition(SlotKind::OneWay, self::SOURCE_CURSOR, 0);
                }
                $layout[self::TOGGLE_SLOT] = new SlotDefinition(SlotKind::Toggle, target: MonitorPage::Ender);
                break;
            case MonitorPage::Ender:
                for($i = 0; $i < self::ENDER_SLOTS; ++$i){
                    $layout[$i] = new SlotDefinition(SlotKind::Sync, self::SOURCE_ENDER, $i);
                }
                $layout[self::TOGGLE_SLOT] = new SlotDefinition(SlotKind::Toggle, target: MonitorPage::Main);
                break;
        }
        return $layout;
    }
}

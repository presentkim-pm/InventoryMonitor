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
 * The layout of the chest menu (27 slots, the biggest the vanilla chest screen shows), pure data without any server
 * state. The top two rows (0-17) hold the content of a page, the bottom row (18-26) the page buttons: previous (18),
 * the page indicator (22) and next (26), which wrap around.
 *
 * Pages: inventory slots 0-17, inventory slots 18-35, armor (10-13: helmet, chestplate, leggings, boots) and the
 * cursor item (16), ender chest slots 0-17, ender chest slots 18-26.
 * Everything else is a border.
 */
final class MonitorLayout{
    public const SIZE = 27;

    public const SOURCE_MAIN = "main";
    public const SOURCE_ARMOR = "armor";
    public const SOURCE_ENDER = "ender";
    public const SOURCE_CURSOR = "cursor";

    /** Slots of a source shown by one content page */
    public const PAGE_SLOTS = 18;
    public const ARMOR_SLOT_OFFSET = 10;
    public const ARMOR_SLOTS = 4;
    public const CURSOR_SLOT = 16;
    public const PREVIOUS_SLOT = 18;
    public const INDICATOR_SLOT = 22;
    public const NEXT_SLOT = 26;

    private function __construct(){
    }

    /** @return array<int, SlotDefinition> menu slot => definition, for all the slots of the menu */
    public static function build(MonitorPage $page, bool $hasCursor = true) : array{
        $layout = [];
        for($i = 0; $i < self::SIZE; ++$i){
            $layout[$i] = new SlotDefinition(SlotKind::Border);
        }

        switch($page){
            case MonitorPage::Inventory1:
                self::fill($layout, self::SOURCE_MAIN, 0, self::PAGE_SLOTS);
                break;
            case MonitorPage::Inventory2:
                self::fill($layout, self::SOURCE_MAIN, self::PAGE_SLOTS, self::PAGE_SLOTS);
                break;
            case MonitorPage::Armor:
                for($i = 0; $i < self::ARMOR_SLOTS; ++$i){
                    $layout[self::ARMOR_SLOT_OFFSET + $i] = new SlotDefinition(SlotKind::Sync, self::SOURCE_ARMOR, $i);
                }
                if($hasCursor){
                    $layout[self::CURSOR_SLOT] = new SlotDefinition(SlotKind::OneWay, self::SOURCE_CURSOR, 0);
                }
                break;
            case MonitorPage::Ender1:
                self::fill($layout, self::SOURCE_ENDER, 0, self::PAGE_SLOTS);
                break;
            case MonitorPage::Ender2:
                self::fill($layout, self::SOURCE_ENDER, self::PAGE_SLOTS, 9);
                break;
        }

        $layout[self::PREVIOUS_SLOT] = new SlotDefinition(SlotKind::Previous, target: $page->previous());
        $layout[self::INDICATOR_SLOT] = new SlotDefinition(SlotKind::Indicator);
        $layout[self::NEXT_SLOT] = new SlotDefinition(SlotKind::Next, target: $page->next());
        return $layout;
    }

    /** @param array<int, SlotDefinition> $layout */
    private static function fill(array &$layout, string $source, int $from, int $count) : void{
        for($i = 0; $i < $count; ++$i){
            $layout[$i] = new SlotDefinition(SlotKind::Sync, $source, $from + $i);
        }
    }
}

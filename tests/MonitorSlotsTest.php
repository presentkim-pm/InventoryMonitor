<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\tests;

use blugin\inventorymonitor\MonitorSlots;
use PHPUnit\Framework\TestCase;
use pocketmine\inventory\ArmorInventory;

/** Pure tests of the slot contract, see {@see MonitorSlots} */
final class MonitorSlotsTest extends TestCase{
    public function testBindingsFitTheScreenAndDoNotOverlap() : void{
        $used = [];
        foreach(MonitorSlots::BINDINGS as $binding){
            self::assertGreaterThanOrEqual(0, $binding["offset"]);
            self::assertLessThanOrEqual(MonitorSlots::SIZE, $binding["offset"] + $binding["count"]);
            for($slot = $binding["offset"]; $slot < $binding["offset"] + $binding["count"]; ++$slot){
                self::assertArrayNotHasKey($slot, $used, "slot $slot is bound twice");
                $used[$slot] = $binding["source"];
            }
        }
        //everything but the three data slots and the two tab icons is mirrored: 36 + 4 + 1 + 1 + 27
        self::assertCount(69, $used);
        self::assertSame(MonitorSlots::SIZE - 5, count($used));
    }

    public function testUnboundSlots() : void{
        self::assertNull(MonitorSlots::sourceOf(MonitorSlots::DATA));
        self::assertNull(MonitorSlots::sourceOf(MonitorSlots::MODEL));
        self::assertNull(MonitorSlots::sourceOf(MonitorSlots::HELD));
        self::assertSame([42, 43, 44], [MonitorSlots::DATA, MonitorSlots::MODEL, MonitorSlots::HELD]);
        self::assertNull(MonitorSlots::sourceOf(MonitorSlots::TAB_INVENTORY));
        self::assertNull(MonitorSlots::sourceOf(MonitorSlots::TAB_ENDER));
        self::assertSame([72, 73], [MonitorSlots::TAB_INVENTORY, MonitorSlots::TAB_ENDER]);
        for($slot = 0; $slot < MonitorSlots::SIZE; ++$slot){
            self::assertFalse(MonitorSlots::isReserved($slot));
        }
    }

    public function testDocumentedRanges() : void{
        for($slot = 0; $slot <= 35; ++$slot){
            self::assertSame(MonitorSlots::SOURCE_INVENTORY, MonitorSlots::sourceOf($slot));
        }
        foreach([36, 37, 38, 39] as $slot){
            self::assertSame(MonitorSlots::SOURCE_ARMOR, MonitorSlots::sourceOf($slot));
        }
        self::assertSame(MonitorSlots::SOURCE_OFFHAND, MonitorSlots::sourceOf(40));
        self::assertSame(MonitorSlots::SOURCE_CURSOR, MonitorSlots::sourceOf(41));
        self::assertSame(42, MonitorSlots::DATA);
        for($slot = 45; $slot <= 71; ++$slot){
            self::assertSame(MonitorSlots::SOURCE_ENDER, MonitorSlots::sourceOf($slot));
        }
        self::assertNull(MonitorSlots::sourceOf(72));
        self::assertNull(MonitorSlots::sourceOf(73));
        self::assertSame(74, MonitorSlots::SIZE);
        self::assertSame(MonitorSlots::HOTBAR_COUNT + MonitorSlots::MAIN_COUNT, MonitorSlots::INVENTORY_COUNT);
        self::assertSame(MonitorSlots::HOTBAR_START + MonitorSlots::HOTBAR_COUNT, MonitorSlots::MAIN_START);
    }

    public function testOnlyTheCursorIsReadOnly() : void{
        foreach(MonitorSlots::BINDINGS as $binding){
            self::assertSame($binding["source"] === MonitorSlots::SOURCE_CURSOR, $binding["readOnly"], $binding["source"]);
        }
    }

    public function testArmorSlots() : void{
        self::assertSame(ArmorInventory::SLOT_HEAD, MonitorSlots::armorSlotOf(36));
        self::assertSame(ArmorInventory::SLOT_CHEST, MonitorSlots::armorSlotOf(37));
        self::assertSame(ArmorInventory::SLOT_LEGS, MonitorSlots::armorSlotOf(38));
        self::assertSame(ArmorInventory::SLOT_FEET, MonitorSlots::armorSlotOf(39));
        self::assertNull(MonitorSlots::armorSlotOf(35));
        self::assertNull(MonitorSlots::armorSlotOf(40));
    }

    public function testScreenKeyIsAValidLibcontainerScreen() : void{
        $screen = new \kim\present\libcontainer\ui\Screen(MonitorSlots::SCREEN_KEY, MonitorSlots::SIZE);
        self::assertSame("§b§w§i§m", $screen->getKey());
    }
}

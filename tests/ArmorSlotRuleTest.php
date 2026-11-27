<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\tests;

use blugin\inventorymonitor\ArmorSlotRule;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\VanillaItems;
use PHPUnit\Framework\TestCase;

final class ArmorSlotRuleTest extends TestCase{
    public function testArmorGoesToItsOwnSlot() : void{
        self::assertTrue(ArmorSlotRule::accepts(VanillaItems::DIAMOND_HELMET(), ArmorInventory::SLOT_HEAD));
        self::assertFalse(ArmorSlotRule::accepts(VanillaItems::DIAMOND_HELMET(), ArmorInventory::SLOT_CHEST));
        self::assertTrue(ArmorSlotRule::accepts(VanillaItems::IRON_BOOTS(), ArmorInventory::SLOT_FEET));
        self::assertFalse(ArmorSlotRule::accepts(VanillaItems::IRON_BOOTS(), ArmorInventory::SLOT_LEGS));
    }

    public function testAirIsAlwaysAccepted() : void{
        foreach([ArmorInventory::SLOT_HEAD, ArmorInventory::SLOT_CHEST, ArmorInventory::SLOT_LEGS, ArmorInventory::SLOT_FEET] as $slot){
            self::assertTrue(ArmorSlotRule::accepts(VanillaItems::AIR(), $slot));
        }
    }

    public function testOtherItemsAreRejected() : void{
        self::assertFalse(ArmorSlotRule::accepts(VanillaItems::APPLE(), ArmorInventory::SLOT_HEAD));
        self::assertFalse(ArmorSlotRule::accepts(VanillaBlocks::STONE()->asItem(), ArmorInventory::SLOT_CHEST));
    }

    public function testPumpkinOnlyOnTheHead() : void{
        self::assertTrue(ArmorSlotRule::accepts(VanillaBlocks::CARVED_PUMPKIN()->asItem(), ArmorInventory::SLOT_HEAD));
        self::assertFalse(ArmorSlotRule::accepts(VanillaBlocks::CARVED_PUMPKIN()->asItem(), ArmorInventory::SLOT_CHEST));
    }
}

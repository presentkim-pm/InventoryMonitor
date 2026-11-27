<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\tests;

use blugin\inventorymonitor\inventory\OfflinePlayerInventory;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use PHPUnit\Framework\TestCase;

final class OfflinePlayerInventoryTest extends TestCase{
    public function testRoundTrip() : void{
        $nbt = CompoundTag::create();
        $nbt->setTag("Inventory", new ListTag([
            VanillaItems::APPLE()->setCount(3)->nbtSerialize(9),   //first inventory slot
            VanillaItems::DIAMOND()->nbtSerialize(44),             //last inventory slot
            VanillaItems::DIAMOND_HELMET()->nbtSerialize(100),     //helmet
            VanillaItems::IRON_BOOTS()->nbtSerialize(103),         //boots
            VanillaItems::STICK()->nbtSerialize(2),                //legacy hotbar slot, ignored
        ], NBT::TAG_Compound));
        $nbt->setTag("EnderChestInventory", new ListTag([
            VanillaItems::GOLD_INGOT()->setCount(7)->nbtSerialize(26),
        ], NBT::TAG_Compound));
        $nbt->setInt("SelectedInventorySlot", 4);

        $offline = OfflinePlayerInventory::load($nbt);
        self::assertSame(36, $offline->main->getSize());
        self::assertSame(4, $offline->armor->getSize());
        self::assertSame(27, $offline->ender->getSize());
        self::assertSame(3, $offline->main->getItem(0)->getCount());
        self::assertTrue($offline->main->getItem(0)->equals(VanillaItems::APPLE()));
        self::assertTrue($offline->main->getItem(35)->equals(VanillaItems::DIAMOND()));
        self::assertCount(2, $offline->main->getContents());
        self::assertTrue($offline->armor->getItem(0)->equals(VanillaItems::DIAMOND_HELMET()));
        self::assertTrue($offline->armor->getItem(3)->equals(VanillaItems::IRON_BOOTS()));
        self::assertSame(7, $offline->ender->getItem(26)->getCount());

        //edit and save into the data, keeping the other tags
        $offline->main->setItem(1, VanillaItems::BREAD());
        $offline->armor->setItem(0, VanillaItems::AIR());
        $offline->ender->clearAll();
        $offline->save($nbt);
        self::assertSame(4, $nbt->getInt("SelectedInventorySlot"));

        $reloaded = OfflinePlayerInventory::load($nbt);
        self::assertTrue($reloaded->main->getItem(1)->equals(VanillaItems::BREAD()));
        self::assertTrue($reloaded->main->getItem(0)->equals(VanillaItems::APPLE()));
        self::assertTrue($reloaded->armor->getItem(0)->isNull());
        self::assertTrue($reloaded->armor->getItem(3)->equals(VanillaItems::IRON_BOOTS()));
        self::assertCount(0, $reloaded->ender->getContents());

        //the saved slots follow the server's layout: inventory from 9, armor from 100
        $slots = [];
        foreach($nbt->getListTag("Inventory") as $tag){
            $slots[] = $tag->getByte("Slot");
        }
        sort($slots);
        self::assertSame([9, 10, 44, 103], $slots);
    }

    public function testMissingTagsGiveEmptyInventories() : void{
        $offline = OfflinePlayerInventory::load(CompoundTag::create());
        self::assertCount(0, $offline->main->getContents());
        self::assertCount(0, $offline->armor->getContents());
        self::assertCount(0, $offline->ender->getContents());
    }
}

<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\tests;

use blugin\inventorymonitor\MonitorLayout;
use blugin\inventorymonitor\MonitorPage;
use blugin\inventorymonitor\SlotKind;
use PHPUnit\Framework\TestCase;

final class MonitorLayoutTest extends TestCase{
    public function testEveryPageFitsTheVanillaChest() : void{
        foreach(MonitorPage::cases() as $page){
            $layout = MonitorLayout::build($page);
            self::assertCount(27, $layout);
            self::assertSame(range(0, 26), array_keys($layout));
        }
    }

    public function testInventoryPages() : void{
        $one = MonitorLayout::build(MonitorPage::Inventory1);
        $two = MonitorLayout::build(MonitorPage::Inventory2);
        for($i = 0; $i < 18; ++$i){
            self::assertSame(SlotKind::Sync, $one[$i]->kind);
            self::assertSame(MonitorLayout::SOURCE_MAIN, $one[$i]->source);
            self::assertSame($i, $one[$i]->sourceSlot);
            self::assertSame(18 + $i, $two[$i]->sourceSlot);
        }
    }

    public function testArmorPage() : void{
        $layout = MonitorLayout::build(MonitorPage::Armor);
        foreach([0, 1, 2, 3] as $armor){
            self::assertSame(SlotKind::Sync, $layout[10 + $armor]->kind);
            self::assertSame(MonitorLayout::SOURCE_ARMOR, $layout[10 + $armor]->source);
            self::assertSame($armor, $layout[10 + $armor]->sourceSlot);
        }
        self::assertSame(SlotKind::OneWay, $layout[16]->kind);
        self::assertSame(MonitorLayout::SOURCE_CURSOR, $layout[16]->source);
        self::assertSame(SlotKind::Border, $layout[0]->kind);
    }

    public function testWithoutCursor() : void{
        self::assertSame(SlotKind::Border, MonitorLayout::build(MonitorPage::Armor, false)[16]->kind);
    }

    public function testEnderPages() : void{
        $one = MonitorLayout::build(MonitorPage::Ender1);
        $two = MonitorLayout::build(MonitorPage::Ender2);
        for($i = 0; $i < 18; ++$i){
            self::assertSame(MonitorLayout::SOURCE_ENDER, $one[$i]->source);
            self::assertSame($i, $one[$i]->sourceSlot);
        }
        for($i = 0; $i < 9; ++$i){
            self::assertSame(18 + $i, $two[$i]->sourceSlot);
        }
        for($i = 9; $i < 18; ++$i){
            self::assertSame(SlotKind::Border, $two[$i]->kind);
        }
    }

    public function testPageButtonsWrapAround() : void{
        foreach(MonitorPage::cases() as $page){
            $layout = MonitorLayout::build($page);
            self::assertSame(SlotKind::Previous, $layout[18]->kind);
            self::assertSame($page->previous(), $layout[18]->target);
            self::assertSame(SlotKind::Indicator, $layout[22]->kind);
            self::assertSame(SlotKind::Next, $layout[26]->kind);
            self::assertSame($page->next(), $layout[26]->target);
            self::assertSame($page, $page->next()->previous());
        }
        self::assertSame(MonitorPage::Inventory1, MonitorPage::Ender2->next());
        self::assertSame(MonitorPage::Ender2, MonitorPage::Inventory1->previous());
    }

    public function testEverySourceSlotIsShownOnce() : void{
        $seen = [];
        foreach(MonitorPage::cases() as $page){
            foreach(MonitorLayout::build($page, false) as $definition){
                if($definition->source !== null){
                    $key = $definition->source . ":" . $definition->sourceSlot;
                    self::assertArrayNotHasKey($key, $seen);
                    $seen[$key] = true;
                }
            }
        }
        //36 inventory + 4 armor + 27 ender chest
        self::assertCount(67, $seen);
    }

    public function testPageLabels() : void{
        self::assertSame("inventory1", MonitorPage::Inventory1->label());
        self::assertSame("ender2", MonitorPage::Ender2->label());
        self::assertSame(1, MonitorPage::Inventory1->number());
    }
}

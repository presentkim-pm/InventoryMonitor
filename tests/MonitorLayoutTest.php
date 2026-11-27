<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\tests;

use blugin\inventorymonitor\MonitorLayout;
use blugin\inventorymonitor\MonitorPage;
use blugin\inventorymonitor\SlotKind;
use PHPUnit\Framework\TestCase;

final class MonitorLayoutTest extends TestCase{
    public function testMainPage() : void{
        $layout = MonitorLayout::build(MonitorPage::Main);
        self::assertCount(54, $layout);

        for($i = 0; $i < 36; ++$i){
            self::assertSame(SlotKind::Sync, $layout[$i]->kind);
            self::assertSame(MonitorLayout::SOURCE_MAIN, $layout[$i]->source);
            self::assertSame($i, $layout[$i]->sourceSlot);
        }
        foreach(range(36, 45) as $i){
            self::assertSame(SlotKind::Border, $layout[$i]->kind);
        }
        foreach([0, 1, 2, 3] as $armor){
            self::assertSame(SlotKind::Sync, $layout[46 + $armor]->kind);
            self::assertSame(MonitorLayout::SOURCE_ARMOR, $layout[46 + $armor]->source);
            self::assertSame($armor, $layout[46 + $armor]->sourceSlot);
        }
        self::assertSame(SlotKind::Border, $layout[50]->kind);
        self::assertSame(SlotKind::OneWay, $layout[51]->kind);
        self::assertSame(MonitorLayout::SOURCE_CURSOR, $layout[51]->source);
        self::assertSame(SlotKind::Border, $layout[52]->kind);
        self::assertSame(SlotKind::Toggle, $layout[53]->kind);
        self::assertSame(MonitorPage::Ender, $layout[53]->target);
    }

    public function testWithoutCursor() : void{
        self::assertSame(SlotKind::Border, MonitorLayout::build(MonitorPage::Main, false)[51]->kind);
    }

    public function testEnderPage() : void{
        $layout = MonitorLayout::build(MonitorPage::Ender);
        self::assertCount(54, $layout);
        for($i = 0; $i < 27; ++$i){
            self::assertSame(SlotKind::Sync, $layout[$i]->kind);
            self::assertSame(MonitorLayout::SOURCE_ENDER, $layout[$i]->source);
            self::assertSame($i, $layout[$i]->sourceSlot);
        }
        for($i = 27; $i < 53; ++$i){
            self::assertSame(SlotKind::Border, $layout[$i]->kind);
        }
        self::assertSame(SlotKind::Toggle, $layout[53]->kind);
        self::assertSame(MonitorPage::Main, $layout[53]->target);
    }

    public function testEverySourceSlotIsShownOnce() : void{
        foreach([MonitorPage::Main, MonitorPage::Ender] as $page){
            $seen = [];
            foreach(MonitorLayout::build($page) as $definition){
                if($definition->source !== null){
                    $key = $definition->source . ":" . $definition->sourceSlot;
                    self::assertArrayNotHasKey($key, $seen);
                    $seen[$key] = true;
                }
            }
        }
    }

    public function testPageLabels() : void{
        self::assertSame("main", MonitorPage::Main->label());
        self::assertSame("ender", MonitorPage::Ender->label());
    }
}

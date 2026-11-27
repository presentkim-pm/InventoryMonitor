<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\tests;

use blugin\inventorymonitor\MonitorLayout;
use blugin\inventorymonitor\MonitorPage;
use blugin\inventorymonitor\PageLayout;
use blugin\inventorymonitor\SlotKind;
use PHPUnit\Framework\TestCase;

final class PageLayoutTest extends TestCase{
    public function testSlotLookup() : void{
        $layout = new PageLayout(MonitorPage::Inventory1);
        self::assertSame(5, $layout->slotOf(MonitorLayout::SOURCE_MAIN, 5));
        self::assertNull($layout->slotOf(MonitorLayout::SOURCE_MAIN, 18));
        self::assertNull($layout->slotOf(MonitorLayout::SOURCE_ARMOR, 1));
        self::assertNull($layout->slotOf(MonitorLayout::SOURCE_ENDER, 0));

        $layout = new PageLayout(MonitorPage::Inventory2);
        self::assertSame(2, $layout->slotOf(MonitorLayout::SOURCE_MAIN, 20));
        self::assertNull($layout->slotOf(MonitorLayout::SOURCE_MAIN, 5));
    }

    public function testArmorPage() : void{
        $layout = new PageLayout(MonitorPage::Armor);
        self::assertSame(11, $layout->slotOf(MonitorLayout::SOURCE_ARMOR, 1));
        self::assertSame(16, $layout->slotOf(MonitorLayout::SOURCE_CURSOR, 0));
    }

    public function testEnderPage() : void{
        $layout = new PageLayout(MonitorPage::Ender2);
        self::assertSame(MonitorPage::Ender2, $layout->page);
        self::assertNull($layout->slotOf(MonitorLayout::SOURCE_MAIN, 5));
        self::assertSame(8, $layout->slotOf(MonitorLayout::SOURCE_ENDER, 26));
        self::assertSame(SlotKind::Next, $layout->slots[26]->kind);
    }

    public function testWithoutCursor() : void{
        self::assertNull((new PageLayout(MonitorPage::Armor, false))->slotOf(MonitorLayout::SOURCE_CURSOR, 0));
    }
}

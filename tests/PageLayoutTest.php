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
        $layout = new PageLayout(MonitorPage::Main);
        self::assertSame(5, $layout->slotOf(MonitorLayout::SOURCE_MAIN, 5));
        self::assertSame(47, $layout->slotOf(MonitorLayout::SOURCE_ARMOR, 1));
        self::assertSame(51, $layout->slotOf(MonitorLayout::SOURCE_CURSOR, 0));
        self::assertNull($layout->slotOf(MonitorLayout::SOURCE_ENDER, 0));
        self::assertNull($layout->slotOf(MonitorLayout::SOURCE_MAIN, 36));
    }

    public function testEnderPage() : void{
        $layout = new PageLayout(MonitorPage::Ender);
        self::assertSame(MonitorPage::Ender, $layout->page);
        self::assertNull($layout->slotOf(MonitorLayout::SOURCE_MAIN, 5));
        self::assertSame(26, $layout->slotOf(MonitorLayout::SOURCE_ENDER, 26));
        self::assertSame(SlotKind::Toggle, $layout->slots[53]->kind);
    }

    public function testWithoutCursor() : void{
        self::assertNull((new PageLayout(MonitorPage::Main, false))->slotOf(MonitorLayout::SOURCE_CURSOR, 0));
    }
}

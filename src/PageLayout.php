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

/** The layout of a page with the reverse lookup (monitored slot => menu slot) */
final class PageLayout{
    /** @var array<int, SlotDefinition> menu slot => definition */
    public readonly array $slots;

    /** @var array<string, array<int, int>> source => [source slot => menu slot] */
    private array $reverse = [];

    public function __construct(public readonly MonitorPage $page, bool $hasCursor = true){
        $this->slots = MonitorLayout::build($page, $hasCursor);
        foreach($this->slots as $slot => $definition){
            if($definition->source !== null){
                $this->reverse[$definition->source][$definition->sourceSlot] = $slot;
            }
        }
    }

    /** @return int|null the menu slot that shows the slot of the source, null if this page does not show it */
    public function slotOf(string $source, int $sourceSlot) : ?int{
        return $this->reverse[$source][$sourceSlot] ?? null;
    }
}

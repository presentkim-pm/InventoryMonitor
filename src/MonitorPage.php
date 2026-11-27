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

use function count;

/** The pages the monitor menu can show, in the order of the previous / next buttons (they wrap around) */
enum MonitorPage : int{
    /** Inventory slots 0-17 (the hotbar and the first row) */
    case Inventory1 = 0;
    /** Inventory slots 18-35 */
    case Inventory2 = 1;
    /** The armor slots and the item on the cursor */
    case Armor = 2;
    /** Ender chest slots 0-17 */
    case Ender1 = 3;
    /** Ender chest slots 18-26 */
    case Ender2 = 4;

    /** Used in the language keys (`chest.page.<label>`) */
    public function label() : string{
        return match($this){
            self::Inventory1 => "inventory1",
            self::Inventory2 => "inventory2",
            self::Armor => "armor",
            self::Ender1 => "ender1",
            self::Ender2 => "ender2",
        };
    }

    /** 1-based number of the page */
    public function number() : int{
        return $this->value + 1;
    }

    public function next() : self{
        return self::from(($this->value + 1) % count(self::cases()));
    }

    public function previous() : self{
        return self::from(($this->value + count(self::cases()) - 1) % count(self::cases()));
    }
}

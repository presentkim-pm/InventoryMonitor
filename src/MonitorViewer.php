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

use kim\present\libcontainer\Menu;
use pocketmine\inventory\InventoryListener;
use pocketmine\player\Player;

/** The menu of one player who is watching a {@see MonitorInstance} */
final class MonitorViewer{
    public PageLayout $layout;

    public ?InventoryListener $menuListener = null;

    public function __construct(
        public readonly Player $player,
        public readonly Menu $menu,
        MonitorPage $page,
        private readonly bool $hasCursor
    ){
        $this->setPage($page);
    }

    public function getPage() : MonitorPage{
        return $this->layout->page;
    }

    public function setPage(MonitorPage $page) : void{
        $this->layout = new PageLayout($page, $this->hasCursor);
    }
}

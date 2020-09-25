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

namespace blugin\inventorymonitor\slot;

use blugin\inventorymonitor\InventoryMonitor;
use blugin\lib\invmenu\responsive\slot\SlotTransactionEvent;
use blugin\lib\invmenu\responsive\slot\ToggleMenuSlot;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\item\Item;

class ToggleInventorySlot extends ToggleMenuSlot{
    public const TYPE_MAIN = "main";
    public const TYPE_ENDER = "ender";

    /** @var string */
    protected $type;

    public function __construct(Item $item, InvMenu $menu, string $type){
        parent::__construct($item->setCustomName(InventoryMonitor::getInstance()->getTranslator()->translate("chest.slot.toggle." . $type)), $menu);
        $this->type = $type;
    }

    public function handleTransaction(SlotTransactionEvent $event) : InvMenuTransactionResult{
        $player = $event->getPlayer();
        $this->getMenu()->setName(InventoryMonitor::getInstance()->getTranslator()->translateTo("chest.name." . $this->type, [$player->getName()], $player));
        return parent::handleTransaction($event);
    }
}
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

namespace blugin\inventorymonitor\listener;

use blugin\inventorymonitor\inventory\group\ArmorGroup;
use blugin\inventorymonitor\inventory\group\CursorGroup;
use blugin\inventorymonitor\inventory\group\InvGroup;
use blugin\inventorymonitor\inventory\SyncInventory;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;

class InventoryEventListener implements Listener{
    /**
     * @priority MONITOR
     *
     * @param InventoryTransactionEvent $event
     */
    public function onInventoryTransactionEvent(InventoryTransactionEvent $event) : void{
        foreach($event->getTransaction()->getActions() as $key => $action){
            if($action instanceof SlotChangeAction){
                $inventory = $action->getInventory();
                if($inventory instanceof SyncInventory){
                    if(!$inventory->isValidSlot($action->getSlot())){
                        $event->setCancelled();
                    }
                }elseif($inventory instanceof PlayerCursorInventory){
                    $syncInventory = SyncInventory::get($inventory->getHolder()->getName());
                    if($syncInventory !== null){
                        $syncInventory->setItem(CursorGroup::START, $action->getTargetItem(), false);
                    }
                }elseif($inventory instanceof ArmorInventory){
                    $syncInventory = SyncInventory::get($inventory->getHolder()->getName());
                    if($syncInventory !== null){
                        $syncInventory->setItem($action->getSlot() + ArmorGroup::START, $action->getTargetItem());
                    }
                }elseif($inventory instanceof PlayerInventory){
                    $syncInventory = SyncInventory::get($inventory->getHolder()->getName());
                    if($syncInventory !== null){
                        $syncInventory->setItem($action->getSlot() + InvGroup::START, $action->getTargetItem());
                    }
                }
            }
        }
    }
}

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

use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\GameMode;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
use pocketmine\player\Player;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function array_fill;

/**
 * A player model that only one viewer sees, for the model of the monitor screen. It is not a real entity of the world:
 * the server only sends its packets to the viewer, so the target's emotes and movements are not shown and an offline
 * player has a model too. The screen's live_horse_renderer finds it by its actor id ({@see getActorId()}).
 */
final class MonitorPuppet{
    /** Blocks below the viewer's feet: the model is inside the ground, out of the world view, and still drawn by the UI */
    public const DEPTH = 3.0;

    /** Ticks the player list entry is kept after the spawn, see {@see removeListEntry()} */
    public const LIST_ENTRY_TICKS = 20;

    private readonly int $actorId;

    private ?UuidInterface $uuid = null;

    /** @var list<Item> head, chest, legs, feet, main hand, off hand: what the viewer has been sent */
    private array $sent = [];

    public function __construct(
        private readonly Player $viewer,
        private readonly Skin $skin
    ){
        $this->actorId = Entity::nextRuntimeId();
    }

    public function getActorId() : int{
        return $this->actorId;
    }

    /** @param list<Item> $equipment head, chest, legs, feet, main hand, off hand */
    public function spawn(array $equipment) : void{
        $session = $this->viewer->getNetworkSession();
        $uuid = Uuid::uuid4();
        $session->sendDataPacket(PlayerListPacket::create([
            PlayerListEntry::createAdditionEntry($uuid, $this->actorId, "", $session->getTypeConverter()->getSkinAdapter()->toSkinData($this->skin))
        ]));

        $metadata = new EntityMetadataCollection();
        $metadata->setGenericFlag(EntityMetadataFlags::IMMOBILE, true);
        $metadata->setGenericFlag(EntityMetadataFlags::SILENT, true);
        $metadata->setGenericFlag(EntityMetadataFlags::CAN_SHOW_NAMETAG, false);
        $metadata->setGenericFlag(EntityMetadataFlags::ALWAYS_SHOW_NAMETAG, false);
        $metadata->setString(EntityMetadataProperties::NAMETAG, "");
        $metadata->setFloat(EntityMetadataProperties::SCALE, 1.0);
        $metadata->setFloat(EntityMetadataProperties::BOUNDING_BOX_WIDTH, 0.0);
        $metadata->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, 0.0);

        $session->sendDataPacket(AddPlayerPacket::create(
            $uuid,
            "",
            $this->actorId,
            "",
            $this->position(),
            null,
            0.0,
            0.0,
            0.0,
            new ItemStackWrapper(0, ItemStack::null()),
            GameMode::SURVIVAL,
            $metadata->getAll(),
            new PropertySyncData([], []),
            new AbilitiesData(CommandPermissions::NORMAL, PlayerPermissions::VISITOR, $this->actorId, [
                new AbilitiesLayer(AbilitiesLayer::LAYER_BASE, array_fill(0, AbilitiesLayer::NUMBER_OF_ABILITIES, false), 0.0, 0.0, 0.0)
            ]),
            [],
            null,
            "",
            DeviceOS::UNKNOWN
        ));
        $this->update($equipment);
        $this->uuid = $uuid;
    }

    /**
     * Removes the puppet from the viewer's player list. Not done right after the spawn: the client loads the skin from
     * the list entry, and removing it in the same batch left the model with the default skin about half the time.
     */
    public function removeListEntry() : void{
        if($this->uuid !== null && $this->viewer->isConnected()){
            $this->viewer->getNetworkSession()->sendDataPacket(PlayerListPacket::create([PlayerListEntry::createRemovalEntry($this->uuid)]));
        }
        $this->uuid = null;
    }

    /**
     * Sends the equipment that changed since the last call.
     *
     * @param list<Item> $equipment head, chest, legs, feet, main hand, off hand
     */
    public function update(array $equipment) : void{
        $changed = fn(int $i) : bool => !isset($this->sent[$i]) || !$this->sent[$i]->equalsExact($equipment[$i]);
        $session = $this->viewer->getNetworkSession();
        $converter = $session->getTypeConverter();
        $wrap = static fn(Item $item) : ItemStackWrapper => ItemStackWrapper::legacy($converter->coreItemStackToNet($item));

        if($changed(0) || $changed(1) || $changed(2) || $changed(3)){
            $session->sendDataPacket(MobArmorEquipmentPacket::create(
                $this->actorId,
                $wrap($equipment[0]),
                $wrap($equipment[1]),
                $wrap($equipment[2]),
                $wrap($equipment[3]),
                new ItemStackWrapper(0, ItemStack::null())
            ));
        }
        if($changed(4)){
            $session->sendDataPacket(MobEquipmentPacket::create($this->actorId, $wrap($equipment[4]), 0, 0, ContainerIds::INVENTORY));
        }
        if($changed(5)){
            $session->sendDataPacket(MobEquipmentPacket::create($this->actorId, $wrap($equipment[5]), 0, 0, ContainerIds::OFFHAND));
        }
        $this->sent = $equipment;
    }

    public function despawn() : void{
        $this->removeListEntry();
        if($this->viewer->isConnected()){
            $this->viewer->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($this->actorId));
        }
    }

    private function position() : Vector3{
        return $this->viewer->getPosition()->subtract(0, self::DEPTH, 0);
    }
}

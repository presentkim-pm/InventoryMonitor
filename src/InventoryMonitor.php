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

use blugin\inventorymonitor\lang\PluginLang;
use kim\present\libcontainer\LibContainer;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\PermissionManager;
use pocketmine\permission\PermissionParser;
use pocketmine\permission\PermissionParserException;
use pocketmine\plugin\PluginBase;
use function array_filter;
use function array_values;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_bool;
use function is_string;
use function strtolower;

final class InventoryMonitor extends PluginBase implements Listener{
    public const PERMISSION_MAIN = "inventorymonitor.cmd";

    private static ?self $instance = null;

    public static function getInstance() : self{
        return self::$instance ?? throw new \LogicException("InventoryMonitor is not loaded");
    }

    private PluginLang $language;

    protected function onLoad() : void{
        self::$instance = $this;
    }

    protected function onEnable() : void{
        LibContainer::register($this);

        //Save default resources
        $this->saveResource("lang/eng/lang.ini", false);
        $this->saveResource("lang/kor/lang.ini", false);

        //Load config file
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $config = $this->getConfig();

        //Load language file
        $selected = $config->getNested("settings.language", PluginLang::FALLBACK_LANGUAGE);
        $this->language = new PluginLang(
            $this->getDataFolder(),
            $this->getResourceFolder(),
            is_string($selected) ? $selected : PluginLang::FALLBACK_LANGUAGE,
            $this->getLogger()
        );
        $this->getLogger()->info($this->language->translate("language.selected", [$this->language->getName(), $this->language->getLang()]));

        //Load permission's default value from config
        $default = $config->getNested("command.permission");
        if($default !== null){
            $this->applyPermissionDefault($default);
        }

        //Register main command
        $name = $config->getNested("command.name", "inventorymonitor");
        $aliases = $config->getNested("command.aliases", []);
        $command = new InventoryMonitorCommand(
            $this,
            is_string($name) && $name !== "" ? $name : "inventorymonitor",
            $this->language->translate("commands.inventorymonitor.description"),
            $this->language->translate("commands.inventorymonitor.usage", [$this->language->translate("commands.inventorymonitor.parameter.player")]),
            is_array($aliases) ? array_values(array_filter($aliases, is_string(...))) : []
        );
        $command->setPermission(self::PERMISSION_MAIN);
        $this->getServer()->getCommandMap()->register($this->getName(), $command);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    private function applyPermissionDefault(mixed $value) : void{
        if(!is_string($value) && !is_bool($value)){
            return;
        }
        try{
            $default = PermissionParser::defaultFromString($value);
        }catch(PermissionParserException $e){
            $this->getLogger()->warning("Invalid command.permission in config.yml: " . $e->getMessage());
            return;
        }

        $manager = PermissionManager::getInstance();
        $everyone = $manager->getPermission(DefaultPermissions::ROOT_USER);
        $operator = $manager->getPermission(DefaultPermissions::ROOT_OPERATOR);
        if($everyone === null || $operator === null){
            return;
        }
        $everyone->removeChild(self::PERMISSION_MAIN);
        $operator->removeChild(self::PERMISSION_MAIN);
        switch($default){
            case PermissionParser::DEFAULT_TRUE:
                $everyone->addChild(self::PERMISSION_MAIN, true);
                break;
            case PermissionParser::DEFAULT_OP:
                $operator->addChild(self::PERMISSION_MAIN, true);
                break;
            case PermissionParser::DEFAULT_NOT_OP:
                $everyone->addChild(self::PERMISSION_MAIN, true);
                $operator->addChild(self::PERMISSION_MAIN, false);
                break;
        }
    }

    protected function onDisable() : void{
        foreach(MonitorInstance::all() as $monitor){
            $monitor->closeAll();
        }
    }

    /**
     * The player data is loaded after the pre-login event, so the offline data being edited has to be saved before.
     *
     * @priority MONITOR
     */
    public function onPlayerPreLoginEvent(PlayerPreLoginEvent $event) : void{
        MonitorInstance::get($event->getPlayerInfo()->getUsername())?->closeAll();
    }

    /** @priority MONITOR */
    public function onPlayerQuitEvent(PlayerQuitEvent $event) : void{
        MonitorInstance::get($event->getPlayer()->getName())?->closeAll();
    }

    public function getLanguage() : PluginLang{
        return $this->language;
    }

    /**
     * Copies the config of the server language (or the fallback language) on first start
     */
    public function saveDefaultConfig() : bool{
        $configFile = $this->getDataFolder() . "config.yml";
        if(file_exists($configFile)){
            return false;
        }

        $lang = strtolower($this->getServer()->getLanguage()->getLang());
        $source = $this->getResourcePath("lang/$lang/config.yml");
        if(!file_exists($source)){
            $source = $this->getResourcePath("lang/" . PluginLang::FALLBACK_LANGUAGE . "/config.yml");
        }
        $contents = file_get_contents($source);
        return $contents !== false && file_put_contents($configFile, $contents) !== false;
    }
}

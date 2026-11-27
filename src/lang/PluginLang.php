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

namespace blugin\inventorymonitor\lang;

use function array_map;
use function file_exists;
use function file_get_contents;
use function is_array;
use function parse_ini_string;
use function preg_match;
use function str_replace;
use function stripcslashes;
use function strtolower;

/**
 * Simple `lang.ini` based translator.
 *
 * The selected language is read from `<data folder>/lang/<lang>/lang.ini` (so server owners can edit it), missing keys
 * fall back to the bundled English file.
 */
final class PluginLang{
    public const FALLBACK_LANGUAGE = "eng";

    private readonly string $langName;

    /** @var array<string, string> */
    private array $lang = [];

    /** @var array<string, string> */
    private array $fallbackLang = [];

    /**
     * @param string $dataFolder     Plugin data folder (with trailing slash)
     * @param string $resourceFolder Bundled resource folder (with trailing slash)
     */
    public function __construct(string $dataFolder, string $resourceFolder, string $lang, private readonly \Logger $logger){
        $this->langName = preg_match('/^[a-z]{3}$/', $lang = strtolower($lang)) === 1 ? $lang : self::FALLBACK_LANGUAGE;

        $fallbackFile = $resourceFolder . "lang/" . self::FALLBACK_LANGUAGE . "/lang.ini";
        $fallback = self::parse($fallbackFile);
        if($fallback === null){
            $this->logger->error("Missing fallback language file");
        }else{
            $this->fallbackLang = $fallback;
        }

        $parsed = self::parse($dataFolder . "lang/{$this->langName}/lang.ini") ?? self::parse($resourceFolder . "lang/{$this->langName}/lang.ini");
        if($parsed === null){
            $this->logger->error("Missing required language file ({$this->langName})");
        }else{
            $this->lang = $parsed;
        }
    }

    /** @return array<string, string>|null */
    private static function parse(string $file) : ?array{
        if(!file_exists($file) || ($contents = file_get_contents($file)) === false){
            return null;
        }
        $parsed = parse_ini_string($contents, false, INI_SCANNER_RAW);
        return is_array($parsed) ? array_map(stripcslashes(...), $parsed) : null;
    }

    /** @param array<int, string|int> $params */
    public function translate(string $id, array $params = []) : string{
        $str = $this->lang[$id] ?? $this->fallbackLang[$id] ?? $id;
        foreach($params as $i => $param){
            $str = str_replace("{%$i}", (string) $param, $str);
        }
        return $str;
    }

    public function getName() : string{
        return $this->translate("language.name");
    }

    public function getLang() : string{
        return $this->langName;
    }
}

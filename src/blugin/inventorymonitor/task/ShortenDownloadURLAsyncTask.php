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

namespace blugin\inventorymonitor\task;

use blugin\inventorymonitor\InventoryMonitor;
use pocketmine\scheduler\AsyncTask;

class ShortenDownloadURLAsyncTask extends AsyncTask{
    private const URL = "https://git.io";

    /** @var string|null File-name and Download-url of latest release */
    private $fileName, $downloadURL;

    /** @var string|null Short url of latest release download */
    private $shortURL = null;

    /**
     * ShortenDownloadURLAsyncTask constructor.
     *
     * @param string $fileName
     * @param string $downloadURL
     */
    public function __construct(string $fileName, string $downloadURL){
        $this->fileName = $fileName;
        $this->downloadURL = $downloadURL;
    }

    /**
     * Actions to execute when run
     *
     * Shorten download url of latest release
     * Get shortened url and store that to $shortURL
     */
    public function onRun() : void{
        try{
            curl_setopt_array($curlHandle = curl_init(), [
                CURLOPT_URL => self::URL,
                CURLOPT_POSTFIELDS => [
                    "code" => $this->fileName,
                    "url" => $this->downloadURL
                ],
                CURLOPT_HEADER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            foreach(explode("\n", curl_exec($curlHandle)) as $key => $line){
                if(strpos($line, "Location: ") === 0){ //starts with "Location: "
                    $this->shortURL = substr($line, strlen("Location: "));
                }
            }
            curl_close($curlHandle);
        }catch(\Exception $exception){
        }
    }

    /**
     * Actions to execute when completed (on main thread)
     * Implement this if you want to handle the data in your AsyncTask after it has been processed
     */
    public function onCompletion() : void{
        if($this->shortURL !== null){
            $plugin = InventoryMonitor::getInstance();
            $plugin->getLogger()->warning("latest release link : {$this->shortURL}");
        }
    }
}

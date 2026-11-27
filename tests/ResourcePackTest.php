<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\tests;

use Ahc\Json\Comment;
use blugin\inventorymonitor\MonitorSlots;
use PHPUnit\Framework\TestCase;

/** The bundled resource pack has to agree with the plugin and with the slot contract */
final class ResourcePackTest extends TestCase{
    private string $pack;

    protected function setUp() : void{
        $this->pack = dirname(__DIR__) . '/resources/pack/';
    }

    /** @return array<string, mixed> */
    private function json(string $file) : array{
        $decoded = (new Comment())->decode((string) file_get_contents($this->pack . $file), true);
        self::assertIsArray($decoded, $file);
        return $decoded;
    }

    private static function pluginVersion() : array{
        preg_match('/^version:\s*(\d+)\.(\d+)\.(\d+)\s*$/m', (string) file_get_contents(dirname(__DIR__) . '/plugin.yml'), $m);
        return [(int) $m[1], (int) $m[2], (int) $m[3]];
    }

    public function testManifest() : void{
        $manifest = $this->json('manifest.json');
        self::assertSame(2, $manifest['format_version']);
        $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
        self::assertMatchesRegularExpression($uuid, $manifest['header']['uuid']);
        self::assertMatchesRegularExpression($uuid, $manifest['modules'][0]['uuid']);
        self::assertNotSame($manifest['header']['uuid'], $manifest['modules'][0]['uuid']);
        self::assertSame('resources', $manifest['modules'][0]['type']);

        //the pack version follows the plugin version
        self::assertSame(self::pluginVersion(), $manifest['header']['version']);
        self::assertSame(self::pluginVersion(), $manifest['modules'][0]['version']);
    }

    public function testEverySlotHasACell() : void{
        $ui = file_get_contents($this->pack . 'ui/inventorymonitor.json');
        self::assertIsString($ui);
        preg_match_all('/"collection_index":\s*(\d+)/', $ui, $matches);
        $indexes = array_map('intval', $matches[1]);
        sort($indexes);

        //every mirrored slot has a cell, the data slot has the label, the reserved slots have nothing
        $expected = [];
        for($slot = 0; $slot < MonitorSlots::SIZE; ++$slot){
            if(MonitorSlots::sourceOf($slot) !== null || $slot === MonitorSlots::DATA){
                $expected[] = $slot;
            }
        }
        self::assertSame($expected, $indexes);
    }

    public function testScreenKeyMatchesThePlugin() : void{
        $ui = $this->json('ui/inventorymonitor.json');
        self::assertStringContainsString(MonitorSlots::SCREEN_KEY, $ui['overlay']['controls'][0]['host']['ignored']);
    }

    public function testUiFilesParse() : void{
        foreach(['ui/_ui_defs.json', 'ui/inventorymonitor.json', 'ui/chest_screen.json', 'ui/pocket_containers.json', 'texts/languages.json'] as $file){
            self::assertNotEmpty($this->json($file), $file);
        }
        self::assertSame(['ui/inventorymonitor.json'], $this->json('ui/_ui_defs.json')['ui_defs']);
    }

    public function testEveryLanguageHasTheSameKeys() : void{
        $keys = [];
        foreach($this->json('texts/languages.json') as $lang){
            $file = $this->pack . "texts/$lang.lang";
            self::assertFileExists($file);
            preg_match_all('/^([^=#\s][^=]*)=/m', (string) file_get_contents($file), $m);
            sort($m[1]);
            $keys[$lang] = $m[1];
        }
        self::assertCount(1, array_unique(array_map('serialize', $keys)), "the .lang files differ in their keys");
    }

    public function testLangFilesOfThePluginHaveTheSameKeys() : void{
        $load = static function(string $lang) : array{
            preg_match_all('/^([a-z][^=\s]*)\s*=/mi', (string) file_get_contents(dirname(__DIR__) . "/resources/lang/$lang/lang.ini"), $m);
            sort($m[1]);
            return $m[1];
        };
        self::assertSame($load('eng'), $load('kor'));
    }

    public function testPackCanBeArchived() : void{
        if(!class_exists(\ZipArchive::class)){
            self::markTestSkipped("ext-zip is not available");
        }
        $zip = sys_get_temp_dir() . '/inventorymonitor-pack-' . bin2hex(random_bytes(4)) . '.zip';
        try{
            \kim\present\register\resourcepack\ResourcePackRegister::archiveDirectory($this->pack, $zip);
            $archive = new \ZipArchive();
            self::assertTrue($archive->open($zip));
            self::assertNotFalse($archive->locateName('manifest.json'));
            self::assertNotFalse($archive->locateName('ui/inventorymonitor.json'));
            $archive->close();
        }finally{
            @unlink($zip);
        }
    }
}

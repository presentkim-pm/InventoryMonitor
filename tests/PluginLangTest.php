<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\tests;

use blugin\inventorymonitor\lang\PluginLang;
use PHPUnit\Framework\TestCase;

final class PluginLangTest extends TestCase{
    private string $resources;
    private string $data;

    protected function setUp() : void{
        $this->resources = dirname(__DIR__) . '/resources/';
        $this->data = sys_get_temp_dir() . '/inventorymonitor-test-' . bin2hex(random_bytes(4)) . '/';
    }

    private function logger() : \Logger{
        return new \PrefixedLogger(new \SimpleLogger(), 'test');
    }

    public function testBundledLanguagesLoad() : void{
        $eng = new PluginLang($this->data, $this->resources, 'eng', $this->logger());
        self::assertSame('English', $eng->getName());
        self::assertSame("x's Inv", $eng->translate('chest.name.main', ['x']));

        $kor = new PluginLang($this->data, $this->resources, 'KOR', $this->logger());
        self::assertSame('kor', $kor->getLang());
        self::assertSame('한국어', $kor->getName());
    }

    public function testParametersAndUnknownKeys() : void{
        $lang = new PluginLang($this->data, $this->resources, 'eng', $this->logger());
        self::assertSame('Selected English (eng) as the base language', $lang->translate('language.selected', ['English', 'eng']));
        self::assertSame('no.such.key', $lang->translate('no.such.key'));
    }

    public function testUnknownLanguageFallsBackToEnglish() : void{
        $lang = new PluginLang($this->data, $this->resources, 'xx/../..', $this->logger());
        self::assertSame('eng', $lang->getLang());
        self::assertSame('English', $lang->getName());
    }
}

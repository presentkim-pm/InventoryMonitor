<?php

/**
 * Generates resources/pack/ui/inventorymonitor.json (the 72 cells are tedious to write by hand).
 * Change the layout constants below, run `php tools/generate-ui.php`, and commit the result.
 *
 * The slot numbers are the contract of src/MonitorSlots.php.
 *
 * Two layouts are generated from the same rows:
 *   - wide (classic UI): the ender chest column on the right of the inventory column, above the vanilla inventory
 *   - tall (pocket UI): the ender chest below the inventory, in the vanilla container half of the screen (240 GUI px
 *     wide on a 480 px wide GUI, so the wide layout does not fit there)
 */

declare(strict_types=1);

const CELL = 18;
const PAD = 4;              // space between the frame and the cells
const GAP = 8;              // space between the frame and the middle of the screen (the vanilla inventory label)
const COLUMN = 9 * CELL;    // one inventory column, 162
const HEADER_Y = 0;         // name label (10 high)
const EQUIP_Y = 12;         // armor, offhand, cursor
const MAIN_Y = 32;          // 3 rows of the main inventory
const HOTBAR_Y = 90;        // 4 px below the main inventory
const OFFHAND_X = 82;       // after the four armor cells (72) and a gap
const CURSOR_X = 144;       // the last cell of the inventory column

const WIDE_ENDER_X = COLUMN + 8;
const WIDE_ENDER_Y = MAIN_Y;
const WIDE_WIDTH = WIDE_ENDER_X + COLUMN;   // 332
const WIDE_HEIGHT = HOTBAR_Y + CELL;        // 108

const TALL_ENDER_X = 0;
const TALL_ENDER_Y = HOTBAR_Y + CELL + 14;  // below the hotbar and the "Ender Chest" caption
const TALL_WIDTH = COLUMN;                  // 162
const TALL_HEIGHT = TALL_ENDER_Y + 3 * CELL; // 176

const ARMOR_SILHOUETTES = ["helmet", "chestplate", "leggings", "boots"];

/** @return list<array{int, int, int, ?string}> [collection_index, x, y, silhouette control or null] */
function cells(int $enderX, int $enderY) : array{
    $cells = [];
    for($i = 0; $i < 9; ++$i){
        $cells[] = [$i, $i * CELL, HOTBAR_Y, null];                                  // hotbar 0..8
    }
    for($i = 0; $i < 27; ++$i){
        $cells[] = [9 + $i, ($i % 9) * CELL, MAIN_Y + intdiv($i, 9) * CELL, null];   // main inventory 9..35
    }
    for($i = 0; $i < 4; ++$i){
        $cells[] = [36 + $i, $i * CELL, EQUIP_Y, ARMOR_SILHOUETTES[$i]];             // armor 36..39
    }
    $cells[] = [40, OFFHAND_X, EQUIP_Y, "shield"];                                   // offhand
    $cells[] = [41, CURSOR_X, EQUIP_Y, null];                                        // cursor (read-only)
    for($i = 0; $i < 27; ++$i){
        $cells[] = [45 + $i, $enderX + ($i % 9) * CELL, $enderY + intdiv($i, 9) * CELL, null];  // ender chest 45..71
    }
    return $cells;
}

function cellLines(int $enderX, int $enderY) : string{
    $lines = [];
    foreach(cells($enderX, $enderY) as [$index, $x, $y, $silhouette]){
        $overlay = $silhouette === null ? '' : sprintf(', "$overlay": "inventorymonitor.%s"', $silhouette);
        $lines[] = sprintf('      { "cell_%d@inventorymonitor.cell": { "$index": %d, "offset": [ %d, %d ]%s } }', $index, $index, $x, $y, $overlay);
    }
    return implode(",\n", $lines);
}

$template = file_get_contents(__DIR__ . '/inventorymonitor.json.tpl');
file_put_contents(
    dirname(__DIR__) . '/resources/pack/ui/inventorymonitor.json',
    strtr($template, [
        '{{PAD}}' => (string) PAD,
        '{{GAP}}' => (string) GAP,
        '{{INVENTORY_FRAME_H}}' => (string) (83 + GAP + 1),
        '{{HEADER_Y}}' => (string) HEADER_Y,
        '{{EQUIP_Y}}' => (string) EQUIP_Y,
        '{{MAIN_Y}}' => (string) MAIN_Y,
        '{{HOTBAR_Y}}' => (string) HOTBAR_Y,
        '{{CURSOR_X}}' => (string) CURSOR_X,
        '{{CAPTION_X}}' => (string) (CURSOR_X - 44),    // right-aligned, ends 4 px before the cursor cell
        '{{CAPTION_Y}}' => (string) (EQUIP_Y + 4),     // vertically centred on the cell
        '{{WIDE_FRAME_W}}' => (string) (WIDE_WIDTH + 2 * PAD),
        '{{WIDE_FRAME_H}}' => (string) (WIDE_HEIGHT + 2 * PAD),
        '{{WIDE_WIDTH}}' => (string) WIDE_WIDTH,
        '{{WIDE_HEIGHT}}' => (string) WIDE_HEIGHT,
        '{{WIDE_NAME_W}}' => (string) (WIDE_ENDER_X - 4),
        '{{WIDE_ENDER_X}}' => (string) WIDE_ENDER_X,
        '{{WIDE_ENDER_CAPTION_Y}}' => (string) HEADER_Y,
        '{{WIDE_CELLS}}' => cellLines(WIDE_ENDER_X, WIDE_ENDER_Y),
        '{{TALL_WIDTH}}' => (string) TALL_WIDTH,
        '{{TALL_HEIGHT}}' => (string) TALL_HEIGHT,
        '{{TALL_NAME_W}}' => (string) TALL_WIDTH,
        '{{TALL_ENDER_X}}' => (string) TALL_ENDER_X,
        '{{TALL_ENDER_CAPTION_Y}}' => (string) (TALL_ENDER_Y - 11),
        '{{TALL_CELLS}}' => cellLines(TALL_ENDER_X, TALL_ENDER_Y),
    ])
);
echo "Generated ", 2 * count(cells(0, 0)), " cells (wide and tall)\n";

<?php

/**
 * Generates resources/pack/ui/inventorymonitor.json (the 72 cells are tedious to write by hand).
 * Change the layout constants below, run `php tools/generate-ui.php`, and commit the result.
 *
 * The slot numbers are the contract of src/MonitorSlots.php.
 */

declare(strict_types=1);

const CELL = 18;
const PAD = 6;              // space between the frame and the cells
const ENDER_X = 170;        // the ender chest column starts here (the inventory column is 9 cells = 162 wide)
const HEADER_Y = 0;         // section labels (10 high)
const EQUIP_Y = 12;         // armor, offhand, cursor
const MAIN_Y = 32;          // 3 rows of the main inventory, and of the ender chest
const HOTBAR_Y = 90;        // 4 px below the main inventory
const OFFHAND_X = 82;       // after the four armor cells (72) and a gap
const CURSOR_X = 144;       // the last cell of the inventory column
const WIDTH = ENDER_X + 9 * CELL;   // 332
const HEIGHT = HOTBAR_Y + CELL;     // 108

/** @return list<array{int, int, int}> [collection_index, x, y] */
function cells() : array{
    $cells = [];
    for($i = 0; $i < 9; ++$i){
        $cells[] = [$i, $i * CELL, HOTBAR_Y];                                  // hotbar 0..8
    }
    for($i = 0; $i < 27; ++$i){
        $cells[] = [9 + $i, ($i % 9) * CELL, MAIN_Y + intdiv($i, 9) * CELL];   // main inventory 9..35
    }
    for($i = 0; $i < 4; ++$i){
        $cells[] = [36 + $i, $i * CELL, EQUIP_Y];                              // armor 36..39
    }
    $cells[] = [40, OFFHAND_X, EQUIP_Y];                                       // offhand
    $cells[] = [41, CURSOR_X, EQUIP_Y];                                        // cursor (read-only)
    for($i = 0; $i < 27; ++$i){
        $cells[] = [45 + $i, ENDER_X + ($i % 9) * CELL, MAIN_Y + intdiv($i, 9) * CELL];  // ender chest 45..71
    }
    return $cells;
}

$cellLines = [];
foreach(cells() as [$index, $x, $y]){
    $cellLines[] = sprintf('                  { "cell_%d@inventorymonitor.cell": { "collection_index": %d, "offset": [ %d, %d ] } }', $index, $index, $x, $y);
}

$frameW = WIDTH + 2 * PAD;
$frameH = HEIGHT + 2 * PAD;
$cells = implode(",\n", $cellLines);

$template = file_get_contents(__DIR__ . '/inventorymonitor.json.tpl');
file_put_contents(
    dirname(__DIR__) . '/resources/pack/ui/inventorymonitor.json',
    strtr($template, [
        '{{FRAME_W}}' => (string) $frameW,
        '{{FRAME_H}}' => (string) $frameH,
        '{{WIDTH}}' => (string) WIDTH,
        '{{HEIGHT}}' => (string) HEIGHT,
        '{{PAD}}' => (string) PAD,
        '{{ENDER_X}}' => (string) ENDER_X,
        '{{HEADER_Y}}' => (string) HEADER_Y,
        '{{EQUIP_Y}}' => (string) EQUIP_Y,
        '{{CURSOR_X}}' => (string) CURSOR_X,
        '{{CAPTION_X}}' => (string) (CURSOR_X - 44),    // right-aligned, ends 4 px before the cursor cell
        '{{CAPTION_Y}}' => (string) (EQUIP_Y + 4),     // vertically centred on the cell
        '{{CELLS}}' => $cells,
    ])
);
echo "Generated ", count(cells()), " cells\n";

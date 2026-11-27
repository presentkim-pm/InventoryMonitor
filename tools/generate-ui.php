<?php

/**
 * Generates resources/pack/ui/inventorymonitor.json (the 72 cells are tedious to write by hand).
 * Change the layout constants below, run `php tools/generate-ui.php`, and commit the result.
 *
 * The slot numbers are the contract of src/MonitorSlots.php. The target panel copies the right panel of the vanilla
 * inventory screen: the armor column, the player model with the offhand cell beside it, then the items of the selected
 * tab (inventory: 3 rows and the hotbar, ender chest: 3 rows).
 */

declare(strict_types=1);

const CELL = 18;
const PAD = 7;                      // space between a frame and its content, as in the vanilla chest screen
const GAP = 4;                      // space between the target panel and the viewer's inventory (classic UI)
const CONTENT_W = 9 * CELL;         // 162
const TAB_W = 81;                   // two tabs fill the content width
const EQUIP_Y = 18;                 // armor column and model, below the tabs
const MODEL_X = CELL + 2;           // the model box (52 x 70) right of the armor column, as in the vanilla screen
const MODEL_SIZE = 32;              // the renderer control; the model is about twice as tall, clipped by the box
const MODEL_Y = 4;                  // the renderer inside the box (the player's head is at the top of the control)
const OFFHAND_X = MODEL_X + 52 + 4; // right of the model box
const OFFHAND_Y = EQUIP_Y + 3 * CELL; // the bottom row of the armor column
const CURSOR_X = CONTENT_W - CELL;  // the last column
const CURSOR_Y = EQUIP_Y + 2 * CELL;
const ITEMS_Y = EQUIP_Y + 4 * CELL + 4; // below the armor column
const PAGE_H = 3 * CELL + 4 + CELL; // 3 rows, a gap and the hotbar
const CONTENT_H = ITEMS_Y + PAGE_H;
const OWN_H = 69 + CELL;            // caption, 3 rows, hotbar (see own_inventory)

const ARMOR_SILHOUETTES = ["helmet", "chestplate", "leggings", "boots"];

function cellLine(int $index, int $x, int $y, ?string $silhouette, string $indent) : string{
    $overlay = $silhouette === null ? '' : sprintf(', "$overlay": "inventorymonitor.%s"', $silhouette);
    return sprintf('%s{ "cell_%d@inventorymonitor.cell": { "$index": %d, "offset": [ %d, %d ]%s } }', $indent, $index, $index, $x, $y, $overlay);
}

/** @return list<string> armor 36..39, offhand 40, cursor 41 */
function equipCells() : array{
    $lines = [];
    for($i = 0; $i < 4; ++$i){
        $lines[] = cellLine(36 + $i, 0, EQUIP_Y + $i * CELL, ARMOR_SILHOUETTES[$i], '      ');
    }
    $lines[] = cellLine(40, OFFHAND_X, OFFHAND_Y, "shield", '      ');
    $lines[] = cellLine(41, CURSOR_X, CURSOR_Y, null, '      ');
    return $lines;
}

/** @return list<string> main inventory 9..35 in 3 rows, hotbar 0..8 below */
function inventoryCells() : array{
    $lines = [];
    for($i = 0; $i < 27; ++$i){
        $lines[] = cellLine(9 + $i, ($i % 9) * CELL, intdiv($i, 9) * CELL, null, '            ');
    }
    for($i = 0; $i < 9; ++$i){
        $lines[] = cellLine($i, $i * CELL, 3 * CELL + 4, null, '            ');
    }
    for($i = 0; $i < 9; ++$i){
        $lines[] = sprintf('            { "held_%d@inventorymonitor.held_frame": { "$held_key": "h%d", "offset": [ %d, %d ] } }', $i, $i, $i * CELL - 2, 3 * CELL + 4 - 2);
    }
    return $lines;
}

/** @return list<string> ender chest 45..71 in 3 rows */
function enderCells() : array{
    $lines = [];
    for($i = 0; $i < 27; ++$i){
        $lines[] = cellLine(45 + $i, ($i % 9) * CELL, intdiv($i, 9) * CELL, null, '            ');
    }
    return $lines;
}

$frameW = CONTENT_W + 2 * PAD;
$template = file_get_contents(__DIR__ . '/inventorymonitor.json.tpl');
$cells = [equipCells(), inventoryCells(), enderCells()];
file_put_contents(
    dirname(__DIR__) . '/resources/pack/ui/inventorymonitor.json',
    strtr($template, [
        '{{PAD}}' => (string) PAD,
        '{{CONTENT_W}}' => (string) CONTENT_W,
        '{{CONTENT_H}}' => (string) CONTENT_H,
        '{{TAB_W}}' => (string) TAB_W,
        '{{EQUIP_Y}}' => (string) EQUIP_Y,
        '{{MODEL_X}}' => (string) MODEL_X,
        '{{MODEL_BOX_Y}}' => (string) (EQUIP_Y + 1),
        '{{MODEL_BORDER_X}}' => (string) (MODEL_X - 1),
        '{{MODEL_BORDER_Y}}' => (string) EQUIP_Y,
        '{{MODEL_SIZE}}' => (string) MODEL_SIZE,
        '{{MODEL_Y}}' => (string) MODEL_Y,
        '{{OFFHAND_X}}' => (string) OFFHAND_X,
        '{{NAME_Y}}' => (string) EQUIP_Y,
        '{{NAME_W}}' => (string) (CONTENT_W - OFFHAND_X),
        '{{CURSOR_CAPTION_X}}' => (string) (CURSOR_X - 44),
        '{{CURSOR_CAPTION_Y}}' => (string) (CURSOR_Y + 4),
        '{{ITEMS_Y}}' => (string) ITEMS_Y,
        '{{PAGE_H}}' => (string) PAGE_H,
        '{{OWN_H}}' => (string) OWN_H,
        '{{FRAME_W}}' => (string) $frameW,
        '{{FRAME_H}}' => (string) (CONTENT_H + 2 * PAD),
        '{{OWN_FRAME_H}}' => (string) (OWN_H + 2 * PAD),
        '{{CLASSIC_W}}' => (string) (2 * $frameW + GAP),
        '{{EQUIP_CELLS}}' => implode(",\n", $cells[0]),
        '{{INVENTORY_CELLS}}' => implode(",\n", $cells[1]),
        '{{ENDER_CELLS}}' => implode(",\n", $cells[2]),
    ])
);
echo "Generated ", array_sum(array_map('count', $cells)), " cells\n";

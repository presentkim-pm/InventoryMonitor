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
const GAP = 4;                      // space between the target frame and the viewer's inventory frame (classic UI)
const TITLE_H = 20;                 // the title row of the target frame
const CONTENT_W = 9 * CELL;         // 162
const TAB_W = 84;                   // two tabs and TABS_X fit on the target frame (CONTENT_W + 2 * PAD)
const TABS_X = 4;
const TAB_H = 22;                   // the tabs stand on the top edge of the window
const MODEL_X = CELL + 2;           // the model box (52 x 70) right of the armor column, as in the vanilla screen
const MODEL_SIZE = 32;              // the renderer control; the model is about twice as tall, clipped by the box
const MODEL_Y = 4;                  // the renderer inside the box (the player's head is at the top of the control)
const OFFHAND_X = MODEL_X + 52 + 4; // right of the model box
const OFFHAND_Y = 3 * CELL;         // the bottom row of the armor column
const CURSOR_X = CONTENT_W - CELL;  // the last column
const CURSOR_Y = 2 * CELL;
const ITEMS_Y = 4 * CELL + 4;       // below the armor column
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
        $lines[] = cellLine(36 + $i, 0, $i * CELL, ARMOR_SILHOUETTES[$i], '      ');
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

$template = file_get_contents(__DIR__ . '/inventorymonitor.json.tpl');
$cells = [equipCells(), inventoryCells(), enderCells()];
file_put_contents(
    dirname(__DIR__) . '/resources/pack/ui/inventorymonitor.json',
    strtr($template, [
        '{{PAD}}' => (string) PAD,
        '{{CONTENT_W}}' => (string) CONTENT_W,
        '{{CONTENT_H}}' => (string) CONTENT_H,
        '{{TAB_W}}' => (string) TAB_W,
        '{{TAB_H}}' => (string) TAB_H,
        '{{TABS_W}}' => (string) (2 * TAB_W),
        '{{TABS_X}}' => (string) TABS_X,
        '{{TABS_Y}}' => (string) -TAB_H,
        '{{MODEL_X}}' => (string) MODEL_X,
        '{{MODEL_BORDER_X}}' => (string) (MODEL_X - 1),
        '{{MODEL_SIZE}}' => (string) MODEL_SIZE,
        '{{MODEL_Y}}' => (string) MODEL_Y,
        '{{OFFHAND_X}}' => (string) OFFHAND_X,
        '{{CURSOR_X}}' => (string) CURSOR_X,
        '{{CURSOR_CAPTION_X}}' => (string) (CURSOR_X - 44),
        '{{CURSOR_CAPTION_Y}}' => (string) (CURSOR_Y + 4),
        '{{ITEMS_Y}}' => (string) ITEMS_Y,
        '{{PAGE_H}}' => (string) PAGE_H,
        '{{OWN_H}}' => (string) OWN_H,
        '{{TITLE_H}}' => (string) TITLE_H,
        '{{OWN_Y}}' => (string) (TITLE_H + CONTENT_H - OWN_H),
        '{{WINDOW_H}}' => (string) (TITLE_H + CONTENT_H + PAD),
        '{{WINDOW_Y}}' => (string) intdiv(TAB_H, 2),
        '{{PANEL_W}}' => (string) (CONTENT_W + 2 * PAD),
        '{{CLASSIC_W}}' => (string) (2 * (CONTENT_W + 2 * PAD) + GAP),
        '{{EQUIP_CELLS}}' => implode(",\n", $cells[0]),
        '{{INVENTORY_CELLS}}' => implode(",\n", $cells[1]),
        '{{ENDER_CELLS}}' => implode(",\n", $cells[2]),
    ])
);
echo "Generated ", array_sum(array_map('count', $cells)), " cells\n";

# Changelog

## Unreleased

### ⚠️ Breaking changes
- The whole inventory is shown on **one screen drawn by a resource pack** (JSON UI), instead of a 27-slot chest with
  five pages. The pack is bundled in the plugin, registered with
  [resourcepack-register](https://github.com/presentkim-pm/resourcepack-register) and required from players (the plugin
  sets the server's `force_resources`). Without the pack the client would only draw the first 27 of the 72 slots.
- The screen has 72 slots: hotbar, main inventory, armor, offhand, cursor (view only), a label with the name and the
  online / offline status of the player, and the ender chest. See the slot contract in the README.
- Requires [libcontainer](https://github.com/presentkim-pm/libcontainer) `0.2.0` (ranged `bind()`, resource-pack screens).
- Language keys: `chest.name.main` is renamed `monitor.title`; `chest.slot.*` and `chest.page.*` are removed;
  `monitor.label` and `monitor.status.online` / `monitor.status.offline` are added (missing keys fall back to the
  bundled English text, so copy the new keys into an edited `lang.ini`).

### 🔄 Changes
- The off-hand item of an offline player is shown and saved too.
- The pocket UI shows the screen in its container half, with the ender chest below the inventory; the classic UI keeps
  the ender chest beside it. Empty armor and offhand cells show the vanilla silhouettes.

## 5.0.0 (2026-11-27)

### ⚠️ Breaking changes
- The menu is a 27-slot chest with five pages instead of a 54-slot double chest with two pages. The vanilla chest
  screen shows at most 27 cells, and [libcontainer](https://github.com/presentkim-pm/libcontainer) (which replaces
  InvMenu) does not open double chests without a resource-pack screen. The bottom row holds the page buttons
  (previous, page indicator, next; they wrap around). Pages: inventory slots 1-18, inventory slots 19-36, armor
  (helmet, chestplate, leggings, boots) with the cursor item, ender chest slots 1-18 and 19-27.
- Language keys: `chest.name.ender` and `chest.slot.toggle.*` are removed; `chest.slot.previous`, `chest.slot.next`,
  `chest.slot.page` and `chest.page.*` are added (missing keys fall back to the bundled English text).

### 🔄 Changes
- The window is created with libcontainer instead of InvMenu (the InvMenu requirement and its inline composer package
  are removed). Armor slots still accept only the matching armor piece, and the monitor still mirrors the player in
  both directions in real time.

## 4.0.0 (2026-11-27)

The version for PocketMine-MP API 3 is kept on the
[`legacy/pmmp-5.44`](https://github.com/presentkim-pm/inventorymonitor/tree/legacy/pmmp-5.44) branch.

### ⚠️ Breaking changes
- Rewritten for [axolotl-pm/PocketMine-MP](https://github.com/axolotl-pm/PocketMine-MP) `5.51` (BedrockProtocol 64,
  Bedrock `1.26.60`) and **PHP 8.4+**.
- Distributed as a phar from GitHub Releases instead of Poggit.
- The virions `InvMenuPlus`, `SelfFactoryTrait`, `SingletonTrait`, `libcommand`, `libtranslator`, `Cert` and
  `PreventExtractTrait` are gone: the certification / encryption is dropped, and the plugin has its own `lang.ini`
  loader and a plain PocketMine `Command`. Only [muqsit/invmenu](https://github.com/Muqsit/InvMenu) is used (from
  Composer, bundled into the phar at release).
- `config.yml`: the command settings moved to `command.name`, `command.aliases` and `command.permission` (was
  `inventorymonitor.*` in `command/<lang>.yml`), and `settings.language` selects the language.
- The language files moved from `locale/<lang>.ini` to `lang/<lang>/lang.ini`. `commands.inventorymonitor.usage` was added.
- The classes were redesigned (`MonitorInstance`, `MonitorViewer`, `MonitorLayout`, ...); `OfflinePlayerInventory`
  is now a holder of the three offline `SimpleInventory`.

### 🔄 Changes
- The menu is synchronized with the monitored inventories with inventory listeners (both directions), instead of
  re-sending slots one tick after each `InventoryTransactionEvent`.
- The offline player data is read and written in the PocketMine-MP 5 layout (inventory from slot 9, armor from slot 100).
- `composer.json` added (used for development, testing and the release build); `plugin.yml` has `src-namespace-prefix`.

### 🐛 Fixes
- Armor slots of the menu accept only the matching armor piece (the server's armor slot rule), so you cannot put a
  stack of dirt on someone's head.
- A viewer opening the monitor twice, or a failed menu send, no longer leaves a stale monitor instance behind.
- The offline data is only written when something was changed.

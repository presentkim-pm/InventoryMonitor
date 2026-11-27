# Changelog

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

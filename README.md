
<!-- PROJECT BADGES -->
<div align="center">

![Version][version-badge]
[![Stars][stars-badge]][stars-url]
[![License][license-badge]][license-url]

</div>


<!-- PROJECT LOGO -->
<br />
<div align="center">
  <h3>InventoryMonitor</h3>
  <p align="center">
    Real-time responsive player inventory viewer.

[Contact to me][author-discord] · [Report a bug][issues-url] · [Request a feature][issues-url]

  </p>
</div>


<!-- ABOUT THE PROJECT -->

### Overview

- **What it does**
  - `/inventorymonitor <player>` opens a double chest that mirrors the inventory of the player, online or offline,
    and stays in sync with it in real time.
  - You can modify the inventory from the menu too: the changes are applied to the player (offline players' data is
    saved when the last viewer closes the menu).
- **Features**
  - Main page: inventory (36 slots), armor (helmet, chestplate, leggings, boots), the item on the cursor (view only).
  - Ender chest page, switched with the button in the bottom right corner.
  - Armor slots only accept the matching armor piece.
  - The command name, aliases and default permission are configurable.
  - Multi-language messages (`eng`, `kor`), editable in `plugins/InventoryMonitor/lang/<lang>/lang.ini`.

![demo](assets/screenshot/demo.gif?raw=true)

---

### Installation & Requirements

- **Server**
  - [axolotl-pm/PocketMine-MP](https://github.com/axolotl-pm/PocketMine-MP) `5.51` or higher (`plugin.yml` `api: [5.51.0]`).
  - PHP `8.4` or higher.
  - The [InvMenu](https://github.com/Muqsit/InvMenu) library is bundled into the plugin, nothing else is needed.
- **Installation**
  - Download `InventoryMonitor.phar` from [GitHub Releases][releases-url] and place it into your server's `plugins` directory.
  - Restart the server; the plugin will load automatically according to `plugin.yml`.
- **Old versions**
  - The version for PocketMine-MP API 3 (3.x) is kept on the
    [`legacy/pmmp-5.44`](https://github.com/presentkim-pm/inventorymonitor/tree/legacy/pmmp-5.44) branch.
- **Development**
  - `composer install` then `composer test` runs the PHPUnit tests.

---

### Command

| command                         | aliases                                | description                                     |
|---------------------------------|----------------------------------------|-------------------------------------------------|
| `/inventorymonitor <player>`    | `invmonitor`, `iv`, `인벤토리모니터`        | Monitor the inventory of the player (in game only) |

An online player may be given by the beginning of the name; an offline player needs the full name and saved data.

### Permission

| permission             | default | description  |
|------------------------|---------|--------------|
| `inventorymonitor.cmd` | OP      | main command |

### Configuration

`plugins/InventoryMonitor/config.yml` is created on the first start (in the server language if it is `eng` or `kor`).

| key                  | default                                         | description                                         |
|----------------------|-------------------------------------------------|-----------------------------------------------------|
| `settings.language`  | server language / `eng`                         | language of the messages (`eng`, `kor`)             |
| `command.name`       | `inventorymonitor`                              | name of the main command                            |
| `command.aliases`    | `["invmonitor", "iv", "인벤토리모니터"]`        | aliases of the main command                         |
| `command.permission` | `"op"`                                          | default of `inventorymonitor.cmd` (`true`, `false`, `op`, `notop`) |

---

### License

Distributed under the **LGPL-3.0 License**. See [LICENSE][license-url] for more information.

-----

[author-discord]: https://discordapp.com/users/345772340279508993

[version-badge]: https://img.shields.io/github/v/release/presentkim-pm/inventorymonitor?display_name=tag&style=for-the-badge&label=VERSION

[stars-badge]: https://img.shields.io/github/stars/presentkim-pm/inventorymonitor.svg?style=for-the-badge

[license-badge]: https://img.shields.io/github/license/presentkim-pm/inventorymonitor.svg?style=for-the-badge

[stars-url]: https://github.com/presentkim-pm/inventorymonitor/stargazers

[releases-url]: https://github.com/presentkim-pm/inventorymonitor/releases

[issues-url]: https://github.com/presentkim-pm/inventorymonitor/issues

[license-url]: https://github.com/presentkim-pm/inventorymonitor/blob/main/LICENSE

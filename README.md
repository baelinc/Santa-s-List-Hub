# Santa's List Hub - FPP Plugin

Displays your [Santa's List Hub](../santas-list-hub) Naughty/Nice list on two
pixel overlay zones, sized for *your* panels:

- **Top zone** — shows `NICE` (green) or `NAUGHTY` (red), color-coded
- **Bottom zone** — a panel grid that scrolls the names on the current list

Panel pixel size and how many panels make up each zone (e.g. 1 wide x 1 tall
for the label, 3 wide x 2 tall for the names grid) are set on the Settings
page, not hardcoded — this works for 32x16, 64x32, or any other panel size
and grid layout.

## Install

1. Copy this folder to `/home/fpp/media/plugins/` on your FPP device — **any
   folder name works**, the plugin detects its own location automatically
   (it no longer has to be named `fpp-plugin-santaslist` specifically).
2. Reboot, or run `scripts/fpp_install.sh` manually, and restart `fppd`.

## Set up

1. **Enter your panel spec** in Content Setup -> Santa's List Hub -> "Your panels":
   pixel size per panel, and how many panels wide/tall each zone is. The page
   computes the exact resolution each overlay model needs to be.
2. **Create two Models in FPP** (Displays -> Models) sized to match those
   computed resolutions — one for the top label row, one for the names grid.
3. **Get your API key** from Santa's List Hub: Show Settings -> Display Connections.
4. Enter your Hub URL and API key, test the connection, pick the two models you
   created, and hit **Save**. If a model's real size doesn't match your panel
   spec, the page warns you.

See the in-app Help page for more detail.

## How it works

- `cron/pull.php` runs on a schedule (1-10 min, set in Advanced settings) and
  pulls the latest names from the hub into a local cache.
- `bin/display-daemon.php` runs continuously in the background, decides which
  list (Nice/Naughty) should be on screen right now, and pushes it to the two
  overlay models via FPP's `/api/overlays/model/{model}/text` endpoint whenever
  it changes.
- Settings are stored at `/home/fpp/media/plugindata/fpp-plugin-santaslist-config.json`.
- Logs are at `/home/fpp/media/logs/fpp-plugin-santaslist.log`.

## Requirements

- FPP 9.0+ (uses the pixel overlay `/api/overlays/...` endpoints)
- `php-curl`

#!/bin/bash

# fpp-plugin-santaslist install script

. ${FPPDIR}/scripts/common

# Self-locate: this script lives in <plugin>/scripts/, so its parent is the
# real install directory, whatever the folder is actually named on disk
# (a git clone or "Download ZIP" won't necessarily be named fpp-plugin-santaslist).
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

mkdir -p /home/fpp/media/plugindata
chown fpp:fpp /home/fpp/media/plugindata

# Install the default cron.d schedule (every 2 minutes), pointed at wherever
# this plugin actually lives. The Settings page will rewrite this file if the
# refresh interval is changed later.
CRON_LINE="*/2 * * * * fpp /usr/bin/php ${PLUGIN_DIR}/cron/pull.php >/dev/null 2>&1"
echo "# Santa's List Hub - pulls fresh names from the hub on a schedule." > /tmp/santaslist-cron
echo "# Regenerated automatically when the refresh interval is changed in Settings." >> /tmp/santaslist-cron
echo "$CRON_LINE" >> /tmp/santaslist-cron
sudo cp /tmp/santaslist-cron /etc/cron.d/fpp-plugin-santaslist-cron
rm -f /tmp/santaslist-cron

chmod +x "${PLUGIN_DIR}/cron/pull.php"
chmod +x "${PLUGIN_DIR}/bin/display-daemon.php"

echo "Santa's List Hub plugin installed from ${PLUGIN_DIR}. Open Content Setup -> Santa's List Hub to configure it."

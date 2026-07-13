#!/bin/bash

# fpp-plugin-santaslist uninstall script

FPPDIR="${FPPDIR:-/opt/fpp}"
if [ -f "${FPPDIR}/scripts/common" ]; then
	. ${FPPDIR}/scripts/common
fi

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

php -r "require '${PLUGIN_DIR}/lib/SantasListPlugin.php'; \$p = new SantasListPlugin(); \$p->stopDaemon(); \$p->disableZones();" 2>/dev/null

sudo rm -f /etc/cron.d/fpp-plugin-santaslist-cron
rm -f /home/fpp/media/scripts/santaslist-enable.sh /home/fpp/media/scripts/santaslist-disable.sh

echo "Santa's List Hub plugin removed. Your settings are kept at /home/fpp/media/plugindata/ in case you reinstall."

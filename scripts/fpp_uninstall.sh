#!/bin/bash

# fpp-plugin-santaslist uninstall script

. ${FPPDIR}/scripts/common

php -r "require '/home/fpp/media/plugins/fpp-plugin-santaslist/lib/SantasListPlugin.php'; \$p = new SantasListPlugin(); \$p->stopDaemon(); \$p->disableZones();" 2>/dev/null

sudo rm -f /etc/cron.d/fpp-plugin-santaslist-cron

echo "Santa's List Hub plugin removed. Your settings are kept at /home/fpp/media/plugindata/ in case you reinstall."

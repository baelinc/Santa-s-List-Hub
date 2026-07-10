#!/bin/bash

# fpp-plugin-santaslist install script

. ${FPPDIR}/scripts/common

mkdir -p /home/fpp/media/plugindata
chown fpp:fpp /home/fpp/media/plugindata

# Install the default cron.d schedule (every 2 minutes). The Settings page
# will rewrite this file if the refresh interval is changed later.
sudo cp /home/fpp/media/plugins/fpp-plugin-santaslist/templates/santaslist-cronTemplate /etc/cron.d/fpp-plugin-santaslist-cron

chmod +x /home/fpp/media/plugins/fpp-plugin-santaslist/cron/pull.php
chmod +x /home/fpp/media/plugins/fpp-plugin-santaslist/bin/display-daemon.php

echo "Santa's List Hub plugin installed. Open Content Setup -> Santa's List Hub to configure it."

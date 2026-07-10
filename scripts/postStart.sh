#!/bin/sh

# fpp-plugin-santaslist postStart script
# fppd just (re)started, which clears overlay model state -- relaunch the
# display daemon so the two zones get pushed again.

php -r "
require '/home/fpp/media/plugins/fpp-plugin-santaslist/lib/SantasListPlugin.php';
\$p = new SantasListPlugin();
if (\$p->config['enabled']) {
	\$p->startDaemon();
}
" 2>/dev/null

echo "Santa's List Hub PostStart complete."

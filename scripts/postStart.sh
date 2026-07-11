#!/bin/sh

# fpp-plugin-santaslist postStart script
# fppd just (re)started, which clears overlay model state -- relaunch the
# display daemon so the two zones get pushed again.

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"

php -r "
require '${PLUGIN_DIR}/lib/SantasListPlugin.php';
\$p = new SantasListPlugin();
if (\$p->config['enabled']) {
	\$p->startDaemon();
}
" 2>/dev/null

echo "Santa's List Hub PostStart complete."

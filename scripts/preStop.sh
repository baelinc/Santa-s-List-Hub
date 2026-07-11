#!/bin/sh

# fpp-plugin-santaslist preStop script

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"

php -r "require '${PLUGIN_DIR}/lib/SantasListPlugin.php'; \$p = new SantasListPlugin(); \$p->stopDaemon();" 2>/dev/null

echo "Santa's List Hub PreStop complete."

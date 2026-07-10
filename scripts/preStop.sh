#!/bin/sh

# fpp-plugin-santaslist preStop script

php -r "require '/home/fpp/media/plugins/fpp-plugin-santaslist/lib/SantasListPlugin.php'; \$p = new SantasListPlugin(); \$p->stopDaemon();" 2>/dev/null

echo "Santa's List Hub PreStop complete."

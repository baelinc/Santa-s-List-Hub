#!/usr/bin/php
<?php
/**
 * Santa's List Hub - display daemon
 *
 * Runs continuously in the background. Cron (cron/pull.php) is responsible
 * for periodically fetching fresh names from the hub; this process is only
 * responsible for the *timing* of switching between NICE and NAUGHTY, which
 * needs finer granularity than cron's one-minute resolution can offer, and
 * for re-pushing to the overlay models whenever the list changes or FPP
 * restarts fppd (which clears overlay model state).
 */

require_once __DIR__ . '/../lib/SantasListPlugin.php';

$plugin = new SantasListPlugin();
$plugin->log('Display daemon starting.');

$lastPushedList = null;

while (true) {
	// Reload config each loop so changes saved from the settings page take
	// effect without needing to restart the daemon manually.
	$plugin->config = $plugin->loadConfig();

	if (!$plugin->config['enabled'] || (!$plugin->config['top_model'] && !$plugin->config['bottom_model'])) {
		sleep(5);
		continue;
	}

	$listType = $plugin->currentListType();

	if ($listType !== $lastPushedList) {
		$cache = $plugin->loadCache();
		$plugin->pushDisplay($listType, $cache);
		$lastPushedList = $listType;
	}

	sleep(2);
}

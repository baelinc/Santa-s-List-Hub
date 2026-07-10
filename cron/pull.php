#!/usr/bin/php
<?php
/**
 * Santa's List Hub - cron puller
 * Fetches the latest approved names from the hub and caches them locally
 * so the display daemon never has to make a network call on its tight
 * switching loop.
 */

require_once __DIR__ . '/../lib/SantasListPlugin.php';

$plugin = new SantasListPlugin();

if (!$plugin->config['enabled']) {
	exit(0);
}
if (!$plugin->config['hub_url'] || !$plugin->config['api_key']) {
	exit(0);
}

$plugin->pullNames();

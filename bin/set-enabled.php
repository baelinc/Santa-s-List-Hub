#!/usr/bin/php
<?php
/**
 * Santa's List Hub - enable/disable toggle for scheduling.
 * Usage: set-enabled.php 1   (turn display on)
 *        set-enabled.php 0   (turn display off)
 *
 * This is what the schedulable wrapper scripts in /home/fpp/media/scripts/
 * call, so the display can be turned on and off from FPP's own Scheduler
 * (as a "Run Script" command) right alongside your regular sequences.
 */

require_once __DIR__ . '/../lib/SantasListPlugin.php';

$arg = isset($argv[1]) ? trim($argv[1]) : '';
$turnOn = in_array(strtolower($arg), array('1', 'on', 'true', 'enable', 'enabled'), true);
$turnOff = in_array(strtolower($arg), array('0', 'off', 'false', 'disable', 'disabled'), true);

if (!$turnOn && !$turnOff) {
	fwrite(STDERR, "Usage: set-enabled.php <1|0>\n");
	exit(1);
}

$plugin = new SantasListPlugin();
$plugin->saveConfig(array('enabled' => $turnOn));

if ($turnOn) {
	$plugin->ensureContinuousOutput();
	$plugin->startDaemon();
	$plugin->log('Display turned ON via scheduled script.');
	echo "Santa's List Hub display enabled.\n";
} else {
	$plugin->stopDaemon();
	$plugin->disableZones();
	$plugin->stopContinuousOutput();
	$plugin->log('Display turned OFF via scheduled script.');
	echo "Santa's List Hub display disabled.\n";
}

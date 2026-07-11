<?php
/*
 * Santa's List Hub API endpoints, exposed under:
 *   /api/plugin/fpp-plugin-santaslist/<endpoint>
 *
 * See fpp-plugin-Template/api.php for the convention this follows.
 */

require_once __DIR__ . '/lib/SantasListPlugin.php';

/**
 * Read a request param defensively. `param()` (Limonade) should have this
 * already, but we fall back to $_POST/$_GET and a raw JSON-body re-parse so
 * a request never silently comes through empty.
 */
$GLOBALS['__slh_raw_body'] = null;
$GLOBALS['__slh_json_body'] = null;

function slhRawBody() {
	if ($GLOBALS['__slh_raw_body'] === null) {
		$GLOBALS['__slh_raw_body'] = file_get_contents('php://input');
		$decoded = json_decode($GLOBALS['__slh_raw_body'], true);
		$GLOBALS['__slh_json_body'] = is_array($decoded) ? $decoded : array();
	}
	return $GLOBALS['__slh_raw_body'];
}

function slhParam($key, $default = null) {
	slhRawBody(); // ensure it's been read exactly once, before anything else touches php://input
	if (function_exists('param')) {
		$v = param($key);
		if ($v !== null && $v !== '') return $v;
	}
	if (isset($_POST[$key]) && $_POST[$key] !== '') return $_POST[$key];
	if (isset($_GET[$key]) && $_GET[$key] !== '') return $_GET[$key];
	if (isset($GLOBALS['__slh_json_body'][$key]) && $GLOBALS['__slh_json_body'][$key] !== '') {
		return $GLOBALS['__slh_json_body'][$key];
	}
	return $default;
}

function getEndpointsfpppluginsantaslist() {
	$result = array();

	array_push($result, array('method' => 'GET',  'endpoint' => 'status',          'callback' => 'santaslistStatus'));
	array_push($result, array('method' => 'GET',  'endpoint' => 'models',          'callback' => 'santaslistModels'));
	array_push($result, array('method' => 'GET',  'endpoint' => 'fonts',           'callback' => 'santaslistFonts'));
	array_push($result, array('method' => 'GET',  'endpoint' => 'config',          'callback' => 'santaslistGetSettings'));
	array_push($result, array('method' => 'POST', 'endpoint' => 'test-connection', 'callback' => 'santaslistTestConnection'));
	array_push($result, array('method' => 'POST', 'endpoint' => 'config',          'callback' => 'santaslistSaveSettings'));
	array_push($result, array('method' => 'POST', 'endpoint' => 'refresh',         'callback' => 'santaslistRefresh'));

	return $result;
}

// GET /api/plugin/fpp-plugin-santaslist/status
function santaslistStatus() {
	$plugin = new SantasListPlugin();
	$state = $plugin->loadState();
	$cache = $plugin->loadCache();

	$result = array(
		'enabled'              => $plugin->config['enabled'],
		'daemon_running'       => $plugin->daemonIsRunning(),
		'current_list'         => $state['current_list'] ?? null,
		'last_pushed_at'       => $state['last_pushed_at'] ?? 0,
		'last_pull_at'         => $state['last_pull_at'] ?? 0,
		'last_error'           => $state['last_error'] ?? null,
		'seconds_until_switch' => $plugin->secondsUntilNextSwitch(),
		'nice_total'           => $cache['nice_total'] ?? count($cache['nice'] ?? array()),
		'naughty_total'        => $cache['naughty_total'] ?? count($cache['naughty'] ?? array()),
		'account'              => $cache['account'] ?? null,
		'top_zone_size'        => $plugin->zoneSize('top'),
		'bottom_zone_size'     => $plugin->zoneSize('bottom'),
	);
	return json($result);
}

// GET /api/plugin/fpp-plugin-santaslist/models
function santaslistModels() {
	$plugin = new SantasListPlugin();
	return json(array('models' => $plugin->listOverlayModels()));
}

// GET /api/plugin/fpp-plugin-santaslist/fonts
function santaslistFonts() {
	$plugin = new SantasListPlugin();
	return json(array('fonts' => $plugin->listOverlayFonts()));
}

// GET /api/plugin/fpp-plugin-santaslist/settings
function santaslistGetSettings() {
	$plugin = new SantasListPlugin();
	$cfg = $plugin->config;
	$cfg['api_key'] = $cfg['api_key'] ? str_repeat('*', 8) . substr($cfg['api_key'], -4) : '';
	$cfg['top_zone_size'] = $plugin->zoneSize('top');
	$cfg['bottom_zone_size'] = $plugin->zoneSize('bottom');
	return json($cfg);
}

// POST /api/plugin/fpp-plugin-santaslist/test-connection
function santaslistTestConnection() {
	$plugin = new SantasListPlugin();
	$hubUrl = trim((string)slhParam('hub_url', ''));
	$apiKey = trim((string)slhParam('api_key', ''));

	// Support testing with the already-saved key (masked key sent back unchanged).
	if ($apiKey === '' || strpos($apiKey, '*') !== false) {
		$apiKey = $plugin->config['api_key'];
	}

	if ($hubUrl === '' || $apiKey === '') {
		return json(array(
			'ok' => false,
			'error' => 'Hub URL and API key are both required.',
			'debug' => array('received_hub_url' => $hubUrl, 'received_key_length' => strlen($apiKey)),
		));
	}

	$res = $plugin->hubGetMeta($hubUrl, $apiKey);
	if (!$res['ok']) {
		return json(array(
			'ok' => false,
			'error' => $res['error'],
			'debug' => array(
				'requested_url' => rtrim($hubUrl, '/') . '/api/v1/meta?key=' . str_repeat('*', max(0, strlen($apiKey) - 4)) . substr($apiKey, -4),
				'http_code' => $res['http_code'] ?? null,
				'curl_available' => function_exists('curl_init'),
			),
		));
	}
	return json(array('ok' => true, 'account' => $res['data']['account'] ?? $res['data']));
}

// POST /api/plugin/fpp-plugin-santaslist/settings
function santaslistSaveSettings() {
	$plugin = new SantasListPlugin();

	$allowed = array(
		'hub_url', 'api_key', 'mode', 'alternate_seconds', 'refresh_minutes',
		'max_names', 'name_separator', 'no_names_message',
		'top_panel_pixel_width', 'top_panel_pixel_height',
		'top_panels_wide', 'top_panels_tall',
		'bottom_panel_pixel_width', 'bottom_panel_pixel_height',
		'bottom_panels_wide', 'bottom_panels_tall',
		'top_model', 'top_font', 'top_font_size', 'top_anti_alias',
		'nice_color', 'naughty_color',
		'bottom_model', 'bottom_font', 'bottom_font_size', 'bottom_anti_alias',
		'bottom_text_color', 'bottom_position', 'bottom_pixels_per_second',
		'enabled',
	);
	$newConfig = array();
	foreach ($allowed as $key) {
		$v = slhParam($key);
		if ($v !== null) $newConfig[$key] = $v;
	}

	// Don't overwrite a real saved key with the masked placeholder the UI
	// sends back when the user didn't touch the API key field.
	if (isset($newConfig['api_key']) && strpos($newConfig['api_key'], '*') !== false) {
		unset($newConfig['api_key']);
	}

	if (empty($newConfig['hub_url']) && empty($plugin->config['hub_url'])) {
		return json(array('ok' => false, 'error' => 'Hub URL is required.'));
	}
	if (empty($newConfig['api_key']) && empty($plugin->config['api_key'])) {
		return json(array('ok' => false, 'error' => 'API key is required.'));
	}

	$plugin->saveConfig($newConfig);
	$plugin->updateCronInterval($plugin->config['refresh_minutes']);

	$cache = $plugin->pullNames();

	if ($plugin->config['enabled']) {
		$plugin->startDaemon();
	} else {
		$plugin->stopDaemon();
		$plugin->disableZones();
	}

	$cfg = $plugin->config;
	$cfg['api_key'] = $cfg['api_key'] ? str_repeat('*', 8) . substr($cfg['api_key'], -4) : '';
	$cfg['top_zone_size'] = $plugin->zoneSize('top');
	$cfg['bottom_zone_size'] = $plugin->zoneSize('bottom');

	return json(array('ok' => true, 'config' => $cfg, 'cache' => $cache));
}

// POST /api/plugin/fpp-plugin-santaslist/refresh
function santaslistRefresh() {
	$plugin = new SantasListPlugin();
	$cache = $plugin->pullNames();
	if ($cache === false) {
		$state = $plugin->loadState();
		return json(array('ok' => false, 'error' => $state['last_error'] ?? 'Unable to reach the hub.'));
	}
	if ($plugin->config['enabled']) {
		$plugin->pushDisplay($plugin->currentListType(), $cache);
	}
	return json(array('ok' => true, 'cache' => $cache));
}

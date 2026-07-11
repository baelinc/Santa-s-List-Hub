<?php
/**
 * Santa's List Hub - FPP Plugin
 * Shared logic used by the settings page, the AJAX API, the cron puller,
 * and the display daemon. Keeping this in one place means the settings
 * page and the background processes can never drift out of sync on how
 * a setting is interpreted.
 */

class SantasListPlugin {

	public $pluginName = 'fpp-plugin-santaslist';
	public $dirs;
	public $config;

	const NICE = 'nice';
	const NAUGHTY = 'naughty';

	public function __construct() {
		$this->dirs = array(
			'plugin'    => dirname(__DIR__) . '/',   // this file lives in <plugin>/lib/, so the parent is the real plugin root, whatever it's named on disk
			'data'      => '/home/fpp/media/plugindata/',
			'logs'      => '/home/fpp/media/logs/',
		);
		if (!is_dir($this->dirs['data'])) {
			@mkdir($this->dirs['data'], 0775, true);
		}
		$this->config = $this->loadConfig();
	}

	// ------------------------------------------------------------------
	// File paths
	// ------------------------------------------------------------------

	public function configPath() { return $this->dirs['data'] . $this->pluginName . '-config.json'; }
	public function cachePath()  { return $this->dirs['data'] . $this->pluginName . '-cache.json'; }
	public function statePath() { return $this->dirs['data'] . $this->pluginName . '-state.json'; }
	public function pidPath()   { return $this->dirs['data'] . $this->pluginName . '-daemon.pid'; }
	public function logPath()   { return $this->dirs['logs'] . $this->pluginName . '.log'; }

	// ------------------------------------------------------------------
	// Config
	// ------------------------------------------------------------------

	public function defaultConfig() {
		return array(
			'hub_url'                 => '',
			'api_key'                 => '',
			'mode'                    => 'alternate',   // alternate | nice_only | naughty_only
			'alternate_seconds'       => 15,
			'refresh_minutes'         => 2,
			'max_names'               => 150,
			'name_separator'          => '     •     ',
			'no_names_message'        => "Santa's still checking the list...",

			// Panel spec -- drives the schematic and the size validation
			// against whatever overlay model the user picks. Purely
			// informational: FPP itself defines the real model size when
			// it's created, this just lets the settings page tell you
			// what size to make it, for any panel type / grid layout.
			// Top and bottom zones get independent panel specs since the
			// label panel and the names-grid panels are often different
			// hardware (or mounted in a different orientation).
			'top_panel_pixel_width'   => 64,
			'top_panel_pixel_height'  => 32,
			'top_panels_wide'         => 1,
			'top_panels_tall'         => 1,
			'bottom_panel_pixel_width' => 64,
			'bottom_panel_pixel_height'=> 32,
			'bottom_panels_wide'      => 3,
			'bottom_panels_tall'      => 2,

			'top_model'               => '',
			'top_font'                => '',
			'top_font_size'           => 26,
			'top_anti_alias'          => true,
			'nice_color'              => '#1FA05A',
			'naughty_color'           => '#C62828',
			'bottom_model'            => '',
			'bottom_font'             => '',
			'bottom_font_size'        => 18,
			'bottom_anti_alias'       => true,
			'bottom_text_color'       => '#F4EFE1',
			'bottom_position'         => 'Right to Left',
			'bottom_pixels_per_second'=> 40,
			'enabled'                 => false,
			'anchor_epoch'            => null,
		);
	}

	/** Computed pixel size of a zone from its own panel spec: [width, height]. */
	public function zoneSize($zone) {
		if ($zone === 'top') {
			$pw = max(1, (int)$this->config['top_panel_pixel_width']);
			$ph = max(1, (int)$this->config['top_panel_pixel_height']);
			return array($pw * max(1, (int)$this->config['top_panels_wide']), $ph * max(1, (int)$this->config['top_panels_tall']));
		}
		$pw = max(1, (int)$this->config['bottom_panel_pixel_width']);
		$ph = max(1, (int)$this->config['bottom_panel_pixel_height']);
		return array($pw * max(1, (int)$this->config['bottom_panels_wide']), $ph * max(1, (int)$this->config['bottom_panels_tall']));
	}

	public function loadConfig() {
		$defaults = $this->defaultConfig();
		if (!file_exists($this->configPath())) {
			return $defaults;
		}
		$raw = @file_get_contents($this->configPath());
		$data = json_decode($raw, true);
		if (!is_array($data)) {
			return $defaults;
		}
		return array_merge($defaults, $data);
	}

	public function saveConfig($newConfig) {
		$defaults = $this->defaultConfig();
		$merged = array_merge($defaults, $this->config, $newConfig);

		// Anchor epoch drives the deterministic alternate-list clock. Set it
		// once, the first time the plugin is ever configured, and otherwise
		// leave it alone -- resetting it on every save would make the
		// on-screen list jump every time someone tweaks an unrelated setting.
		if (empty($merged['anchor_epoch'])) {
			$merged['anchor_epoch'] = time();
		}

		$ok = @file_put_contents($this->configPath(), json_encode($merged, JSON_PRETTY_PRINT));
		if ($ok !== false) {
			$this->config = $merged;
			return true;
		}
		$this->log('ERROR: could not write config file to ' . $this->configPath());
		return false;
	}

	// ------------------------------------------------------------------
	// Cache (last names pulled from the hub) + daemon state
	// ------------------------------------------------------------------

	public function loadCache() {
		if (!file_exists($this->cachePath())) {
			return array('fetched_at' => 0, 'nice' => array(), 'naughty' => array(), 'nice_total' => 0, 'naughty_total' => 0, 'account' => null);
		}
		$data = json_decode(@file_get_contents($this->cachePath()), true);
		return is_array($data) ? $data : array('fetched_at' => 0, 'nice' => array(), 'naughty' => array());
	}

	public function saveCache($cache) {
		@file_put_contents($this->cachePath(), json_encode($cache, JSON_PRETTY_PRINT));
	}

	public function loadState() {
		if (!file_exists($this->statePath())) {
			return array('current_list' => null, 'last_pushed_at' => 0, 'last_error' => null);
		}
		$data = json_decode(@file_get_contents($this->statePath()), true);
		return is_array($data) ? $data : array('current_list' => null, 'last_pushed_at' => 0);
	}

	public function saveState($state) {
		@file_put_contents($this->statePath(), json_encode($state, JSON_PRETTY_PRINT));
	}

	public function log($msg) {
		$line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
		@file_put_contents($this->logPath(), $line, FILE_APPEND);
	}

	// ------------------------------------------------------------------
	// HTTP helpers
	// ------------------------------------------------------------------

	private function httpRequest($method, $url, $body = null, $timeout = 8) {
		if (!function_exists('curl_init')) {
			return array('ok' => false, 'error' => 'PHP curl extension is not available on this system.', 'http_code' => 0);
		}
		$ch = curl_init();
		$headers = array('Accept: application/json');
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		if ($body !== null) {
			$headers[] = 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		$errno = curl_errno($ch);
		curl_close($ch);

		if ($response === false) {
			// errno 51/60/35 are all SSL/certificate-trust failures -- extremely
			// common on Pi-based FPP images with an outdated ca-certificates
			// package, and they fail identically no matter what URL/key you use.
			if (in_array($errno, array(51, 60, 35))) {
				return array('ok' => false, 'error' =>
					"SSL certificate verification failed ($err). This usually means the CA certificate bundle on your FPP device is outdated. Try: sudo apt-get update && sudo apt-get install --reinstall ca-certificates",
					'http_code' => 0, 'curl_errno' => $errno);
			}
			if (in_array($errno, array(6, 7))) {
				return array('ok' => false, 'error' =>
					"Could not reach that host ($err). Check the Hub URL and that this FPP device has network/DNS access to it.",
					'http_code' => 0, 'curl_errno' => $errno);
			}
			return array('ok' => false, 'error' => "Connection failed: $err", 'http_code' => 0, 'curl_errno' => $errno);
		}
		$decoded = json_decode($response, true);
		if ($httpCode >= 200 && $httpCode < 300) {
			return array('ok' => true, 'data' => (is_array($decoded) ? $decoded : $response), 'http_code' => $httpCode);
		}
		$errMsg = is_array($decoded) && isset($decoded['error']) ? $decoded['error'] : "HTTP $httpCode";
		return array('ok' => false, 'error' => $errMsg, 'http_code' => $httpCode);
	}

	// ------------------------------------------------------------------
	// Santa's List Hub client
	// ------------------------------------------------------------------

	private function hubBase() {
		return rtrim($this->config['hub_url'], '/');
	}

	public function hubGetMeta($hubUrl = null, $apiKey = null) {
		$base = $hubUrl !== null ? rtrim($hubUrl, '/') : $this->hubBase();
		$key = $apiKey !== null ? $apiKey : $this->config['api_key'];
		if (!$base || !$key) {
			return array('ok' => false, 'error' => 'Hub URL and API key are both required.');
		}
		return $this->httpRequest('GET', $base . '/api/v1/meta?key=' . rawurlencode($key));
	}

	public function hubGetNames($listType, $limit = null) {
		$base = $this->hubBase();
		$key = $this->config['api_key'];
		if (!$base || !$key) {
			return array('ok' => false, 'error' => 'Hub URL and API key are both required.');
		}
		$limit = $limit ?: $this->config['max_names'];
		$url = $base . '/api/v1/names?key=' . rawurlencode($key)
			. '&list=' . rawurlencode($listType)
			. '&limit=' . (int)$limit;
		return $this->httpRequest('GET', $url);
	}

	/** Pull fresh names for both lists from the hub and cache them locally. */
	public function pullNames() {
		$meta = $this->hubGetMeta();
		if (!$meta['ok']) {
			$state = $this->loadState();
			$state['last_error'] = $meta['error'];
			$this->saveState($state);
			$this->log('Pull failed: ' . $meta['error']);
			return false;
		}

		$nice = $this->hubGetNames(self::NICE);
		$naughty = $this->hubGetNames(self::NAUGHTY);

		$cache = array(
			'fetched_at'    => time(),
			'account'       => $meta['data']['account'] ?? null,
			'nice'          => $nice['ok'] ? ($nice['data']['names'] ?? array()) : array(),
			'naughty'       => $naughty['ok'] ? ($naughty['data']['names'] ?? array()) : array(),
			'nice_total'    => $nice['ok'] ? ($nice['data']['total'] ?? 0) : 0,
			'naughty_total' => $naughty['ok'] ? ($naughty['data']['total'] ?? 0) : 0,
		);
		$this->saveCache($cache);

		$state = $this->loadState();
		$state['last_error'] = null;
		$state['last_pull_at'] = time();
		$this->saveState($state);

		$this->log("Pulled names: {$cache['nice_total']} nice, {$cache['naughty_total']} naughty.");
		return $cache;
	}

	// ------------------------------------------------------------------
	// Local FPP overlay-model API client
	// ------------------------------------------------------------------

	private function localApiBase() {
		return 'http://localhost/api';
	}

	public function listOverlayModels() {
		$res = $this->httpRequest('GET', $this->localApiBase() . '/overlays/models', null, 4);
		return $res['ok'] ? $res['data'] : array();
	}

	public function listOverlayFonts() {
		$res = $this->httpRequest('GET', $this->localApiBase() . '/overlays/fonts', null, 4);
		return $res['ok'] ? $res['data'] : array();
	}

	private function setOverlayText($model, $message, $color, $font, $fontSize, $position, $pixelsPerSecond, $antiAlias) {
		if (!$model) return false;
		$url = $this->localApiBase() . '/overlays/model/' . rawurlencode($model) . '/text';
		$body = array(
			'Message'         => $message,
			'Color'           => $color,
			'Font'            => (string)$font,
			'FontSize'        => (int)$fontSize,
			'Position'        => $position,
			'PixelsPerSecond' => (int)$pixelsPerSecond,
			'AntiAlias'       => (bool)$antiAlias,
			'AutoEnable'      => true,
		);
		return $this->httpRequest('PUT', $url, $body, 5);
	}

	private function setOverlayState($model, $state) {
		if (!$model) return false;
		$url = $this->localApiBase() . '/overlays/model/' . rawurlencode($model) . '/state';
		return $this->httpRequest('PUT', $url, array('State' => (int)$state), 5);
	}

	// ------------------------------------------------------------------
	// Display logic
	// ------------------------------------------------------------------

	/** Which list ("nice"/"naughty") should be on screen right now, deterministically. */
	public function currentListType() {
		if ($this->config['mode'] === 'nice_only') return self::NICE;
		if ($this->config['mode'] === 'naughty_only') return self::NAUGHTY;

		$anchor = $this->config['anchor_epoch'] ?: time();
		$period = max(5, (int)$this->config['alternate_seconds']);
		$elapsed = time() - $anchor;
		$idx = (int)floor($elapsed / $period) % 2;
		return $idx === 0 ? self::NICE : self::NAUGHTY;
	}

	public function secondsUntilNextSwitch() {
		if ($this->config['mode'] !== 'alternate') return null;
		$anchor = $this->config['anchor_epoch'] ?: time();
		$period = max(5, (int)$this->config['alternate_seconds']);
		$elapsed = time() - $anchor;
		return $period - ($elapsed % $period);
	}

	public function buildNameString($listType, $cache) {
		$names = $cache[$listType] ?? array();
		if (empty($names)) {
			return $this->config['no_names_message'];
		}
		$sep = $this->config['name_separator'];
		$parts = array();
		foreach ($names as $n) {
			$first = trim($n['first_name'] ?? '');
			$last = trim($n['last_initial'] ?? '');
			if ($first === '') continue;
			$parts[] = $last !== '' ? "$first $last." : $first;
		}
		if (empty($parts)) {
			return $this->config['no_names_message'];
		}
		return implode($sep, $parts);
	}

	/** Push the given list type to both overlay zones. */
	public function pushDisplay($listType, $cache = null) {
		if ($cache === null) $cache = $this->loadCache();

		$label = $listType === self::NICE ? 'NICE' : 'NAUGHTY';
		$color = $listType === self::NICE ? $this->config['nice_color'] : $this->config['naughty_color'];

		if ($this->config['top_model']) {
			$this->setOverlayText(
				$this->config['top_model'], $label, $color,
				$this->config['top_font'], $this->config['top_font_size'],
				'Center', 0, $this->config['top_anti_alias']
			);
			$this->setOverlayState($this->config['top_model'], 1);
		}

		if ($this->config['bottom_model']) {
			$message = $this->buildNameString($listType, $cache);
			$this->setOverlayText(
				$this->config['bottom_model'], $message, $this->config['bottom_text_color'],
				$this->config['bottom_font'], $this->config['bottom_font_size'],
				$this->config['bottom_position'], $this->config['bottom_pixels_per_second'],
				$this->config['bottom_anti_alias']
			);
			$this->setOverlayState($this->config['bottom_model'], 1);
		}

		$state = $this->loadState();
		$state['current_list'] = $listType;
		$state['last_pushed_at'] = time();
		$this->saveState($state);
		$this->log("Pushed display: $listType");
	}

	/** Turn both overlay zones off (used when the plugin is disabled/uninstalled). */
	public function disableZones() {
		if ($this->config['top_model']) $this->setOverlayState($this->config['top_model'], 0);
		if ($this->config['bottom_model']) $this->setOverlayState($this->config['bottom_model'], 0);
	}

	// ------------------------------------------------------------------
	// Daemon process management
	// ------------------------------------------------------------------

	public function daemonIsRunning() {
		if (!file_exists($this->pidPath())) return false;
		$pid = (int)trim(file_get_contents($this->pidPath()));
		if ($pid <= 0) return false;
		$out = trim(shell_exec("kill -0 $pid 2>/dev/null && echo alive"));
		return $out === 'alive';
	}

	public function startDaemon() {
		if ($this->daemonIsRunning()) {
			$this->stopDaemon();
		}
		$script = $this->dirs['plugin'] . 'bin/display-daemon.php';
		$cmd = 'nohup /usr/bin/php ' . escapeshellarg($script) . ' > ' . escapeshellarg($this->logPath()) . ' 2>&1 & echo $!';
		$pid = trim(shell_exec($cmd));
		if (ctype_digit($pid)) {
			file_put_contents($this->pidPath(), $pid);
			$this->log("Daemon started, pid $pid");
			return true;
		}
		$this->log('ERROR: failed to start daemon');
		return false;
	}

	public function stopDaemon() {
		if (!file_exists($this->pidPath())) return true;
		$pid = (int)trim(file_get_contents($this->pidPath()));
		if ($pid > 0) {
			shell_exec("kill $pid 2>/dev/null");
			$this->log("Daemon stopped, pid $pid");
		}
		@unlink($this->pidPath());
		return true;
	}

	// ------------------------------------------------------------------
	// Cron management (name-pull schedule)
	// ------------------------------------------------------------------

	public function cronTemplatePath() { return $this->dirs['plugin'] . 'templates/santaslist-cronTemplate'; }
	public function cronInstalledPath() { return '/etc/cron.d/' . $this->pluginName . '-cron'; }

	/** Rewrite the cron.d entry to match the configured refresh interval (minutes). */
	public function updateCronInterval($minutes) {
		$minutes = max(1, (int)$minutes);
		$content = "# Santa's List Hub - pulls fresh names from the hub on a schedule.\n"
			. "# Regenerated automatically when the refresh interval is changed in Settings.\n"
			. "*/{$minutes} * * * * fpp /usr/bin/php {$this->dirs['plugin']}cron/pull.php >/dev/null 2>&1\n";
		file_put_contents($this->cronTemplatePath(), $content);
		shell_exec('sudo cp ' . escapeshellarg($this->cronTemplatePath()) . ' ' . escapeshellarg($this->cronInstalledPath()));
		$this->log("Cron interval set to every {$minutes} minute(s).");
	}
}

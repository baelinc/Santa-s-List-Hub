(function () {
	'use strict';

	var API = '/api/plugin/' + (window.SLH_PLUGIN_NAME || 'fpp-plugin-santaslist') + '/';
	var state = { models: [], fonts: [], modelsByName: {}, previewMode: 'nice' };

	function $(sel, root) { return (root || document).querySelector(sel); }
	function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

	function apiGet(endpoint) {
		return fetch(API + endpoint, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function apiPost(endpoint, body) {
		return fetch(API + endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(body || {})
		}).then(function (r) { return r.json(); });
	}

	function toast(msg, isError) {
		var el = $('#slh-toast');
		el.textContent = msg;
		el.className = 'slh-toast show' + (isError ? ' error' : '');
		clearTimeout(toast._t);
		toast._t = setTimeout(function () { el.className = 'slh-toast'; }, 3800);
	}

	function fmtModelLabel(m) {
		var name = m.Name || m.name || '';
		var w = m.width || m.Width || '?';
		var h = m.height || m.Height || '?';
		return name + '  (' + w + '\u00d7' + h + ')';
	}

	function populateSelect(select, items, currentValue, placeholder, autoSelectFirst) {
		select.innerHTML = '';
		var optEmpty = document.createElement('option');
		optEmpty.value = '';
		optEmpty.textContent = placeholder;
		select.appendChild(optEmpty);
		items.forEach(function (item) {
			var opt = document.createElement('option');
			if (typeof item === 'string') {
				opt.value = item;
				opt.textContent = item;
			} else {
				opt.value = item.Name || item.name;
				opt.textContent = fmtModelLabel(item);
			}
			select.appendChild(opt);
		});
		if (currentValue) {
			select.value = currentValue;
		} else if (autoSelectFirst && items.length > 0) {
			// FPP's text renderer has no real "default font" -- an empty
			// font name fails outright, so pick a real one rather than
			// leaving this on the blank placeholder.
			var first = items[0];
			select.value = typeof first === 'string' ? first : (first.Name || first.name);
		}
	}

	function loadModelsAndFonts(cfg) {
		return Promise.all([apiGet('models'), apiGet('fonts')]).then(function (results) {
			state.models = (results[0] && results[0].models) || [];
			state.fonts = (results[1] && results[1].fonts) || [];
			state.modelsByName = {};
			state.models.forEach(function (m) {
				var name = m.Name || m.name;
				if (name) state.modelsByName[name] = { w: m.width || m.Width, h: m.height || m.Height };
			});

			populateSelect($('#slh-top-model'), state.models, cfg.top_model, 'Choose the label-row overlay model\u2026', false);
			populateSelect($('#slh-bottom-model'), state.models, cfg.bottom_model, 'Choose the names-grid overlay model\u2026', false);
			populateSelect($('#slh-top-font'), state.fonts, cfg.top_font, 'Default font', true);
			populateSelect($('#slh-bottom-font'), state.fonts, cfg.bottom_font, 'Default font', true);

			if (state.models.length === 0) {
				$('#slh-no-models-hint').style.display = 'block';
			}
			if (state.fonts.length === 0) {
				toast('FPP reported no fonts available -- text overlays may fail until at least one font exists.', true);
			}
		});
	}

	function applyConfigToForm(cfg) {
		$('#slh-hub-url').value = cfg.hub_url || '';
		$('#slh-api-key').value = cfg.api_key || '';
		$('#slh-enabled').checked = !!cfg.enabled;
		$('#slh-keep-alive').checked = cfg.keep_alive_enabled !== false;

		$('#slh-top-panel-w').value = cfg.top_panel_pixel_width || 64;
		$('#slh-top-panel-h').value = cfg.top_panel_pixel_height || 32;
		$('#slh-top-wide').value = cfg.top_panels_wide || 1;
		$('#slh-top-tall').value = cfg.top_panels_tall || 1;
		$('#slh-bottom-panel-w').value = cfg.bottom_panel_pixel_width || 64;
		$('#slh-bottom-panel-h').value = cfg.bottom_panel_pixel_height || 32;
		$('#slh-bottom-wide').value = cfg.bottom_panels_wide || 3;
		$('#slh-bottom-tall').value = cfg.bottom_panels_tall || 2;

		$all('.slh-mode-card input').forEach(function (r) { r.checked = (r.value === cfg.mode); });
		updateModeCardStyles();
		$('#slh-alternate-seconds').value = cfg.alternate_seconds || 15;
		toggleAlternateSecondsVisibility();

		$('#slh-top-font-size').value = cfg.top_font_size || 26;
		$('#slh-nice-color').value = cfg.nice_color || '#1FA05A';
		$('#slh-naughty-color').value = cfg.naughty_color || '#C62828';
		$('#slh-top-antialias').checked = cfg.top_anti_alias !== false;

		$('#slh-bottom-font-size').value = cfg.bottom_font_size || 18;
		$('#slh-bottom-color').value = cfg.bottom_text_color || '#F4EFE1';
		$('#slh-bottom-position').value = cfg.bottom_position || 'Right to Left';
		$('#slh-bottom-speed').value = cfg.bottom_pixels_per_second || 40;
		$('#slh-bottom-speed-out').textContent = $('#slh-bottom-speed').value + ' px/sec';
		$('#slh-bottom-antialias').checked = cfg.bottom_anti_alias !== false;
		$('#slh-separator').value = (cfg.name_separator || '').trim();
		$('#slh-no-names-message').value = cfg.no_names_message || '';

		$all('input[name="slh-bottom-style"]').forEach(function (r) { r.checked = (r.value === (cfg.bottom_display_style || 'ticker')); });
		updateModeCardStyles();
		toggleBottomStyleVisibility();
		$('#slh-list-align').value = cfg.bottom_list_align || 'left';
		$('#slh-list-mode').value = cfg.bottom_list_mode || 'scroll';
		$('#slh-list-count').value = cfg.bottom_list_count || 8;
		$('#slh-list-order').value = cfg.bottom_list_reverse ? '1' : '0';

		$('#slh-refresh-minutes').value = cfg.refresh_minutes || 2;
		$('#slh-max-names').value = cfg.max_names || 150;

		updateSchematic();
		updatePreviewText();
	}

	function readFormToConfig() {
		var mode = ($('.slh-mode-card input:checked') || {}).value || 'alternate';
		return {
			hub_url: $('#slh-hub-url').value.trim(),
			api_key: $('#slh-api-key').value.trim(),
			enabled: $('#slh-enabled').checked,
			keep_alive_enabled: $('#slh-keep-alive').checked,
			mode: mode,
			alternate_seconds: parseInt($('#slh-alternate-seconds').value, 10) || 15,

			top_panel_pixel_width: parseInt($('#slh-top-panel-w').value, 10) || 64,
			top_panel_pixel_height: parseInt($('#slh-top-panel-h').value, 10) || 32,
			top_panels_wide: parseInt($('#slh-top-wide').value, 10) || 1,
			top_panels_tall: parseInt($('#slh-top-tall').value, 10) || 1,
			bottom_panel_pixel_width: parseInt($('#slh-bottom-panel-w').value, 10) || 64,
			bottom_panel_pixel_height: parseInt($('#slh-bottom-panel-h').value, 10) || 32,
			bottom_panels_wide: parseInt($('#slh-bottom-wide').value, 10) || 3,
			bottom_panels_tall: parseInt($('#slh-bottom-tall').value, 10) || 2,

			top_model: $('#slh-top-model').value,
			top_font: $('#slh-top-font').value,
			top_font_size: parseInt($('#slh-top-font-size').value, 10) || 26,
			top_anti_alias: $('#slh-top-antialias').checked,
			nice_color: $('#slh-nice-color').value,
			naughty_color: $('#slh-naughty-color').value,

			bottom_model: $('#slh-bottom-model').value,
			bottom_font: $('#slh-bottom-font').value,
			bottom_font_size: parseInt($('#slh-bottom-font-size').value, 10) || 18,
			bottom_anti_alias: $('#slh-bottom-antialias').checked,
			bottom_text_color: $('#slh-bottom-color').value,
			bottom_position: $('#slh-bottom-position').value,
			bottom_pixels_per_second: parseInt($('#slh-bottom-speed').value, 10) || 40,
			name_separator: '     ' + ($('#slh-separator').value || '\u2022').trim() + '     ',
			no_names_message: $('#slh-no-names-message').value || "Santa's still checking the list...",

			bottom_display_style: (($('input[name="slh-bottom-style"]:checked') || {}).value) || 'ticker',
			bottom_list_align: $('#slh-list-align').value,
			bottom_list_mode: $('#slh-list-mode').value,
			bottom_list_count: parseInt($('#slh-list-count').value, 10) || 8,
			bottom_list_reverse: $('#slh-list-order').value === '1',

			refresh_minutes: parseInt($('#slh-refresh-minutes').value, 10) || 2,
			max_names: parseInt($('#slh-max-names').value, 10) || 150
		};
	}

	function updateModeCardStyles() {
		$all('.slh-mode-card').forEach(function (card) {
			var input = card.querySelector('input');
			card.classList.toggle('selected', input.checked);
		});
	}

	function toggleAlternateSecondsVisibility() {
		var mode = ($('.slh-mode-card input:checked') || {}).value;
		$('#slh-alternate-seconds-field').style.display = (mode === 'alternate') ? '' : 'none';
	}

	function toggleBottomStyleVisibility() {
		var style = (($('input[name="slh-bottom-style"]:checked') || {}).value) || 'ticker';
		$('#slh-ticker-options').style.display = (style === 'ticker') ? '' : 'none';
		$('#slh-list-options').style.display = (style === 'list') ? '' : 'none';

		// Scroll speed only matters when something is actually scrolling.
		var tickerScrolling = style === 'ticker' && $('#slh-bottom-position').value !== 'Center';
		var listScrolling = style === 'list' && $('#slh-list-mode').value === 'scroll';
		$('#slh-bottom-speed-field').style.display = (tickerScrolling || listScrolling) ? '' : 'none';

		updateListPreview();
	}

	function updateListPreview() {
		var names = ['Emma R.', 'Liam T.', 'Ava S.'];
		var count = parseInt($('#slh-list-count').value, 10) || 8;
		names = names.slice(0, count);
		if ($('#slh-list-order').value === '1') names = names.slice().reverse();
		var align = $('#slh-list-align').value;
		var arrow = align === 'left' ? '\u2190' : (align === 'right' ? '\u2192' : '\u2194');
		$('#slh-list-preview').textContent = arrow + ' ' + names.join('  /  ') + ' (' + align + ', one per line on the panel)';
	}

	function readPanelSpec() {
		return {
			topPw: Math.max(1, parseInt($('#slh-top-panel-w').value, 10) || 64),
			topPh: Math.max(1, parseInt($('#slh-top-panel-h').value, 10) || 32),
			topWide: Math.max(1, parseInt($('#slh-top-wide').value, 10) || 1),
			topTall: Math.max(1, parseInt($('#slh-top-tall').value, 10) || 1),
			bottomPw: Math.max(1, parseInt($('#slh-bottom-panel-w').value, 10) || 64),
			bottomPh: Math.max(1, parseInt($('#slh-bottom-panel-h').value, 10) || 32),
			bottomWide: Math.max(1, parseInt($('#slh-bottom-wide').value, 10) || 3),
			bottomTall: Math.max(1, parseInt($('#slh-bottom-tall').value, 10) || 2)
		};
	}

	function panelCountLabel(wide, tall) {
		if (wide === 1 && tall === 1) return '1 panel';
		if (tall === 1) return wide + ' panels wide';
		return wide + '\u00d7' + tall + ' panels';
	}

	function checkModelMismatch(selectEl, warnEl, expectedW, expectedH) {
		var name = selectEl.value;
		var info = name ? state.modelsByName[name] : null;
		if (!info || !info.w || !info.h) {
			warnEl.style.display = 'none';
			return;
		}
		if (parseInt(info.w, 10) !== expectedW || parseInt(info.h, 10) !== expectedH) {
			warnEl.textContent = '\u26a0 "' + name + '" is ' + info.w + '\u00d7' + info.h +
				' but your panel spec computes to ' + expectedW + '\u00d7' + expectedH +
				'. Double-check your panel counts above, or the model in FPP \u2014 unless that\u2019s intentional.';
			warnEl.style.display = 'block';
		} else {
			warnEl.style.display = 'none';
		}
	}

	function updateSchematic() {
		var spec = readPanelSpec();
		var topW = spec.topPw * spec.topWide, topH = spec.topPh * spec.topTall;
		var bottomW = spec.bottomPw * spec.bottomWide, bottomH = spec.bottomPh * spec.bottomTall;

		// Top zone box: fixed 160px width, height follows the real aspect ratio.
		var top = $('#slh-schematic-top');
		top.style.width = '160px';
		top.style.aspectRatio = topW + ' / ' + topH;
		top.style.height = 'auto';

		var topSizeText = panelCountLabel(spec.topWide, spec.topTall) + ' \u00b7 ' + topW + '\u00d7' + topH;
		$('#slh-schematic-top-label').textContent = topSizeText;
		$('#slh-top-hint-size').textContent = topSizeText;

		// Bottom zone grid: rebuild cells to match the configured panel count.
		var grid = $('#slh-schematic-grid');
		grid.style.width = '160px';
		grid.style.gridTemplateColumns = 'repeat(' + spec.bottomWide + ', 1fr)';
		grid.style.gridTemplateRows = 'repeat(' + spec.bottomTall + ', 1fr)';
		grid.innerHTML = '';
		var cellCount = spec.bottomWide * spec.bottomTall;
		for (var i = 0; i < cellCount; i++) {
			var cell = document.createElement('div');
			cell.className = 'slh-panel-cell';
			cell.style.aspectRatio = spec.bottomPw + ' / ' + spec.bottomPh;
			grid.appendChild(cell);
		}

		var bottomSizeText = panelCountLabel(spec.bottomWide, spec.bottomTall) + ' \u00b7 ' + bottomW + '\u00d7' + bottomH;
		$('#slh-schematic-grid-label').textContent = bottomSizeText;
		$('#slh-bottom-hint-size').textContent = bottomSizeText;

		var isNice = state.previewMode === 'nice';
		top.classList.toggle('is-nice', isNice);
		top.classList.toggle('is-naughty', !isNice);
		grid.classList.toggle('is-nice', isNice);
		grid.classList.toggle('is-naughty', !isNice);
		top.textContent = isNice ? 'NICE' : 'NAUGHTY';

		$('#slh-preview-nice-btn').classList.toggle('slh-primary', isNice);
		$('#slh-preview-naughty-btn').classList.toggle('slh-primary', !isNice);

		checkModelMismatch($('#slh-top-model'), $('#slh-top-model-warning'), topW, topH);
		checkModelMismatch($('#slh-bottom-model'), $('#slh-bottom-model-warning'), bottomW, bottomH);
	}

	function updatePreviewText() {
		var sep = ($('#slh-separator').value || '\u2022').trim();
		var sample = ['Emma R.', 'Liam T.', 'Ava S.'].join('   ' + sep + '   ');
		$('#slh-scroll-preview').textContent = sample;
	}

	function setConnectionPill(status) {
		var pill = $('#slh-status-pill');
		var seal = $('#slh-status-seal');
		var text = $('#slh-status-text');
		seal.className = 'slh-seal ' + status;
		if (status === 'ok') text.textContent = 'Connected';
		else if (status === 'bad') text.textContent = 'Not connected';
		else text.textContent = 'Checking\u2026';
	}

	function refreshStatusBar() {
		apiGet('status').then(function (res) {
			if (!res) return;
			if (res.last_error) {
				setConnectionPill('bad');
			} else if (res.last_pull_at) {
				setConnectionPill('ok');
			}
			var bits = [];
			if (res.account) bits.push(res.account);
			if (res.enabled) {
				bits.push((res.current_list ? res.current_list.toUpperCase() : '\u2013') + ' on screen');
				bits.push(res.nice_total + ' nice / ' + res.naughty_total + ' naughty');
			} else {
				bits.push('Display disabled');
			}
			$('#slh-status-detail').textContent = bits.join('  \u00b7  ');
		}).catch(function () { setConnectionPill('bad'); });
	}

	function testConnection() {
		var btn = $('#slh-test-btn');
		btn.disabled = true;
		btn.textContent = 'Testing\u2026';
		setConnectionPill('pending');
		apiPost('test-connection', {
			hub_url: $('#slh-hub-url').value.trim(),
			api_key: $('#slh-api-key').value.trim()
		}).then(function (res) {
			btn.disabled = false;
			btn.textContent = 'Test connection';
			if (res.ok) {
				setConnectionPill('ok');
				toast('Connected' + (res.account ? ' as ' + res.account : '') + '.');
			} else {
				setConnectionPill('bad');
				toast(res.error || 'Could not connect to the hub.', true);
				if (res.debug) console.error('Santa\'s List Hub test-connection debug:', res.debug);
			}
		}).catch(function (err) {
			btn.disabled = false;
			btn.textContent = 'Test connection';
			setConnectionPill('bad');
			toast('Could not reach the plugin API. Check the browser console for details.', true);
			console.error('Santa\'s List Hub test-connection request failed:', err);
		});
	}

	function saveSettings() {
		var btn = $('#slh-save-btn');
		btn.disabled = true;
		btn.textContent = 'Saving\u2026';
		apiPost('config', readFormToConfig()).then(function (res) {
			btn.disabled = false;
			btn.textContent = 'Save settings';
			if (res.ok) {
				toast('Settings saved.');
				applyConfigToForm(res.config);
				refreshStatusBar();
			} else {
				toast(res.error || 'Could not save settings.', true);
				if (res.debug) console.error('Santa\'s List Hub save-settings debug:', res.debug);
			}
		}).catch(function () {
			btn.disabled = false;
			btn.textContent = 'Save settings';
			toast('Could not save settings.', true);
		});
	}

	function refreshNow() {
		var btn = $('#slh-refresh-btn');
		btn.disabled = true;
		btn.textContent = 'Refreshing\u2026';
		apiPost('refresh', {}).then(function (res) {
			btn.disabled = false;
			btn.textContent = 'Refresh now';
			if (res.ok) {
				toast('Pulled the latest list from the hub.');
				refreshStatusBar();
			} else {
				toast(res.error || 'Refresh failed.', true);
			}
		}).catch(function () {
			btn.disabled = false;
			btn.textContent = 'Refresh now';
			toast('Refresh failed.', true);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		if (!$('#slh-hub-url')) return; // not the settings page

		apiGet('config').then(function (cfg) {
			applyConfigToForm(cfg || {});
			return loadModelsAndFonts(cfg || {});
		}).then(function () {
			updateSchematic();
			refreshStatusBar();
		});

		$all('.slh-mode-card input').forEach(function (r) {
			r.addEventListener('change', function () {
				updateModeCardStyles();
				toggleAlternateSecondsVisibility();
			});
		});

		$('#slh-top-model').addEventListener('change', updateSchematic);
		$('#slh-bottom-model').addEventListener('change', updateSchematic);
		[
			'#slh-top-panel-w', '#slh-top-panel-h', '#slh-top-wide', '#slh-top-tall',
			'#slh-bottom-panel-w', '#slh-bottom-panel-h', '#slh-bottom-wide', '#slh-bottom-tall'
		].forEach(function (sel) {
			$(sel).addEventListener('input', updateSchematic);
		});

		function panelFieldsFor(target) {
			return target === 'top'
				? { w: $('#slh-top-panel-w'), h: $('#slh-top-panel-h') }
				: { w: $('#slh-bottom-panel-w'), h: $('#slh-bottom-panel-h') };
		}

		$all('.slh-panel-preset:not(.slh-panel-rotate-btn)').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var fields = panelFieldsFor(this.getAttribute('data-target'));
				fields.w.value = this.getAttribute('data-w');
				fields.h.value = this.getAttribute('data-h');
				updateSchematic();
			});
		});
		$all('.slh-panel-rotate-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var fields = panelFieldsFor(this.getAttribute('data-target'));
				var w = parseInt(fields.w.value, 10) || 0;
				var h = parseInt(fields.h.value, 10) || 0;
				if (w <= 0 || h <= 0) return; // don't swap into a blank/invalid state
				fields.w.value = h;
				fields.h.value = w;
				updateSchematic();
			});
		});
		$('#slh-separator').addEventListener('input', updatePreviewText);
		$('#slh-bottom-speed').addEventListener('input', function () {
			$('#slh-bottom-speed-out').textContent = this.value + ' px/sec';
		});

		$all('input[name="slh-bottom-style"]').forEach(function (r) {
			r.addEventListener('change', function () {
				updateModeCardStyles();
				toggleBottomStyleVisibility();
			});
		});
		$('#slh-bottom-position').addEventListener('change', toggleBottomStyleVisibility);
		['#slh-list-mode', '#slh-list-align', '#slh-list-count', '#slh-list-order'].forEach(function (sel) {
			$(sel).addEventListener('change', function () {
				toggleBottomStyleVisibility();
				updateListPreview();
			});
			$(sel).addEventListener('input', updateListPreview);
		});

		$('#slh-preview-nice-btn').addEventListener('click', function () { state.previewMode = 'nice'; updateSchematic(); });
		$('#slh-preview-naughty-btn').addEventListener('click', function () { state.previewMode = 'naughty'; updateSchematic(); });

		$('#slh-test-btn').addEventListener('click', testConnection);
		$('#slh-save-btn').addEventListener('click', saveSettings);
		$('#slh-refresh-btn').addEventListener('click', refreshNow);

		setInterval(refreshStatusBar, 20000);
	});
})();

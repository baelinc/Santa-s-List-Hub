<script>window.SLH_PLUGIN_NAME = <?php echo json_encode(isset($pluginName) ? $pluginName : 'fpp-plugin-santaslist'); ?>;</script>
<div class="slh-app">
	<div class="slh-header">
		<div class="slh-title">
			<h1>&#127877; Santa's List Hub</h1>
			<span>Live status</span>
		</div>
		<div class="slh-status-pill" id="slh-status-pill">
			<span class="slh-seal pending" id="slh-status-seal"></span>
			<span id="slh-status-text">Checking&hellip;</span>
		</div>
	</div>

	<div class="slh-card">
		<h2>Now showing</h2>
		<p class="slh-hint" id="slh-status-now" style="font-size:15px;">&hellip;</p>
	</div>

	<div class="slh-two-col">
		<div class="slh-card">
			<div class="slh-eyebrow">Nice list</div>
			<h2 id="slh-nice-count" style="color:var(--slh-nice-soft);">&ndash;</h2>
		</div>
		<div class="slh-card">
			<div class="slh-eyebrow">Naughty list</div>
			<h2 id="slh-naughty-count" style="color:var(--slh-naughty-soft);">&ndash;</h2>
		</div>
	</div>

	<div class="slh-card">
		<div class="slh-eyebrow">Sync</div>
		<p class="slh-hint" id="slh-status-sync">&hellip;</p>
		<button type="button" id="slh-refresh-btn" class="slh-primary">Refresh now</button>
	</div>

	<div class="slh-card">
		<div class="slh-eyebrow">Live preview</div>
		<h2 style="margin-bottom:10px;">What's actually being pushed to each zone</h2>
		<p class="slh-hint">Pulled directly from FPP's overlay pixel buffer, refreshed every couple seconds. This shows
			what the plugin is sending regardless of whether your physical panels are working, so it's useful for telling
			software issues apart from hardware/cabling ones.</p>
		<div style="display:flex; flex-wrap:wrap; gap:20px; align-items:flex-start; margin-top:10px;">
			<div>
				<div class="slh-zone-label" id="slh-preview-top-label">Top zone</div>
				<canvas id="slh-preview-top-canvas" style="image-rendering:pixelated; border:1px solid var(--slh-border); background:#000; max-width:100%;"></canvas>
			</div>
			<div>
				<div class="slh-zone-label" id="slh-preview-bottom-label">Bottom zone</div>
				<canvas id="slh-preview-bottom-canvas" style="image-rendering:pixelated; border:1px solid var(--slh-border); background:#000; max-width:100%;"></canvas>
			</div>
		</div>
		<div class="slh-inline-note" id="slh-preview-error" style="display:none; margin-top:10px;"></div>
	</div>

	<div class="slh-toast" id="slh-toast"></div>
</div>
<script>
(function(){
	var API = '/api/plugin/' + (window.SLH_PLUGIN_NAME || 'fpp-plugin-santaslist') + '/';
	function apiGet(e){ return fetch(API+e,{credentials:'same-origin'}).then(function(r){return r.json();}); }
	function apiPost(e,b){ return fetch(API+e,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(b||{})}).then(function(r){return r.json();}); }
	function fppGet(path){ return fetch('/api/' + path, {credentials:'same-origin'}).then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); }); }
	function $(s){ return document.querySelector(s); }

	var previewModels = { top: null, bottom: null };
	var previewScale = 4; // upscale small panels so they're actually visible on screen

	function drawModelPreview(canvasId, modelName) {
		var canvas = $(canvasId);
		if (!modelName) {
			canvas.width = 200; canvas.height = 60;
			var ctx0 = canvas.getContext('2d');
			ctx0.fillStyle = '#000'; ctx0.fillRect(0,0,canvas.width,canvas.height);
			ctx0.fillStyle = '#666'; ctx0.font = '12px sans-serif';
			ctx0.fillText('No model selected', 10, 34);
			return Promise.resolve();
		}
		return Promise.all([
			fppGet('overlays/model/' + encodeURIComponent(modelName)),
			fppGet('overlays/model/' + encodeURIComponent(modelName) + '/data')
		]).then(function (results) {
			var w = results[0] && (results[0].width || results[0].Width);
			var h = results[0] && (results[0].height || results[0].Height);
			var data = results[1] && results[1].data;
			if (!Array.isArray(data)) throw new Error('Unexpected response shape from /data');
			if (!w || !h) throw new Error('Could not determine model dimensions');

			canvas.width = w; canvas.height = h;
			var ctx = canvas.getContext('2d');
			var img = ctx.createImageData(w, h);
			var n = w * h;
			for (var i = 0; i < n; i++) {
				img.data[i*4]   = data[i*3]   || 0;
				img.data[i*4+1] = data[i*3+1] || 0;
				img.data[i*4+2] = data[i*3+2] || 0;
				img.data[i*4+3] = 255;
			}
			ctx.putImageData(img, 0, 0);

			canvas.style.width = (w * previewScale) + 'px';
			canvas.style.height = (h * previewScale) + 'px';
		});
	}

	function refreshPreview() {
		var jobs = [];
		if (previewModels.top) jobs.push(drawModelPreview('#slh-preview-top-canvas', previewModels.top));
		if (previewModels.bottom) jobs.push(drawModelPreview('#slh-preview-bottom-canvas', previewModels.bottom));
		Promise.all(jobs).then(function () {
			$('#slh-preview-error').style.display = 'none';
		}).catch(function (err) {
			$('#slh-preview-error').style.display = 'block';
			$('#slh-preview-error').textContent = 'Could not read live pixel data: ' + err.message;
		});
	}

	function initPreview() {
		apiGet('config').then(function (cfg) {
			previewModels.top = cfg.top_model || null;
			previewModels.bottom = cfg.bottom_model || null;
			$('#slh-preview-top-label').textContent = 'Top zone' + (previewModels.top ? ' \u2014 ' + previewModels.top : ' (no model selected)');
			$('#slh-preview-bottom-label').textContent = 'Bottom zone' + (previewModels.bottom ? ' \u2014 ' + previewModels.bottom : ' (no model selected)');
			refreshPreview();
			setInterval(refreshPreview, 2500);
		});
	}

	function fmtAgo(ts){
		if (!ts) return 'never';
		var s = Math.floor(Date.now()/1000) - ts;
		if (s < 60) return s + 's ago';
		if (s < 3600) return Math.floor(s/60) + 'm ago';
		return Math.floor(s/3600) + 'h ago';
	}
	function refresh(){
		apiGet('status').then(function(res){
			if (!res) return;
			var seal = $('#slh-status-seal'), text = $('#slh-status-text');
			if (!res.enabled) { seal.className='slh-seal'; text.textContent='Disabled'; }
			else if (res.last_error) { seal.className='slh-seal bad'; text.textContent='Error'; }
			else { seal.className='slh-seal ok'; text.textContent='Running'; }

			$('#slh-status-now').textContent = res.enabled
				? ((res.current_list ? res.current_list.toUpperCase() : 'Starting up') +
					(res.seconds_until_switch ? ' \u00b7 switches in ' + res.seconds_until_switch + 's' : ''))
				: 'Display is turned off.';

			$('#slh-nice-count').textContent = (res.nice_total != null ? res.nice_total : '\u2013') + ' names';
			$('#slh-naughty-count').textContent = (res.naughty_total != null ? res.naughty_total : '\u2013') + ' names';

			var syncBits = ['Last pulled from hub: ' + fmtAgo(res.last_pull_at)];
			if (res.last_error) syncBits.push('Error: ' + res.last_error);
			$('#slh-status-sync').textContent = syncBits.join('  \u00b7  ');
		});
	}
	document.addEventListener('DOMContentLoaded', function(){
		refresh();
		setInterval(refresh, 5000);
		initPreview();
		$('#slh-refresh-btn').addEventListener('click', function(){
			apiPost('refresh', {}).then(refresh);
		});
	});
})();
</script>

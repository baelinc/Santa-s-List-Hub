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

	<div class="slh-toast" id="slh-toast"></div>
</div>
<script>
(function(){
	var API = '/api/plugin/' + (window.SLH_PLUGIN_NAME || 'fpp-plugin-santaslist') + '/';
	function apiGet(e){ return fetch(API+e,{credentials:'same-origin'}).then(function(r){return r.json();}); }
	function apiPost(e,b){ return fetch(API+e,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(b||{})}).then(function(r){return r.json();}); }
	function $(s){ return document.querySelector(s); }
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
		$('#slh-refresh-btn').addEventListener('click', function(){
			apiPost('refresh', {}).then(refresh);
		});
	});
})();
</script>

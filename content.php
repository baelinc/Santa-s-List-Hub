<script>window.SLH_PLUGIN_NAME = <?php echo json_encode(isset($pluginName) ? $pluginName : 'fpp-plugin-santaslist'); ?>;</script>
<div class="slh-app">

	<div class="slh-header">
		<div class="slh-title">
			<h1>&#127877; Santa's List Hub</h1>
			<span>Naughty &amp; Nice display for your pixel panels</span>
		</div>
		<div class="slh-status-pill" id="slh-status-pill">
			<span class="slh-seal pending" id="slh-status-seal"></span>
			<span id="slh-status-text">Checking&hellip;</span>
			<span id="slh-status-detail" style="margin-left:6px; opacity:0.8;"></span>
		</div>
	</div>

	<div class="slh-top-grid">

		<!-- Panel layout schematic -->
		<div class="slh-schematic">
			<div class="slh-eyebrow">Step 1 &middot; Your panels</div>

			<div class="slh-field-row" style="width:100%;">
				<div class="slh-field">
					<label for="slh-panel-w">Panel pixel width</label>
					<input type="number" id="slh-panel-w" min="1" max="256">
				</div>
				<div class="slh-field">
					<label for="slh-panel-h">Panel pixel height</label>
					<input type="number" id="slh-panel-h" min="1" max="256">
				</div>
			</div>
			<div class="slh-preview-toggle" style="margin-bottom:4px;">
				<button type="button" class="slh-panel-preset" data-w="32" data-h="16">32&times;16</button>
				<button type="button" class="slh-panel-preset" data-w="64" data-h="32">64&times;32</button>
				<button type="button" class="slh-panel-preset" data-w="32" data-h="32">32&times;32</button>
				<button type="button" class="slh-panel-preset" data-w="16" data-h="16">16&times;16</button>
			</div>

			<div class="slh-field-row" style="width:100%;">
				<div class="slh-field">
					<label for="slh-top-wide">Top zone: panels wide</label>
					<input type="number" id="slh-top-wide" min="1" max="20">
				</div>
				<div class="slh-field">
					<label for="slh-top-tall">Top zone: panels tall</label>
					<input type="number" id="slh-top-tall" min="1" max="20">
				</div>
			</div>
			<div class="slh-field-row" style="width:100%;">
				<div class="slh-field">
					<label for="slh-bottom-wide">Bottom zone: panels wide</label>
					<input type="number" id="slh-bottom-wide" min="1" max="20">
				</div>
				<div class="slh-field">
					<label for="slh-bottom-tall">Bottom zone: panels tall</label>
					<input type="number" id="slh-bottom-tall" min="1" max="20">
				</div>
			</div>

			<div class="slh-rig">
				<div class="slh-zone-label" id="slh-schematic-top-label">1 panel wide &middot; 64&times;32</div>
				<div class="slh-panel-top" id="slh-schematic-top">NICE</div>
			</div>
			<div class="slh-rig">
				<div class="slh-zone-label" id="slh-schematic-grid-label">3&times;2 panels &middot; 192&times;64</div>
				<div class="slh-panel-grid" id="slh-schematic-grid"></div>
			</div>
			<div class="slh-preview-toggle">
				<button type="button" id="slh-preview-nice-btn" class="slh-primary">Preview Nice</button>
				<button type="button" id="slh-preview-naughty-btn">Preview Naughty</button>
			</div>
		</div>

		<!-- Connect to hub -->
		<div class="slh-card" style="margin-bottom:0;">
			<div class="slh-eyebrow">Step 2</div>
			<h2>Connect to your hub</h2>
			<p class="slh-hint">Find your API key on the Hub under Show Settings &rarr; Display Connections.</p>

			<div class="slh-field">
				<label for="slh-hub-url">Hub URL</label>
				<input type="text" id="slh-hub-url" placeholder="https://yourhub.com/your-show">
			</div>
			<div class="slh-field">
				<label for="slh-api-key">API key</label>
				<input type="password" id="slh-api-key" placeholder="SLH_xxxxxxxxxxxxxxxx">
			</div>
			<button type="button" id="slh-test-btn">Test connection</button>
		</div>
	</div>

	<!-- What to show -->
	<div class="slh-card">
		<div class="slh-eyebrow">Step 2</div>
		<h2>What to display</h2>

		<div class="slh-mode-picker">
			<label class="slh-mode-card">
				<input type="radio" name="slh-mode" value="alternate">
				<div class="slh-mode-title">Alternate</div>
				<div class="slh-mode-desc">Switch back and forth between the Nice list and the Naughty list.</div>
			</label>
			<label class="slh-mode-card">
				<input type="radio" name="slh-mode" value="nice_only">
				<div class="slh-mode-title">Nice list only</div>
				<div class="slh-mode-desc">Always show who's on the Nice list.</div>
			</label>
			<label class="slh-mode-card">
				<input type="radio" name="slh-mode" value="naughty_only">
				<div class="slh-mode-title">Naughty list only</div>
				<div class="slh-mode-desc">Always show who's on the Naughty list.</div>
			</label>
		</div>

		<div class="slh-field" id="slh-alternate-seconds-field" style="max-width:260px;">
			<label for="slh-alternate-seconds">Seconds per list before switching</label>
			<input type="number" id="slh-alternate-seconds" min="5" max="300" step="1">
		</div>
	</div>

	<!-- Top zone -->
	<div class="slh-card">
		<div class="slh-eyebrow">Step 3</div>
		<h2>Top zone &mdash; the label panel</h2>
		<p class="slh-hint">Shows "NICE" or "NAUGHTY", color-coded, across your top zone (<span id="slh-top-hint-size">1 panel &middot; 64&times;32</span>).</p>

		<div class="slh-field">
			<label for="slh-top-model">Overlay model</label>
			<select id="slh-top-model"></select>
			<div class="slh-inline-note" id="slh-top-model-warning" style="display:none;"></div>
			<div class="slh-inline-note" id="slh-no-models-hint" style="display:none;">
				No pixel overlay models found yet. Create one in FPP under Models for your top-row panel, then reload this page.
			</div>
		</div>

		<div class="slh-field-row">
			<div class="slh-field">
				<label for="slh-top-font">Font</label>
				<select id="slh-top-font"></select>
			</div>
			<div class="slh-field">
				<label for="slh-top-font-size">Font size</label>
				<input type="number" id="slh-top-font-size" min="6" max="100">
			</div>
			<div class="slh-field">
				<label>&nbsp;</label>
				<label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--slh-text-muted);">
					<input type="checkbox" id="slh-top-antialias" style="width:auto;"> Anti-alias text
				</label>
			</div>
		</div>

		<div class="slh-field-row">
			<div class="slh-color-field slh-field">
				<label for="slh-nice-color">Nice color</label>
				<div class="slh-color-row">
					<input type="color" id="slh-nice-color">
				</div>
			</div>
			<div class="slh-color-field slh-field">
				<label for="slh-naughty-color">Naughty color</label>
				<div class="slh-color-row">
					<input type="color" id="slh-naughty-color">
				</div>
			</div>
		</div>
	</div>

	<!-- Bottom zone -->
	<div class="slh-card">
		<div class="slh-eyebrow">Step 4</div>
		<h2>Bottom zone &mdash; the names grid</h2>
		<p class="slh-hint">Scrolls through the names on the current list across your bottom zone (<span id="slh-bottom-hint-size">3&times;2 panels &middot; 192&times;64</span>).</p>

		<div class="slh-field">
			<label for="slh-bottom-model">Overlay model</label>
			<select id="slh-bottom-model"></select>
			<div class="slh-inline-note" id="slh-bottom-model-warning" style="display:none;"></div>
		</div>

		<div class="slh-field-row">
			<div class="slh-field">
				<label for="slh-bottom-font">Font</label>
				<select id="slh-bottom-font"></select>
			</div>
			<div class="slh-field">
				<label for="slh-bottom-font-size">Font size</label>
				<input type="number" id="slh-bottom-font-size" min="6" max="100">
			</div>
			<div class="slh-color-field slh-field">
				<label for="slh-bottom-color">Text color</label>
				<input type="color" id="slh-bottom-color">
			</div>
		</div>

		<div class="slh-field-row">
			<div class="slh-field">
				<label for="slh-bottom-position">Scroll direction</label>
				<select id="slh-bottom-position">
					<option value="Right to Left">Right to left</option>
					<option value="Left to Right">Left to right</option>
					<option value="Bottom to Top">Bottom to top</option>
					<option value="Top to Bottom">Top to bottom</option>
					<option value="Center">Centered, no scroll</option>
				</select>
			</div>
			<div class="slh-field">
				<label for="slh-bottom-speed">Scroll speed <span id="slh-bottom-speed-out"></span></label>
				<input type="range" id="slh-bottom-speed" min="5" max="150" step="1">
			</div>
			<div class="slh-field">
				<label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--slh-text-muted); margin-top:20px;">
					<input type="checkbox" id="slh-bottom-antialias" style="width:auto;"> Anti-alias text
				</label>
			</div>
		</div>

		<div class="slh-field-row">
			<div class="slh-field">
				<label for="slh-separator">Separator between names</label>
				<input type="text" id="slh-separator" maxlength="6">
			</div>
			<div class="slh-field">
				<label for="slh-no-names-message">Message when a list is empty</label>
				<input type="text" id="slh-no-names-message" maxlength="80">
			</div>
		</div>

		<div class="slh-inline-note" id="slh-scroll-preview"></div>
	</div>

	<!-- Advanced -->
	<div class="slh-card">
		<div class="slh-eyebrow">Advanced</div>
		<h2>Sync settings</h2>
		<div class="slh-field-row">
			<div class="slh-field">
				<label for="slh-refresh-minutes">Check the hub for new names every</label>
				<select id="slh-refresh-minutes">
					<option value="1">1 minute</option>
					<option value="2">2 minutes</option>
					<option value="5">5 minutes</option>
					<option value="10">10 minutes</option>
				</select>
			</div>
			<div class="slh-field">
				<label for="slh-max-names">Max names to pull per list</label>
				<input type="number" id="slh-max-names" min="10" max="1000" step="10">
			</div>
		</div>
	</div>

	<hr class="slh-divider">

	<div class="slh-card">
		<div class="slh-toggle-row">
			<div>
				<h2 style="margin-bottom:2px;">Display enabled</h2>
				<p class="slh-hint" style="margin:0;">Turn this off to blank both zones without losing your settings.</p>
			</div>
			<label class="slh-switch">
				<input type="checkbox" id="slh-enabled">
				<span class="slh-switch-track"></span>
			</label>
		</div>
	</div>

	<div class="slh-footer-actions">
		<button type="button" id="slh-refresh-btn" class="slh-ghost">Refresh now</button>
		<button type="button" id="slh-save-btn" class="slh-primary">Save settings</button>
	</div>

	<div class="slh-toast" id="slh-toast"></div>
</div>

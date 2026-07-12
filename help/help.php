<div class="slh-app">
	<div class="slh-header">
		<div class="slh-title">
			<h1>&#127877; Santa's List Hub</h1>
			<span>Setup guide</span>
		</div>
	</div>

	<div class="slh-card">
		<h2>1. Tell the plugin about your panels</h2>
		<p class="slh-hint">On the Settings page, in each of the Top Zone and Bottom Zone cards, enter that
			zone's panel pixel size (e.g. 64&times;32) and how many panels make up the zone. The two zones
			are independent &mdash; your label panel and your names-grid panels can be different hardware or
			mounted in different orientations. Use the <b>Rotate 90&deg;</b> button next to either zone's panel
			size to swap width/height for a portrait-mounted panel, without affecting the other zone.</p>
	</div>

	<div class="slh-card">
		<h2>2. Create two pixel overlay models in FPP</h2>
		<p class="slh-hint">Go to <b>Displays &rarr; Models</b> and create two Pixel Overlay Models sized to match
			what the Settings page computed for you: one for the top label row, one for the bottom names grid.
			Give them names you'll recognize, e.g. <code>NN-Label</code> and <code>NN-Names</code>.</p>
	</div>

	<div class="slh-card">
		<h2>3. Get your API key from the hub</h2>
		<p class="slh-hint">On Santa's List Hub, open your show, go to <b>Show Settings &rarr; Display Connections</b>, and copy the API key and your show's URL.</p>
	</div>

	<div class="slh-card">
		<h2>4. Connect and pick your models</h2>
		<p class="slh-hint">Open <b>Content Setup &rarr; Santa's List Hub</b>, paste in your Hub URL and API key, test the connection, then choose the two models you created in step 2 for the top and bottom zones. If a model's real size doesn't match your panel spec, the page will warn you.</p>
	</div>

	<div class="slh-card">
		<h2>Scheduling it alongside your other pixels</h2>
		<p class="slh-hint">If this Pi also drives other pixel outputs (a prop, a matrix, etc.), you don't need to
			do anything differently for those &mdash; schedule your regular sequences/playlists in FPP's Scheduler
			exactly as you always have. This plugin only ever fills in the gaps when FPP is otherwise idle, and
			steps aside the instant something else starts playing.</p>
		<p class="slh-hint">To turn the Naughty/Nice display itself on and off on a schedule, use FPP's Scheduler
			with a <b>Run Script</b> command, and pick <code>santaslist-enable.sh</code> or
			<code>santaslist-disable.sh</code> &mdash; installing this plugin adds both to FPP's script list
			automatically. Add two Scheduler entries (e.g. enable at 6pm, disable at 10pm) the same way you'd
			schedule any playlist. If you don't see them in the picker, re-run this plugin's install
			(<code>scripts/fpp_install.sh</code>) and restart fppd.</p>
	</div>

	<div class="slh-card">
		<h2>Why is my panel blank even though the plugin says it's connected?</h2>
		<p class="slh-hint">Pixel overlays only render on top of <i>active</i> output &mdash; if FPP is sitting idle
			(not playing any sequence, playlist, or effect), there's nothing for the overlay to draw onto, even
			though everything else is working correctly. By default this plugin auto-starts a minimal looping blank
			playlist whenever it detects FPP is idle, so this shouldn't come up &mdash; but if you've turned off
			"Keep FPP actively playing" in Settings, you'll need to keep something else playing yourself.</p>
	</div>

	<div class="slh-card">
		<h2>How it stays in sync</h2>
		<p class="slh-hint">
			A background process checks the hub for new names on the schedule you set under Advanced (every 1&ndash;10 minutes),
			and separately switches between the Nice and Naughty lists on the schedule you set in "What to display."
			Hitting <b>Refresh now</b> pulls immediately without waiting.
		</p>
	</div>
</div>

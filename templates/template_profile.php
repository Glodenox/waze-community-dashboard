<?php include('templates/template_header.php'); ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
<?php if (isset($user)) { ?>
				<h1 class="page-header">Profile</h1>
					<div class="fluid-container">
						<h3>Actions</h3>
						<p><button class="btn btn-primary" id="resync">Resync profile data with Slack</button> <a href="<?=ROOT_FOLDER?>profile/logout" class="btn btn-danger">Log out</a></p>
						<h3>Claimed Management Area</h3>
						<p>The area defined below will be used to decide which reports are of importance to you. Any reports located outside of this area will not count towards the counters in the sidebar.</p>
						<div id="areaMap" style="height:20vw; position:relative">
							<div unselectable="on" class="olControlNoSelect" style="position: absolute;top: 10px;z-index: 2000;text-align: center;left: 0;right: 0;" id="heatmap-message">
								<span style="background-color: #fff;padding: 3px;border: 2px solid #337ab7;color: #777;">Heatmap loading...</span>
							</div>
						</div>
						<h3>Data retrieved from Slack</h3>
						<dl class="dl-horizontal">
							<dt>Name</dt>
							<dd><?=$user->name?></dd>
							<dt>Slack User ID</dt>
							<dd><?=$user->slack_user_id?></dd>
							<dt>Slack Team ID</dt>
							<dd><?=$user->slack_team_id?></dd>
						</dl>
						<h3>Preferences</h3>
						<form class="form-horizontal">
							<div class="form-group">
								<label class="control-label col-sm-3">Report processing</label>
								<div class="help-block col-sm-9">After changing the status of a report</div>
								<div class="col-sm-9 col-sm-offset-3">
									<div class="radio">
										<label><input type="radio" name="auto-jump" id="autoJumpOn"<?=($user->process_auto_jump ? ' checked="checked"' : '')?>/> jump to the next report in the results</label>
									</div>
									<div class="radio">
										<label><input type="radio" name="auto-jump" id="autoJumpOff"<?=(!$user->process_auto_jump ? ' checked="checked"' : '')?>/> remain at the current report</label>
									</div>
								</div>
							</div>
<?php /*							<div class="form-group">
								<label class="control-label col-sm-3" for="editorLevel">Editor level</label>
								<div class="col-sm-9 col-sm-offset-3">
									<select id="editorLevel" class="form-control" aria-describedby="editorLevelHelp">
										<option value="">Not set</option>
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="5">5</option>
										<option value="6">6</option>
									</select>
								</div>
								<span class="help-block col-sm-9" id="editorLevelHelp">Your editor level within the Waze Map Editor. This setting is only used for visual cues.</span>
							</div>
*/ ?>
							<div class="form-group">
								<label class="control-label col-sm-3">Automatically subscribe to reports</label>
								<div class="help-block col-sm-9">When you perform any of the selected actions below, the system will automatically subscribe you to receive notifications on this report (<?=$user->follow_bits?>)</div>
								<div class="col-sm-9 col-sm-offset-3">
<?php
$subscribe_actions = array(
	ACTION_SET_STATUS => 'Change report status',
	ACTION_SET_LEVEL => 'Change editor level',
	ACTION_SET_PRIORITY => 'Change report priority'/*,
	ACTION_MESSAGE => 'Posted a message'*/
);
foreach ($subscribe_actions as $action_idx => $action) { ?>
									<div class="checkbox">
										<label>
											<input type="checkbox" name="follow-<?=$action_idx?>" value="<?=$action_idx?>"<?=(($user->follow_bits & (1 << $action_idx)) == 0 ? '' : ' checked=""')?> />
											<?=$action?>
										</label>
									</div>
<?php } ?>
								</div>
							</div>
							<div class="form-group">
								<label class="control-label col-sm-3">Notification triggers</label>
								<div class="help-block col-sm-9">When any of these actions is performed on a report you are subscribed to, you will receive a Slack notification (<?=$user->notify_bits?>)</div>
								<div class="col-sm-9 col-sm-offset-3">
<?php
$notification_actions = array(
	ACTION_INSERT => 'Report created',
	ACTION_SET_STATUS => 'Report status changed',
	ACTION_SET_LEVEL => 'Editor level changed',
	ACTION_SET_PRIORITY => 'Report priority changed'/*,
	ACTION_MESSAGE => 'Posted a message'*/
);
foreach ($notification_actions as $action_idx => $action) { ?>
									<div class="checkbox">
										<label>
											<input type="checkbox" name="notify-<?=$action_idx?>" value="<?=$action_idx?>"<?=(($user->notify_bits & (1 << $action_idx)) == 0 ? '' : ' checked=""')?> />
											<?=$action?>
										</label>
									</div>
<?php } ?>
								</div>
							</div>
						</form>
						<h3>Personal Statistics</h3>
<?php if ($action_stats !== false && count($action_stats) != 0) { ?>
						<div class="col-sm-12 col-md-6 text-center">
							<div id="actionChart" style="height:300px"></div>
							<strong>Actions performed</strong>
						</div>
<?php } ?>
					</div>
					<script src="<?=ROOT_FOLDER?>js/OpenLayers.js"></script>
					<script src="<?=ROOT_FOLDER?>js/heatmap.js"></script>
					<script src="<?=ROOT_FOLDER?>js/heatmap-openlayers-renderer.js"></script>
					<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
					<script type="text/javascript">
function loadPage(url, params, callback) {
	var request = new XMLHttpRequest();
	request.addEventListener('load', callback);
	request.responseType = 'json';
	var paramUrl = url + (params && Object.keys(params).length > 0 ? '?' + Object.keys(params).map(function(key) { return key + '=' + params[key]; }).join('&') : '');
	request.open('GET', paramUrl);
	request.send();
};

document.getElementById('resync').addEventListener('click', function() {
	var statusId = Status.show('info', 'Synchronizing data with Slack...');
	loadPage('<?=ROOT_FOLDER?>profile/resync', {}, function() {
		Status.hide(statusId);
		if (this.response.ok) {
			Status.show('success', 'Synchronation successful', 4000);
		} else {
			console.log('Synchronisation: error received', this.response);
			Status.show('danger', this.response.error);
		}
	});
});
var mapProjection = 'EPSG:900913';
var siteProjection = 'CRS:84';
var bounds = new OpenLayers.Bounds(<?=$management_area->west?>, <?=$management_area->south?>, <?=$management_area->east?>,<?=$management_area->north?>).transform(siteProjection, mapProjection);
// Management area
var areaMap = new OpenLayers.Map({
	div: 'areaMap',
	center: bounds.getCenterLonLat(),
	layers: [
		new OpenLayers.Layer.XYZ('Waze Livemap', [
			'https://worldtiles1.waze.com/tiles/${z}/${x}/${y}.png',
			'https://worldtiles2.waze.com/tiles/${z}/${x}/${y}.png',
			'https://worldtiles3.waze.com/tiles/${z}/${x}/${y}.png',
			'https://worldtiles4.waze.com/tiles/${z}/${x}/${y}.png'
		], {
			projection: mapProjection,
			numZoomLevels: 18,
			attribution: '&copy; 2006-' + (new Date()).getFullYear() + ' <a href="https://www.waze.com/livemap" target="_blank">Waze Mobile</a>. All Rights Reserved.'
		})
	],
	zoom: 7
});
areaMap.zoomToExtent(bounds);
OpenLayers.Feature.Vector.style['default'].strokeWidth = 2;
OpenLayers.Feature.Vector.style['default'].fillColor = '#ffffff';
OpenLayers.Feature.Vector.style['default'].strokeColor = '#428bca';
OpenLayers.Feature.Vector.style['default'].fillOpacity = 1;
OpenLayers.Feature.Vector.style['default'].cursor = 'pointer';

var vectors = new OpenLayers.Layer.Vector('Vector Layer', {
	eventListeners: {
		'featuremodified': e => saveManagementArea(e.feature.geometry.getBounds().clone().transform(mapProjection, siteProjection))
	}
});
areaMap.addLayer(vectors);
var managementArea = new OpenLayers.Feature.Vector(bounds.toGeometry(), {}, {
	strokeColor: '#428bca',
	fillColor: '#428bca',
	fillOpacity: 0.3
});
vectors.addFeatures([ managementArea ]);
var modifyControl = new OpenLayers.Control.ModifyFeature(vectors, {
	mode: OpenLayers.Control.ModifyFeature.RESIZE | OpenLayers.Control.ModifyFeature.RESHAPE | OpenLayers.Control.ModifyFeature.DRAG,
	createVertices: false,
	standalone: true,
	toggle: false,
	clickout: false
});
areaMap.addControl(modifyControl);
modifyControl.activate();
modifyControl.selectFeature(managementArea);

function saveManagementArea(bounds) {
	var statusId = Status.show('info', 'Saving management area');
	loadPage('<?=ROOT_FOLDER?>profile/change-area', {
		north: bounds.top,
		east: bounds.right,
		south: bounds.bottom,
		west: bounds.left
	}, function() {
		Status.hide(statusId);
		if (this.response.ok) {
			Status.show('success', 'Management area saved', 4000);
		} else {
			console.log('Management area: error received', this.response);
			Status.show('danger', this.response.error);
		}
	});
}
var heatmap = new OpenLayers.Layer.Vector('Heatmap', {
	opacity: 0.7,
	renderers: [ 'Heatmap' ],
	rendererOptions: {
		weight: 'count',
		heatmapConfig: { radius: 15 }
	}
});
areaMap.addLayer(heatmap);
areaMap.raiseLayer(heatmap, -1);
// Heatmap in management area map
loadPage('<?=ROOT_FOLDER?>reports/heatmap', {}, function() {
	var heatmapData = [];
	this.response.heatmap.forEach(function(entry) {
		var point = new OpenLayers.Geometry.Point(entry.lon, entry.lat).transform(siteProjection, mapProjection)
		heatmapData.push(new OpenLayers.Feature.Vector(point, { count: entry.reports}));
	});
	heatmap.addFeatures(heatmapData);
	document.getElementById('heatmap-message').style.display = 'none';
});
// Statistics
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(function() {
	var actions = new google.visualization.DataTable();
	actions.addColumn('string', 'Action');
	actions.addColumn('number', 'Times performed');
	actions.addRows([
<?php 
if (count($action_stats) > 0) {
	$last_key = end($action_stats)->action_id;
	foreach ($action_stats as $action_stat) {
		echo "		['" . ACTIONS[$action_stat->action_id] . "'," . $action_stat->total . ']' . ($action_stat->action_id != $last_key ? ',' : '');
	}
} ?>
	]);
	var options = { pieHole: 0.4, sliceVisibilityThreshold: 0.05, legend: { position: 'bottom' } };
	var chart = new google.visualization.PieChart(document.getElementById('actionChart'));
	chart.draw(actions, options);
	window.addEventListener('resize', function() {
		chart.draw(actions, options);
	});
});
document.getElementById('autoJumpOn').addEventListener('click', function() {
	loadPage('<?=ROOT_FOLDER?>profile/change-autojump', { autojump: true }, function() {
		if (this.response.ok) {
			Status.show('success', 'Preference updated', 4000);
		} else {
			Status.show('danger', this.response.error);
		}
	});
});
document.getElementById('autoJumpOff').addEventListener('click', function() {
	loadPage('<?=ROOT_FOLDER?>profile/change-autojump', { autojump: false }, function() {
		if (this.response.ok) {
			Status.show('success', 'Preference updated', 4000);
		} else {
			Status.show('danger', this.response.error);
		}
	});
});
					</script>
<?php } // end logged in test ?>
				</div>
<?php include('templates/template_footer.php'); ?>

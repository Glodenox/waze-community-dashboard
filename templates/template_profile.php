<?php include('templates/template_header.php'); ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
<?php if (isset($user)) { ?>
				<h1 class="page-header">Profile</h1>
					<div class="fluid-container">
						<h3>Actions</h3>
						<p><button class="btn btn-primary" id="resync">Resync profile data with Slack</button> <a href="<?=ROOT_FOLDER?>profile/logout" class="btn btn-danger">Log out</a></p>
						<h3>Claimed Management Area</h3>
						<p>The area defined below will be used to decide which reports are of importance to you. Any reports located outside of this area will not count towards the counters in the sidebar.</p>
						<div id="areaMap" style="height: 20vw;"></div>
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
								<span class="help-block col-sm-9" id="editorLevelHelp>Your editor level within the Waze Map Editor. This setting is only used for visual cues.</span>
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
					<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDH7C24331Mc6DQJc7xf7gxMOb3Z69yZ-E&amp;libraries=visualization"></script>
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
var managementArea = {
	north: <?=$management_area->north?>,
	south: <?=$management_area->south?>,
	west: <?=$management_area->west?>,
	east: <?=$management_area->east?>
};
// Management area
var center = { lat: (managementArea.north + managementArea.south) / 2, lng: (managementArea.west + managementArea.east) / 2 };
var areaMap = new google.maps.Map(document.getElementById('areaMap'), {
	center: center,
	zoom: 7,
	clickableIcons: false,
	mapTypeControl: false,
	streetViewControl: false
});
var areaBounds = new google.maps.Rectangle({
	bounds: managementArea,
	map: areaMap,
	editable: true,
	draggable: true,
	zIndex: 10
});
areaMap.fitBounds(managementArea);
var dragging = false;
areaBounds.addListener('drag', function() {
	dragging = true;
});
areaBounds.addListener('dragend', function() {
	dragging = false;
	saveManagementArea();
});
areaBounds.addListener('bounds_changed', function() {
	if (!dragging) {
		saveManagementArea();
	}
});
function saveManagementArea() {
	var statusId = Status.show('info', 'Saving management area');
	loadPage('<?=ROOT_FOLDER?>profile/change-area', areaBounds.bounds.toJSON(), function() {
		Status.hide(statusId);
		if (this.response.ok) {
			Status.show('success', 'Management area saved', 4000);
		} else {
			console.log('Management area: error received', this.response);
			Status.show('danger', this.response.error);
		}
	});
}
google.maps.event.addDomListener(window, "resize", function() {
	var center = areaMap.getCenter();
	google.maps.event.trigger(areaMap, "resize");
	areaMap.setCenter(center); 
});
// Heatmap in management area map
loadPage('<?=ROOT_FOLDER?>reports/heatmap', {}, function() {
	var heatmapData = [];
	this.response.heatmap.forEach(function(entry) {
		heatmapData.push({location: new google.maps.LatLng(entry.lat, entry.lon), weight: entry.reports});
	});
	var heatmap = new google.maps.visualization.HeatmapLayer({
		data: heatmapData,
		maxIntensity: 10
	});
	heatmap.setMap(areaMap);
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
		console.log('resized');
		chart.draw(actions, options);
	});
});
					</script>
<?php } // end logged in test ?>
				</div>
<?php include('templates/template_footer.php'); ?>

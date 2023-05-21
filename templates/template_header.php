<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8"/>
		<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
		<meta name="viewport" content="width=device-width, initial-scale=1"/>
		<meta name="description" content="<?=$meta_tags['description']?>"/>
		<meta name="author" content="<?=$meta_tags['author']?>"/>

		<title><?=htmlspecialchars($page_title)?></title>

		<link rel="icon" href="<?=ROOT_FOLDER?>images/favicon.ico" type="image/x-icon" />
		<!-- Bootstrap core CSS -->
		<link href="<?=ROOT_FOLDER?>css/bootstrap.min.css?<?=substr(md5(filectime('css/bootstrap.min.css')), 0, 10)?>" rel="stylesheet"/>
		<link href="<?=ROOT_FOLDER?>css/dashboard.css?<?=substr(md5(filectime('css/dashboard.css')), 0, 10)?>" rel="stylesheet"/>
		<link href="<?=ROOT_FOLDER?>css/font-awesome.min.css?<?=substr(md5(filectime('css/font-awesome.min.css')), 0, 10)?>" rel="stylesheet"/>
	</head>

	<body>
		<nav class="navbar navbar-inverse navbar-fixed-top">
			<div class="container-fluid">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<a class="navbar-brand" href="<?=ROOT_FOLDER?>"><i class="fa fa-fw fa-dashboard"></i> Waze Community Dashboard</a>
				</div>
				<div id="navbar" class="navbar-collapse collapse">
					<ul class="nav navbar-nav navbar-right">
<?php if (isset($user)) { ?>
						<li><a href="<?=ROOT_FOLDER?>profile"><img src="<?=$user->avatar?>" style="width:25px;height:25px;border-radius:12px;margin:-5px 0" />&nbsp; <?=$user->name?></a></li>
<?php } else { ?>
						<li><a href="https://slack.com/openid/connect/authorize?response_type=code&amp;scope=openid,profile&amp;team=TCKQCM9QS&amp;client_id=<?=SLACK_CLIENT_ID?>&amp;redirect_uri=<?=BASE_URL.'auth'?>"><i class="fa fa-fw fa-user-times fa-lg"></i>&nbsp; Log In</a></li>
<?php } ?>
					</ul>
					<ul class="nav navbar-nav visible-xs-block">
						<li<?php if (isset($active_module) && $active_module == 'index') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>"><i class="fa fa-home fa-fw fa-lg"></i>&nbsp; Overview</a></li>
					</ul>
					<ul class="nav navbar-nav visible-xs-block">
						<li<?php if (isset($active_module) && $active_module == 'reports') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>reports"><i class="fa fa-map-marker fa-fw fa-lg"></i>&nbsp; Reports <span class="badge"><?=$todo_count['reports']?></span></a></li>
						<li<?php if (isset($active_module) && $active_module == 'news_items') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>news-items"><i class="fa fa-rss-square fa-fw fa-lg"></i>&nbsp; News Items <span class="badge">0</span></a></li>
						<li<?php if (isset($active_module) && $active_module == 'support_topics') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>support-topics"><i class="fa fa-life-ring fa-fw fa-lg"></i>&nbsp; Support Topics <span class="badge"><?=$todo_count['support-topics']?></span></a></li>
					</ul>
					<ul class="nav navbar-nav visible-xs-block">
						<li<?php if (isset($active_module) && $active_module == 'data_sources') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>data-sources"><i class="fa fa-database fa-fw fa-lg"></i>&nbsp; Data Sources</a></li>
					</ul>
					<ul class="nav navbar-nav visible-xs-block">
						<li<?php if (isset($active_module) && $active_module == 'suggestions') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>suggestions"><i class="fa fa-commenting-o fa-fw fa-lg"></i>&nbsp; Feature Suggestions</a></li>
					</ul>
				</div>
			</div>
		</nav>

		<script src="<?=ROOT_FOLDER?>js/jquery.min.js?<?=substr(md5(filectime('js/jquery.min.js')), 0, 10)?>"></script>
		<script src="<?=ROOT_FOLDER?>js/jquery-ui.min.js?<?=substr(md5(filectime('js/jquery-ui.min.js')), 0, 10)?>"></script>
		<script src="<?=ROOT_FOLDER?>js/bootstrap.min.js?<?=substr(md5(filectime('js/bootstrap.min.js')), 0, 10)?>"></script>

		<div class="container-fluid">
			<div class="row">
				<div class="col-sm-3 col-md-2 sidebar">
					<ul class="nav nav-sidebar">
						<li<?php if (isset($active_module) && $active_module == 'index') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>"><i class="fa fa-home fa-fw fa-lg"></i>&nbsp; Overview</a></li>
					</ul>
					<ul class="nav nav-sidebar">
						<li<?php if (isset($active_module) && $active_module == 'reports') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>reports"><i class="fa fa-map-marker fa-fw fa-lg"></i>&nbsp; Reports <span class="badge"><?=$todo_count['reports']?></span></a></li>
						<li<?php if (isset($active_module) && $active_module == 'news_items') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>news-items"><i class="fa fa-rss-square fa-fw fa-lg"></i>&nbsp; News Items <span class="badge">0</span></a></li>
						<li<?php if (isset($active_module) && $active_module == 'support_topics') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>support-topics"><i class="fa fa-life-ring fa-fw fa-lg"></i>&nbsp; Support Topics <span class="badge"><?=$todo_count['support-topics']?></span></a></li>
					</ul>
					<ul class="nav nav-sidebar">
						<li<?php if (isset($active_module) && $active_module == 'data_sources') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>data-sources"><i class="fa fa-database fa-fw fa-lg"></i>&nbsp; Data Sources</a></li>
					</ul>
					<ul class="nav nav-sidebar">
						<li<?php if (isset($active_module) && $active_module == 'suggestions') {?> class="active"<?php } ?>><a href="<?=ROOT_FOLDER?>suggestions"><i class="fa fa-commenting-o fa-fw fa-lg"></i>&nbsp; Feature Suggestions</a></li>
					</ul>
				</div>
				<div id="status" class="col-sm-2 col-sm-offset-5 col-md-2 col-md-offset-5 alert text-center hidden" style="position: fixed; z-index: 100; margin-top: 5px; font-weight: 700; white-space: pre-line"></div>
				<script type="text/javascript">
var Status = (function() {
	var previousStatusType;
	var container = document.getElementById('status');
	var timeoutId;
	var counter = 0;

	return {
		show: function(type, text, ttl) {
			while (container.lastChild) {
				container.removeChild(container.lastChild);
			}
			var message = '';
			if (Array.isArray(text)) {
				message += text.join("\n");
			} else {
				message += text;
			}
			container.appendChild(document.createTextNode(message));
			container.classList.remove(previousStatusType);
			previousStatusType = 'alert-' + type;
			container.classList.add(previousStatusType);
			container.classList.remove('hidden');
			if (timeoutId) {
				clearTimeout(timeoutId);
			}
			if (ttl) {
				timeoutId = setTimeout(() => Status.hide(counter), ttl);
			}
			counter += 1;
			return counter;
		},
		hide: function(identifier) {
			if (identifier != counter) {
				return;
			}
			if (timeoutId) {
				clearTimeout(timeoutId);
			}
			timeoutId = null;
			container.classList.add('hidden');
		}
	};
})();
				</script>
<?php if (DEBUG) { ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2" style="margin-top:20px">
					<div class="alert alert-info" role="alert">
						<p><i class="fa fa-warning fa-fw fa-2x"></i> <strong>Watch out!</strong> This site is meant to be used for testing purposes only and might contain test data! The production environment is located at <a href="https://www.wazebelgium.be/dashboard/" class="alert-link">https://www.wazebelgium.be/dashboard/</a>.</p>
					</div>
					<script>
if (localStorage.ignoreMessage == null && confirm('This is the testing environment of the Community Dashboard. Do you wish to be redirected to the production environment on wazebelgium.be instead?')) {
	window.location = (window.location + "").replace('https://home.tomputtemans.com/waze/', 'https://www.wazebelgium.be/');
} else {
	localStorage.ignoreMessage = true;
}
					</script>
				</div>
<?php } ?>

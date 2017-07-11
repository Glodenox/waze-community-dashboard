<?php include('templates/template_header.php'); ?>

				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
					<h1 class="page-header">Support topics</h1>
					<p>This page lists all non-sticky topics that haven't received a reply on the Waze forum. You can also subscribe to the forum for updates, but this can help as a reminder.</p>
					<table class="table table-striped table-hover table-condensed">
						<thead>
							<tr>
<?php if (isset($user)) { ?>
								<th></th>
<?php } ?>
								<th>Topic title</th>
								<th>Forum</th>
								<th>Date posted</th>
							</tr>
						</thead>
						<tbody>
<?php if (count($support_topics) == 0) { ?>
							<tr class="warning">
								<td colspan="<?=(isset($user) ? '4' : '3')?>" class="text-center">All forum topics have been processed</td>
							</tr>
<?php }
foreach ($support_topics as $topic) { ?>
							<tr>
<?php if (isset($user)) { ?>
								<td><button class="btn btn-danger btn-sm process-button" data-topic="<?=$topic->id?>">Mark as processed / to ignore</button></td>
<?php } ?>
								<td><a href="https://www.waze.com/forum/viewtopic.php?f=<?=$topic->forum_id?>&t=<?=$topic->id?>" target="_blank" /><?=$topic->title?></a></td>
								<td><a href="https://www.waze.com/forum/viewforum.php?f=<?=$topic->forum_id?>" target="_blank"><?=$topic->forum_name?></a></td>
								<td><?=time_elapsed_string(date('c', $topic->timestamp))?></td>
							</tr>
<?php } ?>
						</tbody>
					</table>
				</div>
				<script type="text/javascript">
var buttons = document.querySelectorAll('button.process-button');
for (var i = 0; i < buttons.length; i++) {
	buttons[i].addEventListener('click', function(e) {
		var self = e.target;
		var statusId = Status.show('info', 'Marking as processed...');
		var request = new XMLHttpRequest();
		request.addEventListener('load', function() {
			Status.hide(statusId);
			if (this.response.ok) {
				var tr = self.parentNode.parentNode;
				tr.parentNode.removeChild(tr);
			} else {
				Status.show('danger', 'Could not process:\n' + this.response.error);
			}
		});
		request.responseType = 'json';
		request.open('GET', '<?=ROOT_FOLDER?>support-topics/process?id=' + this.dataset.topic);
		request.send();
	});
}
				</script>

<?php include('templates/template_footer.php'); ?>

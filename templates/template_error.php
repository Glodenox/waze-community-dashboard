<?php include('templates/template_header.php'); ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
					<h1 class="page-header">Error</h1>
<?php if (isset($error) && $error) {?>
					<div class="alert alert-danger" role="alert">
						<?=$error?>
					</div>
<?php } ?>
				</div>
<?php include('templates/template_footer.php'); ?>

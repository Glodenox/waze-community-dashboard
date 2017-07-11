<?php if (count($code_errors) > 0) { ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 alert alert-warning">
					<p><strong>PHP errors encountered</strong></p>
<?php foreach ($code_errors as $code_error) { ?>
					<?=$code_error?><br />
<?php } ?>
				</div>
<?php } ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2">
					<p class="text-center" style="font-style:italic">This site is ran by top community members and is not operated or supervised by Waze</p>
				</div>
			</div>
		</div>
	</body>
</html>
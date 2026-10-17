<?php style('xmlviewer', 'style'); ?>
<div class="section xmlviewer-error">
	<h2><?php p($l->t('XML Viewer')); ?></h2>
	<p><?php p($_['message']); ?></p>
	<a class="xmlviewer-close" href="<?php p($_['backUrl']); ?>"><span aria-hidden="true">×</span> <?php p($l->t('Close and return to files')); ?></a>
</div>

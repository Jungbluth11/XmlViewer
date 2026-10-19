<?php
declare(strict_types=1);

style('xmlviewer', 'style');
$parserStylesheetUrl = $_['parserStylesheetUrl'] ?? '';
$parserScriptUrl = $_['parserScriptUrl'] ?? '';
$title = $_['title'] ?? '';
?>
<?php if ($parserStylesheetUrl !== ''): ?><link rel="stylesheet" href="<?php p($parserStylesheetUrl); ?>"><?php endif; ?>
<div id="xmlviewer" class="section<?php if ($_['isPublic']): ?> xmlviewer-public<?php endif; ?>" data-parser-script-url="<?php p($parserScriptUrl); ?>">
	<header class="xmlviewer-header">
		<h1><?php p($title); ?></h1>
		<div class="xmlviewer-actions">
			<a class="xmlviewer-download" href="<?php p($_['downloadUrl']); ?>"><?php p($l->t('Download')); ?></a>
			<?php if ($_['showClose']): ?><a class="xmlviewer-close" href="<?php p($_['backUrl']); ?>"><?php p($l->t('Close')); ?></a><?php endif; ?>
		</div>
	</header>
	<?php require $_['templatePath']; ?>
</div>

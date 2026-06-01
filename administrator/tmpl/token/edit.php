<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
?>
<?php if (!empty($this->generatedToken)) : ?>
	<div class="alert alert-success">
		<h2 class="h4 alert-heading"><?php echo Text::_('COM_DOWNLOADTRACKER_TOKEN_CREATED_HEADING'); ?></h2>
		<p><?php echo Text::_('COM_DOWNLOADTRACKER_TOKEN_CREATED_MESSAGE'); ?></p>
		<p><strong><?php echo Text::_('COM_DOWNLOADTRACKER_FIELD_TOKEN_LABEL'); ?></strong></p>
		<p><code><?php echo $this->escape((string) $this->generatedToken['raw_token']); ?></code></p>
		<?php if (!empty($this->generatedToken['download_url'])) : ?>
			<p><strong><?php echo Text::_('COM_DOWNLOADTRACKER_FIELD_PROTECTED_DOWNLOAD_URL_LABEL'); ?></strong></p>
			<div class="d-flex gap-2 align-items-center">
				<code><?php echo $this->escape((string) $this->generatedToken['download_url']); ?></code>
				<button
					type="button"
					class="btn btn-sm btn-outline-secondary js-downloadtracker-copy-url"
					data-download-url="<?php echo $this->escape((string) $this->generatedToken['download_url']); ?>"
				>
					<?php echo Text::_('COM_DOWNLOADTRACKER_COPY_URL'); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>
<form action="<?php echo Route::_('index.php?option=com_downloadtracker&view=token&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="token-form" class="form-validate">
	<div class="row">
		<div class="col-lg-9">
			<?php echo $this->form->renderFieldset('details'); ?>
		</div>
	</div>
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
<script>
document.addEventListener('click', function (event) {
	var button = event.target.closest('.js-downloadtracker-copy-url');

	if (!button || !navigator.clipboard) {
		return;
	}

	navigator.clipboard.writeText(button.getAttribute('data-download-url'));
});
</script>

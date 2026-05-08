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
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
?>
<form action="<?php echo Route::_('index.php?option=com_downloadtracker&view=product&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="product-form" class="form-validate">
	<div class="row">
		<div class="col-lg-9">
			<?php echo $this->form->renderFieldset('details'); ?>
		</div>
	</div>
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

class TokenController extends FormController
{
	protected $view_list = 'tokens';

	public function reissue(): void
	{
		$this->checkToken();
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->authorise('core.edit', 'com_downloadtracker')) {
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$tokenId = $this->input->getInt('id');
		/** @var \GrantHood\Component\DownloadTracker\Administrator\Model\TokenModel $model */
		$model = $this->getModel('Token', 'Administrator');
		$result = $model->reissue($tokenId);

		if (empty($result['success']) || ($result['email_status'] ?? '') !== 'sent') {
			$app->enqueueMessage((string) ($result['error'] ?? Text::_('COM_DOWNLOADTRACKER_ERROR_TOKEN_REISSUE_FAILED')), 'error');
			$this->setRedirect(Route::_('index.php?option=com_downloadtracker&task=token.edit&id=' . $tokenId, false));

			return;
		}

		$app->setUserState('com_downloadtracker.generated_token', $result);
		$app->enqueueMessage(Text::sprintf('COM_DOWNLOADTRACKER_TOKEN_REISSUED', (string) $result['customer_email']), 'message');
		$this->setRedirect(Route::_('index.php?option=com_downloadtracker&task=token.edit&id=' . (int) $result['token_id'], false));
	}
}

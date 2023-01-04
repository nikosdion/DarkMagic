<?php
/**
 *  @package   DarkMagic
 *  @copyright Copyright (c)2019-2023 Nicholas K. Dionysopoulos
 *  @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\DarkMagic\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use function simplexml_load_file;

class AboutField extends FormField
{
	/**
	 * Hide the label for this field
	 *
	 * @var   bool
	 * @since 2.0.02
	 */
	protected $hiddenLabel = true;

	/**
	 * The name of the field
	 *
	 * @var   string
	 * @since 2.0.2
	 */
	protected $type = 'about';

	protected function getInput()
	{
		$xml      = simplexml_load_file(JPATH_PLUGINS . '/system/darkmagic/darkmagic.xml');
		$logo     = file_get_contents(JPATH_ROOT . '/media/plg_system_darkmagic/images/logo.svg');
		$title    = Text::_('PLG_SYSTEM_DARKMAGIC_CONFIG_ABOUT_TITLE');
		$subtitle = Text::_('PLG_SYSTEM_DARKMAGIC_CONFIG_ABOUT_SUBTITLE');
		$version  = Text::sprintf('PLG_SYSTEM_DARKMAGIC_CONFIG_ABOUT_VERSION', $xml->version);
		$issues   = Text::_('PLG_SYSTEM_DARKMAGIC_CONFIG_ABOUT_ISSUES');

		return <<< HTML
<div class="card card-body m-1 bg-dark text-white">
	<div class="row cols-1 cols-sm-2 cols-md-4 cols-lg-6">
		<div class="col-1 col-lg-2">
			$logo
		</div>
		<div class="col">
			<h4 class="h1 text-white mb-0">$title</h4>
			<p class="text-danger pb-2 border-bottom"><em>$subtitle</em></p>
			<p class="d-flex align-items-center">
				<span class="h3 text-white m-0 pe-1">$version</span>
				<span class="fs-5 fw-normal">– {$xml->copyright}</span>
			</p>
			<p class="text-danger">
				$issues
				<a href="https://github.com/nikosdion/DarkMagic" class="text-light fw-bold text-decoration-underline">
					<span class="fab fa-github" aria-hidden="true"></span>
					GitHub
				</a>
			</p>
		</div>
	</div>
</div>
HTML;
	}


}
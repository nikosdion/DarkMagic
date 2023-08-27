<?php
/**
 *  @package   DarkMagic
 *  @copyright Copyright (c)2019-2023 Nicholas K. Dionysopoulos
 *  @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Log\Log;

class plg_system_darkmagicInstallerScript extends InstallerScript
{
	protected $minimumPhp = '7.2.0';

	protected $minimumJoomla = '4.2.0';

	/**
	 * A list of folders to be deleted
	 *
	 * @var    array
	 * @since  2.2.0
	 */
	protected $deleteFolders = [
		'media/plg_system_darkmagic/js',
	];

	public function preflight($type, $parent)
	{
		if (version_compare(JVERSION, '4.999999.999999', 'gt')) {
			Log::add('DarkMagic is not compatible with Joomla! 5.0 or later. Joomla has made it impossible to have a reliable Dark Mode anymore.', Log::WARNING, 'jerror');

			return false;
		}


		return parent::preflight($type, $parent);
	}
}
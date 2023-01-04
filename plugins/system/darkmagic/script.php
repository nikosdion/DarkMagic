<?php
/**
 *  @package   DarkMagic
 *  @copyright Copyright (c)2019-2023 Nicholas K. Dionysopoulos
 *  @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Installer\InstallerScript;

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
}
<?php
/**
 *  @package   DarkMagic
 *  @copyright Copyright (c)2019-2023 Nicholas K. Dionysopoulos
 *  @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\DarkMagic\Extension\DarkMagic;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	public function register(Container $container)
	{
		/** @var \Joomla\CMS\Extension\MVCComponent $component */
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$config  = (array) PluginHelper::getPlugin('system', 'darkmagic');
				$subject = $container->get(DispatcherInterface::class);

				return new DarkMagic($subject, $config);
			}
		);
	}
};

<?php
/**
 * @package   DarkMagic
 * @copyright Copyright (c)2019-2020 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;

class plgSystemDarkMagic extends CMSPlugin
{
	/**
	 * Should I apply the dark theme?
	 *
	 * @var   bool
	 * @since 1.0.0.b1
	 */
	private $enabled;

	public function __construct(&$subject, $config = [])
	{
		parent::__construct($subject, $config);

		$this->enabled = $this->evaluateDarkModeConditions();
	}

	/**
	 * Hooks on Joomla onBeforeRender event, manipulating the document header to activate Dark Mode
	 *
	 * @throws  Exception
	 * @since   1.0.0.b1
	 */
	public function onBeforeRender(): void
	{
		// Is this Light Mode?
		if (!$this->enabled)
		{
			return;
		}

		// Make sure I can get basic Joomla objects before proceeding
		try
		{
			/** @var CMSApplication $app */
			$app      = Factory::getApplication();
			$document = $app->getDocument();
		}
		catch (Exception $e)
		{
			return;
		}

		// Are we REALLY sure this is an HTML document?
		if (!($document instanceof HtmlDocument))
		{
			return;
		}

		// Get the plugin configuration
		$applyWhen = $this->params->get('applywhen', 'always');

		// Administrator dark mode
		try
		{
			$this->darkModeAdministrator($applyWhen, $document);
		}
		catch (Exception $e)
		{
			// It's OK. It's not the end of the world.
		}
	}

	/**
	 * Evaluate the conditions for enabling dark mode. Returns true if Dark Mode should be enabled.
	 *
	 * @return  bool
	 * @since   1.0.0.b1
	 */
	private function evaluateDarkModeConditions(): bool
	{
		// Joomla 4 only, folks
		if (version_compare(JVERSION, '3.999.999', 'le'))
		{
			return false;
		}

		// Are the conditions for Dark Mode met?
		switch ($this->params->get('applywhen'))
		{
			default:
			case 'always':
			case 'browser':
				return true;

				break;

			case 'dusk':
				return $this->isNight();

				break;
		}
	}

	/**
	 * Is it night yet?
	 *
	 * Checks the sun position based on the defined location.
	 *
	 * @return  bool
	 *
	 * @since   1.0.0.b1
	 */
	private function isNight(): bool
	{
		try
		{
			$app    = Factory::getApplication();
			$user   = $app->getIdentity() ?? new User();
			$params = $user->params;

			if (!is_object($params) || !($params instanceof Registry))
			{
				$params = new Registry($params);
			}

			$userLocation = [
				$params->get('darkmagic_latitude', 0.00),
				$params->get('darkmagic_longitude', 0.00),
			];
		}
		catch (Exception $e)
		{
			$userLocation = [0.00, 0.00];
		}

		if (($userLocation[0] < 0.0001) && ($userLocation[1] < 0.0001))
		{
			$userLocation = [
				$this->params->get('latitude', 0.00),
				$this->params->get('longitude', 0.00),
			];
		}

		$zenith       = $this->params->get('zenith', '96');
		$afterSunrise = ((int) $this->params->get('aftersunrise', 0)) * 60;
		$beforeSunset = ((int) $this->params->get('beforesunset', 0)) * 60;
		$now          = time();
		$sunrise      = date_sunrise($now, SUNFUNCS_RET_TIMESTAMP, $userLocation[0], $userLocation[1], $zenith);
		$sunset       = date_sunset($now, SUNFUNCS_RET_TIMESTAMP, $userLocation[0], $userLocation[1], $zenith);
		$sunrise      += $afterSunrise;
		$sunset       -= $beforeSunset;

		if ($sunrise > $now)
		{
			// We are between midnight and sunrise. Dark Mode.
			return true;
		}

		// Between sunrise and sunset.
		if (($sunrise <= $now) && ($sunset >= $now))
		{
			return false;
		}

		// Between sunset and midnight. Dark Mode.
		return true;
	}

	/**
	 * Create a media version query string for the plugin.
	 *
	 * The media version query string is created from the modification dates of the CSS files we are going to be
	 * loading and the plugin file itself. This is simultaneously very accurate and does not divulge plugin version
	 * information since each site will have a different file modification time for each file.
	 *
	 * @return  string
	 * @since   1.0.0.b1
	 */
	private function getMediaVersion(): string
	{
		$files = Folder::files(JPATH_ROOT . '/media/plg_system_darkmagic/css', '.css', false, true);
		array_unshift($files, __FILE__);

		return sha1(implode(':', array_map('filemtime', $files)));
	}

	/**
	 * Postpone loading of the specified CSS file until after the DOM is ready
	 *
	 * @param   string  $url    The URL to the CSS file to load
	 * @param   string  $media  Media query for the CSS file, default "screen"
	 *
	 *
	 * @throws Exception
	 * @since  1.0.0.b1
	 */
	private function postponeCSSLoad(string $url, string $media = 'screen')
	{
		/** @var CMSApplication $app */
		$app = Factory::getApplication();
		/** @var HtmlDocument $doc */
		$doc = $app->getDocument();

		if (!$doc instanceof HtmlDocument)
		{
			return;
		}

		$wa = $doc->getWebAssetManager();

		if (!$wa->assetExists('script', 'plg_system_darkmagic.postponed'))
		{
			$wa->registerAndUseScript('plg_system_darkmagic.postponed', Uri::base() . '../media/plg_system_darkmagic/js/postponed.js', [
				'version' => $this->getMediaVersion(),
			], [
				'defer' => true,
			]);
		}

		$doc->addScriptOptions('plg_system_darkmagic.postponedCSS', [
			$url => $media,
		], true);
	}

	/**
	 * Gets the inline CSS overrides for the administrator template
	 *
	 * @return  string  Inline CSS overrides
	 *
	 * @since   1.0.0.b2
	 */
	private function getInlineCSSOverrideAdmin(): string
	{
		$css = '';

		$navbar_color     = $this->params->get('templateColor') ?: '';
		$header_color     = $this->params->get('headerColor') ?: '';
		$sideBarColor     = $this->params->get('sidebarColor') ?: '';
		$linkColor        = $this->params->get('linkColor');
		$background_color = $this->params->get('loginBackgroundColor') ?: '';

		if ($navbar_color)
		{
			$css .= <<<CSS
		
	.navbar-inner,
	.navbar-inverse .navbar-inner,
	.dropdown-menu li > a:hover,
	.dropdown-menu .active > a,
	.dropdown-menu .active > a:hover,
	.navbar-inverse .nav li.dropdown.open > .dropdown-toggle,
	.navbar-inverse .nav li.dropdown.active > .dropdown-toggle,
	.navbar-inverse .nav li.dropdown.open.active > .dropdown-toggle,
	#status.status-top {
		background: $navbar_color;
	}

CSS;
		}

		if ($header_color)
		{
			$css .= <<<CSS
	.header {
		background: $header_color;
	}

CSS;
		}

		if ($sideBarColor)
		{
			$css .= <<<CSS

	.nav-list > .active > a,
	.nav-list > .active > a:hover {
		background: $sideBarColor;
	}

CSS;
		}

		if ($linkColor)
		{
			$css .= <<<CSS

	a,
	.j-toggle-sidebar-button {
		color: $linkColor;
	}

CSS;
		}

		if ($background_color)
		{
			$css .= <<<CSS

			
	.view-login {
		background-color: $background_color;
	}

CSS;
		}

		return $css;
	}

	/**
	 * Is this the administrator application using the Atum template?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0.b2
	 */
	private function isAdminAtum(): bool
	{
		// Can I get a reference to the CMS application?
		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			return false;
		}

		// Is this the site administrator?
		if (!$app->isClient('administrator'))
		{
			return false;
		}

		// Is the template in use Atum (the only one supported)?
		if ($app->getTemplate() != 'atum')
		{
			return false;
		}

		return true;
	}

	/**
	 * Enables Dark Mode for the administrator application
	 *
	 * @param   string        $applyWhen
	 * @param   HtmlDocument  $document
	 *
	 * @throws Exception
	 * @since  1.0.0.b2
	 */
	private function darkModeAdministrator(string $applyWhen, HtmlDocument $document): void
	{
		// Are we in the administrator application, using the Atum template?
		if (!$this->isAdminAtum())
		{
			return;
		}

		// Get inline CSS override
		$overrideCss = $this->getInlineCSSOverrideAdmin();

		/** @var CMSApplication $app */
		$app = Factory::getApplication();
		$wa  = $app->getDocument()->getWebAssetManager();

		switch ($applyWhen)
		{
			case 'always':
			case 'dusk':
			default:
				// Tell the browser what kind of color scheme we support
				$document->setMetaData('color-scheme', 'dark');

				// Load the dark mode CSS
				$wa->registerAndUseStyle('plg_system_darkmagic', Joomla\CMS\Uri\Uri::base() . '../media/plg_system_darkmagic/css/custom.css', [
					'version' => $this->getMediaVersion(),
				], [
					'type' => 'text/css',
				]);

				// Apply the TinyMCE skin
				$this->postponeCSSLoad('../media/plg_system_darkmagic/css/skin.css');

				// Apply the inline CSS overrides
				if (!empty($overrideCss))
				{
					$wa->addInlineStyle($overrideCss);
				}

				break;

			case 'browser':
				// Tell the browser what kind of color scheme we support
				$document->setMetaData('color-scheme', 'light dark');

				// Load the dark mode CSS conditionally
				$wa->registerAndUseStyle('plg_system_darkmagic', Joomla\CMS\Uri\Uri::base() . '../media/plg_system_darkmagic/css/custom.css', [
					'version' => $this->getMediaVersion(),
				], [
					'type'  => 'text/css',
					'media' => '(prefers-color-scheme: dark)',
				]);

				// Apply the TinyMCE skin conditionally
				$this->postponeCSSLoad('../media/plg_system_darkmagic/css/skin.css', '(prefers-color-scheme: dark)');

				// Apply the inline CSS overrides
				if (!empty($overrideCss))
				{
					$overrideCss = <<< CSS

@media screen and (prefers-color-scheme: dark)
{
	$overrideCss
}

CSS;

					$wa->addInlineStyle($overrideCss);
				}

				break;
		}
	}
}
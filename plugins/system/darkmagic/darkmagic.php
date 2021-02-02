<?php
/**
 * @package   DarkMagic
 * @copyright Copyright (c)2019-2021 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

class plgSystemDarkMagic extends CMSPlugin
{
	/**
	 * Should I apply the dark theme?
	 *
	 * @var   bool
	 * @since 1.0.0.b1
	 */
	private $enabled = true;

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

		// Site dark mode
		try
		{
			$this->darkModeSite($applyWhen, $document);
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
			$user   = Factory::getUser();
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
		$afterSunrise = ((int) $this->get('aftersunrise', 0)) * 60;
		$beforeSunset = ((int) $this->get('beforesunset', 0)) * 60;
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
	 * loading. This is simultaneously very accurate and does not divulge plugin version information since each site
	 * will have a different file modification time for each file.
	 *
	 * @return  string
	 * @since   1.0.0.b1
	 */
	private function getMediaVersion(): string
	{
		$fileModTimes = [
			filemtime(__FILE__),
			filemtime(JPATH_ROOT . '/media/plg_system_darkmagic/css/content.css'),
			filemtime(JPATH_ROOT . '/media/plg_system_darkmagic/css/custom.css'),
			filemtime(JPATH_ROOT . '/media/plg_system_darkmagic/css/skin.css'),
		];

		return sha1(implode(':', $fileModTimes));
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
		$js = <<< JS
jQuery(document).ready(function($) {
    window.setTimeout(function() {
	    var head   = document.getElementsByTagName("head")[0];
	    var link   = document.createElement("link");
	    link.rel   = "stylesheet";
	    link.type  = "text/css";
	    link.href  = '$url';
	    link.media = "$media";
	    head.appendChild(link);
    }, 250);
});

JS;
		Factory::getApplication()->getDocument()->addScriptDeclaration($js);
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
	 * Is this the administrator application using the Isis template?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0.b2
	 */
	private function isAdminIsis(): bool
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

		// Is the template in use Isis (the only one supported)?
		if ($app->getTemplate() != 'isis')
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
		// Am I allowed to apply Dark Mode to the administrator?
		if ($this->params->get('enable_backend', 1) != 1)
		{
			return;
		}

		// Are we in the administrator application, using the Isis template?
		if (!$this->isAdminIsis())
		{
			return;
		}

		// Get inline CSS override
		$overrideCss = $this->getInlineCSSOverrideAdmin();

		switch ($applyWhen)
		{
			case 'always':
			case 'dusk':
			default:
				// Tell the browser what kind of color scheme we support
				$document->setMetaData('color-scheme', 'dark');

				// Load the dark mode CSS
				$document->addStyleSheet(
					'../media/plg_system_darkmagic/css/custom.css', [
					'version' => $this->getMediaVersion(),
				], [
						'type' => 'text/css',
					]
				);

				// Apply the TinyMCE skin
				$this->postponeCSSLoad(Uri::root(true) . '/media/plg_system_darkmagic/css/skin.css');

				// Apply the inline CSS overrides
				if (!empty($overrideCss))
				{
					$document->addStyleDeclaration($overrideCss);
				}

				break;

			case 'browser':
				// Tell the browser what kind of color scheme we support
				$document->setMetaData('color-scheme', 'light dark');

				// Load the dark mode CSS conditionally
				$document->addStyleSheet(
					'../media/plg_system_darkmagic/css/custom.css', [
					'version' => $this->getMediaVersion(),

				], [
						'type'  => 'text/css',
						'media' => '(prefers-color-scheme: dark)',
					]
				);

				// Apply the TinyMCE skin conditionally
				$this->postponeCSSLoad(Uri::root(true) . '/media/plg_system_darkmagic/css/skin.css', '(prefers-color-scheme: dark)');

				// Apply the inline CSS overrides
				if (!empty($overrideCss))
				{
					$overrideCss = <<< CSS

@media screen and (prefers-color-scheme: dark)
{
	$overrideCss
}

CSS;

					$document->addStyleDeclaration($overrideCss);
				}

				break;
		}
	}

	/**
	 * Enables Dark Mode for the site application
	 *
	 * @param   string        $applyWhen
	 * @param   HtmlDocument  $document
	 *
	 * @throws Exception
	 * @since  1.0.0.b2
	 */
	private function darkModeSite(string $applyWhen, HtmlDocument $document): void
	{
		// Am I allowed to apply Dark Mode to the administrator?
		if ($this->params->get('enable_frontend', 1) != 1)
		{
			return;
		}

		// Are we in the administrator application, using the Isis template?
		if (!$this->isSiteProtostar())
		{
			return;
		}

		// Get inline CSS override
		$overrideCss = $this->getInlineCSSOverrideSite();

		switch ($applyWhen)
		{
			case 'always':
			case 'dusk':
			default:
				// Tell the browser what kind of color scheme we support
				$document->setMetaData('color-scheme', 'dark');

				// Load the dark mode CSS
				$document->addStyleSheet(
					'../media/plg_system_darkmagic/css/custom.css', [
					'version' => $this->getMediaVersion(),
				], [
						'type' => 'text/css',
					]
				);

				// Apply the TinyMCE skin
			$this->postponeCSSLoad(Uri::root(true) . '/media/plg_system_darkmagic/css/skin.css');

				// Apply the inline CSS overrides
				if (!empty($overrideCss))
				{
					$document->addStyleDeclaration($overrideCss);
				}

				break;

			case 'browser':
				// Tell the browser what kind of color scheme we support
				$document->setMetaData('color-scheme', 'light dark');

				// Load the dark mode CSS conditionally
				$document->addStyleSheet(
					'../media/plg_system_darkmagic/css/custom.css', [
					'version' => $this->getMediaVersion(),

				], [
						'type'  => 'text/css',
						'media' => '(prefers-color-scheme: dark)',
					]
				);

				// Apply the TinyMCE skin conditionally
				$this->postponeCSSLoad(Uri::root(true) . '/media/plg_system_darkmagic/css/skin.css', '(prefers-color-scheme: dark)');

				// Apply the inline CSS overrides
				if (!empty($overrideCss))
				{
					$overrideCss = <<< CSS

@media screen and (prefers-color-scheme: dark)
{
	$overrideCss
}

CSS;

					$document->addStyleDeclaration($overrideCss);
				}

				break;
		}
	}

	/**
	 * Is this the administrator application using the Isis template?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0.b2
	 */
	private function isSiteProtostar(): bool
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
		if (!$app->isClient('site'))
		{
			return false;
		}

		// Is the template in use Isis (the only one supported)?
		if ($app->getTemplate() != 'protostar')
		{
			return false;
		}

		return true;
	}

	/**
	 * Gets the inline CSS overrides for the administrator template
	 *
	 * @return  string  Inline CSS overrides
	 *
	 * @since   1.0.0.b2
	 */
	private function getInlineCSSOverrideSite(): string
	{
		$css = '';

		$frontendTemplateColor   = $this->params->get('frontendTemplateColor') ?: '';
		$frontendBackgroundColor = $this->params->get('frontendBackgroundColor') ?: '';

		if ($frontendTemplateColor)
		{
			$css .= <<<CSS

	body, body.site {
		border-top: 3px solid $frontendTemplateColor;
	}

	a {
		color: $frontendTemplateColor;
	}

	.nav-list > .active > a,
	.nav-list > .active > a:hover,
	.dropdown-menu li > a:hover,
	.dropdown-menu .active > a,
	.dropdown-menu .active > a:hover,
	.nav-pills > .active > a,
	.nav-pills > .active > a:hover,
	.btn-primary {
		background: $frontendTemplateColor;
	}

CSS;
		}

		if ($frontendBackgroundColor)
		{
			$css .= <<<CSS

	body, body.site, body .container, .body .container {
		background-color: $frontendBackgroundColor;
	}

CSS;
		}

		return $css;
	}

}
<?php
/**
 *  @package   DarkMagic
 *  @copyright Copyright (c)2019-2021 Nicholas K. Dionysopoulos
 *  @license   GNU General Public License version 3, or later
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Joomla\Plugin\System\DarkMagic\Extension;

// Prevent direct access
defined('_JEXEC') or die;

use Exception;
use Joomla;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use const JPATH_ROOT;

/**
 * DarkMagic Plugin for Joomla!™ 4
 *
 * @since        2.0.1
 * @noinspection PhpUnused
 */
class DarkMagic extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Should I try to detect and register legacy event listeners?
	 *
	 * @var    boolean
	 * @since  2.0.0
	 */
	protected $allowLegacyListeners = true;

	/**
	 * The Joomla! application object
	 *
	 * @var   CMSApplication|SiteApplication|AdministratorApplication
	 * @since 1.0.0
	 */
	protected $app;

	/**
	 * Should I apply the dark theme?
	 *
	 * @var   bool
	 * @since 1.0.0.b1
	 */
	private $enabled;

	/**
	 * Constructor
	 *
	 * @param   DispatcherInterface  &$subject  The object to observe
	 * @param   array                $config    An optional associative array of configuration settings.
	 *
	 * @since   1.0.0.b1
	 */
	public function __construct(&$subject, $config = [])
	{
		parent::__construct($subject, $config);

		$this->enabled = $this->evaluateDarkModeConditions();
	}

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   2.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onBeforeRender' => 'onBeforeRender',
		];
	}

	/**
	 * Hooks on Joomla onBeforeRender event, manipulating the document header to activate Dark Mode
	 *
	 * @throws  Exception
	 * @since   1.0.0.b1
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onBeforeRender(Event $event): void
	{
		// Is this Light Mode?
		if (!$this->enabled)
		{
			return;
		}

		// Make sure I can get basic Joomla objects before proceeding
		try
		{
			$document = $this->app->getDocument();
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

		// Are we in the administrator application, using the Atum template?
		if (!$this->isAdminAtum())
		{
			return;
		}

		// Get inline CSS override
		$bgLight      = $this->params->get('bg-light', '#343a40') ?: '#343a40l';
		$textDark     = $this->params->get('text-dark', '#dee2e6') ?: '#dee2e6';
		$textLight    = $this->params->get('text-light', '#212529') ?: '#212529';
		$linkColor    = $this->params->get('link-color', '#80abe2') ?: '#80abe2';
		$specialColor = $this->params->get('special-color', '#b2bfcd') ?: '#b2bfcd';

		/** @var Registry $tParams */
		$tParams           = $this->app->getTemplate(true)->params;
		$lightSpecialColor = $tParams->get('special-color', '#001B4C');

		/**
		 * The link hover color needs to have a fixed lightness difference to the link color. If the link color's
		 * lightness plus that difference would get it to be brighter than 100% we will make it darker. Otherwise, we
		 * will make it lighter. For this, we need to convert the RGB to HSL, make adjustments and convert back to RGB.
		 */
		[$h, $s, $v] = $this->rgbToHsv($linkColor);
		$fixedDiff = 15;

		if ($v > (100 - $fixedDiff))
		{
			$v = $v - $fixedDiff;
		}
		else
		{
			$v = $v + $fixedDiff;
		}

		$linkHoverColor = $this->hsvToRgb($h, $s, $v);

		$overrideCss = <<< CSS
:root {
		--template-bg-light: $bgLight !important;
		--template-text-dark: $textDark !important;
		--template-text-light: $textLight !important;
		--template-link-color: $linkColor !important;
		--template-link-hover-color: $linkHoverColor !important;
		--template-special-color: $specialColor !important;
	}
CSS;

		$wa = $document->getWebAssetManager();

		switch ($applyWhen)
		{
			case 'always':
			case 'dusk':
			default:
				// Tell the browser what kind of color scheme we support
				$document->setMetaData('color-scheme', 'dark');

				// Load the dark mode CSS
				$wa->registerAndUseStyle('plg_system_darkmagic', Joomla\CMS\Uri\Uri::base() . '../media/plg_system_darkmagic/css/atum.css', [
					'version' => $this->getMediaVersion(),
				], [
					'type' => 'text/css',
				]);

				// Apply the TinyMCE skin
				$this->postponeCSSLoad('../media/plg_system_darkmagic/css/skin.css');

				// Apply the inline CSS overrides
				$wa->addInlineStyle($overrideCss);

				$document->setMetaData('theme-color', $specialColor);

				break;

			case 'browser':
				// Tell the browser what kind of color scheme we support
				$document->setMetaData('color-scheme', 'light dark');

				// Load the dark mode CSS conditionally
				$wa->registerAndUseStyle('plg_system_darkmagic', Joomla\CMS\Uri\Uri::base() . '../media/plg_system_darkmagic/css/atum.css', [
					'version' => $this->getMediaVersion(),
				], [
					'type'  => 'text/css',
					'media' => '(prefers-color-scheme: dark)',
				]);

				// Apply the TinyMCE skin conditionally
				$this->postponeCSSLoad('../media/plg_system_darkmagic/css/skin.css', '(prefers-color-scheme: dark)');

				// Apply the inline CSS overrides
				$overrideCss = <<< CSS

@media screen and (prefers-color-scheme: dark)
{
$overrideCss
}

CSS;

				$wa->addInlineStyle($overrideCss);

				/** @noinspection HtmlUnknownAttribute */
				$document->addCustomTag(sprintf('<meta name="theme-color" content="%s" media="(prefers-color-scheme: light)">', $lightSpecialColor));
				/** @noinspection HtmlUnknownAttribute */
				$document->addCustomTag(sprintf('<meta name="theme-color" content="%s" media="(prefers-color-scheme: dark)">', $specialColor));

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
		if (!$this->isSiteCassiopeia())
		{
			return;
		}

		/** @var CMSApplication $app */
		$wa = $document->getWebAssetManager();

		switch ($applyWhen)
		{
			case 'always':
			case 'dusk':
			default:
				// Tell the browser what kind of color scheme we support
				$document->setMetaData('color-scheme', 'dark');

				// Load the dark mode CSS
				$wa->registerAndUseStyle('plg_system_darkmagic', Joomla\CMS\Uri\Uri::base() . '../media/plg_system_darkmagic/css/cassiopeia.css', [
					'version' => $this->getMediaVersion(),
				], [
					'type' => 'text/css',
				]);

				// Apply the TinyMCE skin
				$this->postponeCSSLoad('../media/plg_system_darkmagic/css/skin.css');
				break;

			case 'browser':
				// Tell the browser what kind of color scheme we support
				$document->setMetaData('color-scheme', 'light dark');

				// Load the dark mode CSS conditionally
				$wa->registerAndUseStyle('plg_system_darkmagic', Joomla\CMS\Uri\Uri::base() . '../media/plg_system_darkmagic/css/cassiopeia.css', [
					'version' => $this->getMediaVersion(),
				], [
					'type'  => 'text/css',
					'media' => '(prefers-color-scheme: dark)',
				]);

				// Apply the TinyMCE skin conditionally
				$this->postponeCSSLoad('../media/plg_system_darkmagic/css/skin.css', '(prefers-color-scheme: dark)');

				break;
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

				/** @noinspection PhpUnreachableStatementInspection */
				break;

			case 'dusk':
				return $this->isNight();

				/** @noinspection PhpUnreachableStatementInspection */
				break;
		}
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
	 * Convert a Hue, Saturation, Value color definition to a hex RGB color
	 *
	 * @param   int  $hue         Hue (0...360)
	 * @param   int  $saturation  Saturation (0..100)
	 * @param   int  $value       Value (0..100)
	 *
	 * @return  string
	 *
	 * @since   2.0.0
	 */
	private function hsvToRgb(int $hue, int $saturation, int $value): string
	{
		// Normalize HSV to 0-360, 0-1, 0-1 respectively
		$hue        = max(0, ($hue > 360) ? 0 : $hue);
		$saturation = max(0.0, min($saturation / 100, 1.0));
		$value      = max(0.0, min($value / 100, 1.0));

		/**
		 * Fully unsaturated colors have the same RGB values as the lightness
		 */
		if ($saturation < 0.01)
		{
			$hexValue = str_pad(dechex($value), 2, '0', STR_PAD_LEFT);

			return '#' . str_repeat($hexValue, 3);
		}

		$sectoredHue    = $hue / 60;
		$sector         = floor($sectoredHue);
		$sectorPosition = $sectoredHue - $sector;

		$p = $value * (1.0 - $saturation);
		$q = $value * (1.0 - ($saturation * $sectorPosition));
		$t = $value * (1.0 - ($saturation * (1.0 - $sectorPosition)));

		switch ($sector)
		{
			case 0:
				$r = $value;
				$g = $t;
				$b = $p;
				break;

			case 1:
				$r = $q;
				$g = $value;
				$b = $p;
				break;

			case 2:
				$r = $p;
				$g = $value;
				$b = $t;
				break;

			case 3:
				$r = $p;
				$g = $q;
				$b = $value;
				break;

			case 4:
				$r = $t;
				$g = $p;
				$b = $value;
				break;

			case 5:
			default:
				$r = $value;
				$g = $p;
				$b = $q;
		}

		$r = 255 * $r;
		$g = 255 * $g;
		$b = 255 * $b;

		return '#' .
			str_pad(dechex($r), 2, '0', STR_PAD_LEFT) .
			str_pad(dechex($g), 2, '0', STR_PAD_LEFT) .
			str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
	}

	/**
	 * Is this the administrator application using the Atum template?
	 *
	 * @return  bool
	 *
	 * @since   2.0.0
	 */
	private function isAdminAtum(): bool
	{
		// Is this the site administrator?
		if (!$this->app->isClient('administrator'))
		{
			return false;
		}

		// Is the template in use Atum (the only one supported)?
		if ($this->app->getTemplate() != 'atum')
		{
			return false;
		}

		return true;
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
			$user   = $this->app->getIdentity() ?? new User();
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
		if ($sunset >= $now)
		{
			return false;
		}

		// Between sunset and midnight. Dark Mode.
		return true;
	}

	/**
	 * Is this the site application using the Cassiopeia template?
	 *
	 * @return  bool
	 *
	 * @since   2.0.0
	 */
	private function isSiteCassiopeia(): bool
	{
		// Is this the site administrator?
		if (!$this->app->isClient('site'))
		{
			return false;
		}

		// Is the template in use Isis (the only one supported)?
		if ($this->app->getTemplate() != 'cassiopeia')
		{
			return false;
		}

		return true;
	}

	/**
	 * Postpone loading of the specified CSS file until after the DOM is ready
	 *
	 * @param   string  $url    The URL to the CSS file to load
	 * @param   string  $media  Media query for the CSS file, default "screen"
	 *
	 * @throws  Exception
	 * @since        1.0.0.b1
	 *
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function postponeCSSLoad(string $url, string $media = 'screen')
	{
		/** @var HtmlDocument $doc */
		$doc = $this->app->getDocument();

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
	 * Convert a hex RGB color to a Hue, Saturation, Brightness array.
	 *
	 * @param   string  $hexCode
	 *
	 * @return  array
	 *
	 * @since   2.0.0
	 */
	private function rgbToHsv(string $hexCode): array
	{
		// Normalize the hex code
		$hexCode = trim(ltrim($hexCode, '#'));

		if (strlen($hexCode) == 3)
		{
			$hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
		}

		// Convert to RGB values
		[$red, $green, $blue] = array_map('hexdec', str_split($hexCode, 2));

		$relativeR = ($red / 255);
		$relativeG = ($green / 255);
		$relativeB = ($blue / 255);

		$minRGB = min($relativeR, $relativeG, $relativeB);
		$maxRGB = max($relativeR, $relativeG, $relativeB);
		$chroma = $maxRGB - $minRGB;

		// Brightness is simply the maximum RGB value
		$brightness = 100 * $maxRGB;

		// Saturation is the Chroma divided by the maximum RGB relative value, if Chroma is non-zero. Else it's zero.
		$saturation = 100 * (($chroma < 0.01) ? 0 : ($chroma / $maxRGB));

		// When Chroma is 0 the Hue is also 0 by convention
		$hue = 0;

		// For non-zero Chroma values we need to calculate the hue.
		if ($chroma >= 0.01)
		{
			/**
			 * Hue is calculated on a 360 color circle. The circle is divided into six sectors with an angle of 60
			 * degrees each.
			 *
			 * The first thing we need to do is find which sector and which portion of that sector we're in, depending
			 * on which RGB component was the minimum RGB value.
			 */
			if (($red - $minRGB) <= 0.01)
			{
				$hue = 3 - (($relativeG - $relativeB) / $chroma);
			}
			elseif (($blue - $minRGB) <= 0.01)
			{
				$hue = 1 - (($relativeR - $relativeG) / $chroma);
			}
			else
			{
				$hue = 5 - (($relativeB - $relativeR) / $chroma);
			}

			/**
			 * So far our hue calculation told us which part of a sector we're in. For example, 5.1 means 10% into the
			 * fifth sector. Each sector is 60 degrees. Therefore we multiply by the sector's angle (60 degrees) to get
			 * the position in the 360 degree color wheel.
			 */
			$hue = 60 * $hue;
		}

		return [(int) $hue, (int) $saturation, (int) $brightness];
	}
}
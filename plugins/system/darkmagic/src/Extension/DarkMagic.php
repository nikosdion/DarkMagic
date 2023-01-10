<?php
/**
 *  @package   DarkMagic
 *  @copyright Copyright (c)2019-2023 Nicholas K. Dionysopoulos
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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
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
	 * @since  2.0.0
	 * @var    boolean
	 */
	protected $allowLegacyListeners = true;

	/**
	 * The Joomla! application object
	 *
	 * @since 1.0.0
	 * @var   CMSApplication|SiteApplication|AdministratorApplication
	 */
	protected $app;

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
			'onAfterRender'  => 'onAfterRender',
		];
	}

	/**
	 * Runs after Joomla has rendered the document. Adds the CSS class to the body tag.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @since   2.2.0
	 */
	public function onAfterRender(Event $event): void
	{
		// Make sure I can get basic Joomla objects before proceeding
		try
		{
			$document = ($this->app instanceof CMSApplication) ? $this->app->getDocument() : null;
		}
		catch (Exception $e)
		{
			return;
		}

		// Are we REALLY sure this is an HTML document?
		if (!($document instanceof HtmlDocument) || $document->getMimeEncoding() !== 'text/html')
		{
			return;
		}

		// Make sure we have the `<BODY` opening tag
		$body = $this->app->getBody();

		if (stripos($body, '<body') === false)
		{
			return;
		}

		// Add a class to the document indicating the dark mode setting
		$class = 'joomla-dark-never';

		if ($this->evaluateDarkModeConditions())
		{
			$class = $this->params->get('applywhen', 'always') === 'browser'
				? 'joomla-dark-auto' : 'joomla-dark-always';
		}

		$body = preg_replace_callback(
			'#<body(.*)class\s*=\s*"(.*)"(.*)>#',
			function ($matches) use ($class) {
				return sprintf(
					'<body%sclass="%s %s"%s>',
					$matches[1],
					$matches[2],
					$class,
					$matches[3]
				);
			},
			$body
		);

		$this->app->setBody($body);
	}

	/**
	 * Hooks on Joomla onBeforeRender event, manipulating the document header to activate Dark Mode
	 *
	 * @throws  Exception
	 * @since   1.0.0.b1
	 */
	public function onBeforeRender(Event $event): void
	{
		// Make sure I can get basic Joomla objects before proceeding
		try
		{
			$document = ($this->app instanceof CMSApplication) ? $this->app->getDocument() : null;
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

		// Is this Light Mode?
		if (!$this->evaluateDarkModeConditions())
		{
			$document->setMetaData('color-scheme', 'light');

			return;
		}

		if ($this->app->isClient('administrator'))
		{
			$this->darkMode('backend', 'atum');
		}
		elseif ($this->app->isClient('site'))
		{
			$this->darkMode('frontend', 'cassiopeia');
		}
	}

	/**
	 * Applies the Dark Mode for a specific site section
	 *
	 * @param   string  $siteSection   The site section, frontend or backend
	 * @param   string  $templateName  The template name for which we will apply the Dark Mode
	 *
	 * @since   2.0.0
	 */
	private function darkMode(string $siteSection, string $templateName)
	{
		// Make sure that the site is using the right template
		if (!$this->isThisTemplate($templateName))
		{
			// Tell the browser what kind of color scheme we support
			return;
		}

		// Is Dark Mode disabled for this site section?
		$key = 'enable_' . $siteSection;
		$default = $siteSection === 'site' ? 0 : 1;

		if ($this->params->get($key, $default) == 0)
		{
			return;
		}

		// Get some basic information
		$document  = $this->app->getDocument();
		$applyWhen = $this->params->get('applywhen', 'always');

		$wa = $document->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('plg_system_darkmagic');

		$autoDark        = $applyWhen === 'browser';
		$darkModeEditors = $this->getConfigKey('editors', $siteSection, 1) == 1;
		$customColors    = $this->getConfigKey('colours', $siteSection, 1) == 1;

		// Tell the browser what kind of color scheme we support
		$document->setMetaData('color-scheme', $autoDark ? 'light dark' : 'dark');

		// Load the dark mode CSS
		$wa->useStyle('darkmagic.' . $siteSection . '_template' . ($autoDark ? '_conditional' : ''));

		// Should I apply dark mode in editors?
		if ($darkModeEditors)
		{
			// Apply the TinyMCE skin
			$contentDark = $this->getConfigKey('tinyMceContent', $siteSection, 1) == 1;
			$this->applyTinyMCEDark($autoDark, $contentDark);

			// Apply the CodeMirror theme
			$codeMirrorDark = $this->getConfigKey('codeMirrorDark', $siteSection, '');
			$this->applyCodeMirrorDark($codeMirrorDark, !$autoDark);
		}

		// Should I apply custom colors?
		if ($customColors)
		{
			if ($siteSection === 'frontend')
			{
				$cassiopeiaCustomColourCSS = <<< CSS
:root {
  --cassiopeia-color-primary: hsl(var(--hue, 213), 67%, 20%);
  --cassiopeia-color-link: var(--template-link-color);
  --cassiopeia-color-hover: var(--template-link-hover-color);
}

CSS;

				// Replace Cassiopeia's custom color CSS files
				$styleName = $wa->assetExists('style', 'theme.colors_standard')
					? 'theme.colors_standard' : 'theme.colors_alternative';

				if ($wa->assetExists('style', $styleName))
				{
					$wa->disableStyle($styleName);
					$wa->addInlineStyle($cassiopeiaCustomColourCSS, ['name' => 'theme.colors_standard']);
				}
			}

			// Get inline CSS override
			$hueHSL       = $this->getConfigKey('hue', $siteSection, 'hsl(214, 63%, 20%)') ?: 'hsl(214, 63%, 20%)';
			$bgLight      = $this->getConfigKey('bg-light', $siteSection, '#343a40') ?: '#343a40l';
			$textDark     = $this->getConfigKey('text-dark', $siteSection, '#dee2e6') ?: '#dee2e6';
			$textLight    = $this->getConfigKey('text-light', $siteSection, '#212529') ?: '#212529';
			$linkColor    = $this->getConfigKey('link-color', $siteSection, '#80abe2') ?: '#80abe2';
			$specialColor = $this->getConfigKey('special-color', $siteSection, '#b2bfcd') ?: '#b2bfcd';

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

			preg_match(
				'#^hsla?\(([0-9]+)[\D]+([0-9]+)[\D]+([0-9]+)[\D]+([0-9](?:.\d+)?)?\)$#i',
				$hueHSL,
				$matches
			);
			$hue = $matches[1] ?? 214;

			$overrideCss = <<< CSS
:root {
		--hue: $hue !important;
		--template-bg-light: $bgLight !important;
		--template-text-dark: $textDark !important;
		--template-text-light: $textLight !important;
		--template-link-color: $linkColor !important;
		--template-link-hover-color: $linkHoverColor !important;
		--template-special-color: $specialColor !important;
}
CSS;

			// Apply the inline CSS overrides
			$mediaQuery = $autoDark
				? 'screen and (prefers-color-scheme: dark)'
				: 'screen';
			$wa->addInlineStyle($overrideCss, [], ['media' => $mediaQuery]);

			if ($autoDark)
			{
				/** @var Registry $tParams */
				$tParams           = $this->app->getTemplate(true)->params;
				$lightSpecialColor = $tParams->get('special-color', '#001B4C');

				/** @noinspection HtmlUnknownAttribute */
				$document->addCustomTag(sprintf('<meta name="theme-color" content="%s" media="(prefers-color-scheme: light)">', $lightSpecialColor));
				/** @noinspection HtmlUnknownAttribute */
				$document->addCustomTag(sprintf('<meta name="theme-color" content="%s" media="(prefers-color-scheme: dark)">', $specialColor));
			}
			else
			{
				$document->setMetaData('theme-color', $specialColor);
			}
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
	 * Apply the TinyMCE dark mode theme.
	 *
	 * When $auto is false it loads the oxide-dark theme. Then it's true it loads a custom theme which automatically
	 * switches between oxide and oxide-dark based on the browser's preferences.
	 *
	 * The $contentDark flag controls whether to load a content-dark.css file from the backend template (with a fall
	 * back to the one we provide with the plugin). This is a magic little piece of CSS which makes the editor have a
	 * dark theme in the content editing area. It's best to disable this and have the frontend template provide the
	 * correct color theme.
	 *
	 * @param   bool  $auto         Should the skin change automatically between light and dark?
	 * @param   bool  $contentDark  Should the content be forced to be dark?
	 *
	 * @since   2.2.0
	 */
	private function applyTinyMCEDark(bool $auto = true, bool $contentDark = false)
	{
		$document = $this->app->getDocument();
		$opts     = $document->getScriptOptions('plg_editor_tinymce');

		if (empty($opts) || !is_array($opts))
		{
			return;
		}

		$opts['tinyMCE'] = (!isset($opts['tinyMCE']) || !is_array($opts['tinyMCE'])) ? [] : $opts['tinyMCE'];

		$opts['tinyMCE']['default']         = $opts['tinyMCE']['default'] ?? [];
		$opts['tinyMCE']['default']['skin'] = $opts['tinyMCE']['default']['skin'] ?? 'oxide';

		$hasDefaultSkin = $opts['tinyMCE']['default']['skin'] === 'oxide';

		if (!$auto)
		{
			// Forced dark mode: use oxide-dark and set the body class to `joomla-forced-dark`
			if ($hasDefaultSkin)
			{
				$opts['tinyMCE']['default']['skin'] = 'oxide-dark';
			}

			$opts['tinyMCE']['default']['body_class'] = 'joomla-forced-dark';
		}
		else
		{
			// Auto dark mode: use the custom theme and set the body class to `joomla-suto-dark`
			if ($hasDefaultSkin)
			{
				$opts['tinyMCE']['default']['skin_url'] = '/media/plg_system_darkmagic/css/tinymce';
			}

			$opts['tinyMCE']['default']['body_class'] = 'joomla-auto-dark';
		}

		// Optional: force Dark Mode compatibility in TinyMCE content
		if ($contentDark)
		{
			// Find the content-dark.css file of the current (frontend or backend) template.
			$autoDark = HTMLHelper::_(
				'stylesheet',
				'content-dark.css',
				[
					'pathOnly'    => true,
					'relative'    => true,
					'detectDebug' => true,
				]
			);

			if (empty($autoDark))
			{
				$autoDark = HTMLHelper::_(
					'stylesheet',
					'plg_system_darkmagic/content-dark.css',
					[
						'pathOnly'    => true,
						'relative'    => true,
						'detectDebug' => true,
					]
				);
			}

			// If the file exists, load it as the last CSS file
			if (!empty($autoDark))
			{
				$opts['tinyMCE']['default']['content_css'] =
					$opts['tinyMCE']['default']['content_css'] .
					',' . $autoDark;
			}
		}

		// Apply the new TinyMCE default options
		$document->addScriptOptions('plg_editor_tinymce', $opts);
	}

	/**
	 * Get a config key for a specific section of the site
	 *
	 * @param   string  $key           The base name of the config key
	 * @param   string  $siteSection   The site section, frontend or backend.
	 * @param   string  $defaultValue  The default value to provide if none is specified.
	 *
	 * @return  string  The resulting value.
	 *
	 * @since   2.1.0
	 */
	private function getConfigKey(string $key, string $siteSection, string $defaultValue): string
	{
		return $this->params->get(
			$key . '_' . $siteSection,
			$this->params->get($key, $defaultValue)
		);
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
			str_pad(dechex((int) $r), 2, '0', STR_PAD_LEFT) .
			str_pad(dechex((int) $g), 2, '0', STR_PAD_LEFT) .
			str_pad(dechex((int) $b), 2, '0', STR_PAD_LEFT);
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
	 * Checks if the current template is the specified template or a child template of it.
	 *
	 * @param   string  $templateName  The template we wish to test for.
	 *
	 * @return  bool
	 *
	 * @since   2.1.0
	 */
	private function isThisTemplate(string $templateName): bool
	{
		// Is the template in use Isis (the only one supported)?
		$templateInfo = $this->app->getTemplate(true);

		if ($templateInfo->template === $templateName)
		{
			return true;
		}

		if (isset($templateInfo->parent) && $templateInfo->parent === $templateName)
		{
			return true;
		}

		return false;
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

	/**
	 * Convert the raw CSS of a CodeMirror theme to something suitable for a Dark Mode override.
	 *
	 * @param   string  $theme  The name of the theme to convert
	 *
	 * @return  string|null  The converted CSS; NULL if the theme could not be loaded.
	 *
	 * @since   2.2.0
	 */
	private function themeToDarkModeOverride(string $theme): ?string
	{
		$rawTheme = @file_get_contents(
			JPATH_ROOT . '/media/vendor/codemirror/theme/' . $theme . '.css'
		);

		if ($rawTheme === false)
		{
			return null;
		}

		// Remove the theme prefix.
		$rawTheme = str_replace('.cm-s-' . $theme, '', $rawTheme);

		/**
		 * Make all rules !important so they can override whichever theme is already loaded.
		 *
		 * DO NOT CHANGE THE FOLLOWING LINES! THERE IS A REASON FOR EVERY ONE OF THEM!
		 *
		 * They may look redundant but they are not. If you do not understand why they are not
		 * redundant, start with the dracula.css CodeMirror theme file to understand the concept.
		 *
		 * The first line normalises existing !important annotations, removing a possible space
		 * between the annotation and the following semicolon. It allows the next line to work.
		 * The second line removes all !important annotations adjacent to a semicolon. This will let
		 * the next line to work without duplicating existing !important annotations.
		 * The third line replaces semicolons with !important; which makes all CSS rules have the
		 * !important annotation, ensuring they will override an already loaded theme.
		 */
		$rawTheme = str_replace('!important ', '!important', $rawTheme);
		$rawTheme = str_replace('!important;', ';', $rawTheme);
		$rawTheme = str_replace(';', '!important;', $rawTheme);

		return $rawTheme;
	}

	/**
	 * Apply a dark theme to CodeMirror
	 *
	 * @param   string  $theme   The name of the CodeMirror to apply
	 * @param   bool    $forced  Should the theme be forcibly applied?
	 *
	 * @since   2.2.0
	 */
	private function applyCodeMirrorDark(string $theme, bool $forced = false)
	{
		$input     = $this->app->getInput();
		$extension = PluginHelper::getPlugin('editors', 'codemirror');

		if (
			$input->getCmd('option') === 'com_plugins'
			&& $input->getCmd('view') === 'plugin'
			&& $input->getCmd('layout') === 'edit'
			&& $input->getCmd('extension_id') == (is_object($extension) ? $extension->id : -1)
		)
		{
			return;
		}

		$theme   = $theme ?: 'dracula';
		$media   = $forced ? 'screen' : 'screen and (prefers-color-scheme: dark)';
		$darkCSS = $this->themeToDarkModeOverride($theme) ?? $this->themeToDarkModeOverride('dracula');

		if (empty($darkCSS))
		{
			return;
		}

		$this->app->getDocument()->getWebAssetManager()
			->addInlineStyle(
				$darkCSS,
				[],
				['media' => $media]
			);
	}
}
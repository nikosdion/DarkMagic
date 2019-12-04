<?php
/**
 * @package   DarkMagic
 * @copyright Copyright (c)2019-2019 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
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

	public function onBeforeRender(): void
	{
		try
		{
			$app      = Factory::getApplication();
			$input    = $app->input;
			$document = $app->getDocument();
		}
		catch (Exception $e)
		{
			return;
		}

		// Is this format=html mode?
		if ($input->getCmd('format', 'html') != 'html')
		{
			return;
		}

		// Are we REALLY sure this is an HTML document?
		if (!($document instanceof JDocumentHtml))
		{
			return;
		}

		// Do I need to change the TinyMCE theme?
		$changeTinyMCESkin = $this->params->get('autotinymcetheme', 1);

		// Do I need to apply dark mode?
		if (!$this->enabled)
		{
			// Light Mode. Do I have to reset the TinyMCE skin?
			if ($changeTinyMCESkin)
			{
				$this->changeTinyMceSkin($document, 'lightgray');
			}

			return;
		}

		// Special handling for "browser" activation mode
		if ($this->params->get('applywhen', 'always') == 'browser')
		{
			// Load our special JavaScript
			$document->addScript('../plugins/system/darkmagic/media/js/darkmagic.js', [
				'version' => $this->getMediaVersion()
			], [
				'type'  => 'text/javascript',
				'defer' => true,
				'async' => true,
			]);

			return;
		}

		// Apply the dark mode CSS
		$document->addStyleSheet(
			'../plugins/system/darkmagic/darktheme/administrator/templates/isis/css/custom.css', [
			'version' => $this->getMediaVersion(),
			// conditional

		], [
				'type'  => 'text/css',
				'media' => 'screen',
			]
		);

		// Apply the TinyMCE skin
		if ($changeTinyMCESkin)
		{
			$this->changeTinyMceSkin($document, 'charcoal');
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
	 * Create a media version query string from the modification dates of the CSS files we are going to be loading. This
	 * is simultaneously very accurate and does not divulge plugin version information since each site will have a
	 * different file modification time for each file.
	 *
	 * @return  string
	 * @since   1.0.0.b1
	 */
	private function getMediaVersion(): string
	{
		$fileModTimes = [
			filemtime(__FILE__),
			filemtime(__DIR__ . '/media/js/darkmagic.js'),
			filemtime(__DIR__ . '/darktheme/administrator/templates/isis/css/custom.css'),
			filemtime(__DIR__ . '/darktheme/media/editors/tinymce/skins/charcoal/skin.min.css'),
			filemtime(__DIR__ . '/darktheme/media/editors/tinymce/skins/charcoal/content.inline.min.css'),
			filemtime(__DIR__ . '/darktheme/media/editors/tinymce/skins/charcoal/content.min.css'),
		];

		return sha1(implode(':', $fileModTimes));
	}

	/**
	 * @param   JDocumentHtml  $document
	 * @param   string         $skin
	 *
	 * @return  void
	 * @since   1.0.0.b1
	 */
	private function changeTinyMceSkin(JDocumentHtml $document, string $skin = 'charcoal'): void
	{
		$tinyMceOptions = $document->getScriptOptions('plg_editor_tinymce');

		if (!is_array($tinyMceOptions) || empty($tinyMceOptions) || !isset($tinyMceOptions['tinyMCE']) || !isset($tinyMceOptions['tinyMCE']['default']))
		{
			return;
		}

		$tinyMceOptions['tinyMCE']['default']['skin'] = $skin;

		$document->addScriptOptions('plg_editor_tinymce', $tinyMceOptions, true);
	}
}
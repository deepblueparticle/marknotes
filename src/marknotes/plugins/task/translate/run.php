<?php
/**
 * Translate : receive a MD string and call an online translation
 * application like Google Translate.
 *
 * Answer to URL index.php?task=task.translate.run&content=....&param=language_code
 *
 * @Link https://github.com/Stichoza/google-translate-php/
 */
namespace MarkNotes\Plugins\Task\Translate;

defined('_MARKNOTES') or die('No direct access allowed');

use Stichoza\GoogleTranslate\TranslateClient;

class Run extends \MarkNotes\Plugins\Task\Plugin
{
	protected static $me = __CLASS__;
	protected static $json_settings = 'plugins.task.translate';
	protected static $json_options = 'plugins.options.task.translate';

	/**
	 * After the translation, the syntax of Markdown is sometimes
	 * broken like for images :
	 * instead of keeping
	 *		![ALT](https://.../images/teaser.jpg)
	 * after the translation it becomes
	 *		! [ALT] (https://.../images/teaser.jpg)
	 * and these spaces are bad; the image syntax is broken
	 */
	private static function cleaning(string $content) : string
	{
		$pattern =
			// Search for an image (starting with "!")
			'!'.
			// Followed by one or more space and a [...] construction
			' *(\[[^\]]+\])'.
			// Followed by one or more space and a (...) construction
			' *(\([^\)]+\))';

		// And remove spaces so keep only ![...](...) and not
		// !	[...]  (...) for instance
		$replacement = '!$1$2';
		$content = preg_replace('/'.$pattern.'/', $replacement, $content);

		return $content;
	}

	/**
	 * Call Google API translation library
	 */
	private static function translate(string $content) : string
	{
		$return = '';

		// Retrieve the language used for marknotes
		// Default : translate into the language specified in
		// settings.json -> regional -> language
		$aeSettings = \MarkNotes\Settings::getInstance();
		$arrSettings = $aeSettings->getPlugins('/regional');
		$default = trim($arrSettings['language'] ?? 'en');

		// Get the param parameter which contains, if mentionned,
		// the language to use for the Translation
		// Indeed, we can have Marknotes in French but wish
		// to translate to English f.i.
		$aeFunctions = \MarkNotes\Functions::getInstance();

		$language = trim($aeFunctions->getParam('param', 'string', $default, false, 2));

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug = \MarkNotes\Debug::getInstance();
			$aeDebug->log("Translate into ".$language, "debug");
		}
		/*<!-- endbuild -->*/

		// google-translate-php is using a specific URL
		// Get that URL from the settings of the task
		// and if not mentionned, used the default one
		$url = 'https://translate.google.cn/translate_a/single';
		$url = self::getOptions('url', $url);

		$libs = __DIR__.DS.'libs/google-translate-php/';
		require_once($libs.'Tokens/TokenProviderInterface.php');
		require_once($libs.'Tokens/GoogleTokenGenerator.php');
		require_once($libs.'Tokens/SampleTokenGenerator.php');
		require_once($libs.'TranslateClient.php');

		$tr = new TranslateClient();

		// Don't mention source language, autodetect
		$tr->setSource(null);

		// Defined the target language (the &param value on the
		// querystring, default is the settings -> regionnal ->
		// language
		$tr->setTarget($language);

		// Define the URL of the Google Translate service
		$tr->setUrlBase($url);

		$return = $tr->translate($content);

		return self::cleaning($return);
	}

	private static function getVariable(string $line) : string
	{
		$aeFiles = \MarkNotes\Files::getInstance();
		$aeSession = \MarkNotes\Session::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();

		// Try to retrieve the original_url,
		// this from the YAML block if present
		$yaml = $aeSession->get('yaml', array());
		$arrYAML = array();

		if ($yaml !== array()) {
			$lib=$aeSettings->getFolderLibs()."symfony/yaml/Yaml.php";
			if ($aeFiles->exists($lib)) {
				include_once $lib;
				$arrYAML =  \Symfony\Component\Yaml\Yaml::parse($yaml);
			}
		}

		if (stripos($line, '%yaml_original_url%')!==false) {
			$value = $arrYAML['original_url']??'';
			$line = str_ireplace('%yaml_original_url%', $value, $line);
		}

		return $line;

	}

	public static function run(&$params = null) : bool
	{
		if (self::isEnabled(true)) {
			$aeFunctions = \MarkNotes\Functions::getInstance();

			$content = trim($aeFunctions->getParam('content', 'unsafe', '', false));

			if ($content == '') {
				$content = 'ERROR - The translate task has been called '.
					'but no content has been provided. '.
					'That task requires a mandatory content parameter';

				/*<!-- build:debug -->*/
				$aeSettings = \MarkNotes\Settings::getInstance();
				if ($aeSettings->getDebugMode()) {
					$aeDebug = \MarkNotes\Debug::getInstance();
					$aeDebug->log($content,"error");
				}
				/*<!-- endbuild -->*/
			} else {
				// Call html-to-markdown and make the conversion to MD
				$content = self::translate($content);

				// Get options from settings.json
				$arr = self::getOptions('include', array());

				// Is there a text to add at the end ?
				$after = $arr['after']??'';

				if ($after!=='') {
					$content.=PHP_EOL.PHP_EOL.self::getVariable($after);
				}
			}
		} else {
			$aeSettings = \MarkNotes\Settings::getInstance();
			$content = $aeSettings->getText('action_prohibited');
		}

		header('Content-Type: text/plain; charset=utf-8');
		header('Content-Transfer-Encoding: ascii');
		echo $content;

		die();
	}
}

<?php
/**
 * Export the note as a .html file
 */

namespace MarkNotes\Plugins\Task\Export;

defined('_MARKNOTES') or die('No direct access allowed');

class HTML extends \MarkNotes\Plugins\Task\Plugin
{
	protected static $me = __CLASS__;
	protected static $json_settings = 'plugins.task.export.html';
	protected static $json_options = '';

	private static $extension = 'html';

	/**
	 * Make the conversion
	 */
	public static function run(&$params = null) : bool
	{
		$aeFiles = \MarkNotes\Files::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();
		$aeSession = \MarkNotes\Session::getInstance();

		$final = $aeFiles->removeExtension($params['filename']).'.'.static::$extension;
		$final = $aeSettings->getFolderDocs(true).$final;

		$html = '';

		$arrSettings = $aeSettings->getPlugins(JSON_OPTIONS_CACHE);
		$bCache = boolval($arrSettings['enabled'] ?? false);

		if ($bCache) {
			$aeCache = \MarkNotes\Cache::getInstance();

			// The list of tags can vary from one user to an
			// another so we need to use his username
			$key = $aeSession->getUser().'###'.
				$params['filename'];

			$cached = $aeCache->getItem(md5($key));
			$data = $cached->get();
			$html = $data['html']??'';
		}

		if (trim($html) == '') {
			// Get the HTML content
			$aeTask = \MarkNotes\Tasks\Display::getInstance();
			$html = $aeTask->run($params);

			if ($bCache) {
				// Save the list in the cache
				$arr['from_cache'] = 1;
				$arr['html'] = $html;
				// Get the duration for the HTML cache (default : 31 days)
				$duration = $arrSettings['duration']['html'];
				$cached->set($arr)->expiresAfter($duration);
				$aeCache->save($cached);
				$arr['from_cache'] = 0;
			}
		} else { // if ($html == '')
			/*<!-- build:debug -->*/
			if ($aeSettings->getDebugMode()) {
				$aeDebug = \MarkNotes\Debug::getInstance();
				$aeDebug->log("    Retrieved from cache [".$key."]","debug");
			}
			/*<!-- endbuild -->*/

			// Debug : add a meta cache=1 just after the <head> tag
			// Get the start position of the tag
			preg_match('~<head.*~', $html, $matches, PREG_OFFSET_CAPTURE);
			$pos = $matches[0][1];
			// Get the ">" character so we can know where <head> is
			// positionned since, perhaps, there are a few attributes
			$pos = strpos($html, '>', $pos) + 1;

			// Ok, insert the new meta
			$meta = '<meta name="cached" content="1">';
			$html = substr_replace($html, $meta, $pos, 0);
		}

		// Generate the .html file ... only if not yet there
		// AND ONLY IF THE NOTE DOESN'T CONTAINS ENCRYPTED DATA
		// (otherwise would be no more encrypted in the .html file)
		// Display the HTML rendering of a note
		//if ($aeSession->get('NoteContainsEncryptedData',false)==false) {
		//	if (!$aeFiles->exists($final)) {
				// Accentuated char nightmare : try first without using
				// the decode function. If not OK, then use utf8_decode
				//$bReturn = $aeFiles->create($final, $html);
				//if (!$bReturn) {
				//	$bReturn = $aeFiles->create(utf8_decode($final), //$html);
				//	if (!$bReturn) {
				//		$final = '';
				//		/*<!-- build:debug -->*/
				//		if ($aeSettings->getDebugMode()) {
				//			$aeDebug = \MarkNotes\Debug::getInstance();
				//			$aeDebug->log("Error while trying to create //[".$final."]", "error");
				//		}
				//		/*<!-- endbuild -->*/
				//	}
				//}
			//}  // 	if(!$aeFiles->exists($final))
			// Store the filename so the export->after->display
			// plugin knows which file should be displayed
		//	$params['output'] = $final;

		//} else { // if ($aeSession->get('NoteContainsEncryptedData'
			$params['content'] = $html;
		//}

		return true;
	}
}

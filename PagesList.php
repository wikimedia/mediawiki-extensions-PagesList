<?php
/**
 * PagesList extension
 *
 * For more info see https://mediawiki.org/wiki/Extension:PagesList
 *
 * @file
 * @ingroup Extensions
 * @author Ike Hecht, 2015
 * @license GNU General Public Licence 2.0 or later
 */
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'PagesList' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['PagesList'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['PagesList'] = __DIR__ . '/PagesList.i18n.alias.php';
	wfWarn(
		'Deprecated PHP entry point used for the PagesList extension. ' .
		'Please use wfLoadExtension() instead, ' .
		'see https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the PagesList extension requires MediaWiki 1.29+' );
}

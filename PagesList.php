<?php
/**
 * PagesList extension
 *
 * For more info see http://mediawiki.org/wiki/Extension:PagesList
 *
 * @file
 * @ingroup Extensions
 * @author Ike Hecht, 2015
 * @license GNU General Public Licence 2.0 or later
 */
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'PagesList',
	'author' => array(
		'Ike Hecht',
	),
	'version' => '0.2.0',
	'url' => 'https://www.mediawiki.org/wiki/Extension:PagesList',
	'descriptionmsg' => 'pageslist-desc',
);

$wgAutoloadClasses['PagesList'] = __DIR__ . '/PagesList.class.php';
$wgAutoloadClasses['PagesListHooks'] = __DIR__ . '/PagesList.hooks.php';
$wgAutoloadClasses['PagesListOptions'] = __DIR__ . '/specials/PagesListOptions.php';
$wgAutoloadClasses['SpecialPagesList'] = __DIR__ . '/specials/SpecialPagesList.php';
$wgAutoloadClasses['SpecialPagesListQueryPage'] = __DIR__ .
	'/specials/SpecialPagesListQueryPage.php';
$wgMessagesDirs['PagesList'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['PagesListAlias'] = __DIR__ . '/PagesList.i18n.alias.php';
$wgExtensionMessagesFiles['PagesListMagic'] = __DIR__ . '/PagesList.magic.php';

$wgSpecialPages['PagesList'] = 'SpecialPagesList';
$wgSpecialPages['PagesListQueryPage'] = 'SpecialPagesListQueryPage';

$wgHooks['ParserFirstCallInit'][] = 'PagesListHooks::setupParserFunction';
$wgHooks['BeforePageDisplay'][] = 'PagesListHooks::onBeforePageDisplay';
$wgHooks['ResourceLoaderGetConfigVars'][] = 'PagesListHooks::onResourceLoaderGetConfigVars';

$wgResourceModules['ext.PagesList'] = array(
	'scripts' => 'modules/ext.PagesList.datatables.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'PagesList',
	'dependencies' => 'ext.PagesList.datatables'
);

$wgResourceModules['ext.PagesList.datatables'] = array(
	'scripts' => 'modules/DataTables/media/js/jquery.dataTables.js',
	'styles' => 'modules/DataTables/media/css/jquery.dataTables.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'PagesList'
);

/* Configuration */
/**
 * Show a column on Special:PagesList that shows who the page was last modified by
 */
$wgPagesListShowLastUser = false;

/**
 * Show a column on Special:PagesList that shows when the page was last modified
 *
 * Possible values:
 *	false - Don't display this column
 *	PagesList::LAST_MODIFICATION_HUMAN - Display this column in human-readable format
 *		(i.e. "3 minutes ago" or "Friday at 07:20")
 *	true|PagesList::LAST_MODIFICATION_DATE - Display this column, showing the date
 */
$wgPagesListShowLastModification = false;

/**
 * An array of options for the DataTables plugin. See https://datatables.net/reference/option/ for
 * more information.
 *
 * Example:
 * $wgPagesListDataTablesOptions = array(
 * 	'iDisplayLength' => 25,
 *	// Don't sort by first column - results in sort by "last modified", descending
 * 	'aaSorting' => array()
 * );
 */
$wgPagesListDataTablesOptions = array();

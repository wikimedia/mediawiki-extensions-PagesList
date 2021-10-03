<?php

use MediaWiki\MediaWikiServices;

/**
 * Hooks for PagesList extension
 *
 * @file
 * @ingroup Extensions
 */
class PagesListHooks {

	/**
	 * Make PagesList vars available to JS
	 *
	 * @global array $wgPagesListDataTablesOptions
	 * @param array &$vars
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $wgPagesListDataTablesOptions, $wgPagesListUseAjax,
		$wgPagesListShowLastUser, $wgPagesListShowLastModification;

		$vars['wgPagesList'] = [
			'dataTablesOptions' => FormatJson::encode( $wgPagesListDataTablesOptions ),
			'useAjax' => $wgPagesListUseAjax,
			'showLastUser' => $wgPagesListShowLastUser,
			'showLastModification' => $wgPagesListShowLastModification
		];

		return true;
	}

	/**
	 * If there is a DataTables format on this page, load those modules
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin Unused
	 * @return bool
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		if ( PagesList::$loadDataTables ) {
			PagesList::addDataTablesToOutput( $out );
		}

		return true;
	}

	/**
	 * Set up the #pageslist parser function
	 *
	 * @param Parser &$parser
	 * @return bool
	 */
	public static function setupParserFunction( Parser &$parser ) {
		$parser->setFunctionHook( 'pageslist', __CLASS__ . '::pageslistParserFunction', Parser::SFH_OBJECT_ARGS );

		return true;
	}

	/**
	 * The parser function is called with the form:
	 * {{#pageslist:
	 *    namespace=namespacename | invert=yes/NO | associated=yes/NO | category=categoryname |
	 *    format=plain/ol/ul/table/DATATABLE }}
	 *
	 * For the main namespace, use namespace=main.
	 *
	 * @global boolean $wgPagesListShowLastUser
	 * @global boolean $wgPagesListShowLastModification
	 * @param Parser $parser Unused
	 * @param PPFrame $frame
	 * @param array $args
	 * @return string
	 */
	public static function pageslistParserFunction(
	Parser $parser, PPFrame $frame, array $args ) {
		global $wgPagesListShowLastUser, $wgPagesListShowLastModification, $wgPagesListUseAjax;

		$params = self::extractOptions( $args, $frame );
		$options = [ 'namespace', 'invert', 'associated', 'category', 'basepage', 'format' ];
		foreach ( $options as $option ) {
			$params[$option] = isset( $params[$option] ) ? $params[$option] : null;
		}

		$categoryTitle = Title::makeTitleSafe( NS_CATEGORY, $params['category'] );
		$basePageTitle = Title::newFromText( $params['basepage'] );

		$namespaceId = self::getNamespaceIndex( $params['namespace'] );

		$bools = [ 'invert', 'associated' ];
		foreach ( $bools as $bool ) {
			if ( strtolower( $params[$bool] ) == 'yes' ) {
				$params[$bool] = true;
			} else {
				$params[$bool] = false;
			}
		}

		$pagesList = new PagesList( wfGetDB( DB_REPLICA ), $namespaceId, $params['invert'],
			$params['associated'], $categoryTitle, $basePageTitle );

		if ( $params['format'] === null ) {
			$params['format'] = 'datatable';
		}

		if ( !$wgPagesListUseAjax || $params['format'] !== 'datatable' ) {
			$pagesList->doQuery();
		}

		$output = $pagesList->getList( $params['format'], $wgPagesListShowLastUser,
			$wgPagesListShowLastModification );
		return [ $output, 'noparse' => true, 'isHTML' => true ];
	}

	/**
	 * Converts an array of values in form [0] => "name=value" into a real
	 * associative array in form [name] => value
	 *
	 * @param array $options
	 * @param PPFrame $frame
	 * @return array
	 */
	public static function extractOptions( array $options, PPFrame $frame ) {
		$results = [];

		foreach ( $options as $option ) {
			$pair = explode( '=', $frame->expand( $option ), 2 );
			if ( count( $pair ) == 2 ) {
				$name = strtolower( trim( $pair[0] ) );
				$value = trim( $pair[1] );
				$results[$name] = $value;
			}
		}

		return $results;
	}

	/**
	 * Get the canonical index for this namespace name.
	 *
	 * @todo Place in a utils class
	 * @param string $namespaceName The canonical name of this index or "main" for the main namespace
	 * @return int
	 */
	public static function getNamespaceIndex( $namespaceName ) {
		if ( strtolower( $namespaceName ) === 'main' ) {
			$namespaceName = '';
		}

		if ( $namespaceName === null ) {
			return null;
		} else {
			return MediaWikiServices::getInstance()
				->getNamespaceInfo()
				->getCanonicalIndex( strtolower( $namespaceName ) );
		}
	}
}

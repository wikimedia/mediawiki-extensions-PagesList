<?php

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
	 * @param array $vars
	 * @return boolean
	 */
	public static function onResourceLoaderGetConfigVars( Array &$vars ) {
		global $wgPagesListDataTablesOptions;

		$vars['wgPagesList'] = array(
			'dataTablesOptions' => FormatJson::encode( $wgPagesListDataTablesOptions )
		);

		return true;
	}

	/**
	 * If there is a DataTables format on this page, load those modules
	 *
	 * @param OutputPage $out
	 * @param Skin $skin Unused
	 * @return boolean
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
	 * @param Parser $parser
	 * @return boolean
	 */
	public static function setupParserFunction( Parser &$parser ) {
		$parser->setFunctionHook( 'pageslist', __CLASS__ . '::pageslistParserFunction', SFH_OBJECT_ARGS );

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
		global $wgPagesListShowLastUser, $wgPagesListShowLastModification;

		$params = self::extractOptions( $args, $frame );
		$options = array( 'namespace', 'invert', 'associated', 'category', 'basepage', 'format' );
		foreach ( $options as $option ) {
			$params[$option] = isset( $params[$option] ) ? $params[$option] : null;
		}

		$categoryTitle = Title::makeTitleSafe( NS_CATEGORY, $params['category'] );
		$basePageTitle = Title::newFromText( $params['basepage'] );

		if ( strtolower( $params['namespace'] ) === 'main' ) {
			$params['namespace'] = '';
		}
		if ( is_null( $params['namespace'] ) ) {
			$namespaceId = null;
		} else {
			$namespaceId = MWNamespace::getCanonicalIndex( strtolower( $params['namespace'] ) );
		}

		$bools = array( 'invert', 'associated' );
		foreach ( $bools as $bool ) {
			if ( strtolower( $params[$bool] ) == 'yes' ) {
				$params[$bool] = true;
			} else {
				$params[$bool] = false;
			}
		}

		$pagesList = new PagesList( wfGetDB( DB_SLAVE ), $namespaceId, $params['invert'],
			$params['associated'], $categoryTitle, $basePageTitle );

		if ( is_null( $params['format'] ) ) {
			$params['format'] = 'datatable';
		}

		$output = $pagesList->getList( $params['format'], $wgPagesListShowLastUser,
			$wgPagesListShowLastModification );
		return array( $output, 'noparse' => true, 'isHTML' => true );
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
		$results = array();

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
}

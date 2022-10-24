<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * @author Ike Hecht
 */
class PagesList extends ContextSource {
	/**
	 * Display the last modification column in human-readable format
	 */
	const LAST_MODIFICATION_DATE = 1;

	/**
	 * Display the last modification column, showing the date
	 */
	const LAST_MODIFICATION_HUMAN = 2;

	/**
	 * Namespace to target
	 *
	 * @var int Namespace constant
	 */
	private $namespace;

	/**
	 * Invert the namesace selection - select every namespace other than the one stored in
	 * $namespace
	 *
	 * @var bool
	 */
	private $nsInvert;

	/**
	 * Include the associated namespace of $namespace - its Talk page
	 *
	 * @var bool
	 */
	private $associated;

	/**
	 * Category name to target
	 *
	 * @var Title
	 */
	private $category;

	/**
	 * Base Page to target
	 *
	 * @var Title
	 */
	private $basePage;

	/**
	 * A read-only database object
	 *
	 * @var IDatabase
	 */
	private $db;

	/**
	 * Flag to indicated that the DataTables JS & CSS should later be loaded because there is a
	 * DataTables item on this page
	 *
	 * @var bool
	 */
	public static $loadDataTables = false;

	/**
	 * Result object for the query. Warning: seek before use.
	 *
	 * @var IResultWrapper
	 */
	public $result;

	/**
	 * @param IDatabase $db
	 * @param int|null $namespace A nampesace index
	 * @param bool $nsInvert Set to true to show pages in all namespaces EXCEPT $namespace
	 * @param bool $associated
	 * @param Title|null $category
	 * @param Title|null $basePage
	 * @param IContextSource|null $context
	 */
	function __construct( IDatabase $db, $namespace = null, $nsInvert = false, $associated = false,
		Title $category = null, Title $basePage = null, IContextSource $context = null ) {
		if ( $context ) {
			$this->setContext( $context );
		}

		$this->db = $db;
		$this->namespace = $namespace;
		$this->nsInvert = $nsInvert;
		$this->associated = $associated;
		$this->category = $category;
		$this->basePage = $basePage;
	}

	/**
	 * Perform the db query
	 * The query code is based on ContribsPager
	 *
	 * @param string|bool $offset Index offset, inclusive
	 * @param int|bool $limit Exact query limit
	 * @param string $indexField
	 * @param bool $descending Query direction, false for ascending, true for descending
	 */
	public function doQuery( $offset = false, $limit = false, $indexField = 'rev_timestamp',
		$descending = true ) {
		list( $tables, $fields, $conds, $fname, $options, $join_conds ) = $this->buildQueryInfo( $offset,
			$limit, $indexField, $descending );
		$this->result = $this->db->select(
			$tables, $fields, $conds, $fname, $options, $join_conds
		);
		$this->result->rewind(); // Paranoia
	}

	/**
	 * Generate an array to be turned into the full and final query.
	 *
	 * @param string $offset Index offset, inclusive
	 * @param int $limit Exact query limit
	 * @param string $indexField
	 * @param bool $descending Query direction, false for ascending, true for descending
	 * @return array
	 */
	protected function buildQueryInfo( $offset, $limit, $indexField, $descending ) {
		$fname = __METHOD__ . ' (' . $this->getSqlComment() . ')';
		$info = $this->getQueryInfo();
		$tables = $info['tables'];
		$fields = $info['fields'];
		$conds = isset( $info['conds'] ) ? $info['conds'] : [];
		$options = isset( $info['options'] ) ? $info['options'] : [];
		$join_conds = isset( $info['join_conds'] ) ? $info['join_conds'] : [];

		$options['ORDER BY'] = $indexField . ( $descending ? ' DESC' : ' ASC' );
		if ( $offset ) {
			$options['OFFSET'] = intval( $offset );
		}
		if ( $limit ) {
			$options['LIMIT'] = intval( $limit );
		}
		return [ $tables, $fields, $conds, $fname, $options, $join_conds ];
	}

	/**
	 * Generate an array of basic query info.
	 *
	 * @return array
	 */
	function getQueryInfo() {
		$revQuery = MediaWikiServices::getInstance()->getRevisionStore()->getQueryInfo( [ 'user', 'page' ] );
		$tables = $revQuery['tables'];
		$fields = [
			'namespace' => 'page_namespace',
			'title' => 'page_title',
			'value' => 'rev_timestamp',
			'userId' => $revQuery['fields']['rev_user'],
			'userName' => $revQuery['fields']['rev_user_text'],
		];
		$join_cond = $revQuery['joins'];

		$conds = [
			'page_is_redirect' => 0,
			'page_latest=rev_id'
		];

		if ( is_int( $this->namespace ) ) {
			$conds = array_merge( $conds, $this->getNamespaceCond() );
		}

		if ( $this->category instanceof Title ) {
			$tables[] = 'categorylinks';
			$conds = array_merge( $conds, [ 'cl_to' => $this->category->getDBkey() ] );
			$join_cond['categorylinks'] = [ 'INNER JOIN', 'cl_from = page_id' ];
		}

		if ( $this->basePage instanceof Title ) {
			$conds = array_merge( $conds,
				[ 'page_title' . $this->db->buildLike( $this->basePage->getDBkey() .
					'/', $this->db->anyString() ),
				'page_namespace' => $this->basePage->getNamespace() ]
			);
		}

		$options = [];
		$index = false; # todo
		if ( $index ) {
			$options['USE INDEX'] = [ 'revision' => $index ];
		}
		$queryInfo = [
			'tables' => $tables,
			'fields' => $fields,
			'conds' => $conds,
			'options' => $options,
			'join_conds' => $join_cond
		];

		return $queryInfo;
	}

	/**
	 * Limit the results to specific namespaces
	 *
	 * @return array
	 */
	function getNamespaceCond() {
		$selectedNS = $this->db->addQuotes( $this->namespace );
		$eq_op = $this->nsInvert ? '!=' : '=';
		$bool_op = $this->nsInvert ? 'AND' : 'OR';

		if ( !$this->associated ) {
			return [ "page_namespace $eq_op $selectedNS" ];
		}

		$associatedNS = $this->db->addQuotes(
			MediaWikiServices::getInstance()
				->getNamespaceInfo()
				->getAssociated( $this->namespace )
		);

		return [
			"page_namespace $eq_op $selectedNS " .
			$bool_op .
			" page_namespace $eq_op $associatedNS"
		];
	}

	/**
	 * @return string
	 */
	function getSqlComment() {
		return get_class( $this );
	}

	function getList( $format, $showLastUser, $showLastModification, OutputPage $out = null ) {
		$useAjax = false;
		switch ( $format ) {
			case 'datatable':
				/** @todo modify to check if DataTables submodule exists */
				self::$loadDataTables = true;
				global $wgPagesListUseAjax;
				$useAjax = $wgPagesListUseAjax;
			case 'table':
				return $this->getResultTable( $useAjax, $showLastUser, $showLastModification );
			case 'ol':
			case 'ul':
				return $this->getResultList( $format, $showLastUser, $showLastModification );
			case 'plain':
				return $this->getResultPlain( $showLastUser, $showLastModification );
			default:
				/** @todo maybe return an error or something */
				return 'invalid format';
		}
	}

	/**
	 * Get the fields to sort by title
	 *
	 * @return array
	 */
	public function getOrderFields() {
		return [ 'page_namespace', 'page_title' ];
	}

	/**
	 * Borrowed from AncientPagesPage
	 *
	 * @param Skin $skin Unused
	 * @param object $result Result row
	 * @param bool $showLastUser
	 * @param int|bool $showLastModification
	 * @return string
	 */
	protected function getResultTableRow( $skin, $result, $showLastUser = false,
		$showLastModification = false ) {
		$output = Html::openElement( 'tr' );
		$output .= Html::rawElement( 'td', [], $this->getLinkedTitle( $result ) );
		if ( $showLastUser ) {
			$output .= Html::rawElement( 'td', [], $this->getLastUser( $result ) );
		}
		if ( $showLastModification ) {
			$output .= Html::element( 'td', [],
					$this->getLastModification( $result, $showLastModification ) );
		}
		$output .= Html::closeElement( 'tr' );
		return $output;
	}

	/**
	 * @param bool $useAjax
	 * @param bool $showLastUser
	 * @param int|bool $showLastModification
	 * @return string HTML table
	 */
	protected function getResultTable( $useAjax = true, $showLastUser = false,
		$showLastModification = false ) {
		$output = Html::openElement( 'table',
				[ 'class' => 'pages-list stripe row-border hover' ] );
		$output .= Html::openElement( 'thead' );
		$output .= Html::openElement( 'tr' );
		$output .= Html::rawElement( 'th', [], $this->msg( 'pageslist-title' ) );
		if ( $showLastUser ) {
			$output .= Html::rawElement( 'th', [], $this->msg( 'pageslist-last-user' ) );
		}
		if ( $showLastModification ) {
			$output .= Html::rawElement( 'th', [], $this->msg( 'pageslist-last-modified' ) );
		}
		$output .= Html::closeElement( 'tr' );
		$output .= Html::closeElement( 'thead' );
		$output .= Html::openElement( 'tbody' );
		# If the data won't be loaded later via AJAX, load it here.
		if ( !$useAjax ) {
			while ( $resultRow = $this->result->fetchObject() ) {
				$output .= $this->getResultTableRow( $this->getSkin(), $resultRow, $showLastUser,
					$showLastModification );
			}
		}
		$output .= Html::closeElement( 'tbody' );
		$output .= Html::closeElement( 'table' );
		return $output;
	}

	/**
	 * Get the total number of rows in this result
	 *
	 * @return int
	 */
	public function getTotalRows() {
		return $this->result->numRows();
	}

	/**
	 * Get an array representing this result, used by the API
	 *
	 * @param bool $showLastUser
	 * @param bool $showLastModification
	 * @return array
	 */
	public function getResultArray( $showLastUser = false, $showLastModification = false ) {
		$array = [];
		while ( $result = $this->result->fetchObject() ) {
			$arrayElement = [ 'title' => $this->getLinkedTitle( $result ) ];
			if ( $showLastUser ) {
				$arrayElement['rev_user_text'] = $this->getLastUser( $result );
			}
			if ( $showLastModification ) {
				$arrayElement['rev_timestamp'] = $this->getLastModification( $result, $showLastModification );
			}
			$array[] = $arrayElement;
		}

		return $array;
	}

	/**
	 * Add all necessary DataTables scripts and styles to output
	 *
	 * @param OutputPage &$out
	 */
	public static function addDataTablesToOutput( OutputPage &$out ) {
		$out->addModules( 'ext.PagesList' );
	}

	/**
	 * Get an HTML link to the user page
	 *
	 * @param object $result
	 * @return string HTML
	 */
	protected function getLastUser( $result ) {
		return Linker::userLink( $result->userId, $result->userName );
	}

	/**
	 * Get a linked page title
	 *
	 * @param object $result
	 * @return string HTML
	 */
	protected function getLinkedTitle( $result ) {
		$title = Title::makeTitle( $result->namespace, $result->title );
		return Linker::linkKnown(
			$title, htmlspecialchars( MediaWikiServices::getInstance()->getContentLanguage()
				->convert( $title->getPrefixedText() ) )
		);
	}

	/**
	 * Get the date of the last modification
	 *
	 * @param object $result
	 * @param int|bool $showLastModification
	 * @return string HTML
	 */
	protected function getLastModification( $result,
		$showLastModification = self::LAST_MODIFICATION_DATE ) {
		// Uses == to match where set to "true"
		if ( $showLastModification == self::LAST_MODIFICATION_DATE ) {
			$output = $this->getLastModificationDate( $result );
		} elseif ( $showLastModification === self::LAST_MODIFICATION_HUMAN ) {
			$output = $this->getLastModificationHuman( $result );
		} else {
			$output = '';/** @todo throw error? or default to LAST_MODIFICATION_DATE? */
		}
		return $output;
	}

	/**
	 * Get the date of the last modification as a plain-old date
	 *
	 * @param object $result
	 * @return string HTML
	 */
	private function getLastModificationDate( $result ) {
		return $this->getLanguage()->userDate( $result->value, $this->getUser() );
	}

	/**
	 * Get the date of the last modification in human-readable format
	 *
	 * @param object $result
	 * @return string HTML
	 */
	private function getLastModificationHuman( $result ) {
		return $this->getLanguage()->getHumanTimestamp(
			new MWTimestamp( $result->value ),
			null,
			$this->getUser()
		);
	}

	/**
	 * @param string $format
	 * @param bool $showLastUser
	 * @param int|bool $showLastModification
	 * @return string HTML list
	 */
	protected function getResultList( $format, $showLastUser = false, $showLastModification = false ) {
		$html = Html::openElement( $format, [ 'class' => 'pageslist' ] );
		while ( $resultRow = $this->result->fetchObject() ) {
			$html .= Html::rawElement( 'li', [],
					$this->getListItem( $resultRow, $showLastUser, $showLastModification ) );
		}
		$html .= Html::closeElement( $format );

		return $html;
	}

	/**
	 * @param bool $showLastUser
	 * @param int|bool $showLastModification
	 * @return string HTML comma separated list
	 */
	protected function getResultPlain( $showLastUser = false, $showLastModification = false ) {
		$html = Html::openElement( 'div', [ 'class' => 'pageslist' ] );
		$list = [];
		while ( $resultRow = $this->result->fetchObject() ) {
			$list[] = $this->getListItem( $resultRow, $showLastUser, $showLastModification );
		}
		$html .= $this->getLanguage()->commaList( $list );
		$html .= Html::closeElement( 'div' );

		return $html;
	}

	/**
	 * Get a single list item
	 *
	 * @param object $resultRow
	 * @param bool $showLastUser
	 * @param int|bool $showLastModification
	 * @return string HTML list item
	 */
	public function getListItem( $resultRow, $showLastUser, $showLastModification ) {
		$extras = [];
		if ( $showLastUser ) {
			$extras[] = $this->getLastUser( $resultRow );
		}
		if ( $showLastModification ) {
			$extras[] = $this->getLastModification( $resultRow, $showLastModification );
		}
		$commaList = $this->getLanguage()->commaList( $extras );
		return $this->getLanguage()->specialList( $this->getLinkedTitle( $resultRow ), $commaList );
	}
}

<?php

/**
 *
 *
 * @author Ike Hecht
 */
class PagesList extends ContextSource {
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
	 * @var boolean
	 */
	private $nsInvert;

	/**
	 * Include the associated namespace of $namespace - its Talk page
	 *
	 * @var boolean
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
	 * @var DatabaseBase
	 */
	private $db;

	/**
	 * Flag to indicated that the DataTables JS & CSS should later be loaded because there is a
	 * DataTables item on this page
	 *
	 * @var Boolean
	 */
	public static $loadDataTables = false;

	/**
	 * The index to actually be used for ordering. This is a single column,
	 * for one ordering, even if multiple orderings are supported.
	 * @todo fixme
	 * @var string
	 */
	protected $indexField = 'rev_timestamp';

	/**
	 * Result object for the query. Warning: seek before use.
	 *
	 * @var ResultWrapper
	 */
	public $result;

	/**
	 *
	 * @param DatabaseBase $db
	 * @param string $namespace
	 * @param boolean $nsInvert
	 * @param boolean $associated
	 * @param Title $category
	 * @param Title $basePage
	 * @param IContextSource $context
	 */
	function __construct( DatabaseBase $db, $namespace = null, $nsInvert = false, $associated = false,
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

		$this->doQuery();
	}

	/**
	 * Perform the db query
	 * The query code is based on ContribsPager
	 */
	protected function doQuery() {
		# Use the child class name for profiling
		$fname = __METHOD__ . ' (' . get_class( $this ) . ')';
		wfProfileIn( $fname );

		list( $tables, $fields, $conds, $fname, $options, $join_conds ) = $this->buildQueryInfo();
		$this->result = $this->db->select(
			$tables, $fields, $conds, $fname, $options, $join_conds
		);
		$this->result->rewind(); // Paranoia

		wfProfileOut( $fname );
	}

	/**
	 * Generate an array to be turned into the full and final query.
	 *
	 * @return array
	 */
	protected function buildQueryInfo() {
		$fname = __METHOD__ . ' (' . $this->getSqlComment() . ')';
		$info = $this->getQueryInfo();
		$tables = $info['tables'];
		$fields = $info['fields'];
		$conds = isset( $info['conds'] ) ? $info['conds'] : array();
		$options = isset( $info['options'] ) ? $info['options'] : array();
		$join_conds = isset( $info['join_conds'] ) ? $info['join_conds'] : array();

		#$options['ORDER BY'] = $this->indexField . ' DESC';

		return array( $tables, $fields, $conds, $fname, $options, $join_conds );
	}

	/**
	 * Generate an array of basic query info.
	 *
	 * @return array
	 */
	function getQueryInfo() {
		$tables = array( 'revision', 'page' );
		$fields = array(
			'namespace' => 'page_namespace',
			'title' => 'page_title',
			'value' => 'rev_timestamp',
			'userId' => 'rev_user',
			'userName' => 'rev_user_text'
		);
		$join_cond = array();

		$conds = array(
			'page_is_redirect' => 0,
			'page_latest=rev_id'
		);

		if ( is_int( $this->namespace ) ) {
			$conds = array_merge( $conds, $this->getNamespaceCond() );
		}

		if ( $this->category instanceof Title ) {
			$tables[] = 'categorylinks';
			$conds = array_merge( $conds, array( 'cl_to' => $this->category->getDBkey() ) );
			$join_cond['categorylinks'] = array( 'INNER JOIN', 'cl_from = page_id' );
		}

		if ( $this->basePage instanceof Title ) {
			$conds = array_merge( $conds,
				array( 'page_title' . $this->db->buildLike( $this->basePage->getDBkey() .
					'/', $this->db->anyString() ),
				'page_namespace' => $this->basePage->getNamespace() )
			);
		}

		$options = array();
		$index = false; #todo
		if ( $index ) {
			$options['USE INDEX'] = array( 'revision' => $index );
		}
		$queryInfo = array(
			'tables' => $tables,
			'fields' => $fields,
			'conds' => $conds,
			'options' => $options,
			'join_conds' => $join_cond
		);

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
			return array( "page_namespace $eq_op $selectedNS" );
		}

		$associatedNS = $this->db->addQuotes(
			MWNamespace::getAssociated( $this->namespace )
		);

		return array(
			"page_namespace $eq_op $selectedNS " .
			$bool_op .
			" page_namespace $eq_op $associatedNS"
		);
	}

	/**
	 * @return string
	 */
	function getSqlComment() {
		return get_class( $this );
	}

	function getList( $format, $showLastUser, $showLastModification, OutputPage $out = null ) {
		if ( !isset( $out ) ) {
			$out = $this->getOutput();
		}
		switch ( $format ) {
			case 'datatable':
				/** @todo modify to check if DataTables submodule exists */
				self::$loadDataTables = true;
			case 'table':
				return $this->getResultTable( $showLastUser, $showLastModification );
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
		return array( 'page_namespace', 'page_title' );
	}

	/**
	 * Borrowed from AncientPagesPage
	 *
	 * @param Skin $skin Unused
	 * @param object $result Result row
	 * @param boolean $showLastUser
	 * @param boolean $showLastModification
	 * @return string
	 */
	protected function getResultTableRow( $skin, $result, $showLastUser = false,
		$showLastModification = false ) {
		$output = Html::openElement( 'tr' );
		$output .= Html::rawElement( 'td', array(), $this->getLinkedTitle( $result ) );
		if ( $showLastUser ) {
			$output .= Html::rawElement( 'td', array(), $this->getLastUser( $result ) );
		}
		if ( $showLastModification ) {
			$output .= Html::element( 'td', array(), $this->getLastModification( $result ) );
		}
		$output .= Html::closeElement( 'tr' );
		return $output;
	}

	/**
	 *
	 * @param boolean $showLastUser
	 * @param boolean $showLastModification
	 * @return string HTML table
	 */
	protected function getResultTable( $showLastUser = false, $showLastModification = false ) {
		$output = Html::openElement( 'table',
				array( 'class' => 'pages-list stripe row-border hover' ) );
		$output .= Html::openElement( 'thead' );
		$output .= Html::openElement( 'tr' );
		$output .= Html::rawElement( 'th', array(), $this->msg( 'pageslist-title' ) );
		if ( $showLastUser ) {
			$output .= Html::rawElement( 'th', array(), $this->msg( 'pageslist-last-user' ) );
		}
		if ( $showLastModification ) {
			$output .= Html::rawElement( 'th', array(), $this->msg( 'pageslist-last-modified' ) );
		}
		$output .= Html::closeElement( 'tr' );
		$output .= Html::closeElement( 'thead' );
		$output .= Html::openElement( 'tbody' );
		while ( $resultRow = $this->result->fetchObject() ) {
			$output .= $this->getResultTableRow( $this->getSkin(), $resultRow, $showLastUser,
				$showLastModification );
		}
		$output .= Html::closeElement( 'tbody' );
		$output .= Html::closeElement( 'table' );
		return $output;
	}


	/**
	 * Add all necessary DataTables scripts and styles to output
	 *
	 * @param OutputPage $out
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
	 * @global Language $wgContLang
	 * @param object $result
	 * @return string HTML
	 */
	protected function getLinkedTitle( $result ) {
		global $wgContLang;
		$title = Title::makeTitle( $result->namespace, $result->title );
		return Linker::linkKnown(
				$title, htmlspecialchars( $wgContLang->convert( $title->getPrefixedText() ) )
		);
	}

	/**
	 * Get the date of the last modification
	 *
	 * @param object $result
	 * @return string HTML
	 */
	protected function getLastModification( $result ) {
		return $this->getLanguage()->userDate( $result->value, $this->getUser() );
	}

	/**
	 *
	 * @param string $format
	 * @param boolean $showLastUser
	 * @param boolean $showLastModification
	 * @return string HTML list
	 */
	protected function getResultList( $format, $showLastUser = false, $showLastModification = false ) {
		$html = Html::openElement( $format, array( 'class' => 'pageslist' ) );
		while ( $resultRow = $this->result->fetchObject() ) {
			$html .= Html::rawElement( 'li', array(),
					$this->getListItem( $resultRow, $showLastUser, $showLastModification ) );
		}
		$html .= Html::closeElement( $format );

		return $html;
	}

	/**
	 *
	 * @param boolean $showLastUser
	 * @param boolean $showLastModification
	 * @return string HTML comma separated list
	 */
	protected function getResultPlain( $showLastUser = false, $showLastModification = false ) {
		$html = Html::openElement( 'div', array( 'class' => 'pageslist' ) );
		$list = array();
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
	 * @param boolean $showLastUser
	 * @param boolean $showLastModification
	 * @return string HTML list item
	 */
	public function getListItem( $resultRow, $showLastUser, $showLastModification ) {
		$extras = array();
		if ( $showLastUser ) {
			$extras[] = $this->getLastUser( $resultRow );
		}
		if ( $showLastModification ) {
			$extras[] = $this->getLastModification( $resultRow );
		}
		$commaList = $this->getLanguage()->commaList( $extras );
		return $this->getLanguage()->specialList( $this->getLinkedTitle( $resultRow ), $commaList );
	}
}

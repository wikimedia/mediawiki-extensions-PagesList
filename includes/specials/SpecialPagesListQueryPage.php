<?php
/**
 * SpecialPage for PagesList extension
 *
 * @file
 * @ingroup Extensions
 */

class SpecialPagesListQueryPage extends QueryPage {
	/**
	 * @var PagesList
	 */
	private $pagesList;

	/**
	 * @var PagesListOptions
	 */
	private $pagesListOptions;

	public function __construct() {
		parent::__construct( 'PagesListQueryPage' );

		$opts = $this->fetchOptionsFromRequest( PagesListOptions::getDefaultOptions() );
		$categoryTitle = Title::makeTitleSafe( NS_CATEGORY, $opts['categories'] );
		$basePageTitle = Title::newFromText( $opts['basepage'] );
		$this->pagesList = new PagesList( wfGetDB( DB_REPLICA ), $opts['namespace'], $opts['invert'],
			$opts['associated'], $categoryTitle, $basePageTitle );
		$this->pagesListOptions = new PagesListOptions(
			$this->getPageTitle(), $opts, $this->getContext() );
	}

	/**
	 * Fetch values for a FormOptions object from the WebRequest associated with this instance.
	 *
	 * Intended for subclassing, e.g. to add a backwards-compatibility layer.
	 *
	 * @param FormOptions $opts
	 * @return FormOptions
	 */
	protected function fetchOptionsFromRequest( $opts ) {
		$opts->fetchValuesFromRequest( $this->getRequest() );

		return $opts;
	}

	function isExpensive() {
		return true;
	}

	function getQueryInfo() {
		return $this->pagesList->getQueryInfo();
	}

	function usesTimestamps() {
		return true;
	}

	function getOrderFields() {
		return $this->pagesList->getOrderFields();
	}

	function sortDescending() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'pages';
	}

	/**
	 * Borrowed from AncientPagesPage
	 *
	 * @global boolean $wgPagesListShowLastModification
	 * @param Skin $skin Unused
	 * @param object $result Result row
	 * @return string
	 */
	function formatResult( $skin, $result ) {
		global $wgPagesListShowLastUser, $wgPagesListShowLastModification;

		return $this->pagesList->getListItem( $result, $wgPagesListShowLastUser,
				$wgPagesListShowLastModification );
	}

	public function getPageHeader() {
		return $this->pagesListOptions->getPageHeader();
	}

	public function isIncludable() {
		return true;
	}
}

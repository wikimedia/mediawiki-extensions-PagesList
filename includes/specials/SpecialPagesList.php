<?php

/**
 * SpecialPage for PagesList extension
 *
 * @file
 * @ingroup Extensions
 */
class SpecialPagesList extends IncludableSpecialPage {
	/**
	 * @var PagesList
	 */
	private $pagesList;

	/**
	 * @var PagesListOptions
	 */
	private $pagesListOptions;

	public function __construct() {
		parent::__construct( 'PagesList' );

		global $wgPagesListUseAjax;

		$opts = $this->fetchOptionsFromRequest( PagesListOptions::getDefaultOptions() );
		$categoryTitle = Title::makeTitleSafe( NS_CATEGORY, $opts['categories'] );
		$basePageTitle = Title::newFromText( $opts['basepage'] );
		$this->pagesList = new PagesList( wfGetDB( DB_REPLICA ), $opts['namespace'], $opts['invert'],
			$opts['associated'], $categoryTitle, $basePageTitle );
		if ( !$wgPagesListUseAjax ) {
			$this->pagesList->doQuery();
		}
		$this->pagesListOptions = new PagesListOptions(
			$this->getPageTitle(), $opts, $this->getContext() );
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub Unused
	 */
	public function execute( $sub ) {
		global $wgPagesListShowLastUser, $wgPagesListShowLastModification;

		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'pageslist' ) );
		$out->addHTML( $this->pagesListOptions->getPageHeader() );
		/** @todo modify to check if DataTables submodule exists */
		$out->addHTML( $this->pagesList->getList( 'datatable', $wgPagesListShowLastUser,
				$wgPagesListShowLastModification, $out ) );
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

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'pages';
	}
}

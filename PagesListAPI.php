<?php

/**
 * API for AJAX requests from DataTables
 * @author Ike Hecht
 */
class PagesListAPI extends ApiBase {
	/**
	 *
	 * @var PagesList
	 */
	private $pagesList;

	/**
	 * The total number of Pages in the full list of Pages
	 *
	 * @var int
	 */
	private $totalRows;

	/**
	 *
	 * @global boolean $wgPagesListShowLastUser
	 * @global boolean $wgPagesListShowLastModification
	 * @return boolean
	 */
	public function execute() {
		global $wgPagesListShowLastUser, $wgPagesListShowLastModification;

		# Will generate a warning
		$request = $this->extractRequestParams();

		/** @todo implement the rest of the parameters to the constructor */
		$this->pagesList = new PagesList( wfGetDB( DB_SLAVE ) );
		/**
		 * @todo Gets the total rows. This is queried on every AJAX call,
		 * which is bad for performance.
		 */
		$this->pagesList->doQuery();
		$this->totalRows = $this->pagesList->getTotalRows();

		// Borrowed from ssp.class.php
		$start = $length = false;
		if ( isset( $request['start'] ) && $request['length'] != -1 ) {
			$start = $request['start'];
			$length = $request['length'];
		}

		$this->pagesList->doQuery( $start, $length );
		$data = $this->pagesList->getResultArray( $wgPagesListShowLastUser,
			$wgPagesListShowLastModification );

		$result = $this->getResult();

		$result->addValue( null, 'data', $data );
		$result->addValue( null, 'recordsTotal', $this->totalRows );
		$result->addValue( null, 'recordsFiltered', $this->totalRows );/** @todo implement? */
		$result->addValue( null, 'draw', $request['draw'] );

		return true;
	}

	/**
	 *
	 * @return string
	 */
	public function getDescription() {
		return 'Shows a list of pages contained in the wiki.';
	}

	/**
	 *
	 * @return array
	 */
	public function getAllowedParams() {
		return array(
			'draw' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false
			),
			'start' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false
			),
			'length' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false
			)
		);
	}

	/**
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array(
			'draw' => 'Value to ensure response matches this request',
			'start' => 'Result offset, zero-based',
			'length' => 'Number of results to return'
		);
	}

	/**
	 *
	 * @return array
	 */
	public function getExamples() {
		return array(
			'api.php?action=' . $this->getModuleName() . '&draw=2&format=json&start=10&length=10'
			=> 'Get results 10-19 from the PagesList'
		);
	}
}

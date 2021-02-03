<?php

/**
 * PagesListOptions
 *
 * @author Ike Hecht
 */
class PagesListOptions extends ContextSource {
	/**
	 * @var Title
	 */
	private $pageTitle;

	/**
	 * @var FormOptions
	 */
	private $opts;

	/**
	 * @param Title $pageTitle
	 * @param FormOptions $opts
	 * @param IContextSource|null $context
	 */
	function __construct( Title $pageTitle, FormOptions $opts, IContextSource $context = null ) {
		$this->pageTitle = $pageTitle;
		$this->opts = $opts;

		if ( $context ) {
			$this->setContext( $context );
		}
	}

	/**
	 * Get a header for this page that allows selection of namespace & category
	 * borrowed from SpecialRecentChanges
	 *
	 * @global string $wgScript
	 * @return string HTML header
	 */
	public function getPageHeader() {
		global $wgScript;
		$opts = $this->opts;

		$extraOpts = $this->getExtraOptions( $opts );
		$extraOptsCount = count( $extraOpts );
		$count = 0;
		$submit = ' ' . Xml::submitbutton( $this->msg( 'allpagessubmit' )->text() );

		$out = Xml::openElement( 'table', [ 'id' => 'pageslist-header' ] );
		foreach ( $extraOpts as $name => $optionRow ) {
			# Add submit button to the last row only
			++$count;
			$addSubmit = ( $count === $extraOptsCount ) ? $submit : '';

			$out .= Xml::openElement( 'tr' );
			if ( is_array( $optionRow ) ) {
				$out .= Xml::tags(
						'td', [ 'class' => 'mw-label mw-' . $name . '-label' ], $optionRow[0]
				);
				$out .= Xml::tags(
						'td', [ 'class' => 'mw-input' ], $optionRow[1] . $addSubmit
				);
			} else {
				$out .= Xml::tags(
						'td', [ 'class' => 'mw-input', 'colspan' => 2 ], $optionRow . $addSubmit
				);
			}
			$out .= Xml::closeElement( 'tr' );
		}
		$out .= Xml::closeElement( 'table' );

		$unconsumed = $opts->getUnconsumedValues();
		foreach ( $unconsumed as $key => $value ) {
			$out .= Html::hidden( $key, $value );
		}

		$t = $this->pageTitle;
		$out .= Html::hidden( 'title', $t->getPrefixedText() );

		$form = Xml::tags( 'form', [ 'action' => $wgScript ], $out );

		return Xml::fieldset(
				$this->msg( 'pageslist-legend' )->text(), $form, [ 'class' => 'ploptions' ]
		);
	}

	/**
	 * Get options to be displayed in a form
	 *
	 * @param FormOptions $opts
	 * @return array
	 */
	function getExtraOptions( $opts ) {
		$opts->consumeValues( [
			'namespace', 'invert', 'associated', 'categories', 'basepage'
		] );

		$extraOpts = [];
		$extraOpts['namespace'] = $this->namespaceFilterForm( $opts );
		$extraOpts['category'] = $this->categoryFilterForm( $opts );
		$extraOpts['basepage'] = $this->basepageFilterForm( $opts );

		return $extraOpts;
	}

	/**
	 * Creates the choose namespace selection
	 *
	 * @param FormOptions $opts
	 * @return string
	 */
	protected function namespaceFilterForm( FormOptions $opts ) {
		$nsSelect = Html::namespaceSelector(
				[ 'selected' => $opts['namespace'], 'all' => '' ],
				[ 'name' => 'namespace', 'id' => 'namespace' ]
		);
		$nsLabel = Xml::label( $this->msg( 'namespace' )->text(), 'namespace' );
		$invert = Xml::checkLabel(
				$this->msg( 'invert' )->text(), 'invert', 'nsinvert', $opts['invert'],
				[ 'title' => $this->msg( 'tooltip-invert' )->text() ]
		);
		$associated = Xml::checkLabel(
				$this->msg( 'namespace_association' )->text(), 'associated', 'nsassociated',
				$opts['associated'], [ 'title' => $this->msg( 'tooltip-namespace_association' )->text() ]
		);

		return [ $nsLabel, "$nsSelect $invert $associated" ];
	}

	/**
	 * Create an input to filter changes by categories
	 * Borrowed from SpecialRecentChanges
	 *
	 * @param FormOptions $opts
	 * @return array
	 */
	protected function categoryFilterForm( FormOptions $opts ) {
		list( $label, $input ) = Xml::inputLabelSep( $this->msg( 'pageslist-categories' )->text(),
				'categories', 'mw-categories', false, $opts['categories'] );

		return [ $label, $input ];
	}

	/**
	 * @param FormOptions $opts
	 * @return array
	 */
	protected function basepageFilterForm( FormOptions $opts ) {
		list( $label, $input ) = Xml::inputLabelSep( $this->msg( 'pageslist-basepage' )->text(),
				'basepage', 'mw-basepage', false, $opts['basepage'] );

		return [ $label, $input ];
	}

	/**
	 * Get a FormOptions object containing the default options
	 *
	 * @return FormOptions
	 */
	public static function getDefaultOptions() {
		$opts = new FormOptions();

		$opts->add( 'namespace', '', FormOptions::INTNULL );
		$opts->add( 'invert', false );
		$opts->add( 'associated', false );

		$opts->add( 'categories', '' );
		$opts->add( 'basepage', '' );

		return $opts;
	}
}

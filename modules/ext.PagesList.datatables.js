/* eslint-disable */
( document ).ready( function ( $ ) {
	var conf = mw.config.get( 'wgPagesList' ),
		optionsConf = JSON.parse( conf.dataTablesOptions ),
		ajaxConf = {};

	if ( conf.useAjax ) {
		ajaxConf = {
			/*
			 * Ordering and searching are disabled because DataTables sends the ordering info as an
			 * array and Mediawiki's API doesn't like arrays. There is probably a workaround using
			 * the APIGetAllowedParams or some other hook.
			 */
			searching: false,
			ordering: false,
			serverSide: true,
			ajax: {
				url: mw.util.wikiScript( 'api' ),
				type: 'GET',
				data: { action: 'pageslist', format: 'json' },
				dataType: 'json'
			},
			columns: [
				{ data: 'title' }
			]
		};
		if ( conf.showLastUser ) {
			ajaxConf.columns.push( { data: 'rev_user_text' } );
		}
		if ( conf.showLastModification ) {
			ajaxConf.columns.push( { data: 'rev_timestamp' } );
		}
	}

	/* FIXME: If one table on this page uses DataTables, they all will, even if format=table */
	$( 'table.pages-list' ).dataTable( $.extend( { }, ajaxConf, optionsConf ) );
} );

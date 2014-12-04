jQuery( document ).ready( function( $ ) {
    conf = mw.config.get( 'wgPagesList' );
    $( 'table.pages-list' ).dataTable( JSON.parse( conf.dataTablesOptions ) );
} );

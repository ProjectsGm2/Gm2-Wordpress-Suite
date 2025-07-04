( function( blocks, element ) {
    var el = element.createElement;
    blocks.registerBlockType( 'gm2/breadcrumbs', {
        title: 'GM2 Breadcrumbs',
        icon: 'menu',
        category: 'widgets',
        edit: function() {
            return el( 'p', null, 'GM2 Breadcrumbs' );
        },
        save: function() {
            return null;
        }
    } );
} )( window.wp.blocks, window.wp.element );


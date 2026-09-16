( function ( blocks, element, serverSideRender, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	( window.ShopForgeBlocks || [] ).forEach( function ( block ) {
		blocks.registerBlockType( block.name, {
			title: block.title,
			icon: block.icon,
			category: 'woocommerce',
			edit: function () {
				return el(
					'div',
					{ className: 'shopforge-block-preview' },
					el( serverSideRender, { block: block.name } )
				);
			},
			save: function () {
				return null; // render dinamico lato server
			},
		} );
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.i18n );

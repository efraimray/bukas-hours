/**
 * Bukas Hours block — editor script.
 *
 * Written against WordPress globals (no JSX, no build step) so the plugin
 * can be zipped and installed as-is. The block is dynamic: PHP renders it,
 * and ServerSideRender shows the same output inside the editor.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'bukas-hours/status', {
		edit: function ( props ) {
			var show = props.attributes.show;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Display', 'bukas-hours' ) },
						el( SelectControl, {
							label: __( 'Show', 'bukas-hours' ),
							value: show,
							options: [
								{ label: __( 'Status badge only', 'bukas-hours' ), value: 'status' },
								{ label: __( 'Weekly hours only', 'bukas-hours' ), value: 'hours' },
								{ label: __( 'Badge + weekly hours', 'bukas-hours' ), value: 'both' },
								{ label: __( 'Badge, hours and holidays', 'bukas-hours' ), value: 'all' }
							],
							onChange: function ( value ) {
								props.setAttributes( { show: value } );
							}
						} )
					)
				),
				el( ServerSideRender, { block: 'bukas-hours/status', attributes: props.attributes } )
			);
		},
		// Dynamic block: nothing is saved in post content except attributes.
		save: function () {
			return null;
		}
	} );
} )( window.wp );

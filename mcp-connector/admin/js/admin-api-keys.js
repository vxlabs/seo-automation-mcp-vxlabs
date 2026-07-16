( function ( $ ) {
	'use strict';

	function postAjax( action, data ) {
		return $.post( mcpConnectorAdmin.ajaxUrl, Object.assign( {
			action: action,
			nonce: mcpConnectorAdmin.nonce,
		}, data ) );
	}

	function showRevealModal( fullKey, connectorUrl ) {
		$( '#mcp-revealed-key' ).val( fullKey );
		$( '#mcp-revealed-url' ).val( connectorUrl );
		$( '#mcp-reveal-modal' ).show();
	}

	function copyField( $field ) {
		$field.select();
		document.execCommand( 'copy' );
	}

	$( document ).on( 'click', '#mcp-generate-key', function () {
		var $btn = $( this );
		var label = $( '#mcp-key-label' ).val();
		var userId = $( '#mcp-key-user' ).val();

		if ( ! userId ) {
			window.alert( 'Please choose a user for this key to act as.' );
			return;
		}

		$btn.prop( 'disabled', true ).text( 'Generating…' );

		postAjax( 'mcp_generate_key', { label: label, user_id: userId } )
			.done( function ( response ) {
				if ( response.success ) {
					showRevealModal( response.data.full_key, response.data.connector_url );
				} else {
					window.alert( response.data && response.data.message ? response.data.message : 'Failed to generate key.' );
				}
			} )
			.fail( function () {
				window.alert( 'Request failed. Please try again.' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( 'Generate New Key' );
			} );
	} );

	$( document ).on( 'click', '.mcp-revoke-key', function ( e ) {
		e.preventDefault();

		if ( ! window.confirm( 'Revoke this API key? Any client using it will immediately lose access.' ) ) {
			return;
		}

		var id = $( this ).data( 'id' );

		postAjax( 'mcp_revoke_key', { id: id } ).done( function ( response ) {
			if ( response.success ) {
				window.location.reload();
			} else {
				window.alert( response.data && response.data.message ? response.data.message : 'Failed to revoke key.' );
			}
		} );
	} );

	$( document ).on( 'click', '.mcp-delete-key', function ( e ) {
		e.preventDefault();

		if ( ! window.confirm( 'Permanently delete this revoked key record?' ) ) {
			return;
		}

		var id = $( this ).data( 'id' );

		postAjax( 'mcp_delete_key', { id: id } ).done( function ( response ) {
			if ( response.success ) {
				window.location.reload();
			} else {
				window.alert( response.data && response.data.message ? response.data.message : 'Failed to delete key.' );
			}
		} );
	} );

	$( document ).on( 'click', '#mcp-copy-key', function () {
		copyField( $( '#mcp-revealed-key' ) );
	} );

	$( document ).on( 'click', '#mcp-copy-url', function () {
		copyField( $( '#mcp-revealed-url' ) );
	} );

	$( document ).on( 'click', '#mcp-close-modal', function () {
		$( '#mcp-reveal-modal' ).hide();
		window.location.reload();
	} );
} )( jQuery );

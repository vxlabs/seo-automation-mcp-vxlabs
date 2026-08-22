( function ( $ ) {
	'use strict';

	function postAjax( action, data ) {
		return $.post( mcpConnectorAdmin.ajaxUrl, Object.assign( {
			action: action,
			nonce: mcpConnectorAdmin.nonce,
		}, data ) );
	}

	function showRevealModal( fullKey, endpointUrl, connectorUrl ) {
		$( '#mcp-revealed-key' ).val( fullKey );
		$( '#mcp-revealed-endpoint' ).val( endpointUrl );
		$( '#mcp-revealed-url' ).val( connectorUrl );
		$( '#mcp-reveal-modal' ).show();
	}

	function copyField( $field ) {
		$field.select();
		document.execCommand( 'copy' );
	}

	function showSeoEditError( message ) {
		$( '.mcp-seo-edit-error' ).text( message ).show();
	}

	function clearSeoEditError() {
		$( '.mcp-seo-edit-error' ).hide().text( '' );
	}

	function updateSeoCounter( fieldId, countId, maxLen ) {
		var len = $( '#' + fieldId ).val().length;
		$( '#' + countId ).text( len ).toggleClass( 'mcp-seo-count-over', len > maxLen );
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
					showRevealModal( response.data.full_key, response.data.endpoint_url, response.data.connector_url );
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

	$( document ).on( 'click', '#mcp-copy-endpoint', function () {
		copyField( $( '#mcp-revealed-endpoint' ) );
	} );

	$( document ).on( 'click', '#mcp-close-modal', function () {
		$( '#mcp-reveal-modal' ).hide();
		window.location.reload();
	} );

	$( document ).on( 'click', '.mcp-seo-toggle-detail', function ( e ) {
		e.preventDefault();

		var $link = $( this );
		var $row  = $link.closest( 'tr' );
		var $next = $row.next( '.mcp-seo-detail-row' );

		if ( $next.length ) {
			$next.remove();
			return;
		}

		var colspan = $row.children( 'td, th' ).length;

		$link.text( 'Loading…' );

		postAjax( 'mcp_seo_dashboard_detail', { post_id: $link.data( 'post-id' ) } )
			.done( function ( response ) {
				if ( response.success ) {
					$row.after( '<tr class="mcp-seo-detail-row"><td colspan="' + colspan + '">' + response.data.html + '</td></tr>' );
				} else {
					window.alert( response.data && response.data.message ? response.data.message : 'Failed to load details.' );
				}
			} )
			.fail( function () {
				window.alert( 'Request failed. Please try again.' );
			} )
			.always( function () {
				$link.text( 'Details' );
			} );
	} );

	$( document ).on( 'input', '#mcp-seo-edit-title', function () {
		updateSeoCounter( 'mcp-seo-edit-title', 'mcp-seo-edit-title-count', mcpConnectorAdmin.seoTitleMaxLen );
	} );

	$( document ).on( 'input', '#mcp-seo-edit-description', function () {
		updateSeoCounter( 'mcp-seo-edit-description', 'mcp-seo-edit-description-count', mcpConnectorAdmin.seoDescMaxLen );
	} );

	$( document ).on( 'click', '.mcp-seo-edit-open', function ( e ) {
		e.preventDefault();

		var postId = $( this ).data( 'post-id' );
		var $modal = $( '#mcp-seo-edit-modal' );

		clearSeoEditError();

		postAjax( 'mcp_seo_dashboard_get_meta', { post_id: postId } )
			.done( function ( response ) {
				if ( ! response.success ) {
					window.alert( response.data && response.data.message ? response.data.message : 'Failed to load SEO data.' );
					return;
				}

				var data = response.data;

				$( '#mcp-seo-edit-post-id' ).val( postId );
				$( '#mcp-seo-edit-title' ).val( data.title );
				$( '#mcp-seo-edit-description' ).val( data.description );
				$( '#mcp-seo-edit-canonical' ).val( data.canonical_url );
				$( '#mcp-seo-edit-focus-topic' ).val( data.focus_topic );
				$( '#mcp-seo-edit-keywords' ).val( data.keywords );
				$( '#mcp-seo-edit-noindex' ).prop( 'checked', -1 !== data.robots.indexOf( 'noindex' ) );
				$( '#mcp-seo-edit-nofollow' ).prop( 'checked', -1 !== data.robots.indexOf( 'nofollow' ) );

				// Directives the modal doesn't surface (noarchive/nosnippet/noimageindex)
				// are preserved here and merged back in on save — see #mcp-seo-edit-save.
				$modal.data( 'robots', data.robots );

				$( '#mcp-seo-edit-title' ).trigger( 'input' );
				$( '#mcp-seo-edit-description' ).trigger( 'input' );

				$modal.show();
			} )
			.fail( function () {
				window.alert( 'Request failed. Please try again.' );
			} );
	} );

	$( document ).on( 'click', '#mcp-seo-edit-save', function () {
		var $btn = $( this );
		var $modal = $( '#mcp-seo-edit-modal' );
		var robots = ( $modal.data( 'robots' ) || [] ).filter( function ( directive ) {
			return 'noindex' !== directive && 'nofollow' !== directive;
		} );

		if ( $( '#mcp-seo-edit-noindex' ).is( ':checked' ) ) {
			robots.push( 'noindex' );
		}
		if ( $( '#mcp-seo-edit-nofollow' ).is( ':checked' ) ) {
			robots.push( 'nofollow' );
		}

		clearSeoEditError();
		$btn.prop( 'disabled', true ).text( 'Saving…' );

		postAjax( 'mcp_seo_dashboard_save_meta', {
			post_id: $( '#mcp-seo-edit-post-id' ).val(),
			title: $( '#mcp-seo-edit-title' ).val(),
			description: $( '#mcp-seo-edit-description' ).val(),
			canonical_url: $( '#mcp-seo-edit-canonical' ).val(),
			focus_topic: $( '#mcp-seo-edit-focus-topic' ).val(),
			keywords: $( '#mcp-seo-edit-keywords' ).val(),
			robots: robots.join( ',' ),
		} )
			.done( function ( response ) {
				if ( response.success ) {
					window.location.reload();
				} else {
					showSeoEditError( response.data && response.data.message ? response.data.message : 'Failed to save SEO data.' );
				}
			} )
			.fail( function () {
				showSeoEditError( 'Request failed. Please try again.' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( 'Save' );
			} );
	} );

	$( document ).on( 'click', '#mcp-seo-edit-cancel', function () {
		$( '#mcp-seo-edit-modal' ).hide();
	} );

	/**
	 * Upgrades the Technical SEO textareas to CodeMirror when core's code
	 * editor assets are available (see Mcp_Admin::enqueue_assets()). Falls
	 * back to the plain textarea (already styled as class="large-text code")
	 * when the user has disabled syntax highlighting in their profile.
	 */
	$( function () {
		if ( 'undefined' === typeof window.wp || ! wp.codeEditor || ! window.mcpTechnicalSeoEditors ) {
			return;
		}

		var editors = window.mcpTechnicalSeoEditors;

		if ( editors.text ) {
			[ 'mcp-robots-txt', 'mcp-llms-txt' ].forEach( function ( id ) {
				var el = document.getElementById( id );
				if ( el ) {
					wp.codeEditor.initialize( el, editors.text );
				}
			} );
		}

		if ( editors.json ) {
			var jsonEl = document.getElementById( 'mcp-jsonld' );
			if ( jsonEl ) {
				wp.codeEditor.initialize( jsonEl, editors.json );
			}
		}
	} );
} )( jQuery );

/**
 * WP Agent AI – Settings Page JS
 *
 * Powers the "Test Connection" AJAX button on the admin settings page.
 * Deliberately uses plain jQuery (already enqueued by WP core on admin pages)
 * to avoid adding a React build dependency for this simple interaction.
 *
 * @package WpAgentAi
 */

/* global jQuery, wpAgentAiSettings */

( function ( $ ) {
	'use strict';

	$( function () {
		const $btn    = $( '#wp-agent-ai-test-btn' );
		const $result = $( '#wp-agent-ai-test-result' );
		const { ajaxUrl, nonce, i18n } = window.wpAgentAiSettings || {};

		if ( ! $btn.length ) return;

		$btn.on( 'click', function ( e ) {
			e.preventDefault();

			const apiKey = $( '#wp_agent_ai_api_key' ).val().trim();
			const model  = $( '#wp_agent_ai_model' ).val();

			// Guard: require a key before hitting the server.
			if ( ! apiKey ) {
				$result.css( 'color', '#cc1818' ).text( i18n.noKey );
				return;
			}

			$btn.prop( 'disabled', true );
			$result.css( 'color', '#6b7280' ).text( i18n.testing );

			$.post( ajaxUrl, {
				action:  'wp_agent_ai_test_connection',
				nonce,
				api_key: apiKey,
				model,
			} )
			.done( function ( response ) {
				if ( response.success ) {
					$result.css( 'color', '#16a34a' ).text( i18n.success );
				} else {
					const msg = response.data?.message || 'Unknown error';
					$result.css( 'color', '#cc1818' ).text( i18n.fail + msg );
				}
			} )
			.fail( function ( jqXHR ) {
				const msg = jqXHR.responseJSON?.data?.message || 'Network error';
				$result.css( 'color', '#cc1818' ).text( i18n.fail + msg );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );
	} );

} )( jQuery );

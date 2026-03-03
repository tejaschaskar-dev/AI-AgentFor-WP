<?php
/**
 * Plugin Name:       WP Agent AI – Landing Page Writer
 * Plugin URI:        https://wpagentai.com
 * Description:       Generate full landing page sections using OpenRouter AI directly inside Gutenberg.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            WP Agent AI
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-agent-ai
 * Domain Path:       /languages
 *
 * @package WpAgentAi
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_AGENT_AI_VERSION',     '1.0.0' );
define( 'WP_AGENT_AI_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WP_AGENT_AI_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'WP_AGENT_AI_PLUGIN_FILE', __FILE__ );

/**
 * Load plugin textdomain.
 */
function wp_agent_ai_load_textdomain(): void {
	load_plugin_textdomain(
		'wp-agent-ai',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', 'wp_agent_ai_load_textdomain' );

/**
 * Load class files.
 */
require_once WP_AGENT_AI_PLUGIN_DIR . 'includes/class-settings.php';
require_once WP_AGENT_AI_PLUGIN_DIR . 'includes/class-rest.php';

/**
 * Register Gutenberg block and pass data to the editor.
 */
function wp_agent_ai_register_block(): void {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	register_block_type( WP_AGENT_AI_PLUGIN_DIR . 'build/block' );

	// Pass plugin data to the block editor script.
	// WP derives the handle from the block name: {plugin-slug}-{block-slug}-editor-script
	// block name = "wp-agent-ai/landing-page-writer" → handle below.
	// If blocks don't appear, check the exact handle with: get_registered_scripts().
	wp_localize_script(
		'wp-agent-ai-landing-page-writer-editor-script',
		'wpAgentAiData',
		array(
			'restUrl'     => esc_url_raw( rest_url( 'wp-agent-ai/v1/generate' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'hasApiKey'   => ! empty( get_option( 'wp_agent_ai_api_key', '' ) ),
			'settingsUrl' => esc_url( admin_url( 'admin.php?page=wp-agent-ai-settings' ) ),
			'defaults'    => array(
				'tone'   => get_option( 'wp_agent_ai_default_tone',   'professional' ),
				'length' => get_option( 'wp_agent_ai_default_length', 'medium' ),
			),
		)
	);
}
add_action( 'init', 'wp_agent_ai_register_block' );

/**
 * Bootstrap REST API and Settings classes.
 */
function wp_agent_ai_bootstrap(): void {
	WpAgentAi\Rest::instance()->init();
	WpAgentAi\Settings::instance()->init();
}
add_action( 'plugins_loaded', 'wp_agent_ai_bootstrap' );

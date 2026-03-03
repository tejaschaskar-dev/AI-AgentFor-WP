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
	load_plugin_textdomain( 'wp-agent-ai', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wp_agent_ai_load_textdomain' );

/**
 * Load class files.
 */
require_once WP_AGENT_AI_PLUGIN_DIR . 'includes/class-settings.php';
require_once WP_AGENT_AI_PLUGIN_DIR . 'includes/class-rest.php';

/**
 * Register the Gutenberg block — fully manual, no auto-detection.
 */
function wp_agent_ai_register_block(): void {

	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	// ── 1. Read webpack-generated asset manifest ──────────────────────────
	$asset_file = WP_AGENT_AI_PLUGIN_DIR . 'build/block/index.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		// Asset file missing = block JS was never built. Show admin notice.
		add_action( 'admin_notices', 'wp_agent_ai_missing_build_notice' );
		return;
	}

	$asset = require $asset_file;

	// ── 2. Register editor script ─────────────────────────────────────────
	wp_register_script(
		'wp-agent-ai-block-editor-script',
		WP_AGENT_AI_PLUGIN_URL . 'build/block/index.js',
		$asset['dependencies'],
		$asset['version'],
		true   // load in footer
	);

	// ── 3. Inject plugin data BEFORE the block script runs ───────────────
	$plugin_data = array(
		'restUrl'     => esc_url_raw( rest_url( 'wp-agent-ai/v1/generate' ) ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'hasApiKey'   => ! empty( get_option( 'wp_agent_ai_api_key', '' ) ),
		'settingsUrl' => esc_url( admin_url( 'admin.php?page=wp-agent-ai-settings' ) ),
		'defaults'    => array(
			'tone'   => get_option( 'wp_agent_ai_default_tone',   'professional' ),
			'length' => get_option( 'wp_agent_ai_default_length', 'medium' ),
		),
	);

	wp_add_inline_script(
		'wp-agent-ai-block-editor-script',
		'window.wpAgentAiData = ' . wp_json_encode( $plugin_data ) . ';',
		'before'
	);

	// ── 4. Register editor style ──────────────────────────────────────────
	wp_register_style(
		'wp-agent-ai-block-editor-style',
		WP_AGENT_AI_PLUGIN_URL . 'build/block/index.css',
		array( 'wp-components' ),
		$asset['version']
	);

	// ── 5. Register the block type manually ───────────────────────────────
	register_block_type(
		'wp-agent-ai/landing-page-writer',
		array(
			'api_version'     => 3,
			'title'           => __( 'AI Landing Page Writer', 'wp-agent-ai' ),
			'category'        => 'text',          // safe fallback category
			'icon'            => 'superhero-alt',
			'description'     => __( 'Generate landing page sections using OpenRouter AI.', 'wp-agent-ai' ),
			'keywords'        => array( 'ai', 'landing page', 'openrouter' ),
			'editor_script'   => 'wp-agent-ai-block-editor-script',
			'editor_style'    => 'wp-agent-ai-block-editor-style',
			'render_callback' => '__return_empty_string',
			'attributes'      => array(
				'description'   => array( 'type' => 'string',  'default' => '' ),
				'sectionType'   => array( 'type' => 'string',  'default' => 'hero' ),
				'tone'          => array( 'type' => 'string',  'default' => 'professional' ),
				'length'        => array( 'type' => 'string',  'default' => 'medium' ),
				'lastGenerated' => array( 'type' => 'object',  'default' => null ),
			),
			'supports'        => array(
				'html'      => false,
				'reusable'  => false,
				'inserter'  => true,
			),
		)
	);

	// ── 6. Register custom block category ────────────────────────────────
	// (must be done on block_categories_all, not init)
}
add_action( 'init', 'wp_agent_ai_register_block' );

/**
 * Add "WP Agent AI" category to the Gutenberg inserter.
 */
function wp_agent_ai_block_category( array $categories ): array {
	// Prepend so it appears at the top of the inserter.
	return array_merge(
		array(
			array(
				'slug'  => 'wp-agent-ai',
				'title' => __( 'WP Agent AI', 'wp-agent-ai' ),
				'icon'  => 'superhero-alt',
			),
		),
		$categories
	);
}
add_filter( 'block_categories_all', 'wp_agent_ai_block_category', 10, 1 );

/**
 * Admin notice when the JS build is missing.
 */
function wp_agent_ai_missing_build_notice(): void {
	$build_path = WP_AGENT_AI_PLUGIN_DIR . 'build/block/index.asset.php';
	?>
	<div class="notice notice-error">
		<p>
			<strong>WP Agent AI:</strong>
			<?php
			printf(
				/* translators: %s file path */
				esc_html__( 'Plugin JS build is missing. Expected file: %s — Run `npm install && npm run build` inside the plugin folder.', 'wp-agent-ai' ),
				'<code>' . esc_html( $build_path ) . '</code>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Bootstrap REST API and Settings classes.
 */
function wp_agent_ai_bootstrap(): void {
	WpAgentAi\Rest::instance()->init();
	WpAgentAi\Settings::instance()->init();
}
add_action( 'plugins_loaded', 'wp_agent_ai_bootstrap' );

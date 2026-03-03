<?php
/**
 * Admin Settings Page.
 *
 * Registers the WP Agent AI admin menu, settings fields,
 * and handles the AJAX "Test Connection" action.
 *
 * @package WpAgentAi
 */

namespace WpAgentAi;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	/** @var Settings|null Singleton instance. */
	private static ?Settings $instance = null;

	/** Allowed AI models. */
	private const ALLOWED_MODELS = array(
		'openai/gpt-4o',
		'openai/gpt-4o-mini',
		'anthropic/claude-sonnet-4-5',
		'google/gemini-2.0-flash-001',
		'deepseek/deepseek-chat-v3-0324',
	);

	/** Allowed tone values. */
	private const ALLOWED_TONES = array( 'professional', 'friendly', 'bold', 'minimal' );

	/** Allowed length values. */
	private const ALLOWED_LENGTHS = array( 'short', 'medium', 'detailed' );

	public static function instance(): Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Hook everything in.
	 */
	public function init(): void {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wp_agent_ai_test_connection', array( $this, 'handle_test_connection' ) );
	}

	// -------------------------------------------------------------------------
	// Admin Menu
	// -------------------------------------------------------------------------

	public function register_menu(): void {
		add_menu_page(
			__( 'WP Agent AI', 'wp-agent-ai' ),
			__( 'WP Agent AI', 'wp-agent-ai' ),
			'manage_options',
			'wp-agent-ai-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-superhero-alt',
			58
		);

		add_submenu_page(
			'wp-agent-ai-settings',
			__( 'Settings', 'wp-agent-ai' ),
			__( 'Settings', 'wp-agent-ai' ),
			'manage_options',
			'wp-agent-ai-settings',
			array( $this, 'render_settings_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Settings Registration
	// -------------------------------------------------------------------------

	public function register_settings(): void {

		// --- API Key ---
		register_setting(
			'wp_agent_ai_options',
			'wp_agent_ai_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// --- Model ---
		register_setting(
			'wp_agent_ai_options',
			'wp_agent_ai_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_model' ),
				'default'           => 'openai/gpt-4o-mini',
			)
		);

		// --- Default Tone ---
		register_setting(
			'wp_agent_ai_options',
			'wp_agent_ai_default_tone',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_tone' ),
				'default'           => 'professional',
			)
		);

		// --- Default Length ---
		register_setting(
			'wp_agent_ai_options',
			'wp_agent_ai_default_length',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_length' ),
				'default'           => 'medium',
			)
		);

		// --- Sections ---
		add_settings_section( 'wp_agent_ai_main',     __( 'API Configuration', 'wp-agent-ai' ),      '__return_false', 'wp-agent-ai-settings' );
		add_settings_section( 'wp_agent_ai_defaults', __( 'Default Content Settings', 'wp-agent-ai' ), '__return_false', 'wp-agent-ai-settings' );

		// --- Fields ---
		add_settings_field( 'wp_agent_ai_api_key',        __( 'OpenRouter API Key', 'wp-agent-ai' ), array( $this, 'render_api_key_field' ),  'wp-agent-ai-settings', 'wp_agent_ai_main' );
		add_settings_field( 'wp_agent_ai_model',          __( 'AI Model', 'wp-agent-ai' ),           array( $this, 'render_model_field' ),    'wp-agent-ai-settings', 'wp_agent_ai_main' );
		add_settings_field( 'wp_agent_ai_default_tone',   __( 'Default Tone', 'wp-agent-ai' ),       array( $this, 'render_tone_field' ),     'wp-agent-ai-settings', 'wp_agent_ai_defaults' );
		add_settings_field( 'wp_agent_ai_default_length', __( 'Default Length', 'wp-agent-ai' ),     array( $this, 'render_length_field' ),   'wp-agent-ai-settings', 'wp_agent_ai_defaults' );
	}

	// -------------------------------------------------------------------------
	// Sanitize Callbacks
	// -------------------------------------------------------------------------

	public function sanitize_model( string $value ): string {
		return in_array( $value, self::ALLOWED_MODELS, true ) ? $value : 'openai/gpt-4o-mini';
	}

	public function sanitize_tone( string $value ): string {
		return in_array( $value, self::ALLOWED_TONES, true ) ? $value : 'professional';
	}

	public function sanitize_length( string $value ): string {
		return in_array( $value, self::ALLOWED_LENGTHS, true ) ? $value : 'medium';
	}

	// -------------------------------------------------------------------------
	// Field Renderers
	// -------------------------------------------------------------------------

	public function render_api_key_field(): void {
		$value = get_option( 'wp_agent_ai_api_key', '' );
		?>
		<input
			type="password"
			id="wp_agent_ai_api_key"
			name="wp_agent_ai_api_key"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="new-password"
			placeholder="sk-or-..."
		/>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to OpenRouter keys page */
				esc_html__( 'Get your free API key at %s', 'wp-agent-ai' ),
				'<a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">openrouter.ai/keys</a>'
			);
			?>
		</p>
		<?php
	}

	public function render_model_field(): void {
		$value  = get_option( 'wp_agent_ai_model', 'openai/gpt-4o-mini' );
		$models = array(
			'openai/gpt-4o'                  => 'GPT-4o (OpenAI)',
			'openai/gpt-4o-mini'             => 'GPT-4o Mini (OpenAI) — Recommended',
			'anthropic/claude-sonnet-4-5'    => 'Claude Sonnet 4.5 (Anthropic)',
			'google/gemini-2.0-flash-001'    => 'Gemini 2.0 Flash (Google)',
			'deepseek/deepseek-chat-v3-0324' => 'DeepSeek Chat V3 (DeepSeek)',
		);
		?>
		<select id="wp_agent_ai_model" name="wp_agent_ai_model">
			<?php foreach ( $models as $model_id => $label ) : ?>
				<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $value, $model_id ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_tone_field(): void {
		$value = get_option( 'wp_agent_ai_default_tone', 'professional' );
		$tones = array(
			'professional' => __( 'Professional', 'wp-agent-ai' ),
			'friendly'     => __( 'Friendly', 'wp-agent-ai' ),
			'bold'         => __( 'Bold', 'wp-agent-ai' ),
			'minimal'      => __( 'Minimal', 'wp-agent-ai' ),
		);
		?>
		<select id="wp_agent_ai_default_tone" name="wp_agent_ai_default_tone">
			<?php foreach ( $tones as $tone_id => $label ) : ?>
				<option value="<?php echo esc_attr( $tone_id ); ?>" <?php selected( $value, $tone_id ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_length_field(): void {
		$value   = get_option( 'wp_agent_ai_default_length', 'medium' );
		$lengths = array(
			'short'    => __( 'Short', 'wp-agent-ai' ),
			'medium'   => __( 'Medium', 'wp-agent-ai' ),
			'detailed' => __( 'Detailed', 'wp-agent-ai' ),
		);
		?>
		<select id="wp_agent_ai_default_length" name="wp_agent_ai_default_length">
			<?php foreach ( $lengths as $len_id => $label ) : ?>
				<option value="<?php echo esc_attr( $len_id ); ?>" <?php selected( $value, $len_id ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	// -------------------------------------------------------------------------
	// Enqueue Admin Scripts
	// -------------------------------------------------------------------------

	public function enqueue_scripts( string $hook ): void {
		if ( 'toplevel_page_wp-agent-ai-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'wp-agent-ai-settings',
			WP_AGENT_AI_PLUGIN_URL . 'build/admin/settings.js',
			array( 'jquery' ),
			WP_AGENT_AI_VERSION,
			true
		);

		wp_localize_script(
			'wp-agent-ai-settings',
			'wpAgentAiSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wp_agent_ai_test_connection' ),
				'i18n'    => array(
					'testing' => __( 'Testing…', 'wp-agent-ai' ),
					'success' => __( '✓ Connection successful!', 'wp-agent-ai' ),
					'fail'    => __( '✗ Connection failed: ', 'wp-agent-ai' ),
					'noKey'   => __( 'Please enter an API key first.', 'wp-agent-ai' ),
				),
			)
		);

		wp_enqueue_style(
			'wp-agent-ai-settings',
			WP_AGENT_AI_PLUGIN_URL . 'build/admin/settings.css',
			array(),
			WP_AGENT_AI_VERSION
		);
	}

	// -------------------------------------------------------------------------
	// AJAX: Test Connection
	// -------------------------------------------------------------------------

	public function handle_test_connection(): void {
		check_ajax_referer( 'wp_agent_ai_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'wp-agent-ai' ) ),
				403
			);
		}

		$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
		$model   = sanitize_text_field( wp_unslash( $_POST['model']   ?? 'openai/gpt-4o-mini' ) );
		$model   = $this->sanitize_model( $model );

		if ( empty( $api_key ) ) {
			wp_send_json_error(
				array( 'message' => __( 'API key is required.', 'wp-agent-ai' ) ),
				400
			);
		}

		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/chat/completions',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => get_site_url(),
					'X-Title'       => get_bloginfo( 'name' ),
				),
				'body' => wp_json_encode(
					array(
						'model'      => $model,
						'max_tokens' => 5,
						'messages'   => array(
							array( 'role' => 'user', 'content' => 'Reply: OK' ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ), 500 );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg  = $body['error']['message'] ?? __( 'Unknown error from OpenRouter.', 'wp-agent-ai' );
			wp_send_json_error( array( 'message' => $msg ), $code );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'wp-agent-ai' ) ) );
	}

	// -------------------------------------------------------------------------
	// Settings Page Renderer
	// -------------------------------------------------------------------------

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-agent-ai' ) );
		}
		?>
		<div class="wrap wp-agent-ai-settings-wrap">
			<h1>
				<span class="dashicons dashicons-superhero-alt" style="font-size:28px;margin-right:6px;color:#7c3aed;"></span>
				<?php esc_html_e( 'WP Agent AI – Settings', 'wp-agent-ai' ); ?>
			</h1>

			<?php settings_errors( 'wp_agent_ai_options' ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wp_agent_ai_options' );
				do_settings_sections( 'wp-agent-ai-settings' );
				submit_button( __( 'Save Settings', 'wp-agent-ai' ) );
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Test API Connection', 'wp-agent-ai' ); ?></h2>
			<p><?php esc_html_e( 'Verify your API key and selected model are working correctly.', 'wp-agent-ai' ); ?></p>

			<button id="wp-agent-ai-test-btn" class="button button-secondary">
				<?php esc_html_e( 'Test Connection', 'wp-agent-ai' ); ?>
			</button>
			<span id="wp-agent-ai-test-result" style="margin-left:12px;font-weight:600;font-size:14px;"></span>
		</div>
		<?php
	}
}

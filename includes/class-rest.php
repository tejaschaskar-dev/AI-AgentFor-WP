<?php
/**
 * REST API Endpoint: /wp-json/wp-agent-ai/v1/generate
 *
 * Handles prompt validation, OpenRouter API calls,
 * response parsing, and structured error responses.
 *
 * @package WpAgentAi
 */

namespace WpAgentAi;

defined( 'ABSPATH' ) || exit;

/**
 * Class Rest
 */
class Rest {

	/** @var Rest|null Singleton instance. */
	private static ?Rest $instance = null;

	public static function instance(): Rest {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	// -------------------------------------------------------------------------
	// Route Registration
	// -------------------------------------------------------------------------

	public function register_routes(): void {
		register_rest_route(
			'wp-agent-ai/v1',
			'/generate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_generate' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_endpoint_args(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Permission Check
	// -------------------------------------------------------------------------

	public function check_permission(): bool|\WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to use this endpoint.', 'wp-agent-ai' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Endpoint Argument Schema
	// -------------------------------------------------------------------------

	private function get_endpoint_args(): array {
		return array(
			'description' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => static function ( $v ) {
					$v = trim( $v );
					return ! empty( $v ) && mb_strlen( $v ) <= 2000;
				},
				'description'       => __( 'Business/product description (max 2000 chars).', 'wp-agent-ai' ),
			),
			'section_type' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static function ( $v ) {
					return in_array( $v, array( 'hero', 'features', 'testimonials', 'faq', 'pricing', 'cta' ), true );
				},
				'description'       => __( 'Landing page section type.', 'wp-agent-ai' ),
			),
			'tone' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static function ( $v ) {
					return in_array( $v, array( 'professional', 'friendly', 'bold', 'minimal' ), true );
				},
				'description'       => __( 'Writing tone.', 'wp-agent-ai' ),
			),
			'length' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static function ( $v ) {
					return in_array( $v, array( 'short', 'medium', 'detailed' ), true );
				},
				'description'       => __( 'Content length.', 'wp-agent-ai' ),
			),
			'refinement' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
				'description'       => __( 'Optional refinement instructions for regeneration.', 'wp-agent-ai' ),
			),
		);
	}

	// -------------------------------------------------------------------------
	// Main Handler
	// -------------------------------------------------------------------------

	public function handle_generate( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		// 1. Load settings.
		$api_key = get_option( 'wp_agent_ai_api_key', '' );
		$model   = get_option( 'wp_agent_ai_model', 'openai/gpt-4o-mini' );

		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'missing_api_key',
				__( 'OpenRouter API key is not configured. Please visit WP Agent AI → Settings.', 'wp-agent-ai' ),
				array( 'status' => 400 )
			);
		}

		// 2. Extract parameters.
		$description  = $request->get_param( 'description' );
		$section_type = $request->get_param( 'section_type' );
		$tone         = $request->get_param( 'tone' );
		$length       = $request->get_param( 'length' );
		$refinement   = $request->get_param( 'refinement' );

		// 3. Build prompts.
		$system_prompt = $this->build_system_prompt();
		$user_prompt   = $this->build_user_prompt( $description, $section_type, $tone, $length, $refinement );

		// 4. Call OpenRouter.
		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => get_site_url(),
					'X-Title'       => get_bloginfo( 'name' ),
				),
				'body' => wp_json_encode(
					array(
						'model'       => $model,
						'max_tokens'  => $this->get_max_tokens( $length ),
						'temperature' => 0.7,
						'messages'    => array(
							array( 'role' => 'system', 'content' => $system_prompt ),
							array( 'role' => 'user',   'content' => $user_prompt ),
						),
					)
				),
			)
		);

		// 5. Handle WP-level errors (network, timeout).
		if ( is_wp_error( $response ) ) {
			$code    = $response->get_error_code();
			$timeout = str_contains( $code, 'timeout' ) || str_contains( $code, 'connect' );
			return new \WP_Error(
				$timeout ? 'request_timeout' : 'network_error',
				$response->get_error_message(),
				array( 'status' => 503 )
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body      = json_decode( wp_remote_retrieve_body( $response ), true );

		// 6. Handle HTTP-level errors.
		if ( 429 === $http_code ) {
			return new \WP_Error(
				'rate_limit',
				__( 'Rate limit exceeded. Please wait a moment and try again.', 'wp-agent-ai' ),
				array( 'status' => 429 )
			);
		}

		if ( $http_code >= 400 ) {
			$msg = $body['error']['message'] ?? __( 'OpenRouter returned an error.', 'wp-agent-ai' );
			return new \WP_Error( 'openrouter_error', $msg, array( 'status' => $http_code ) );
		}

		// 7. Extract content.
		$content = $body['choices'][0]['message']['content'] ?? '';

		if ( empty( $content ) ) {
			return new \WP_Error(
				'empty_response',
				__( 'The AI returned an empty response. Please try again.', 'wp-agent-ai' ),
				array( 'status' => 500 )
			);
		}

		// 8. Strip markdown fences if model wrapped JSON in them.
		$content = preg_replace( '/^```(?:json)?\s*/m', '', $content );
		$content = preg_replace( '/```\s*$/m', '', $content );
		$content = trim( $content );

		// 9. Parse JSON.
		$parsed = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $parsed ) ) {
			// Graceful fallback: return raw text so block can render a paragraph.
			return rest_ensure_response( array(
				'success'      => true,
				'section_type' => $section_type,
				'data'         => null,
				'raw_fallback' => $content,
				'model_used'   => $body['model'] ?? $model,
			) );
		}

		// 10. Return structured data.
		return rest_ensure_response( array(
			'success'      => true,
			'section_type' => $section_type,
			'data'         => $parsed,
			'raw_fallback' => null,
			'model_used'   => $body['model'] ?? $model,
		) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get_max_tokens( string $length ): int {
		return match ( $length ) {
			'short'    => 600,
			'detailed' => 2000,
			default    => 1200,
		};
	}

	private function build_user_prompt(
		string $description,
		string $section_type,
		string $tone,
		string $length,
		string $refinement
	): string {
		$data = array(
			'description'  => $description,
			'section_type' => $section_type,
			'tone'         => $tone,
			'length'       => $length,
		);

		$prompt = wp_json_encode( $data );

		if ( ! empty( $refinement ) ) {
			$prompt .= "\n\nRefinement instructions from the user: " . $refinement;
		}

		return $prompt;
	}

	private function build_system_prompt(): string {
		return <<<'PROMPT'
You are an expert landing page UX writer and conversion rate specialist with 15+ years of experience writing high-converting copy for SaaS products, agencies, e-commerce, and enterprise clients.

## YOUR TASK
Generate structured landing page section content based on the JSON input you receive. You ALWAYS respond with valid, parseable JSON and NOTHING else — no markdown code fences, no explanation, no preamble.

## OUTPUT SCHEMAS (respond with EXACTLY one, matching the section_type)

hero:
{"hero":{"headline":"...","subheadline":"...","cta_text":"...","cta_secondary":"..."}}

features:
{"features":{"heading":"...","subheading":"...","items":[{"title":"...","description":"...","icon_hint":"..."}]}}

testimonials:
{"testimonials":{"heading":"...","items":[{"name":"...","role":"...","company":"...","quote":"...","rating":5}]}}

faq:
{"faq":{"heading":"...","subheading":"...","items":[{"question":"...","answer":"..."}]}}

pricing:
{"pricing":{"heading":"...","subheading":"...","plans":[{"name":"...","price":"...","period":"...","description":"...","features":["..."],"cta_text":"...","highlighted":false}]}}

cta:
{"cta":{"heading":"...","paragraph":"...","button_text":"...","button_secondary":"...","supporting_text":"..."}}

## TONE GUIDELINES
- professional: authoritative, clear, benefit-driven, no jargon
- friendly: warm, conversational, empathetic, approachable
- bold: punchy, confident, direct, action-oriented power words
- minimal: concise, essential words only, zero fluff

## LENGTH GUIDELINES
- short: minimal copy, punchy phrases, tight headlines
- medium: balanced detail with clear value propositions
- detailed: comprehensive copy with supporting context and depth

## QUALITY RULES
1. Lead with the customer benefit, never the feature
2. Use specific, concrete language — avoid vague marketing claims
3. Headlines must be under 10 words; every word must earn its place
4. CTAs must start with action verbs: Start, Get, Build, Launch, Try, Join
5. If the business description is vague, infer a plausible industry and generate realistic copy
6. Number of items: short=2-3 items, medium=3-4 items, detailed=4-6 items

## EXAMPLE
Input:  {"description":"AI email marketing platform","section_type":"hero","tone":"bold","length":"short"}
Output: {"hero":{"headline":"Send Smarter. Convert Faster.","subheadline":"AI that writes, segments, and sends your best campaign yet — automatically.","cta_text":"Start Free Trial","cta_secondary":"See How It Works"}}
PROMPT;
	}
}

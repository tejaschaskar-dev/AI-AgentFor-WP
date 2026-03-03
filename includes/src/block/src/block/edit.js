/**
 * WP Agent AI – Block Edit Component
 *
 * Full Gutenberg editor UI: inputs, generate/regenerate,
 * REST fetch, block insertion via insertBlocks().
 *
 * @package WpAgentAi
 */

import { useState, useRef } from '@wordpress/element';
import { useBlockProps }    from '@wordpress/block-editor';
import {
	TextareaControl,
	SelectControl,
	Button,
	Spinner,
	Notice,
	Panel,
	PanelBody,
	PanelRow,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { __ }          from '@wordpress/i18n';

// Data injected by wp_localize_script in wp-agent-ai.php.
const {
	restUrl,
	nonce,
	hasApiKey,
	settingsUrl,
	defaults,
} = window.wpAgentAiData || {};

// ─── Option lists ────────────────────────────────────────────────────────────

const SECTION_TYPES = [
	{ label: __( 'Hero',         'wp-agent-ai' ), value: 'hero' },
	{ label: __( 'Features',     'wp-agent-ai' ), value: 'features' },
	{ label: __( 'Testimonials', 'wp-agent-ai' ), value: 'testimonials' },
	{ label: __( 'FAQ',          'wp-agent-ai' ), value: 'faq' },
	{ label: __( 'Pricing',      'wp-agent-ai' ), value: 'pricing' },
	{ label: __( 'CTA',          'wp-agent-ai' ), value: 'cta' },
];

const TONES = [
	{ label: __( 'Professional', 'wp-agent-ai' ), value: 'professional' },
	{ label: __( 'Friendly',     'wp-agent-ai' ), value: 'friendly' },
	{ label: __( 'Bold',         'wp-agent-ai' ), value: 'bold' },
	{ label: __( 'Minimal',      'wp-agent-ai' ), value: 'minimal' },
];

const LENGTHS = [
	{ label: __( 'Short',    'wp-agent-ai' ), value: 'short' },
	{ label: __( 'Medium',   'wp-agent-ai' ), value: 'medium' },
	{ label: __( 'Detailed', 'wp-agent-ai' ), value: 'detailed' },
];

// ─── Block builder helpers ────────────────────────────────────────────────────

/**
 * Convert the AI JSON response into an array of Gutenberg block objects.
 *
 * @param {Object|null} data         Parsed JSON from the API.
 * @param {string}      sectionKey   Section type string.
 * @param {string|null} rawFallback  Raw text when JSON parsing failed.
 * @return {Array} Array of block objects ready for insertBlocks().
 */
function buildBlocks( data, sectionKey, rawFallback ) {
	// Graceful fallback: wrap raw text in a paragraph block.
	if ( rawFallback ) {
		return [ createBlock( 'core/paragraph', { content: rawFallback } ) ];
	}

	const blocks = [];

	switch ( sectionKey ) {

		case 'hero': {
			const h = data?.hero ?? {};
			if ( h.headline )    blocks.push( createBlock( 'core/heading',   { content: h.headline,    level: 1, textAlign: 'center' } ) );
			if ( h.subheadline ) blocks.push( createBlock( 'core/paragraph', { content: h.subheadline, align: 'center' } ) );

			const btns = [];
			if ( h.cta_text )      btns.push( createBlock( 'core/button', { text: h.cta_text,      className: 'is-style-fill' } ) );
			if ( h.cta_secondary ) btns.push( createBlock( 'core/button', { text: h.cta_secondary, className: 'is-style-outline' } ) );
			if ( btns.length )     blocks.push( createBlock( 'core/buttons', { layout: { type: 'flex', justifyContent: 'center' } }, btns ) );
			break;
		}

		case 'features': {
			const f = data?.features ?? {};
			if ( f.heading )    blocks.push( createBlock( 'core/heading',   { content: f.heading,    level: 2, textAlign: 'center' } ) );
			if ( f.subheading ) blocks.push( createBlock( 'core/paragraph', { content: f.subheading, align: 'center' } ) );

			if ( Array.isArray( f.items ) && f.items.length ) {
				const cols = f.items.slice( 0, 4 ).map( ( item ) =>
					createBlock( 'core/column', {}, [
						createBlock( 'core/heading',   { content: item.title,       level: 4 } ),
						createBlock( 'core/paragraph', { content: item.description } ),
					] )
				);
				blocks.push( createBlock( 'core/columns', {}, cols ) );
			}
			break;
		}

		case 'testimonials': {
			const t = data?.testimonials ?? {};
			if ( t.heading ) blocks.push( createBlock( 'core/heading', { content: t.heading, level: 2, textAlign: 'center' } ) );

			if ( Array.isArray( t.items ) ) {
				t.items.forEach( ( item ) => {
					const citation = [
						item.name,
						item.role    ? `, ${ item.role }`    : '',
						item.company ? ` – ${ item.company }` : '',
					].join( '' );

					blocks.push(
						createBlock( 'core/quote', {
							value:    `<p>${ item.quote }</p>`,
							citation,
						} )
					);
				} );
			}
			break;
		}

		case 'faq': {
			const fq = data?.faq ?? {};
			if ( fq.heading )    blocks.push( createBlock( 'core/heading',   { content: fq.heading,    level: 2 } ) );
			if ( fq.subheading ) blocks.push( createBlock( 'core/paragraph', { content: fq.subheading } ) );

			if ( Array.isArray( fq.items ) ) {
				fq.items.forEach( ( item ) => {
					blocks.push(
						createBlock( 'core/details', { summary: item.question }, [
							createBlock( 'core/paragraph', { content: item.answer } ),
						] )
					);
				} );
			}
			break;
		}

		case 'pricing': {
			const pr = data?.pricing ?? {};
			if ( pr.heading )    blocks.push( createBlock( 'core/heading',   { content: pr.heading,    level: 2, textAlign: 'center' } ) );
			if ( pr.subheading ) blocks.push( createBlock( 'core/paragraph', { content: pr.subheading, align: 'center' } ) );

			if ( Array.isArray( pr.plans ) && pr.plans.length ) {
				const cols = pr.plans.map( ( plan ) => {
					const colInner = [
						createBlock( 'core/heading',   { content: plan.name,                              level: 3, textAlign: 'center' } ),
						createBlock( 'core/heading',   { content: `${ plan.price } ${ plan.period || '' }`, level: 2, textAlign: 'center' } ),
						createBlock( 'core/paragraph', { content: plan.description, align: 'center' } ),
					];

					if ( Array.isArray( plan.features ) ) {
						colInner.push(
							createBlock( 'core/list', {
								values: plan.features.map( ( f ) => `<li>${ f }</li>` ).join( '' ),
							} )
						);
					}

					colInner.push(
						createBlock( 'core/buttons', { layout: { type: 'flex', justifyContent: 'center' } }, [
							createBlock( 'core/button', {
								text:      plan.cta_text || __( 'Get Started', 'wp-agent-ai' ),
								className: plan.highlighted ? 'is-style-fill' : 'is-style-outline',
							} ),
						] )
					);

					return createBlock( 'core/column', {}, colInner );
				} );

				blocks.push( createBlock( 'core/columns', {}, cols ) );
			}
			break;
		}

		case 'cta': {
			const c = data?.cta ?? {};
			if ( c.heading )   blocks.push( createBlock( 'core/heading',   { content: c.heading,   level: 2, textAlign: 'center' } ) );
			if ( c.paragraph ) blocks.push( createBlock( 'core/paragraph', { content: c.paragraph, align: 'center' } ) );

			const btns = [];
			if ( c.button_text )      btns.push( createBlock( 'core/button', { text: c.button_text,      className: 'is-style-fill' } ) );
			if ( c.button_secondary ) btns.push( createBlock( 'core/button', { text: c.button_secondary, className: 'is-style-outline' } ) );
			if ( btns.length )        blocks.push( createBlock( 'core/buttons', { layout: { type: 'flex', justifyContent: 'center' } }, btns ) );

			if ( c.supporting_text ) {
				blocks.push(
					createBlock( 'core/paragraph', {
						content: c.supporting_text,
						align:   'center',
						style:   { typography: { fontSize: '14px' } },
					} )
				);
			}
			break;
		}

		default:
			break;
	}

	return blocks;
}

// ─── Edit Component ───────────────────────────────────────────────────────────

export default function Edit( { attributes, setAttributes } ) {
	const { description, sectionType, tone, length } = attributes;

	const [ isLoading,    setIsLoading    ] = useState( false );
	const [ error,        setError        ] = useState( '' );
	const [ refinement,   setRefinement   ] = useState( '' );
	const [ hasGenerated, setHasGenerated ] = useState( false );

	/** Holds the AbortController for the current in-flight request. */
	const abortRef = useRef( null );

	const blockProps = useBlockProps( { className: 'wp-agent-ai-block' } );
	const { insertBlocks } = useDispatch( 'core/block-editor' );

	// ── Generate handler ────────────────────────────────────────────────────

	const handleGenerate = async ( isRegenerate = false ) => {
		if ( isLoading ) return;

		if ( ! description.trim() ) {
			setError( __( 'Please enter a business description before generating.', 'wp-agent-ai' ) );
			return;
		}

		// Cancel any active request (debounce / double-click guard).
		if ( abortRef.current ) {
			abortRef.current.abort();
		}
		abortRef.current = new AbortController();

		setIsLoading( true );
		setError( '' );

		try {
			const res = await fetch( restUrl, {
				method:  'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   nonce,
				},
				body:   JSON.stringify( {
					description,
					section_type: sectionType,
					tone,
					length,
					refinement: isRegenerate ? refinement : '',
				} ),
				signal: abortRef.current.signal,
			} );

			const json = await res.json();

			if ( ! res.ok ) {
				throw new Error( json.message || __( 'An unknown error occurred.', 'wp-agent-ai' ) );
			}

			const blocks = buildBlocks( json.data, json.section_type, json.raw_fallback );

			if ( ! blocks.length ) {
				throw new Error( __( 'No content was generated. Please try again.', 'wp-agent-ai' ) );
			}

			await insertBlocks( blocks, undefined, undefined, false );

			setHasGenerated( true );
			setAttributes( { lastGenerated: { sectionType, tone, length } } );

		} catch ( err ) {
			// Ignore aborted requests.
			if ( err.name === 'AbortError' ) return;
			setError( err.message || __( 'An unexpected error occurred.', 'wp-agent-ai' ) );
		} finally {
			setIsLoading( false );
		}
	};

	// ── Render ───────────────────────────────────────────────────────────────

	return (
		<div { ...blockProps }>

			{/* Block header */}
			<div className="wp-agent-ai-block__header">
				<span className="wp-agent-ai-block__icon" aria-hidden="true">✦</span>
				<strong>{ __( 'AI Landing Page Writer', 'wp-agent-ai' ) }</strong>
			</div>

			{/* No API key warning */}
			{ ! hasApiKey && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'OpenRouter API key not configured. ', 'wp-agent-ai' ) }
					<a href={ settingsUrl } target="_blank" rel="noopener noreferrer">
						{ __( 'Go to Settings →', 'wp-agent-ai' ) }
					</a>
				</Notice>
			) }

			{/* Error notice */}
			{ error && (
				<Notice status="error" isDismissible onDismiss={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			{/* Main settings panel */}
			<Panel>
				<PanelBody title={ __( 'Content Settings', 'wp-agent-ai' ) } initialOpen={ true }>

					<PanelRow>
						<TextareaControl
							label={ __( 'Business Description', 'wp-agent-ai' ) }
							help={ __( 'Describe your product or service. The more specific, the better the output.', 'wp-agent-ai' ) }
							value={ description }
							onChange={ ( val ) => setAttributes( { description: val } ) }
							rows={ 4 }
							placeholder={ __( 'e.g. A SaaS platform that automates social media scheduling for marketing teams…', 'wp-agent-ai' ) }
						/>
					</PanelRow>

					<PanelRow>
						<SelectControl
							label={ __( 'Section Type', 'wp-agent-ai' ) }
							value={ sectionType }
							options={ SECTION_TYPES }
							onChange={ ( val ) => setAttributes( { sectionType: val } ) }
						/>
					</PanelRow>

					<PanelRow>
						<SelectControl
							label={ __( 'Tone', 'wp-agent-ai' ) }
							value={ tone }
							options={ TONES }
							onChange={ ( val ) => setAttributes( { tone: val } ) }
						/>
					</PanelRow>

					<PanelRow>
						<SelectControl
							label={ __( 'Length', 'wp-agent-ai' ) }
							value={ length }
							options={ LENGTHS }
							onChange={ ( val ) => setAttributes( { length: val } ) }
						/>
					</PanelRow>

				</PanelBody>

				{/* Refinement panel — shown only after first generation */}
				{ hasGenerated && (
					<PanelBody title={ __( 'Refinement Instructions', 'wp-agent-ai' ) } initialOpen={ false }>
						<PanelRow>
							<TextareaControl
								label={ __( 'What to change?', 'wp-agent-ai' ) }
								help={ __( 'Describe adjustments for the next generation (optional).', 'wp-agent-ai' ) }
								value={ refinement }
								onChange={ setRefinement }
								rows={ 2 }
								placeholder={ __( 'e.g. Make the headline shorter and punchier, focus on enterprise customers…', 'wp-agent-ai' ) }
							/>
						</PanelRow>
					</PanelBody>
				) }
			</Panel>

			{/* Action buttons */}
			<div className="wp-agent-ai-block__actions">
				<Button
					variant="primary"
					onClick={ () => handleGenerate( false ) }
					disabled={ isLoading || ! hasApiKey }
					isBusy={ isLoading }
					className="wp-agent-ai-block__btn-generate"
				>
					{ isLoading
						? ( <><Spinner />{ __( 'Generating…', 'wp-agent-ai' ) }</> )
						: __( '✦ Generate Content', 'wp-agent-ai' )
					}
				</Button>

				{ hasGenerated && (
					<Button
						variant="secondary"
						onClick={ () => handleGenerate( true ) }
						disabled={ isLoading }
						className="wp-agent-ai-block__btn-regenerate"
					>
						{ __( '↺ Regenerate', 'wp-agent-ai' ) }
					</Button>
				) }
			</div>

			<p className="wp-agent-ai-block__hint">
				{ __( 'Generated blocks will be inserted below this block in the editor.', 'wp-agent-ai' ) }
			</p>

		</div>
	);
}

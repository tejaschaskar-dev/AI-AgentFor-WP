/**
 * WP Agent AI – Block Entry Point
 *
 * Registers the custom block category and the block itself.
 *
 * @package WpAgentAi
 */

import { registerBlockType, getCategories, setCategories } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import Edit from './edit';
import Save from './save';
import metadata from './block.json';

// ─── Register custom block category ────────────────────────────────────────

const existingCategories = getCategories();
const categoryExists     = existingCategories.some( ( c ) => c.slug === 'wp-agent-ai' );

if ( ! categoryExists ) {
	setCategories( [
		{
			slug:  'wp-agent-ai',
			title: __( 'WP Agent AI', 'wp-agent-ai' ),
			icon:  'superhero-alt',
		},
		...existingCategories,
	] );
}

// ─── Register block ─────────────────────────────────────────────────────────

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: Save,
} );

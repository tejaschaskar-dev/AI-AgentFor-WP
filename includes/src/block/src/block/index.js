/**
 * WP Agent AI – Block Registration
 *
 * Category registration is handled server-side via block_categories_all filter.
 * This file only registers the block type with its Edit/Save components.
 *
 * @package WpAgentAi
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ }               from '@wordpress/i18n';

import Edit from './edit';
import Save from './save';

registerBlockType( 'wp-agent-ai/landing-page-writer', {
	apiVersion:  3,
	title:       __( 'AI Landing Page Writer', 'wp-agent-ai' ),
	category:    'wp-agent-ai',
	icon:        'superhero-alt',
	description: __( 'Generate landing page sections using OpenRouter AI.', 'wp-agent-ai' ),
	keywords:    [ 'ai', 'landing page', 'openrouter', 'gpt', 'claude' ],
	attributes: {
		description:   { type: 'string',  default: '' },
		sectionType:   { type: 'string',  default: 'hero' },
		tone:          { type: 'string',  default: 'professional' },
		length:        { type: 'string',  default: 'medium' },
		lastGenerated: { type: 'object',  default: null },
	},
	supports: {
		html:     false,
		reusable: false,
		inserter: true,
	},
	edit: Edit,
	save: Save,
} );

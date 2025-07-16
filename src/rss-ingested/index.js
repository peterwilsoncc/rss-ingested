/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

import './style.scss';

/**
 * Internal dependencies
 */
import Edit from './edit';
import metadata from './block.json';

const { name } = metadata;
export { metadata, name };

export const settings = {
	example: {},
	edit: Edit,
};

registerBlockType( { name, ...metadata }, settings );

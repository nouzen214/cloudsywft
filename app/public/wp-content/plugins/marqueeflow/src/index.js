import { registerBlockType } from '@wordpress/blocks';
import Edit from './blocks/marqueeflow/edit';
import save from './blocks/marqueeflow/save';
import blockConfig from '../blocks/marqueeflow/block.json';

registerBlockType( blockConfig.name, {
	...blockConfig,
	edit: Edit,
	save: save,
} );

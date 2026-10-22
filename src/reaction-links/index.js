/**
 * Microformats Reaction Links Extension
 *
 * Entry point: wires the reaction picker into the block editor once the DOM is
 * ready. All logic lives in ./reactions.js so it can be unit tested in isolation.
 */
import domReady from '@wordpress/dom-ready';

import './editor.scss';
import { init } from './reactions';

domReady( init );

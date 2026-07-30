const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	testPathIgnorePatterns: [ '/build/', '/node_modules/', '/vendor/' ],
};

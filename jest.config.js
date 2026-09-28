const presetConfig = require( '@wordpress/jest-preset-default' );

module.exports = {
	...presetConfig,
	testPathIgnorePatterns: [ '/build/', '/node_modules/', '/vendor/' ],
	/*
	 * Babel is configured here rather than in a root config file, so the transform stays scoped to
	 * the tests: a root Babel config would also apply to the webpack build.
	 */
	transform: {
		'\\.[jt]sx?$': [
			require.resolve( 'babel-jest' ),
			{
				presets: [
					require.resolve( '@wordpress/babel-preset-default' ),
				],
			},
		],
	},
};

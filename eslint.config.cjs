/**
 * ESLint config, extends the `@wordpress/scripts` defaults.
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,

	// The unit tests run on Jest, the default config only knows Vitest.
	{
		files: [ '**/__tests__/**/*.js', '**/*.test.js' ],
		languageOptions: {
			globals: {
				afterAll: 'readonly',
				afterEach: 'readonly',
				beforeAll: 'readonly',
				beforeEach: 'readonly',
				describe: 'readonly',
				expect: 'readonly',
				it: 'readonly',
				jest: 'readonly',
				test: 'readonly',
			},
		},
	},
];

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	plugins: defaultConfig.plugins.map( ( plugin ) => {
		if ( ! ( plugin instanceof DependencyExtractionWebpackPlugin ) ) {
			return plugin;
		}

		return new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				// WordPress only registers this dependency from 6.6 onward.
				// Bundle the React 18 JSX runtime, including calls from imported icons,
				// while leaving React itself and WordPress packages external.
				return request === 'react/jsx-runtime' ? false : undefined;
			},
		} );
	} ),
};

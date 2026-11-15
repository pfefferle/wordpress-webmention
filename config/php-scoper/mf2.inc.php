<?php declare( strict_types = 1 );

// scoper.inc.php

use Isolated\Symfony\Component\Finder\Finder;

return [
	'finders' => [
		Finder::create()
			->files()
			->in( 'vendor/mf2/mf2' )
			->ignoreVCS( true )
			->notName( '/.*\\.xml|.*\\.dist|Makefile|composer\\.json|composer\\.lock|.*\\.Dockerfile/' )
			->exclude( [
				'doc',
				'test',
				'test_old',
				'tests',
				'Tests',
				'vendor-bin',
				'bin',
			]
		)
	],
	'patchers' => [
		// php-scoper does not prefix function names inside XPath strings.
		static function ( string $file_path, string $prefix, string $content ): string {
			return str_replace(
				'"\\\\Mf2\\\\classHasMf2RootClassname"',
				'"' . $prefix . '\\\\Mf2\\\\classHasMf2RootClassname"',
				$content
			);
		},
	],
];
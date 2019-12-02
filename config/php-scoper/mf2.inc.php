<?php declare( strict_types = 1 );

// scoper.inc.php

use Isolated\Symfony\Component\Finder\Finder;

return [
	'finders' => [
		Finder::create()
			->files()
			->in( 'vendor/mf2/mf2' )
			->ignoreVCS( true )
			->notName( '/.*\\.xml|.*\\.dist|Makefile|composer\\.json|composer\\.lock/' )
			->exclude( [
				'doc',
				'test',
				'test_old',
				'tests',
				'Tests',
				'vendor-bin',
				'bin',
			] )
	],
];
<?php

use Wikimedia\Minify\JavaScriptMinifier;

require_once __DIR__ . '/../../../vendor/autoload.php';

function main() {
	$files = [ 'foo.js', 'bar.js', 'quux.js', 'index.js' ];
	$inputs = [];
	foreach ( $files as $file ) {
		$inputs[] = [
			'url' => './src/virtual/' . $file,
			'before' => "\nconsole.log('Boilerplate for $file');",
			'content' => file_get_contents( __DIR__ . '/src-' . $file ),
			'after' => "/* So long $file */",
		];
	}

	// Create the minified response
	// During this step, we skip mapping state for optimal performance.
	$state = JavaScriptMinifier::createMinifier();
	foreach ( $inputs as $input ) {
		$state->addOutput( $input['before'] );
		$state->addSourceFile( $input['url'], $input['content'], true );
		$state->addOutput( $input['after'] );
	}
	$js = $state->getMinifiedOutput() . "\n" . '//# sourceMappingURL=combine.min.js.map';

	// Create the source map
	$state = JavaScriptMinifier::createSourceMapState();
	foreach ( $inputs as $input ) {
		$state->addOutput( $input['before'] );
		$state->addSourceFile( $input['url'], $input['content'], true );
		$state->addOutput( $input['after'] );
	}
	$map = $state->getRawSourceMap();

	file_put_contents( __DIR__ . '/combine.min.js', $js );
	file_put_contents( __DIR__ . '/combine.min.js.map', $map );
}

main();

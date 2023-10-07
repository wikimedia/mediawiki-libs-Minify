<?php

use Wikimedia\Minify\IndexMap;
use Wikimedia\Minify\IndexMapOffset;
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

	// Create the production-like response
	$indexmap = new IndexMap();
	$indexmap->outputFile( 'production.min.js' );
	$js = '';

	foreach ( $inputs as $input ) {
		// Create JS chunk
		// During this step, we skip mapping state for optimal performance.
		$state = JavaScriptMinifier::createMinifier();
		$state->addOutput( $input['before'] );
		$state->addSourceFile( $input['url'], $input['content'], true );
		$state->addOutput( $input['after'] );
		$jsChunk = $state->getMinifiedOutput();

		// Create map chunk
		$state = JavaScriptMinifier::createSourceMapState();
		$state->addOutput( $input['before'] );
		$state->addSourceFile( $input['url'], $input['content'], true );
		$state->addOutput( $input['after'] );
		$mapChunk = $state->getRawSourceMap();

		$indexmap->addEncodedMap(
			$mapChunk,
			IndexMapOffset::newFromText( $jsChunk )
		);
		$js .= $jsChunk;
	}
	$js .= "\n" . '//# sourceMappingURL=production.min.js.map';

	file_put_contents( __DIR__ . '/production.min.js', $js );
	file_put_contents( __DIR__ . '/production.min.js.map', $indexmap->getMap() );
}

main();

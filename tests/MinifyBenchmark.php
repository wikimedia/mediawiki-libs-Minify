<?php
use Wikimedia\Minify\CSSMin;
use Wikimedia\Minify\JavaScriptMinifier;

class MinifyBenchmark {

	public function run(): void {
		// Avoid removing or changing existing bench fixtures (keep apples-to-apples reference).
		$this->benchJavaScriptMinifier( 'jquery', 'https://code.jquery.com/jquery-3.2.1.js' );
		$this->benchJavaScriptMinifier( 'vue-min', 'https://raw.githubusercontent.com/wikimedia/mediawiki/1.35.1/resources/lib/vue/vue.common.prod.js' );
		$this->benchCSSMinMinify();
		$this->benchCSSMinRemap();
	}

	private function benchJavaScriptMinifier( string $label, string $srcUrl ): void {
		$data = $this->loadTmpFile( $label, $srcUrl );
		$iterations = 200;
		$total = 0;
		$max = -INF;
		for ( $i = 1; $i <= $iterations; $i++ ) {
			$start = microtime( true );
			JavaScriptMinifier::minify( $data );
			$took = ( microtime( true ) - $start ) * 1000;
			$max = max( $max, $took );
			$total += ( microtime( true ) - $start ) * 1000;
		}
		$this->outputStat( "JavaScriptMinifier ($label)", $total, $max, $iterations );
	}

	private function benchCSSMinMinify(): void {
		$data = $this->loadTmpFile( 'ooui', 'https://github.com/wikimedia/mediawiki/raw/1.31.0/resources/lib/oojs-ui/oojs-ui-core-wikimediaui.css' );
		$iterations = 1000;
		$total = 0;
		$max = -INF;
		for ( $i = 1; $i <= $iterations; $i++ ) {
			$start = microtime( true );
			CSSMin::minify( $data );
			$took = ( microtime( true ) - $start ) * 1000;
			$max = max( $max, $took );
			$total += ( microtime( true ) - $start ) * 1000;
		}
		$this->outputStat( 'CSSMin::minify (ooui)', $total, $max, $iterations );
	}

	private function benchCSSMinRemap(): void {
		$local = __DIR__ . '/data';
		$data = file_get_contents( "{$local}/bench-remap-example.css" );
		$iterations = 1000;
		$total = 0;
		$max = -INF;
		for ( $i = 1; $i <= $iterations; $i++ ) {
			$start = microtime( true );
			CSSMin::remap( $data, $local, 'https://example.test/data/', true );
			$took = ( microtime( true ) - $start ) * 1000;
			$max = max( $max, $took );
			$total += ( microtime( true ) - $start ) * 1000;
		}
		$this->outputStat( 'CSSMin::remap (example)', $total, $max, $iterations );
	}

	private function outputStat( string $name, int $total, int $max, int $iterations ): void {
		// in milliseconds
		$mean = $total / $iterations;
		$ratePerSecond = 1.0 / ( $mean / 1000.0 );
		echo sprintf(
			"* %-30s %-10s %-12s %-14s %-16s\n",
			$name,
			"ops={$iterations},",
			sprintf( 'max=%.2fms,', $max ),
			sprintf( 'mean=%.2fms,', $mean ),
			sprintf( 'rate=%.0f op/s', $ratePerSecond )
		);
	}

	private function loadTmpFile( string $id, string $url ): string {
		$tmpPrefix = __DIR__ . "/data/tmp";
		$version = md5( $url );
		$file = "{$tmpPrefix}-{$id}.{$version}.dat";
		if ( !is_readable( $file ) ) {
			array_map( 'unlink', glob( "{$tmpPrefix}-{$id}.*" ) );
			$data = file_get_contents( $url );
			if ( $data === false ) {
				throw new Exception( "Failed to fetch fixture: $url" );
			}
			file_put_contents( $file, $data );
		} else {
			$data = file_get_contents( $file );
		}
		return $data;
	}
}

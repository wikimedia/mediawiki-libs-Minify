<?php

use PHPUnit\Framework\TestCase;
use Wikimedia\Minify\Cli;

/**
 * @covers \Wikimedia\Minify\Cli
 */
class CliTest extends TestCase {
	/** @var false|resource */
	private $out;

	protected function setUp(): void {
		$this->out = fopen( 'php://memory', 'rw' );
	}

	private function getOutput() {
		rewind( $this->out );
		return fread( $this->out, 2048 );
	}

	public function testDefault() {
		$cli = new Cli( null, $this->out, [ '/self' ] );
		$cli->run();
		$this->assertStringContainsString( 'usage: self', $this->getOutput() );
		$this->assertSame( 1, $cli->getExitCode(), 'exit code' );
	}

	public function testError() {
		$cli = new Cli( null, $this->out, [ '/self', 'boo' ] );
		$cli->run();
		$this->assertStringContainsString( 'self error: Unknown command', $this->getOutput() );
		$this->assertStringContainsString( 'usage: self', $this->getOutput() );
		$this->assertSame( 1, $cli->getExitCode(), 'exit code' );
	}

	public function testRunCssFile() {
		$cli = new Cli( null, $this->out, [ '/self', 'css', __DIR__ . '/../data/example.css' ] );
		$cli->run();
		$this->assertSame( ".foo,.bar{ prop:value}\n", $this->getOutput() );
		$this->assertSame( 0, $cli->getExitCode(), 'exit code' );
	}

	public function testRunCssIn() {
		$in = fopen( 'php://memory', 'rw' );
		fwrite( $in, "/* comment */ .foo, .bar { prop: value; }\n" );
		rewind( $in );
		$cli = new Cli( $in, $this->out, [ '/self', 'css' ] );
		$cli->run();
		$this->assertSame( ".foo,.bar{prop:value}\n", $this->getOutput() );
		$this->assertSame( 0, $cli->getExitCode(), 'exit code' );
	}

	public function testRunCssRemap() {
		$cli = new Cli( null, $this->out, [ '/self', 'css-remap', __DIR__ . '/../data/embed-example.css' ] );
		$cli->run();
		$this->assertSame(
			".foo{background:url(data:image/gif;base64,R0lGODlhAQABAIAAAACAADAAACwAAAAAAQABAAACAkQBADs=)}\n",
			$this->getOutput()
		);
		$this->assertSame( 0, $cli->getExitCode(), 'exit code' );
	}

	public function testRunJsError() {
		$in = fopen( 'php://memory', 'rw' );
		fwrite( $in, "var x = 4exxxx;\n" );
		rewind( $in );
		$cli = new Cli( $in, $this->out, [ '/self', 'js' ] );
		$cli->run();
		$this->assertSame( "ParseError: Missing decimal digits after exponent at position 8\n", $this->getOutput() );
		$this->assertSame( 1, $cli->getExitCode(), 'exit code' );
	}

	/**
	 * When we require PHP 8+, we could run this in a separate process so that
	 * JavaScriptMinifierTest is able to cover JavaScriptMinifier::ensureExpandedStates()
	 * via a JavaScriptMinifierTest::setUp() using ReflectionProperty::getDefaultValue()
	 * to reset the static members of the class.
	 */
	public function testRunJsFile() {
		$cli = new Cli( null, $this->out, [ '/self', 'js', __DIR__ . '/../data/example.js' ] );
		$cli->run();
		$this->assertSame( "function sum(a,b){return a+b;}\n", $this->getOutput() );
		$this->assertSame( 0, $cli->getExitCode(), 'exit code' );
	}

	public static function provideSourceMapFiles() {
		foreach ( [ 'advanced.js' ] as $file ) {
			$path = __DIR__ . "/../data/sourcemap/$file";
			yield $file => [
				$path,
				preg_replace( '/\.js$/', '.min.js', $path ),
				preg_replace( '/\.js$/', '.min.js.map', $path )
			];
		}
	}

	/**
	 * This is just testRunJsFile again but with the sourcemap data provider.
	 *
	 * @dataProvider provideSourceMapFiles
	 */
	public function testSourceMapMinify( $origPath, $minPath, $mapPath ) {
		$cli = new Cli( null, $this->out, [ '/self', 'js', $origPath ] );
		$cli->run();
		$this->assertStringEqualsFile(
			$minPath,
			$this->getOutput()
		);
	}

	/**
	 * This test just ensures that the source map is identical to a previously
	 * generated file. The files are rather opaque so can't really be verified
	 * by inspection. So if the expected output changes, it has to be verified
	 * by cross-checking against the Mozilla library consumer with
	 * verifySourceMap.js.
	 *
	 * @dataProvider provideSourceMapFiles
	 */
	public function testSourceMap( $origPath, $minPath, $mapPath ) {
		$cli = new Cli( null, $this->out, [ '/self', 'jsmap-web', $origPath ] );
		$cli->run();
		$this->assertStringEqualsFile(
			$mapPath,
			$this->getOutput()
		);
	}
}

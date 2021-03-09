<?php
use Wikimedia\Minify\Cli;

/**
 * @covers Wikimedia\Minify\Cli
 */
class CliTest extends \PHPUnit\Framework\TestCase {
	private $out;

	protected function setUp() : void {
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
		$cli = new Cli( null, $this->out, [ '/self', 'css', __DIR__ . '/data/example.css' ] );
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

	/**
	 * Let PHPUnit run this in a separate proc so that JavaScriptMinifierTest is able to
	 * cover JavaScriptMinifier::ensureExpandedStates(). If and when we require PHP 8+.
	 * this could be removed in favour of a JavaScriptMinifierTest::setUp() using
	 * ReflectionProperty::getDefaultValue() to reset the static members of the class.
	 *
	 * @runInSeparateProcess
	 */
	public function testRunJsFile() {
		$cli = new Cli( null, $this->out, [ '/self', 'js', __DIR__ . '/data/example.js' ] );
		$cli->run();
		$this->assertSame( "function sum(a,b){return a+b;}\n", $this->getOutput() );
		$this->assertSame( 0, $cli->getExitCode(), 'exit code' );
	}
}

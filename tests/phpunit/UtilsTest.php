<?php

use Wikimedia\Minify\Utils;

/**
 * @covers \Wikimedia\Minify\Utils
 */
class UtilsTest extends PHPUnit\Framework\TestCase {
	public static function provideGetJsLength() {
		return [
			[ '', 0 ],
			[ 'abc', 3 ],
			[ '☎', 1 ],
			[ '😀', 2 ]
		];
	}

	/**
	 * @dataProvider provideGetJsLength
	 *
	 * @param string $input
	 * @param int $expected
	 */
	public function testGetJsLength( $input, $expected ) {
		$result = Utils::getJsLength( $input );
		$this->assertSame( $expected, $result );
	}
}

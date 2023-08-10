<?php

use Wikimedia\Minify\IndexMapOffset;

/**
 * @covers \Wikimedia\Minify\IndexMapOffset
 */
class IndexMapOffsetTest extends PHPUnit\Framework\TestCase {
	public function testConstruct() {
		$offset = new IndexMapOffset( 3, 6 );
		$this->assertSame( 3, $offset->line );
		$this->assertSame( 6, $offset->column );
	}

	public function testNewFromArray() {
		$offset = IndexMapOffset::newFromArray( [ 3, 6 ] );
		$this->assertSame( 3, $offset->line );
		$this->assertSame( 6, $offset->column );
	}

	public function testAdd() {
		$section1 = "△△△";
		$offset = IndexMapOffset::newFromText( $section1 );
		$this->assertSame( [ 0, 3 ], $offset->toArray() );

		$section2 = "▽▽▽";
		$size2 = IndexMapOffset::newFromText( $section2 );
		$offset->add( $size2 );
		$this->assertSame( [ 0, 6 ], $offset->toArray() );

		$section3 = "△\n△";
		$size3 = IndexMapOffset::newFromText( $section3 );
		$this->assertSame( [ 1, 1 ], $size3->toArray() );
		$offset->add( $size3 );
		$this->assertSame( [ 1, 1 ], $offset->toArray() );

		$combined = $section1 . $section2 . $section3;
		$sizeCombined = IndexMapOffset::newFromText( $combined );
		$this->assertSame( $offset->toArray(), $sizeCombined->toArray() );
	}
}

<?php

use PHPUnit\Framework\TestCase;
use Wikimedia\Minify\IndexMap;
use Wikimedia\Minify\IndexMapOffset;

/**
 * @covers \Wikimedia\Minify\IndexMap
 */
class IndexMapTest extends TestCase {
	public function testEmptyMap() {
		$map = new IndexMap;
		// The index map part of the spec doesn't exactly say that "file" is
		// optional, but it says "see the description in the standard map", and
		// it is optional in the standard map.
		$expected = <<<JSON
{
"version": 3,
"sections": [
]
}
JSON;
		$this->assertSame( $expected, $map->getMap() );
	}

	public function testOutputFile() {
		$map = new IndexMap;
		$map->outputFile( "app.js" );
		$expected = <<<JSON
{
"version": 3,
"file": "app.js",
"sections": [
]
}
JSON;
		$this->assertSame( $expected, $map->getMap() );
	}

	public function testOneSection() {
		$map = new IndexMap;
		$map->outputFile( "app.js" );
		// We're roughly following the spec example here, but it has several
		// mistakes and is not valid JSON, so we fix that. Also, I am assuming
		// that whitespace is not significant, except for the line numbers of
		// the first four lines.
		$map->addEncodedMap(
			json_encode(
				[
					'version' => 3,
					'file' => 'section.js',
					'sources' => [ 'foo.js', 'bar.js' ],
					'names' => [ 'src', 'maps', 'are', 'fun' ],
					'mappings' => 'AAAA,E;;ABCDE;',
				],
				JSON_PRETTY_PRINT
			),
			new IndexMapOffset( 100, 10 )
		);
		$expected = <<<JSON
{
"version": 3,
"file": "app.js",
"sections": [
{"offset":{"line":0,"column":0},"map":{
    "version": 3,
    "file": "section.js",
    "sources": [
        "foo.js",
        "bar.js"
    ],
    "names": [
        "src",
        "maps",
        "are",
        "fun"
    ],
    "mappings": "AAAA,E;;ABCDE;"
}}
]
}
JSON;
		$this->assertSame( $expected, $map->getMap() );
	}

	public function testTwoSections() {
		$map = new IndexMap;
		$map->outputFile( "app.js" );
		for ( $i = 0; $i < 2; $i++ ) {
			$map->addEncodedMap(
				json_encode(
					[
						'version' => 3,
						'file' => 'section.js',
						'sources' => [ 'foo.js', 'bar.js' ],
						'names' => [ 'src', 'maps', 'are', 'fun' ],
						'mappings' => 'AAAA,E;;ABCDE;',
					],
					JSON_PRETTY_PRINT
				),
				new IndexMapOffset( 100, 10 )
			);
		}
		$expected = <<<JSON
{
"version": 3,
"file": "app.js",
"sections": [
{"offset":{"line":0,"column":0},"map":{
    "version": 3,
    "file": "section.js",
    "sources": [
        "foo.js",
        "bar.js"
    ],
    "names": [
        "src",
        "maps",
        "are",
        "fun"
    ],
    "mappings": "AAAA,E;;ABCDE;"
}},
{"offset":{"line":100,"column":10},"map":{
    "version": 3,
    "file": "section.js",
    "sources": [
        "foo.js",
        "bar.js"
    ],
    "names": [
        "src",
        "maps",
        "are",
        "fun"
    ],
    "mappings": "AAAA,E;;ABCDE;"
}}
]
}
JSON;
		$this->assertSame( $expected, $map->getMap() );
	}
}

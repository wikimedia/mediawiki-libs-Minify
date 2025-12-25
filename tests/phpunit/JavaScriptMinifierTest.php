<?php

use PHPUnit\Framework\TestCase;
use Wikimedia\Minify\JavaScriptMinifier;
use Wikimedia\Minify\ParseError;

/**
 * @covers \Wikimedia\Minify\JavaScriptMinifier
 */
class JavaScriptMinifierTest extends TestCase {

	protected function tearDown(): void {
		// Reset
		$this->setMaxLineLength( 1000 );
		parent::tearDown();
	}

	private function setMaxLineLength( $val ) {
		$classReflect = new ReflectionClass( JavaScriptMinifier::class );
		$classReflect->setStaticPropertyValue( 'maxLineLength', $val );
	}

	public static function provideCases() {
		return [

			// Basic whitespace and comments that should be stripped entirely
			[ "\r\t\f \v\n\r", "" ],
			[ "/* Foo *\n*bar\n*/", "" ],

			/**
			 * Slashes used inside block comments (T28931).
			 * At some point there was a bug that caused this comment to be ended at '* /',
			 * causing /M... to be left as the beginning of a regex.
			 */
			[
				"/**\n * Foo\n * {\n * 'bar' : {\n * "
					. "//Multiple rules with configurable operators\n * 'baz' : false\n * }\n */",
				"" ],

			/**
			 * '  Foo \' bar \
			 *  baz \' quox '  .
			 */
			[
				"'  Foo  \\'  bar  \\\n  baz  \\'  quox  '  .length",
				"'  Foo  \\'  bar  \\\n  baz  \\'  quox  '.length"
			],
			[
				"\"  Foo  \\\"  bar  \\\n  baz  \\\"  quox  \"  .length",
				"\"  Foo  \\\"  bar  \\\n  baz  \\\"  quox  \".length"
			],
			[ "// Foo b/ar baz", "" ],
			[
				"/  Foo  \\/  bar  [  /  \\]  /  ]  baz  /  .length",
				"/  Foo  \\/  bar  [  /  \\]  /  ]  baz  /.length"
			],

			// HTML comments
			[ "<!-- Foo bar", "" ],
			[ "<!-- Foo --> bar", "" ],
			[ "--> Foo", "" ],
			[ "x --> y", "x-->y" ],

			// Semicolon insertion
			[ "(function(){return\nx;})", "(function(){return\nx;})" ],
			[ "throw\nx;", "throw\nx;" ],
			[ "throw new\nError('x');", "throw new Error('x');" ],
			[ "while(p){continue\nx;}", "while(p){continue\nx;}" ],
			[ "while(p){break\nx;}", "while(p){break\nx;}" ],
			[ "var\nx;", "var x;" ],
			[ "x\ny;", "x\ny;" ],
			[ "x\n++y;", "x\n++y;" ],
			[ "x\n!y;", "x\n!y;" ],
			[ "x\n{y}", "x\n{y}" ],
			[ "x\n+y;", "x+y;" ],
			[ "x\n(y);", "x(y);" ],
			[ "5.\nx;", "5.\nx;" ],
			[ "0xFF.\nx;", "0xFF.x;" ],
			[ "5.3.\nx;", "5.3.x;" ],

			// Cover failure case for incomplete hex literal
			[ "0x;", "0x;", 'Expected a hexadecimal number but found 0x;' ],

			// Cover failure case for number with no digits after E
			[ "1.4E", "1.4E", 'Missing decimal digits after exponent' ],

			// Cover failure case for number with several E
			[ "1.4EE2", "1.4EE2", 'Number with several E' ],
			[ "1.4EE", "1.4EE", 'Number with several E' ],

			// Cover valid number with E
			[ "1.4E23", "1.4E23" ],

			// Semicolon insertion between an expression having an inline
			// comment after it, and a statement on the next line (T29046).
			[
				"var a = this //foo bar \n for ( b = 0; c < d; b++ ) {}",
				"var a=this\nfor(b=0;c<d;b++){}"
			],

			// Cover failure case of incomplete regexp at end of file (T75556)
			// FIXME: This is invalid, but currently tolerated
			[ "*/", "*/" ],

			// Cover failure case of incomplete char class in regexp (T75556)
			// FIXME: This is invalid, but currently tolerated
			[ "/a[b/.test", "/a[b/.test" ],

			// Cover failure case of incomplete string at end of file (T75556)
			// FIXME: This is invalid, but currently tolerated
			[ "'a", "'a" ],

			// Token separation
			[ "x  in  y", "x in y" ],
			[ "/x/g  in  y", "/x/g in y" ],
			[ "x  in  30", "x in 30" ],
			[ "x  +  ++  y", "x+ ++y" ],
			[ "x ++  +  y", "x++ +y" ],
			[ "x  /  /y/.exec(z)", "x/ /y/.exec(z)" ],

			// State machine
			[ "/  x/g", "/  x/g" ],
			[ "(function(){return/  x/g})", "(function(){return/  x/g})" ],
			[ "+/  x/g", "+/  x/g" ],
			[ "++/  x/g", "++/  x/g" ],
			[ "x/  x/g", "x/x/g" ],
			[ "(/  x/g)", "(/  x/g)" ],
			[ "if(/  x/g);", "if(/  x/g);" ],
			[ "(x/  x/g)", "(x/x/g)" ],
			[ "([/  x/g])", "([/  x/g])" ],
			[ "+x/  x/g", "+x/x/g" ],
			[ "{}/  x/g", "{}/  x/g" ],
			[ "+{}/  x/g", "+{}/x/g" ],
			[ "(x)/  x/g", "(x)/x/g" ],
			[ "if(x)/  x/g", "if(x)/  x/g" ],
			[ "for(x;x;{}/  x/g);", "for(x;x;{}/x/g);" ],
			[ "x;x;{}/  x/g", "x;x;{}/  x/g" ],
			[ "x:{}/  x/g", "x:{}/  x/g" ],
			[ "switch(x){case y?z:{}/  x/g:{}/  x/g;}", "switch(x){case y?z:{}/x/g:{}/  x/g;}" ],
			[ "function x(){}/  x/g", "function x(){}/  x/g" ],
			[ "+function x(){}/  x/g", "+function x(){}/x/g" ],

			// Multiline quoted string
			[ "var foo=\"\\\nblah\\\n\";", "var foo=\"\\\nblah\\\n\";" ],

			// Multiline quoted string followed by string with spaces
			[
				"var foo=\"\\\nblah\\\n\";\nvar baz = \" foo \";\n",
				"var foo=\"\\\nblah\\\n\";var baz=\" foo \";"
			],

			// URL in quoted string ( // is not a comment)
			[
				"aNode.setAttribute('href','http://foo.bar.org/baz');",
				"aNode.setAttribute('href','http://foo.bar.org/baz');"
			],

			// URL in quoted string after multiline quoted string
			[
				"var foo=\"\\\nblah\\\n\";\naNode.setAttribute('href','http://foo.bar.org/baz');",
				"var foo=\"\\\nblah\\\n\";aNode.setAttribute('href','http://foo.bar.org/baz');"
			],

			// Comma between args in function call
			[
				"myfunc('a', 'b', 'c');",
				"myfunc('a','b','c');"
			],

			// Division vs. regex nastiness
			[
				"alert( (10+10) / '/'.charCodeAt( 0 ) + '//' );",
				"alert((10+10)/'/'.charCodeAt(0)+'//');"
			],
			[ "if(1)/a /g.exec('Pa ss');", "if(1)/a /g.exec('Pa ss');" ],
			[
				// Async as literal followed by division
				"var x = { \n aa: lit / 2 * 3 / lit, \n bb: async / 2 * 3 / lit \n };",
				"var x={aa:lit/2*3/lit,bb:async/2*3/lit};"
			],
			[
				// Yield followed by regex
				"function* myGen() { yield / 2 * 3 /; }",
				"function*myGen(){yield/ 2 * 3 /;}"
			],

			// Unicode letter characters should pass through ok in identifiers (T33187)
			[ "var KaŝSkatolVal = {}", 'var KaŝSkatolVal={}' ],

			// Per spec unicode char escape values should work in identifiers,
			// as long as it's a valid char. In future it might get normalized.
			[ "var Ka\\u015dSkatolVal = {}", 'var Ka\\u015dSkatolVal={}' ],

			// Numbers
			// Fraction is optional
			[ "var a = 5.;", "var a=5.;" ],
			// No ambiguity after explicit fraction
			[ "5.0.toString();", "5.0.toString();" ],
			// No ambiguity after implicit fraction
			[ "5..toString();", "5..toString();" ],
			[ "5.\n.toString();", '5..toString();' ],
			// No ambiguity after space (T303827)
			[ "3\n.foo;", "3 .foo;" ],
			[ "var _ = 2 .toString;", "var _=2 .toString;" ],
			// Invalid syntax: Simple dot notation on number literals is ambigious
			[ "3.foo;", "3.foo;" ],
			// Invalid syntax: Too many decimal points
			[ "5..0;", "5..0;", 'Too many decimal points' ],
			[ "5...toString();", "5...toString();", 'Too many decimal points' ],

			// Cover states for dotless number literals with prop after space (T303827)
			'STATEMENT dotless prop' => [ '42 .foo;', '42 .foo;' ],
			'EXPRESSION dotless prop' => [ 'a = 42 .foo;', 'a=42 .foo;' ],
			'EXPRESSION_NO_NL dotless prop' => [ 'throw 42 .foo;', 'throw 42 .foo;' ],
			'EXPRESSION_END dotless prop' => [ "a = () => {}\n42 .foo;", "a=()=>{}\n42 .foo;" ],
			'EXPRESSION_ARROWFUNC dotless prop' => [ "a = () => 42 .foo;", "a=()=>42 .foo;" ],
			'EXPRESSION_TERNARY dotless prop' => [ "x ? 42 .foo : b;", "x?42 .foo:b;" ],
			'EXPRESSION_TERNARY_ARROWFUNC dotless prop' => [ "x ? () => 42 .foo : b;", "x?()=>42 .foo:b;" ],
			'PAREN_EXPRESSION dotless prop' => [ '( 42 .foo );', '(42 .foo);' ],
			'PAREN_EXPRESSION_ARROWFUNC dotless prop' => [ "( () => 42 .foo);", "(()=>42 .foo);" ],
			'PROPERTY_EXPRESSION dotless prop' => [ "a = { key: 42 .foo };", "a={key:42 .foo};" ],
			'PROPERTY_EXPRESSION_ARROWFUNC dotless prop' => [ "a = { key: 42 .foo };", "a={key:42 .foo};" ],

			// Boolean minification
			[ "var a = { b: true };", "var a={b:true};" ],
			[ "var a = { true: 12 };", "var a={true:12};" ],
			[ "a.true = 12;", "a.true=12;" ],
			[ "a.foo = true;", "a.foo=true;" ],
			[ "a.foo = false;", "a.foo=false;" ],
			[ "a.foo = bar ? false : true;", "a.foo=bar?false:true;" ],
			[ "func( true, false )", "func(true,false)" ],
			[ "function f() { return false; }", "function f(){return false;}" ],
			[ "let f = () => false;", "let f=()=>false;" ],
			// T237042: Beware of `true.toString()`.
			// Changing to `!0.toString()` would be a syntax error.
			// Changing to `!(0).toString()` would return bool false instead of string "true"
			[ "true.toString();", "true.toString();" ],
			[ "x = true.toString()", "x=true.toString()" ],

			// Combined variable declations
			[ 'var a = 1, b = 2;', 'var a=1,b=2;' ],
			[ 'let a = 1, b = 2;', 'let a=1,b=2;' ],
			[ 'const a = 1, b = 2;', 'const a=1,b=2;' ],

			// Template strings
			[ 'let a = `foo + ${ 1 + 2 } + bar`;', 'let a=`foo + ${1+2} + bar`;' ],
			[ 'let a = `foo + ${ "hello world" } + bar`;', 'let a=`foo + ${"hello world"} + bar`;' ],
			[
				'let a = `foo + ${ `bar + ${ `baz + ${ `quux` } + lol` } + ${ `yikes` } ` }`, b = 3;',
				'let a=`foo + ${`bar + ${`baz + ${`quux`} + lol`} + ${`yikes`} `}`,b=3;'
			],
			[ 'let a = `foo$\\\\` + 23;', 'let a=`foo$\\\\`+23;' ],
			// Template string with an escaped \`
			[ 'let a = `foo\\`bar + baz`;', 'let a=`foo\\`bar + baz`;' ],

			// Behavior of 'yield' in generator functions vs normal functions
			[ "function *f( x ) {\n if ( x )\n yield\n ( 42 )\n}", "function*f(x){if(x)yield\n(42)}" ],
			[ "function g( y ) {\n const yield = 42\n yield\n ( 42 )\n}", "function g(y){const yield=42\nyield(42)}" ],
			// Normal function nested inside generator function
			[
				<<<JAVASCRIPT
				function *f( x ) {
					if ( x )
						yield
						( 42 )
					function g() {
						const yield = 42
						yield
						( 42 )
						return
						42
					}
					yield
					42
				}
JAVASCRIPT
				,
				"function*f(x){if(x)yield\n(42)\nfunction g(){const yield=42\nyield(42)\nreturn\n42}yield\n42}",
			],

			// Object literals: optional values, computed keys
			[ "let a = { foo, bar: 'baz', [21 * 2]: 'answer' }", "let a={foo,bar:'baz',[21*2]:'answer'}" ],
			[
				"let a = { [( function ( x ) {\n if ( x )\nreturn\nx*2 } ( 21 ) )]: 'wrongAnswer' }",
				"let a={[(function(x){if(x)return\nx*2}(21))]:'wrongAnswer'}"
			],
			// Functions in object literals
			[
				"let a = { foo() { if ( x )\n return\n 42 }, bar: 21 * 2 };",
				"let a={foo(){if(x)return\n42},bar:21*2};"
			],
			[
				"let a = { *f() { yield\n(42); }, g() { let yield = 42; yield\n(42); };",
				"let a={*f(){yield\n(42);},g(){let yield=42;yield(42);};"
			],
			[
				"function *f() { return { g() { let yield = 42; yield\n(42); } }; }",
				"function*f(){return{g(){let yield=42;yield(42);}};}"
			],
			[
				"function *f() { return { *h() { yield\n(42); } }; }",
				"function*f(){return{*h(){yield\n(42);}};}"
			],

			// Classes
			[
				"class Foo { *f() { yield\n(42); }, g() { let yield = 42; yield\n(42); } }",
				"class Foo{*f(){yield\n(42);},g(){let yield=42;yield(42);}}"
			],
			[
				"class Foo { static *f() { yield\n(42); }, static g() { let yield = 42; yield\n(42); } }",
				"class Foo{static*f(){yield\n(42);},static g(){let yield=42;yield(42);}}"
			],
			[
				"class Foo { get bar() { return\n42 } set baz( val ) { throw new Error( 'yikes' ) } }",
				"class Foo{get bar(){return\n42}set baz(val){throw new Error('yikes')}}"
			],
			// Extends
			[ "class Foo extends Bar { f() { return\n42 } }", "class Foo extends Bar{f(){return\n42}}" ],
			[ "class Foo extends Bar.Baz { f() { return\n42 } }", "class Foo extends Bar.Baz{f(){return\n42}}" ],
			[
				"class Foo extends (function (x) { return\n x.Baz; }(Bar)) { f() { return\n42 } }",
				"class Foo extends(function(x){return\nx.Baz;}(Bar)){f(){return\n42}}"
			],
			[
				"class Foo extends function(x) {return\n 42} { *f() { yield\n 42 } }",
				"class Foo extends function(x){return\n42}{*f(){yield\n42}}"
			],

			// Arrow functions
			[ "let a = ( x, y ) => x + y;", "let a=(x,y)=>x+y;" ],
			[ "let a = ( x, y ) => x ** y;", "let a=(x,y)=>x**y;" ],
			[ "let a = ( x, y ) => { return \n x + y };", "let a=(x,y)=>{return\nx+y};" ],
			[ "let a = ( x, y ) => { return x + y; }\n( 1, 2 )", "let a=(x,y)=>{return x+y;}\n(1,2)" ],
			[ "let a = ( x, y ) => { return x + y; }\n+5", "let a=(x,y)=>{return x+y;}\n+5" ],
			// Note that non-arrow functions behave differently:
			[ "let a = function ( x, y ) { return x + y; }\n( 1, 2 )", "let a=function(x,y){return x+y;}(1,2)" ],
			[ "let a = function ( x, y ) { return x + y; }\n+5", "let a=function(x,y){return x+y;}+5" ],

			// export
			[ "export { Foo, Bar as Baz } from 'thingy';", "export{Foo,Bar as Baz}from'thingy';" ],
			[ "export * from 'thingy';", "export*from'thingy';" ],
			[ "export class Foo { f() { return\n 42 } }", "export class Foo{f(){return\n42}}" ],
			[ "export default class Foo { *f() { yield\n 42 } }", "export default class Foo{*f(){yield\n42}}" ],
			// import
			[ "import { Foo, Bar as Baz, Quux } from 'thingy';", "import{Foo,Bar as Baz,Quux}from'thingy';" ],
			[ "import * as Foo from 'thingy';", "import*as Foo from'thingy';" ],
			[ "import Foo, * as Bar from 'thingy';", "import Foo,*as Bar from'thingy';" ],
			// Semicolon insertion before import/export
			[ "( x, y ) => { return x + y; }\nexport class Foo {}", "(x,y)=>{return x+y;}\nexport class Foo{}" ],
			[ "let x = y + 3\nimport Foo from 'thingy';", "let x=y+3\nimport Foo from'thingy';" ],

			// Reserved words as object properties
			[ "x.export\n++y", "x.export\n++y" ],
			[ "x.import\n++y", "x.import\n++y" ],
			[ "x.class\n++y", "x.class\n++y" ],
			[ "x.function\n++y", "x.function\n++y" ],
			[ "x.yield\n++y", "x.yield\n++y" ],
			[ "function *f() { x.yield\n++y }", "function*f(){x.yield\n++y}" ],
			[ "x.var\n++y", "x.var\n++y" ],
			[ "x.if\n++y", "x.if\n++y" ],
			[ "x.else\n++y", "x.else\n++y" ],
			[ "x.return\n++y", "x.return\n++y" ],

			// Cover failure case of x.class polluting the state machine (T277161)
			[
				"(x && y.class);let obj = {}\n function f() { return\n42 }",
				"(x&&y.class);let obj={}\nfunction f(){return\n42}"
			],
			[
				"x ? y.class : y.foo\n let obj = {}\n function f() { return\n42 }",
				"x?y.class:y.foo\nlet obj={}\nfunction f(){return\n42}"
			],
			[
				"let x = {y: z.class}\n let obj = {}\n function f() { return\n42 }",
				"let x={y:z.class}\nlet obj={}\nfunction f(){return\n42}"
			],
			// Reserved words as property names in an object literal
			[
				"let x = ( { class: 'foo' } ); let obj = {}\n function f() { return\n42 }",
				"let x=({class:'foo'});let obj={}\nfunction f(){return\n42}"
			],
			// Reserved words classified as operators as property names in dot notation (T283244)
			[
				"a.delete = function() { delete x; }\nb = 1",
				"a.delete=function(){delete x;}\nb=1"
			],
			[
				"a.instanceof = function() { delete x; }\nb = 1",
				"a.instanceof=function(){delete x;}\nb=1"
			],
			[
				"(x && y.delete); let obj = {}\n function f() { return\n42 }",
				"(x&&y.delete);let obj={}\nfunction f(){return\n42}",
			],
			[
				"x ? y.delete : y.foo\n let obj = {}\n function f() { return\n42 }",
				"x?y.delete:y.foo\nlet obj={}\nfunction f(){return\n42}",
			],
			[
				"let x = {y: z.delete}\n let obj = {}\n function f() { return\n42 }",
				"let x={y:z.delete}\nlet obj={}\nfunction f(){return\n42}"
			],
			[
				"var\n x \n = \n async \n function foo(){}",
				"var x=async\nfunction foo(){}"
			],
			[
				"var test = function( async\n) { var\n x \n = \n async \n function foo(){} }",
				"var test=function(async){var x=async\nfunction foo(){}}"
			],
			// Async arrow function expressions
			[
				"var\n x \n = \n async () => {  return 1; }",
				"var x=async()=>{return 1;}"
			],
			// Async class methods
			[
				"class User { getId() {\nreturn 42;}\n\n async  login() {\n  return true;\n }\n }",
				"class User{getId(){return 42;}async login(){return true;}}"
			],
			// Async object methods
			[
				"const obj = { foo: 1,\n async login() {await user.login();}\n}",
				"const obj={foo:1,async login(){await user.login();}}"
			],
			// Async IIFE
			[
				"(async function() { \n class User { \n async login() { \n console.log('login'); \n }\n }" .
					"await new User().login();\n\n  })();",
				"(async function(){class User{async login(){console.log('login');}}await new User().login();})();"
			],
			// Trailing comma in function declaration
			[
				"function myFunc(\n  parOne,\nparTwo,\nparThree, // Trailing comma is valid here.\n) {}",
				// TODO: trailing comma should be removed
				"function myFunc(parOne,parTwo,parThree,){}",
				null,
				'Ignore Peast-AST difference: Harmless, we recognise trailing commas'
			],
			// Trailing comma in function call
			[
				"var  x =  fun(1,  2,3,\n 4,\n ) ;",
				// TODO: trailing comma should be removed
				"var x=fun(1,2,3,4,);",
				null,
				'Ignore Peast-AST difference: Harmless, we recognise trailing commas'
			],
			[
				"let lat = ((_b = json_js.wr_properties) == null ? void 0 : _b.lat) ?? 47;\n function get_austria_feature() {\nreturn feature;\n}",
				"let lat=((_b=json_js.wr_properties)==null?void 0:_b.lat)??47;function get_austria_feature(){return feature;}"
			],
			// ES2018 RegEx lookbehinds
			[
				"let a = /(?<=foo)bar/;",
				"let a=/(?<=foo)bar/;"
			],
			[
				"let a = 'bazbar'; a = a.replace( /(?<!foo)bar/, 'foo' ); console.log( a );",
				"let a='bazbar';a=a.replace(/(?<!foo)bar/,'foo');console.log(a);"
			],
			// ES2018 RegEx /s flag
			[
				"let a = /bar/s;",
				"let a=/bar/s;"
			],
			// ES2018 RegEx named capture groups
			[
				"console.log( '2024-01-01'.match( /(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})/ ).groups );",
				"console.log('2024-01-01'.match(/(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})/).groups);"
			],
			// ES2018 RegEx unicode property escapes
			[
				"let a = /\\p{Script=Greek}/u;",
				"let a=/\\p{Script=Greek}/u;"
			],
			// ES2018 spread operator for arrays
			[
				"let arr1 = [ 1, 2, 3 ]; let arr2 = [ 4, 5, 6 ]; let arr3 = [ ...arr1, ...arr2 ]; console.log( arr3 );",
				"let arr1=[1,2,3];let arr2=[4,5,6];let arr3=[...arr1,...arr2];console.log(arr3);"
			],
			// ES2018 spread operator for objects
			[
				"let obj1 = { 1: 1, 2: 2, 3: 3 }; let obj2 = { 4: 4, 5: 5, 6: 6 }; let obj3 = { ...obj1, ...obj2 }; console.log( obj3 );",
				"let obj1={1:1,2:2,3:3};let obj2={4:4,5:5,6:6};let obj3={...obj1,...obj2};console.log(obj3);"
			],
			// ES2018 asynchronous iteration ("for await")
			[
				"for await (const item of iterable) { console.log( item ); }",
				"for await(const item of iterable){console.log(item);}"
			],
		];
	}

	/**
	 * @dataProvider provideCases
	 */
	public function testMinifyOutput( $code, $expectedOutput, $errorMsg = null ) {
		$error = null;
		$minified = JavaScriptMinifier::minify( $code, static function ( $e ) use ( &$error ) {
			$error = $e;
		} );
		$this->assertEquals(
			$expectedOutput,
			$minified,
			"Minified output should be in the form expected."
		);
		if ( $errorMsg ) {
			$this->assertInstanceOf( ParseError::class, $error, 'Returned error' );
			$this->assertEquals( $errorMsg, $error->getMessage(), 'Error message' );
		} else {
			$this->assertSame( null, $error, 'Returned error' );
		}
	}

	public static function provideLineBreaker() {
		return [
			[
				// Regression tests for T34548.
				// Must not break between 'E' and '+'.
				"function abc() { \n var name = 1.23456789E55; \n return name;}",
				8,
				[
					'function abc(){',
					'var name=1.23456789E55;',
					'return name;}'
				],
			],
			[
				"function abc() { \n var name=1.23456789E+5; \n return name;}",
				9,
				[
					'function abc(){',
					'var name=1.23456789E+5;',
					'return name;}'
				],
			],
			[
				"function abc() { \n var name = 1.23456789E-5; \n return name;}",
				12,
				[
					'function abc(){',
					'var name=1.23456789E-5;',
					'return name;}'
				],
			],
			[
				// Must not break before '++'
				"if( x++ ){ \n console.log('hello'); \n }",
				10,
				[
					'if(x++){',
					'console.log(\'hello\');',
					'}',
				],
			],
			[
				// Regression test for T201606.
				// Must not break between 'return' and Expression.
				// Was caused by bad state after '{}' in property value.
				<<<JAVASCRIPT
			call( function () {
				try {
				} catch (e) {
					obj = {
						key: 1 ? 0 : {}
					};
				}
				return name === 'input';
			} );
JAVASCRIPT
				,
				5,
				[
					'call(function(){',
					'try{}catch(e){',
					'obj={',
					'key:1?0:{}',
					'};}',
					// The return Statement:
					//     return [no LineTerminator here] Expression
					'return name===\'input\';',
					'});',
				]
			],
			[
				// Regression test for T201606.
				// Must not break between 'return' and Expression.
				// This was caused by a bad state after a ternary in the expression value
				// for a key in an object literal.
				<<<JAVASCRIPT
call( {
	key: 1 ? 0 : function () {
		return this;
	}
} );
JAVASCRIPT
				,
				5,
				[
					'call({',
					'key:1?0:function(){',
					'return this;',
					'}});'
				]
			],
			[
				// No newline after throw, but a newline after "throw new" is OK
				"throw new Error( 'yikes' ); \n function f () { \n return ++x; }",
				10,
				[
					'throw new Error(\'yikes\');',
					'function f(){',
					'return++x;}'
				]
			],
			[
				// Yield statement in generator function
				<<<JAVASCRIPT
				function *f( x ) {
					yield 42
					function g() {
						let yield = 42;
						yield( 42 )
						return 42
					}
					yield *21*2
					const x = yield 63
					const y = x ? yield 64 : yield 65
					const z = { yield: yield 66 }
					const a = ( yield 67 ) + 68
					const b = z.yield + yield yield 69
				}
JAVASCRIPT
				,
				10,
				[
					'function*f(x){',
					'yield 42',
					'function g(){',
					'let yield=42;',
					'yield(42)',
					'return 42}',
					'yield*21*2',
					'const x=yield 63',
					'const y=x?yield 64:yield 65',
					'const z={yield:yield 66}',
					'const a=(yield 67)+68',
					'const b=z.yield+yield yield 69',
					'}'
				]
			],
			[
				// Template string literal with a function body inside
				'let a = `foo + ${ ( function ( x ) { return x * 2; }( 21 ) ) } + bar`;',
				4,
				[
					'let a=`foo + ${(function(x){return x*2;}(21))} + bar`;'
				]
			],
			[
				// Functions in classes
				"class Foo { \n static *f() { \n yield(42); \n }, \n static g() { \n let yield = 42; \n yield(42); \n } }",
				13,
				[
					'class Foo{',
					'static*f(){',
					'yield(42);},',
					'static g(){',
					'let yield=42;',
					'yield(42);}}'
				]
			],
			[
				"class Foo { get bar() { return 42 } set baz( val ) { throw new Error( 'yikes' ) } }",
				6,
				[
					'class Foo{get bar(){return 42}set baz(val){throw new Error(\'yikes\')}}'
				]
			],
			[
				// Don't break before an arrow
				"let a = (x, y) => x + y;",
				4,
				[
					'let a=(x,y)=>x+y;'
				]
			],
			[
				"let a = (x, y) => { return x + y; };",
				5,
				[
					'let a=(x,y)=>{return x+y;};'
				]
			],
			[
				"export default class Foo { *f() { \n yield 42; \n } }",
				8,
				[
					'export default class Foo{*f(){',
					'yield 42;',
					'}}'
				]
			],
			[
				"export { Foo, Bar as Baz, Quux }; \n import * as Foo from 'thingy';",
				6,
				[
					'export{Foo,Bar as Baz,Quux};',
					'import*as Foo from\'thingy\';'
				]
			],
			[
				"import * as Foo from 'thingy'; \n export { Foo, Bar as Baz, Quux };",
				7,
				[
					'import*as Foo from\'thingy\';',
					'export{Foo,Bar as Baz,Quux};'
				]
			],
			[
				"import Foo, * as Bar from 'thingy';",
				20,
				[
					'import Foo,*as Bar from\'thingy\';'
				]
			],
			// Cover failure case of x.class polluting the state machine (T277161)
			[
				"let blah = (x && y.class); let obj = {}\n function g() { return 42; }",
				3,
				[
					'let blah=(x&&y.class);let obj={}',
					'function g(){return 42;}'
				]
			],
			// Cover failure case where ... is not recognized as a single token (T287526)
			[
				'let blah = foo( ...bar );',
				5,
				[
					'let blah=foo(...bar);'
				]
			],
			'async function declaration' => [
				"async function test( x ) { \n await x.login(); \n }",
				10,
				[
					'async function test(x){',
					'await x.login();',
					'}'
				]
			],
			'async function expression' => [
				"var test = async function( x ) { \n  await x.login(); }",
				10,
				[
					'var test=async function(x){',
					'await x.login();}',
				]
			],
			'async function paren expression' => [
				"var test = [ \n async function( x ) { \n } ];",
				10,
				[
					'var test=[',
					'async function(x){',
					'}];'
				]
			],
			'async as literal' => [
				"var x = { async: 1 }, async = x.async; \n function y() { \n return async; }",
				3,
				[
					'var x={async:1},async=x.async;',
					'function y(){',
					'return async;}'
				]
			],
			'Exponentiation assignment operator' => [
				"var x = 4; \n x **= 2;",
				10,
				[
					'var x=4;x**=2;'

				]
			],
			[
				"let lat = ( a || b) ?? c; \n function e() { \n return feature; \n}",
				4,
				[
					'let lat=(a||b)??c;',
					'function e(){',
					'return feature;',
					'}'

				]
			],
			[
				"var foo = x(a + b) / y(); \n foo++; \n var bar = y( \n z(a), \n z(b), \n z(c), \n z(d), \n z(e) \n );",
				20,
				[
					'var foo=x(a+b)/y();',
					'foo++;var bar=y(z(a),',
					'z(b),z(c),z(d),z(e));'

				]
			],
			"Async methods" => [
				<<< JAVASCRIPT
				class ApiService {
					async fetchData() {
					const response = await fetch('https://api.example.com/data');
					return response.json();
					}
				}
			JAVASCRIPT,
				10,
				[
					'class ApiService{',
					'async fetchData(){',
					'const response=await fetch(\'https://api.example.com/data\');',
					'return response.json();',
					'}}'
				]
					],
			"Optional catch binding" => [
				"try { \n throw new Error('Something went wrong'); \n } catch { \n console.log('An error occurred'); \n}",
				4,
				[
					'try{',
					'throw new Error(\'Something went wrong\');',
					'}catch{',
					'console.log(\'An error occurred\');',
					'}'
				]
			]
		];
	}

	/**
	 * @dataProvider provideLineBreaker
	 */
	public function testLineBreaker( $code, $lineLength, array $expectedLines ) {
		$this->setMaxLineLength( $lineLength );
		$actual = JavaScriptMinifier::minify( $code );
		$this->assertEquals(
			$expectedLines,
			explode( "\n", $actual )
		);
	}

	/**
	 * Ensures that the optimized operator lookahead logic in minifyInternal
	 * is in sync with the token definitions in $tokenTypes.
	 */
	public function testOperatorLookaheadCoversAllTokens() {
		$reflection = new ReflectionClass( JavaScriptMinifier::class );
		$tokenTypes = $reflection->getProperty( 'tokenTypes' )->getValue();
		$opChars = $reflection->getProperty( 'opChars' )->getValue();

		// Filter for operators: tokens that start with a character in $opChars
		$operators = [];
		foreach ( array_keys( $tokenTypes ) as $token ) {
			if ( isset( $opChars[ $token[0] ] ) ) {
				$operators[] = $token;
			}
		}

		// Make sure each operator is parsed as a single token
		foreach ( $operators as $op ) {
			// Prepend 'x' to force the parser into an expression state,
			// ensuring '/' is parsed as division and not a regex.
			$source = "x" . $op;

			$capturedTokens = [];
			$onDebug = static function ( $state ) use ( &$capturedTokens ) {
				$capturedTokens[] = $state['token'];
			};

			JavaScriptMinifier::minifyInternal( $source, null, null, $onDebug );

			// Expect: ['x', $op]
			$this->assertCount(
				2,
				$capturedTokens,
				"Minifier failed to parse '$op' as a single token."
			);

			$this->assertSame(
				$op,
				$capturedTokens[1],
				"Lookahead logic parsed '$op' incorrectly."
			);
		}
	}
}

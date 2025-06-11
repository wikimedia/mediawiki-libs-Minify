<?php

use Peast\Peast;
use Peast\Syntax\Exception as PeastSyntaxException;
use Peast\Traverser;
use PHPUnit\Framework\TestCase;
use Wikimedia\Minify\JavaScriptMinifier;

/**
 * Validate JavaScriptMinifier token stream against Peast AST.
 *
 * Since Minify v2.8.1, we no longer insert new lines (T368204). This has
 * made JavaScriptMinifier significantly more stable. But, for us as
 * maintainers, this made it harder to observe and assert the correctness
 * of the internal model and state machine. (We previously relied on
 * JavaScriptMinifier::testLineBreaker for this.)
 *
 * In order to easily catch issues early during development, this integration
 * test compares our token stream against a rendering of the Peast AST.
 *
 * This test does not require any specific expectations for a given input.
 * Instead, this test can take anything as input, and automatically compares
 * our token stream to the Peast AST rendering. As such, we can re-use all
 * inputs, test cases, and data providers from JavaScriptMinifierTest.php
 * and put them through this test as well.
 *
 * == How to address a test failure ==
 *
 * If this test fails, update ::getExpectedTokensFromPeast() to handle whatever
 * new syntax you're implementing. Consult the default rendering by upstream
 * in \Peast\Renderer::renderNode [1] for how to access relevant child nodes.
 *
 * You can re-run a single case like so:
 *
 *     vendor/bin/phpunit --filter '"provideCases 123"'
 *
 * To inspect a small snippet on its own:
 *
 *     echo 'var x;' | bin/minify jsdebug
 *
 * [1]: https://github.com/mck89/peast/blob/v1.16.3/lib/Peast/Renderer.php#L124
 *
 * @coversNothing
 */
class JavaScriptMinifierTokenTest extends TestCase {

	/**
	 * @dataProvider provideStateMachineCases
	 */
	public function testStateMachine( $code ) {
		try {
			$expected = $this->getExpectedTokensFromPeast( $code );
		} catch ( PeastSyntaxException $e ) {
			$this->expectNotToPerformAssertions();
			return;
		}

		$actual = $this->getTokensFromMinify( $code );
		$this->assertSame( $expected, $actual );
	}

	public static function provideStateMachineCases() {
		foreach ( JavaScriptMinifierTest::provideCases() as $key => $case ) {
			if (
				// Skip cases that contain intentional errors.
				!isset( $case[2] )
				// Skip cases explicitly marked as "Ignore Peast-AST difference"
				&& !isset( $case[3] )
			) {
				yield "provideCases $key" => [ $case[0] ];
			}
		}

		foreach ( JavaScriptMinifierTest::provideLineBreaker() as $key => $case ) {
			yield "provideLineBreaker $key" => [ $case[0] ];
		}
	}

	private function getTokensFromMinify( string $code ): array {
		$actual = [];
		JavaScriptMinifier::minifyInternal(
			$code,
			null,
			null,
			static function ( array $frame ) use ( &$actual ) {
				if ( $frame['type'] === 'TYPE_SEMICOLON' ) {
					return;
				}
				$actual[] = [
					'type' => $frame['type'],
					'token' => $frame['token'],
				];
			}
		);
		return $actual;
	}

	/**
	 * This renders the Peast AST into a list of JavaScriptMinifier tokens,
	 * so that we can compare them against getTokensFromMinify().
	 */
	private function getExpectedTokensFromPeast( string $code ): array {
		$ast = Peast::ES2020( $code )->parse();
		$expected = [];
		$genFnStack = [];

		$traverse = static function ( $node, $parent ) use ( &$traverse, &$expected, &$genFnStack ) {
			if ( !$node ) {
				return;
			}

			$isInGenFn = $genFnStack[ count( $genFnStack ) - 1 ] ?? false;

			$type = $node->getType();
			switch ( $type ) {
				case 'EmptyStatement':
				case 'Program':
					// Nothing to do, traverse the child nodes directly.
					break;
				case 'ArrayExpression':
					$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '[' ];
					foreach ( $node->getElements() as $i => $child ) {
						if ( $i !== 0 ) {
							$expected[] = [ 'type' => 'TYPE_COMMA', 'token' => ',' ];
						}
						$traverse( $child, $node );
					}
					$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ']' ];
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ArrowFunctionExpression':
					if ( $node->getAsync() ) {
						$expected[] = [ 'type' => 'TYPE_ASYNC', 'token' => 'async' ];
					}
					$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '(' ];
					foreach ( $node->getParams() as $i => $child ) {
						if ( $i !== 0 ) {
							$expected[] = [ 'type' => 'TYPE_COMMA', 'token' => ',' ];
						}
						$traverse( $child, $node );
					}
					$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ')' ];
					$expected[] = [ 'type' => 'TYPE_ARROW', 'token' => '=>' ];
					$traverse( $node->getBody(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'AwaitExpression':
					$expected[] = [ 'type' => 'TYPE_AWAIT', 'token' => 'await' ];
					$traverse( $node->getArgument(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'AssignmentExpression':
				case 'BinaryExpression':
				case 'LogicalExpression':
					// Examples:
					// * AssignmentExpression `x = y`
					// * BinaryExpression `x + y`
					// * LogicalExpression `x && y`
					//
					// The left and right are traversed and output on their own.
					// Output the operator (eg. + or =) after the "left" side.
					$traverse( $node->getLeft(), $node );
					$expect = ( $node->getOperator() === '+' || $node->getOperator() === '-' )
						? 'TYPE_ADD_OP'
						: 'TYPE_BIN_OP';
					$expected[] = [ 'type' => $expect, 'token' => $node->getOperator() ];
					$traverse( $node->getRight(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'BlockStatement':
				case 'ClassBody':
					$expected[] = [ 'type' => 'TYPE_BRACE_OPEN', 'token' => '{' ];
					foreach ( $node->getBody() as $child ) {
						$traverse( $child, $node );
					}
					$expected[] = [ 'type' => 'TYPE_BRACE_CLOSE', 'token' => '}' ];
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'BreakStatement':
					$expected[] = [ 'type' => 'TYPE_RETURN', 'token' => 'break' ];
					$traverse( $node->getLabel(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ContinueStatement':
					$expected[] = [ 'type' => 'TYPE_RETURN', 'token' => 'continue' ];
					$traverse( $node->getLabel(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ClassDeclaration':
				case 'ClassExpression':
					$expected[] = [ 'type' => 'TYPE_CLASS', 'token' => 'class' ];
					$traverse( $node->getId(), $node );
					if ( $node->getSuperClass() ) {
						$expected[] = [ 'type' => 'TYPE_LITERAL', 'token' => 'extends' ];
						$traverse( $node->getSuperClass(), $node );
					}
					$traverse( $node->getBody(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'CallExpression':
				case 'NewExpression':
					if ( $type === 'NewExpression' ) {
						$expected[] = [ 'type' => 'TYPE_UN_OP', 'token' => 'new' ];
					}
					// TODO: Handle $node->getOptional() to output foo?.() instead of foo()
					$traverse( $node->getCallee(), $node );
					$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '(' ];
					foreach ( $node->getArguments() as $i => $child ) {
						if ( $i !== 0 ) {
							$expected[] = [ 'type' => 'TYPE_COMMA', 'token' => ',' ];
						}
						$traverse( $child, $node );
					}
					$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ')' ];
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'CatchClause':
					$expected[] = [ 'type' => 'TYPE_IF', 'token' => 'catch' ];
					if ( $node->getParam() ) {
						$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '(' ];
						$traverse( $node->getParam(), $node );
						$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ')' ];
					}
					$traverse( $node->getBody(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ConditionalExpression':
					$traverse( $node->getTest(), $node );
					$expected[] = [ 'type' => 'TYPE_HOOK', 'token' => '?' ];
					$traverse( $node->getConsequent(), $node );
					$expected[] = [ 'type' => 'TYPE_COLON', 'token' => ':' ];
					$traverse( $node->getAlternate(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ExpressionStatement':
					$traverse( $node->getExpression(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ForStatement':
					$expected[] = [ 'type' => 'TYPE_IF', 'token' => 'for' ];
					$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '(' ];
					$traverse( $node->getInit(), $node );
					$traverse( $node->getTest(), $node );
					$traverse( $node->getUpdate(), $node );
					$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ')' ];
					$traverse( $node->getBody(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'FunctionDeclaration':
				case 'FunctionExpression':
					// Example
					// * FunctionDeclaration `function* foo ( arg ) { body }`
					// * FunctionExpression `function( arg ) { body }`

					// Skip `async`, `function`, and `*` if this is an object method (Property) or
					// class method (MethodDefinition). Methods render these in the parent node
					// before the method name instead.
					// Without this exemption, we'd generate the following invalid syntax:
					// * `foo function () {}` instead of `foo() {}`
					// * `*foo* () {}`        instead of `*foo() {}`
					// * `async foo async (`  instead of `async foo() {}`
					$isMethod = $parent && (
						( $parent->getType() === 'Property' && $parent->getMethod() )
						|| $parent->getType() === 'MethodDefinition'
					);

					if ( !$isMethod && $node->getAsync() ) {
						$expected[] = [ 'type' => 'TYPE_ASYNC', 'token' => 'async' ];
					}
					if ( !$isMethod ) {
						$expected[] = [ 'type' => 'TYPE_FUNC', 'token' => 'function' ];
					}
					if ( !$isMethod && $node->getGenerator() ) {
						$expected[] = [ 'type' => 'TYPE_SPECIAL', 'token' => '*' ];
					}
					$traverse( $node->getId(), $node );
					$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '(' ];
					foreach ( $node->getParams() as $i => $child ) {
						if ( $i !== 0 ) {
							$expected[] = [ 'type' => 'TYPE_COMMA', 'token' => ',' ];
						}
						$traverse( $child, $node );
					}
					$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ')' ];
					$genFnStack[] = $node->getGenerator();
					$traverse( $node->getBody(), $node );
					array_pop( $genFnStack );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'Identifier':
					$rawName = $node->getRawName();
					// In Peast, the decision of what an input token means is contextual
					// and reflected in which token type is picked.
					// In JavaScriptMinifier, tokens are mostly stateless and the
					// decisions are reflected in the model and state transitions only.
					// This means literals in "obj.if", "var async;", and "var x = async;"
					// are emitted as the type they could have potentially been,
					// instead of what they actually are.
					$nodeType = [
						'async' => 'TYPE_ASYNC',
						'class' => 'TYPE_CLASS',
						'delete' => 'TYPE_UN_OP',
						'else' => 'TYPE_DO',
						'function' => 'TYPE_FUNC',
						'if' => 'TYPE_IF',
						'instanceof' => 'TYPE_BIN_OP',
						'return' => 'TYPE_RETURN',
						'var' => 'TYPE_VAR',
						'yield' => $isInGenFn ? 'TYPE_RETURN' : null,
					][$rawName] ?? 'TYPE_LITERAL';
					$expected[] = [ 'type' => $nodeType, 'token' => $rawName ];
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'IfStatement':
					$expected[] = [ 'type' => 'TYPE_IF', 'token' => 'if' ];
					$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '(' ];
					$traverse( $node->getTest(), $node );
					$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ')' ];
					$traverse( $node->getConsequent(), $node );
					if ( $node->getAlternate() ) {
						$expected[] = [ 'type' => 'TYPE_DO', 'token' => 'else' ];
						$traverse( $node->getAlternate(), $node );
					}
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'LabeledStatement':
					$traverse( $node->getLabel(), $node );
					$expected[] = [ 'type' => 'TYPE_COLON', 'token' => ':' ];
					$traverse( $node->getBody(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'Literal':
				case 'RegExpLiteral':
					$expected[] = [ 'type' => 'TYPE_LITERAL', 'token' => (string)$node->getRaw() ];
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'MemberExpression':
					$traverse( $node->getObject(), $node );
					// TODO: Handle $node->getOptional() for `?.`
					$expected[] = [ 'type' => 'TYPE_DOT', 'token' => '.' ];
					$traverse( $node->getProperty(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'MethodDefinition':
					if ( $node->getStatic() ) {
						$expected[] = [ 'type' => 'TYPE_LITERAL', 'token' => 'static' ];
					}
					$value = $node->getValue();
					$kind = $node->getKind();
					if ( $kind === $node::KIND_GET || $kind === $node::KIND_SET ) {
						$expected[] = [ 'type' => 'TYPE_LITERAL', 'token' => $kind ];
					} else {
						if ( $value->getAsync() ) {
							$expected[] = [ 'type' => 'TYPE_ASYNC', 'token' => 'async' ];
						}
						if ( $value->getGenerator() ) {
							$expected[] = [ 'type' => 'TYPE_SPECIAL', 'token' => '*' ];
						}
					}
					if ( $node->getComputed() ) {
						$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '[' ];
						$traverse( $node->getKey(), $node );
						$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ']' ];
					} else {
						$traverse( $node->getKey(), $node );
					}
					$traverse( $value, $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ObjectExpression':
					$expected[] = [ 'type' => 'TYPE_BRACE_OPEN', 'token' => '{' ];
					foreach ( $node->getProperties() as $i => $child ) {
						if ( $i !== 0 ) {
							$expected[] = [ 'type' => 'TYPE_COMMA', 'token' => ',' ];
						}
						$traverse( $child, $node );
					}
					$expected[] = [ 'type' => 'TYPE_BRACE_CLOSE', 'token' => '}' ];
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'Property':
					// Examples as part of ObjectExpression "{ foo: value }"
					// * `foo: value`
					// * `get foo() {`
					// * `set foo() {`
					// * `async foo() {`
					// * `*foo() {`
					// * `foo() {`
					// * `[something]: value`
					// DEBUG: let a = { …, [21 * 2]: 'answer' }

					$kind = $node->getKind();
					$isAccessor = $kind === $node::KIND_GET || $kind === $node::KIND_SET;
					if ( $isAccessor ) {
						$expected[] = [ 'type' => 'TYPE_LITERAL', 'token' => $kind ];
					}
					$value = $node->getValue();
					if ( $value->getType() === 'FunctionExpression' && $value->getGenerator() ) {
						$expected[] = [ 'type' => 'TYPE_SPECIAL', 'token' => '*' ];
					}
					if ( $node->getMethod() && $value->getAsync() ) {
						$expected[] = [ 'type' => 'TYPE_ASYNC', 'token' => 'async' ];
					}
					if ( $node->getComputed() ) {
						$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '[' ];
						$traverse( $node->getKey(), $node );
						$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ']' ];
					} else {
						$traverse( $node->getKey(), $node );
					}
					if ( $node->getMethod() || $isAccessor ) {
						$traverse( $value, $node );
					} elseif ( !$node->getShorthand() ) {
						$expected[] = [ 'type' => 'TYPE_COLON', 'token' => ':' ];
						$traverse( $value, $node );
					}
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ParenthesizedExpression':
					$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '(' ];
					$traverse( $node->getExpression(), $node );
					$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ')' ];
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'SpreadElement':
					$expected[] = [ 'type' => 'TYPE_UN_OP', 'token' => '...' ];
					$traverse( $node->getArgument(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ReturnStatement':
					$expected[] = [ 'type' => 'TYPE_RETURN', 'token' => 'return' ];
					$traverse( $node->getArgument(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'SequenceExpression':
					foreach ( $node->getExpressions() as $i => $child ) {
						if ( $i !== 0 ) {
							$expected[] = [ 'type' => 'TYPE_COMMA', 'token' => ',' ];
						}
						$traverse( $child, $node );
					}
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'SwitchStatement':
					$expected[] = [ 'type' => 'TYPE_IF', 'token' => 'switch' ];
					$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '(' ];
					$traverse( $node->getDiscriminant(), $node );
					$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ')' ];
					$expected[] = [ 'type' => 'TYPE_BRACE_OPEN', 'token' => '{' ];
					foreach ( $node->getCases() as $child ) {
						$traverse( $child, $node );
					}
					$expected[] = [ 'type' => 'TYPE_BRACE_CLOSE', 'token' => '}' ];
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'SwitchCase':
					// Examples:
					// * `case 'test':`
					// * `default:`
					if ( $node->getTest() ) {
						$expected[] = [ 'type' => 'TYPE_DO', 'token' => 'case' ];
						$traverse( $node->getTest(), $node );
					} else {
						$expected[] = [ 'type' => 'TYPE_LITERAL', 'token' => 'default' ];
					}
					$expected[] = [ 'type' => 'TYPE_COLON', 'token' => ':' ];
					foreach ( $node->getConsequent() as $child ) {
						$traverse( $child, $node );
					}
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'TemplateElement':
					// Ignore, handled below in TemplateLiteral
					break;
				case 'TemplateLiteral':
					$literal = '`';
					foreach ( $node->getParts() as $part ) {
						if ( $part->getType() === 'TemplateElement' ) {
							$literal .= $part->getRawValue();
						} else {
							$literal .= '${';
							$expected[] = [ 'type' => 'TYPE_LITERAL', 'token' => $literal ];
							$literal = '';

							$traverse( $part, $node );
							$literal .= '}';
						}
					}
					$literal .= '`';
					$expected[] = [ 'type' => 'TYPE_LITERAL', 'token' => $literal ];
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ThrowStatement':
					$expected[] = [ 'type' => 'TYPE_RETURN', 'token' => 'throw' ];
					$traverse( $node->getArgument(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'TryStatement':
					$expected[] = [ 'type' => 'TYPE_DO', 'token' => 'try' ];
					$traverse( $node->getBlock(), $node );
					// An (optional) CatchClause is set as handler
					$traverse( $node->getHandler(), $node );
					if ( $node->getFinalizer() ) {
						$expected[] = [ 'type' => 'TYPE_DO', 'token' => 'finally' ];
						$traverse( $node->getFinalizer(), $node );
					}
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'ThisExpression':
					$expected[] = [ 'type' => 'TYPE_LITERAL', 'token' => 'this' ];
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'UpdateExpression':
				case 'UnaryExpression':
					$expect = $type === 'UpdateExpression'
						? 'TYPE_INCR_OP'
						: ( ( $node->getOperator() === '+' || $node->getOperator() === '-' )
							? 'TYPE_ADD_OP'
							: 'TYPE_UN_OP'
						);
					if ( $node->getPrefix() ) {
						$expected[] = [ 'type' => $expect, 'token' => $node->getOperator() ];
					}
					$traverse( $node->getArgument(), $node );
					if ( !$node->getPrefix() ) {
						$expected[] = [ 'type' => $expect, 'token' => $node->getOperator() ];
					}
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'VariableDeclaration':
					$expected[] = [ 'type' => 'TYPE_VAR', 'token' => $node->getKind() ];
					foreach ( $node->getDeclarations() as $i => $child ) {
						if ( $i !== 0 ) {
							$expected[] = [ 'type' => 'TYPE_COMMA', 'token' => ',' ];
						}
						$traverse( $child, $node );
					}
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'VariableDeclarator':
					$traverse( $node->getId(), $node );
					if ( $node->getInit() ) {
						$expected[] = [ 'type' => 'TYPE_BIN_OP', 'token' => '=' ];
						$traverse( $node->getInit(), $node );
					}
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'WhileStatement':
					$expected[] = [ 'type' => 'TYPE_IF', 'token' => 'while' ];
					$expected[] = [ 'type' => 'TYPE_PAREN_OPEN', 'token' => '(' ];
					$traverse( $node->getTest(), $node );
					$expected[] = [ 'type' => 'TYPE_PAREN_CLOSE', 'token' => ')' ];
					$traverse( $node->getBody(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				case 'YieldExpression':
					$expected[] = [ 'type' => 'TYPE_RETURN', 'token' => 'yield' ];
					if ( $node->getDelegate() ) {
						$expected[] = [ 'type' => 'TYPE_BIN_OP', 'token' => '*' ];
					}
					$traverse( $node->getArgument(), $node );
					return Traverser::DONT_TRAVERSE_CHILD_NODES;
				default:
					// To ease debugging, the default for unknown nodes is
					// to dump the Peast AST node into the PHPUnit output.
					$expected[] = [ 'type' => $node->getType(), 'token' => $node ];
					break;
			}
		};
		$ast->traverse(
			$traverse,
			[ 'passParentNode' => true ]
		);

		return $expected;
	}
}

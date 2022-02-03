/*!
 * See /tests/data/sourcemap/README.md for how to run this.
 */

const sourceMap = require( 'source-map' );
const fs = require( 'fs' );

async function main() {
	let status = 0;
	const originalFileName = process.argv[2];
	const mapFileName = originalFileName + '.map';
	const minifiedFileName = originalFileName.replace( '.js', '.min.js' );

	let mapData = fs.readFileSync( mapFileName, { encoding: 'utf8' } );
	if ( mapData.slice( 0, 3 ) === ')]}' ) {
		mapData = mapData.slice( 3 );
	}

	const originalSrc = fs.readFileSync( originalFileName, { encoding: 'utf8' } );
	const originalLines = originalSrc.split( '\n' );

	const minifiedSrc = fs.readFileSync( minifiedFileName, { encoding: 'utf8' } );
	const minifiedLines = minifiedSrc.split( '\n' );

	const consumer = await new sourceMap.SourceMapConsumer( mapData );

	for ( let minifiedRow = 0; minifiedRow < minifiedLines.length; minifiedRow++ ) {
		const minifiedLine = minifiedLines[minifiedRow];
		const re = /[a-zA-Z]+/g;
		let match;
		while ( ( match = re.exec( minifiedLine ) ) !== null ) {
			const minPos = {
				line: minifiedRow + 1,
				column: match.index,
			};
			const origPos = consumer.originalPositionFor( minPos );
			const originalMatch = originalLines[origPos.line - 1]
				.slice( origPos.column )
				.match( /^[a-zA-Z]+/ );
			const originalToken = originalMatch === null ? null : originalMatch[0];

			if ( originalToken !== match[0] ) {
				console.log( `ERROR: Token mismatch at origPos ` +
					`${origPos.line}:${origPos.column + 1} -> ` +
					`outPos ${minPos.line}:${minPos.column + 1}: ` +
					`${originalToken} != ${match[0]}` );
				status = 1;
			}
		}
	}

	if ( status === 0 ) {
		console.log( 'SUCCESS: source map verified.' );
	}

	return status;
}

main().then( function ( status ) {
	process.exit( status );
} );

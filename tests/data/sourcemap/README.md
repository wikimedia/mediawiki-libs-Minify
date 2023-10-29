The files in this directory are:

- file?.js: Test input
- file?.min.js: Expected minifier output
- file?.js.map: Expected map output

To update the expected output, run `make` in this directory, or run the commands listed in the Makefile.

## verifySourceMap.js

Automatically verify the correctness of a source map file using Mozilla's [source-map](https://github.com/mozilla/source-map) package.

```
npm install source-map@0.7.4
node tests/verifySourceMap.js tests/data/sourcemap/advanced.js
```

verifySourceMap.js should exit with status 0 if the map appears valid.

## End-to-end integration

Manually verify end-to-end correctness as seen by a web browser, or Node.js.

- `simple.html` and `simple.min.js`:
  A single file with fairly simple code, minified, and mapped to its source.
- `combine.html` and `combine.min.js`:
  Multiple files combined and minified, with a single sourcemap that maps
  each chunk back to the appropriate source file.
- `production.html` and `production.min.js`:
  Multiple files combined and minified, with bundled source code (thus using
  virtual path as file names, which don't rely on web access to the original
  source), and an index map that allows each minified chunk to be cacheable
  with its own source map.
  This is the most realistic and represents how Wikimedia uses this
  library in production.

To verify behaviour in a browser, open a `.html` file (no web server
necessary). Or, in Node.js, run:

```
node --enable-source-maps tests/data/sourcemap/production.min.js
```

Expected result in the console:

```
simple.js:3
		throw new Error( 'Boo' );
		      ^

Error: Boo
    at foo (simple.js:3:9)
    at bar (simple.js:9:15)
    at quux (simple.js:14:10)
    at main (simple.js:20:2)
```

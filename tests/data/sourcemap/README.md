
## verifySourceMap.js

The files in this directory are:

- file?.js: Test input
- file?.min.js: Expected minifier output
- file?.js.map: Expected map output

To update the expected output, use

```
php bin/minify js tests/data/sourcemap/file1.js > tests/data/sourcemap/file1.min.js
php bin/minify jsmap-web tests/data/sourcemap/file1.js > tests/data/sourcemap/file1.js.map
npm install source-map@0.7.3
node tests/verifySourceMap.js tests/data/sourcemap/file1.js
```

verifySourceMap.js should exit with status 0 if the map appears valid.

## simple.js

To generate the files derived from simple.js, use:

```
php bin/minify jsmap-raw tests/data/sourcemap/simple.js > tests/data/sourcemap/simple.min.js.map
php bin/minify js tests/data/sourcemap/simple.js > tests/data/sourcemap/simple.min.js
echo -e "//# sourceMappingURL=simple.min.js.map" >> tests/data/sourcemap/simple.min.js
```

Open `simple.html` in any browser, to verify it there.

To verify on Node.js, run:

```
node --enable-source-maps tests/data/sourcemap/simple.min.js
```

Expected:

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

## combine.js

To generate `combine.js`  and related files, run `php ./combine.php` in this directory.

Open `combine.html` in any browser, to verify it there.

To verify on Node.js, run:

```
node --enable-source-maps tests/data/sourcemap/combine.min.js
```

Expected:

```
Boilerplate for foo.js
Boilerplate for bar.js
Boilerplate for quux.js
Boilerplate for index.js
Error: Boo
    at foo (src/virtual/foo.js:3:9)
    at bar (src/virtual/bar.js:2:15)
    at quux (src/virtual/quux.js:3:10)
    at main (src/virtual/index.js:2:2)
```

## Index map tests

To generate `indexmap.min.js` and `indexmap.min.js.map`, run `php ./indexmap.php` in this directory.

Open `indexmap.html` in any browser.

Currently, there is no support for index maps in Node.js.

Expected:

```
Boilerplate for foo.js
Boilerplate for bar.js
Boilerplate for quux.js
Boilerplate for index.js
Uncaught Error: Boo
    foo foo.js:3
    bar bar.js:2
    quux quux.js:3
    main index.js:2
    <anonymous> index.js:5
```

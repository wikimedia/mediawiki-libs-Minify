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

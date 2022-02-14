function foo(num) {
	if (num > 1) {
		throw new Error( 'Boo' );
	}
	return num;
}

function bar(num) {
	return num + foo(num);
}

function quux() {
	if (bar(1)) {
		return bar(2);
	}
	return bar(3);
}

function main() {
	quux();
}

main();

function foo(num) {
	if (num > 1) {
		throw new Error( 'Boo' );
	}
	return num;
}

<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config-library.php';

// Fix PHP 8 build. https://phabricator.wikimedia.org/T325321
$cfg['plugins'] = [];

$cfg['directory_list'] = [
	'src/',
	'vendor/pear/',
];

$cfg['exclude_analysis_directory_list'] = [
	'vendor/',
];

// Ignore for now, contains trivial false positives as of Feb 2021.
$cfg['suppress_issue_types'][] = 'PhanTypePossiblyInvalidDimOffset';

return $cfg;

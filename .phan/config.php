<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config-library.php';

// Ignore for now, contains trivial false positives as of Feb 2021.
$cfg['suppress_issue_types'][] = 'PhanTypePossiblyInvalidDimOffset';

return $cfg;

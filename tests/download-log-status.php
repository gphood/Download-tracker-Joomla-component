<?php

declare(strict_types=1);

define('_JEXEC', 1);

require_once __DIR__ . '/../administrator/src/Service/DownloadLogStatus.php';

use GrantHood\Component\DownloadTracker\Administrator\Service\DownloadLogStatus;

$assertions = [
	DownloadLogStatus::markTestRequest('redirected', 'codex') === 'test_redirected',
	DownloadLogStatus::markTestRequest('downloaded', ' CODEX ') === 'test_downloaded',
	DownloadLogStatus::markTestRequest('denied', 'browser') === 'denied',
	DownloadLogStatus::markTestRequest('test_downloaded', 'codex') === 'test_downloaded',
	DownloadLogStatus::isTest('test_redirected'),
	!DownloadLogStatus::isTest('redirected'),
	DownloadLogStatus::isSuccessful('downloaded'),
	DownloadLogStatus::isSuccessful('redirected'),
	!DownloadLogStatus::isSuccessful('test_downloaded'),
	!DownloadLogStatus::isSuccessful('denied'),
	DownloadLogStatus::getBaseStatus('test_redirected') === 'redirected',
];

foreach ($assertions as $index => $passed) {
	if (!$passed) {
		fwrite(STDERR, 'DownloadLogStatus assertion ' . ($index + 1) . " failed.\n");
		exit(1);
	}
}

echo "DownloadLogStatus checks passed.\n";

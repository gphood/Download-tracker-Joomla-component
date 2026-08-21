<?php

declare(strict_types=1);

define('_JEXEC', 1);

require_once __DIR__ . '/../administrator/src/Service/EmailSecurityScanClassifier.php';

use GrantHood\Component\DownloadTracker\Administrator\Service\EmailSecurityScanClassifier;

$classifier = new EmailSecurityScanClassifier();

$base = [
	'item_id' => 2,
	'is_bot' => 0,
	'bot_reason' => null,
	'token_id' => 6,
	'token_status' => 'valid',
	'status' => 'downloaded',
	'ip_location_status' => 'success',
];

$customer = $base + [
	'id' => 128,
	'downloaded_at' => '2026-08-20 13:01:20',
	'ip_address' => '90.79.113.222',
	'asn' => 'AS3215',
	'asn_domain' => 'orange.com',
];

$microsoft = $base + [
	'id' => 129,
	'downloaded_at' => '2026-08-20 13:02:33',
	'ip_address' => '135.232.20.92',
	'asn' => 'AS8075',
	'asn_domain' => 'microsoft.com',
];

$assertSame = static function ($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
		exit(1);
	}
};

$assertSame(
	[129 => EmailSecurityScanClassifier::MICROSOFT_EMAIL_SECURITY_SCAN],
	$classifier->classify([$customer, $microsoft]),
	'The correlated Microsoft request should be classified.'
);

$assertSame([], $classifier->classify([$microsoft]), 'A Microsoft request without a customer peer must remain unclassified.');

$assertSame(
	[129 => EmailSecurityScanClassifier::MICROSOFT_EMAIL_SECURITY_SCAN],
	$classifier->classify([$microsoft, $customer]),
	'The request order must not affect classification.'
);

$lateMicrosoft = $microsoft;
$lateMicrosoft['downloaded_at'] = '2026-08-20 13:07:34';
$assertSame([], $classifier->classify([$customer, $lateMicrosoft]), 'Requests outside the five-minute window must remain unclassified.');

$sameIpMicrosoft = $microsoft;
$sameIpMicrosoft['ip_address'] = $customer['ip_address'];
$assertSame([], $classifier->classify([$customer, $sameIpMicrosoft]), 'Requests from the same address must remain unclassified.');

$differentTokenCustomer = $customer;
$differentTokenCustomer['token_id'] = 7;
$assertSame([], $classifier->classify([$differentTokenCustomer, $microsoft]), 'Requests using different tokens must remain unclassified.');

$unenrichedCustomer = $customer;
$unenrichedCustomer['ip_location_status'] = null;
$assertSame([], $classifier->classify([$unenrichedCustomer, $microsoft]), 'Both network locations must be enriched before classification.');

$differentItemMicrosoft = $microsoft;
$differentItemMicrosoft['item_id'] = 3;
$assertSame([], $classifier->classify([$customer, $differentItemMicrosoft]), 'Requests for different items must remain unclassified.');

$alreadyClassifiedMicrosoft = $microsoft;
$alreadyClassifiedMicrosoft['bot_reason'] = 'manual_review';
$assertSame([], $classifier->classify([$customer, $alreadyClassifiedMicrosoft]), 'Existing manual classifications must not be overwritten.');

$domainMicrosoft = $microsoft;
$domainMicrosoft['asn'] = 'AS99999';
$domainMicrosoft['asn_domain'] = 'security.microsoft.com';
$assertSame(
	[129 => EmailSecurityScanClassifier::MICROSOFT_EMAIL_SECURITY_SCAN],
	$classifier->classify([$customer, $domainMicrosoft]),
	'A Microsoft-owned subdomain should be recognised even if the ASN metadata differs.'
);

echo "Email security scan classifier tests passed.\n";

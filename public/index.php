<?php
require_once "../vendor/autoload.php";


$recipient = new Recipient();
$recipient->address1 = '11025 Westlake Dr';
$recipient->city = 'Charlotte';
$recipient->country_code = 'US';
$recipient->state_code = 'NC';
$recipient->zip = 28273;

$item = new Item();
$item->quantity = 2;
$item->variant_id = 7679;

$params = new ShippingRateParams();
$params->recipient = $recipient;
$params->items = [$item];

$service = new ShippingDataService(new FileCache());
print_r($service->get($params));

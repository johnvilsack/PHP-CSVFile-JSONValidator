<?php
// Sometimes MACs mess up the line endings in a CSV.  Locally set the INI setting here.
ini_set('auto_detect_line_endings', true);

include ('LISTS.php');
include ('class.ValidatePO.php');

$payload = new ValidatePO;
$payload->source = $_FILES['payload-0']['tmp_name'];
$payload->name= basename($_FILES['payload-0']['name']);

// Convert CSV payload to PHP Array
$payload->convertArray($RequiredColumns);

if ($payload->Error == 1) {
	$response->json($payload);
	exit;
}
//Return error if broken
if (count($payload->Data) <= 0) {
	$payload->FILES = $_FILES;
	$payload->Error = 1;
	$payload->Log['File'] = "No Response from Server";
	$response->json($payload);
	exit;
}

// Run validation against Response data
$payload->Lookup = doLookup();

// Run UPCs and EANs through numeric ringer
if(array_key_exists("UPC", $payload->Data[0])) {
	$payload->makeNumeric("UPC");
	$payload->checkBarcode("UPC");
}

if(array_key_exists("EAN", $payload->Data[0])) {
	$payload->makeNumeric("EAN");
	$payload->checkBarcode("EAN");
}

// Various fixes
$payload->LookupsAndCleanups($ProdClassMinQty);

// Create second array for second PO
$payload->makeVendorPO();

// Dehumanize quotations
$payload->slashFix();

// Dump objects used for manipulation
unset($payload->Lookup);

// Return JSON
$response->json($payload);

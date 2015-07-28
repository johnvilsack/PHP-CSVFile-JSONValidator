<?php

// List of product classes that have minimum quantities.
$ProdClassMinQty = array (
		'FOO' => 2,
		'BAR' => 2,
	);

ksort($ProdClassMinQty);

// List of fields required for a PO
$RequiredColumns = array(
	'Scrubbed',
	'But',
	'Unimportant'
	);

$RequiredColumns = array_fill_keys($RequiredColumns, '');
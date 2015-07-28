<?php
class ValidatePO {

	public function __contruct() {}
	public $Log = array();
	public $Error = 0;

	// Take posted file and convert to array
	public function convertArray($RequiredColumns) {
			$data = array_map("str_getcsv", file($this->source,FILE_SKIP_EMPTY_LINES));
			$header = array_map("trim", array_shift($data));

			// Grab headers and make sure they are valid
			foreach ($header as $k=>$v) {
				if (array_key_exists($v, $RequiredColumns)) {
					unset($RequiredColumns[$v]);
				}  else {
					// Unknown columns are OK.  We want to warn, but not stop.
					$this->logError($v, "Unknown Column ", $v, 0);
				}
			}

			// Dump contents left over, these columns should exist before continuing.
			if (count($RequiredColumns) >= 1) {
				foreach ($RequiredColumns as $k=>$v) {
					// A missing column is a disaster.  Flag and the system will stop processing.
					$this->logError($k, "Missing Column ", $k, 1);
				}
			}

			foreach ($data as $i=>$row) {
				$data[$i] = array_combine($header, array_map("trim",$row));
			}
			$this->Data = $data;
			unset($data);
	}

	// Error logging
	public function logError($id, $field, $value, $type = 0) {
		$this->Log[$field][$id] = $value; 
		$this->LogFile[] = array("Type" => $field, "ItemID" => $id, "Value" => $value); 
		if ($type == 1) {
			$this->Error = 1;
		}
	}

	// Force a field to be numeric only (Barcodes)
	public function makeNumeric($field) {
		foreach($this->Data as $k=>$v) {
			if(!is_numeric($v[$field] ) || $v[$field]  != "") {
				$this->Data[$k][$field]  = preg_replace("/[^0-9]/", "", "$v[$field]");
			}
		}
	}

	// Remove human quotations, apply them programmatically for less errors
	public function SlashFix() {
		foreach($this->Data as $k=>$v) {
			$this->Data[$k]['Focus Word'] = str_replace('"', '', $v['Focus Word']);
			if (preg_match('/\s/',$v['Focus Word'])){
				$this->Data[$k]['Focus Word'] = '""'.$this->Data[$k]['Focus Word'].'""';
			} 
		}
	}

	/* Validate Barcode.
		We can't REALLY validate a barcode, because some of our vendors still make up
		fake barcodes.  This is a pain in the ass, but the best we can do is check the field
		for size.  

		Maybe in a future version, we'll be able to see what a valid barcode is and break 
		when the check digit is wrong.
	*/
	public function checkBarcode($field) {
		foreach($this->Data as $k=>$v) {
			$length = strlen($v[$field]);

			// Break if empty
			if ($length == 0) { continue; }

			// UPCs are 11 or 12 digit
			if($field == "UPC" && ($length != "11" && $length != "12" )) {
				$this->logError($v['Inventory Item ID'], "Bad " . $field, $v[$field], 1);
			}

			if($field == "EAN" && ($length != "12" && $length != "13" )) {
				$this->logError($v['Inventory Item ID'], "Bad " . $field, $v[$field], 1);
			}
		}
	}

	// Parse a second array of only Vendor data.  Low cost and makes parsing easier on the front end
	public function makeVendorPO() {
		foreach($this->Data as $k=>$v) {
			$this->VendorPO[$k]['InventoryID'] = $v['Inventory Item ID'];
			$this->VendorPO[$k]['VendorID'] = $v['VendorID'];
			$this->VendorPO[$k]['Vendor Item ID'] = $v['Vendor Item ID'];

		}
	}

	/* Lookups and Cleanups
		Instead of endlessly looping, I dumped a bunch of checks on each field into one function, as commented below.
		This isn't as pretty as it COULD be, but it IS FASTER.
	*/
	public function LookupsAndCleanups($ProdClassMinQty) {
		foreach($this->Data as $k=>$v) {

			// If Brand doesn't exist in Brand lookup, error it, but don't prevent downloads
			if(!in_array($v[Brand], $this->Lookup[BRANDS])) {
				$this->logError($v['Inventory Item ID'], "New or Unknown Brand", $v['Brand']);
			}

			// If Product Class does not exist, error out and prevent download
			if(!in_array($v['Product Class'], $this->Lookup[PRODUCT_CLASS_ID])) {
				$this->logError($v['Inventory Item ID'], "Unknown Product Class", $v['Product Class'], 1);
			}

			// If VendorID does not exist, error out and prevent download
			if(!in_array($v['VendorID'], $this->Lookup[VENDORS])) {
				$this->logError($v['Inventory Item ID'], "Unknown VendorID", $v['VendorID'], 1);
			}

			// Gender must follow the below guidelines, or error out.
			switch($v['Gender']) {
				case "Men's":
					break;
				case "Women's":
					break;
				case "Kid's":
					break;
				case "Boy's":
					break;
				case "Girl's":
					break;
				case "":
					break;
				default:
					$this->logError($v['Inventory Item ID'], "Unknown Gender", $v['Gender'], 1);
				break;
			}

			/* Minimum Quantity issuance.
				Refactored into an array that can be found in list-minqty.php.  This allows for easy editing as well as viewing on its own. */
			if (isset($ProdClassMinQty[$v['Product Class']])) {
				$this->Data[$k]['Min Qty'] = $ProdClassMinQty[$v['Product Class']];
			}

			// ITEM_DESC field is max 120. Prevent download on error.
			if (strlen($v['Inventory Item Name']) >= 121) {
				$errorMsg = '(' . strlen($v['Inventory Item Name']) . ') ' . $v['Inventory Item Name']; 
				$this->logError($v['Inventory Item ID'], "Long Description Too Long", $errorMsg, 1);
			}

			// ITEM_ID is max 16. Prevent download on error.
			if (strlen($v['Inventory Item ID']) >= 17) {
				$errorMsg = '(' . strlen($v['Inventory Item ID']) . ') ' . $v['Inventory Item ID']; 
				$this->logError($v['Inventory Item ID'], "Item ID Too Long", $errorMsg, 1);
			}

			// Format all price fields that are known.
			$this->Data[$k]['MSRP']  = preg_replace("/[^0-9.]/", "", $v['MSRP']);
			$this->Data[$k]['Selling Price (PC1)']  = preg_replace("/[^0-9.]/", "", $v['Selling Price (PC1)']);
			$this->Data[$k]['Discount Price (PC2)']  = preg_replace("/[^0-9.]/", "", $v['Discount Price (PC2)']);
			$this->Data[$k]['Cost']  = preg_replace("/[^0-9.]/", "", $v['Cost']);
			$this->Data[$k]['LastCost']  = preg_replace("/[^0-9.]/", "", $v['LastCost']);
			$this->Data[$k]['Wholesale']  = preg_replace("/[^0-9.]/", "", $v['Wholesale']);
			$this->Data[$k]['Price Code (PC13)']  = preg_replace("/[^0-9.]/", "", $v['Price Code (PC13)']);

			// Assume Drop Ship should be N
			$this->Data[$k]['Drop Ship']  = "N";

			// Assume Bin Location is always H
			$this->Data[$k]['Bin Loc']  = "H";
		}
	}
}

/* Query Response for lookup tables.
	This is outside the ValidatePO class because I didn't want to kludge a class extension due to Flourish.  I should refactor this into 
	pure PDO later. 
	
	QUERIES HAVE BEEN ALTERED TO PROTECT THE INNOCENT 
*/
function doLookup() {

	$connect = new ConnectDB();
	$db = $connect->Product_Database();

	// Product Classes
	$ProdClasses = $db->query("SELECT PRODUCT_CLASS_ID FROM PRODUCT_CLASSES_TABLE WITH(NOLOCK)");
	$ProdClasses = $ProdClasses->fetchAllRows();

	foreach ($ProdClasses as $k=>$v) {
		$Lookup['PRODUCT_CLASS_ID'][] = $v['PRODUCT_CLASS_ID'];
	}

	// Brand Names
	$Brands = $db->query("SELECT DISTINCT(BRAND) AS BRANDS FROM INVENTORY_TABLE WITH(NOLOCK);");
	$Brands = $Brands->fetchAllRows();

	foreach ($Brands as $k=>$v) {
		$Lookup['BRANDS'][] = $v['BRANDS'];
	}

	// Vendor IDs
	$Vendors = $db->query("SELECT VENDOR_ITEM_ID AS VENDORS FROM VENDOR_TABLE WITH(NOLOCK);");
	$Vendors = $Vendors->fetchAllRows();

	foreach ($Vendors as $k=>$v) {
		$Lookup['VENDORS'][] = $v['VENDORS'];
	}

	// Destroy actual lookups (Everything is in a different array)
	unset($Vendors);
	unset($Brands);
	unset($ProdClasses);

	return $Lookup;
}
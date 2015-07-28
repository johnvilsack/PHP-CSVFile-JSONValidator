<?php 

include('LISTS.php');

echo "<ul>";
foreach($ProdClassMinQty as $k=>$v) {
	echo "<li>$k : $v</li>";
}
echo "</ul>";
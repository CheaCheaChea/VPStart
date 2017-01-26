<?php
include "./utils/tools.php";

$dataRequest = $_POST;
$tblName = $dataRequest['data'];
$condition = $dataRequest['con'];

$queryType = $dataRequest['type'];

$devName = $dataRequest['devName'];
$name = $dataRequest['name'];

$chartType = $dataRequest['chartType'];


if(strtolower($queryType) == strtolower(Helper::DEVICE)) {
 $result = queryRecordDevice( $tblName, $condition);
} else if($queryType == 'queryAll') {
	 $result = queryPowEveReport($devName,$name);
} else if( $queryType == 'queryPowerChart') {
	
	 $result = queryPowerChart($devName,$name,$chartType);
}
else {
 $result = queryRecord( $tblName, $condition);
}



echo json_encode($result);

?>
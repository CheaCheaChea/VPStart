<?php
include "./utils/tools.php";

$dataRequest = $_POST;
$tblName = $dataRequest['name'];
$data = $dataRequest['data'];
$result = insertRecord( $tblName, $data);

echo json_encode($result);

?>
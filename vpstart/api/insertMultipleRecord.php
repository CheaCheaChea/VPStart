<?php
include "./utils/tools.php";

$dataRequest = $_POST;
$data = $dataRequest['data'];
$result = doInsertMultieRecord($data);

echo json_encode($result);

?>
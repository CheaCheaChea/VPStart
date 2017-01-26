<?php

include "api/utils/tools.php";

$data = "85510316991
SR MPT168
DRC001
NP-418935
Power
16:07:34 03/01/17
0,2621,0,0,2621,0,0,0,0,0,0,0,0";

require_once 'dbconfig.php';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function format_date($timedate)
{
    $date_time = explode(" ", $timedate);
    $date_only = explode("/", $date_time[1]);
    $datetime  = $date_only[2] . "/" . $date_only[1] . "/" . $date_only[0] . " " . $date_time[0];
    return $datetime;
}

function number_datetime($num_date, $num_time)
{
    $datetime  = jdtogregorian($num_date + 2415019);
    $date_time = explode("/", $datetime);
    if ($num_time != 0) {
        $tmp     = $num_time * 24;
        $nHour   = intval($tmp);
        $nMinute = intval(($tmp - $nHour) * 60);
        $nSecond = intval(((($tmp - $nHour) * 60) - $nMinute) * 60);
        return $date_time[2] . "/" . $date_time[0] . "/" . $date_time[1] . " " . $nHour . ":" . $nMinute . ":" . $nSecond;
    } else {
        return $date_time[2] . "/" . $date_time[0] . "/" . $date_time[1];
    }
}

function drc001_report($data)
{
    $result  = array();
    $temp    = explode("\n", $data);
    $header  = $temp[0] . "," . $temp[1] . "," . $temp[2] . "," . $temp[3] . "," . $temp[4];
    $temp[5] = hexdec($temp[5]);
    
    for ($x = 7; $x < count($temp); $x++) {
        $temp_ = explode(",", $temp[$x]);
        $value = $temp[5] - ($x - 7);
        $value = $value . "," . number_datetime($temp_[0], 0) . "," . $temp_[1] . "," . number_datetime($temp_[3], $temp_[2]) . "," . $temp_[4] . "," . $temp_[5];
        $value = $header . "," . $value;
        
        $record = explode(",", $value);
        $obj    = array(
            Helper::VP_START_ID => $record[0],
            Helper::NAME => $record[1],
            Helper::DEVICE => $record[2],
            Helper::SERIAL_SWITCH => $record[3],
            Helper::DATA_TYPE => $record[4],
            Helper::CURRENT_INDEX => $record[5],
            Helper::CURRENT_DATE => $record[6],
            Helper::TOTAL_KWH => $record[7],
            Helper::PEAK_TIME => $record[8],
            Helper::PEAK_KW => $record[9],
            Helper::POWER_FACTOR => $record[10]
        );
        array_push($result, $obj);
    }
    
    return $result;
}

function drc001_event($data, $conn)
{
    $result  = array();
    $temp    = explode("\n", $data);
    $header  = $temp[0] . "," . $temp[1] . "," . $temp[2] . "," . $temp[3] . "," . $temp[4];
    $temp[5] = hexdec($temp[5]);
    
    for ($x = 7; $x < count($temp); $x++) {
        $temp_ = explode(",", $temp[$x]);
        $value = $temp[5] - ($x - 7);
        
        if (count($temp_) > 8) {
            $sqlcode    = "SELECT event FROM codeevents WHERE code='$temp_[8]'";
            $resultcode = $conn->query($sqlcode);
            if ($resultcode->num_rows > 0) {
                while ($row = $resultcode->fetch_assoc()) {
                    $temp_[8] = $row["event"];
                }
            }
            if (count($temp_) == 10) {
                $temp_[8] = sprintf($temp_[8], $temp_[9]);
            } else if (count($temp_) == 11) {
                $temp_[8] = sprintf($temp_[8], $temp_[9], $temp_[10]);
            } else if (count($temp_) == 12) {
                $temp_[8] = sprintf($temp_[8], $temp_[9], $temp_[10], $temp_[11]);
            } else if (count($temp_) == 13) {
                $temp_[8] = sprintf($temp_[8], $temp_[9], $temp_[10], $temp_[11], $temp_[12]);
            } else if (count($temp_) == 14) {
                $temp_[8] = sprintf($temp_[8], $temp_[9], $temp_[10], $temp_[11], $temp_[12], $temp_[13]);
            }
        }
        
        $value = $header . "," . $value . "," . number_datetime($temp_[2], $temp_[0]) . "," . $temp_[1] . "," . $temp_[3] . "," . $temp_[8];
        if (strpos($value, 'DSP packets lost') === false) {
            $record = explode(",", $value);
            $obj    = array(
                Helper::VP_START_ID => $record[0],
                Helper::NAME => $record[1],
                Helper::DEVICE => $record[2],
                Helper::SERIAL_SWITCH => $record[3],
                Helper::DATA_TYPE => $record[4],
                Helper::CURRENT_INDEX => $record[5],
                Helper::DATE_TIME => $record[6],
                Helper::MILLI_SECOND => $record[7],
                Helper::SOURCE => $record[8],
                Helper::EVENT_TEXT => $record[9]
            );
            array_push($result, $obj);
        }
    }
    
    return $result;
}

function drc001_power($data)
{
    $result = "";
    $temp   = explode("\n", $data);
    $header = $temp[0] . "," . $temp[1] . "," . $temp[2] . "," . $temp[3] . "," . $temp[4] . "," . format_date($temp[5]);
    $value  = explode(",", $temp[6]);
    $result = $header;
    for ($x = 0; $x < count($value); $x++) {
        if ($result != "") {
            $result = $result . ",";
        }
        if ($x === 0) {
            if ($value[$x] == "0") {
                $value[$x] = "OFF";
            } else if ($value[$x] == "1") {
                $value[$x] = "ON";
            } else if ($value[$x] == "2") {
                $value[$x] = "NO";
            }
            $result = $result . $value[$x];
        } else {
            $result = $result . $value[$x];
        }
    }
    
    $record = explode(",", $result);
    $obj    = array(
        Helper::VP_START_ID => $record[0],
        Helper::NAME => $record[1],
        Helper::DEVICE => $record[2],
        Helper::SERIAL_SWITCH => $record[3],
        Helper::DATA_TYPE => $record[4],
        Helper::DATE_TIME => $record[5],
        Helper::SWITHC_GEAR_STATE => $record[6],
        Helper::CURRENT_A => intval(intval($record[7])/100),
        Helper::CURRENT_B => intval(intval($record[8])/100),
        Helper::CURRENT_C => intval(intval($record[9])/100),
        Helper::CURRENT_GROUND => intval(intval($record[10])/100),
        Helper::VOLTAGE_AB => intval(intval($record[11])/10),
        Helper::VOLTAGE_BC => intval(intval($record[12])/10),
        Helper::VOLTAGE_CA => intval(intval($record[13])/10),
        Helper::APPARENT_POWER => intval(intval($record[14])/1000),
        Helper::REAL_POWER => intval(intval($record[15])/1000),
        Helper::REACTIVE_POWER => intval(intval($record[16])/1000),
        Helper::POWER_FACTOR => intval($record[17])/100,
        Helper::FREQUENCY => intval(intval($record[18])/1000)
    );
    
    return $obj;
}

$temp = explode("\n", $data);

$value = array();
//Data type that valid for table Report: WEEKLY2,MONTHLY2,DAILY2
$reportValidDataType = array(
    strtolower(Helper::WEEKLY2),
    strtolower(Helper::MONTHLY2),
    strtolower(Helper::DAILY2)
);
$dataType = strtolower($temp[4]);

if ($dataType == Helper::POWER) {
    $value = drc001_power($data);
    $value = array($value);
} 
else if (in_array($dataType, $reportValidDataType, true)) {
    $value = drc001_report($data);
} 
else if ($dataType == Helper::EVENT4) {
    $value = drc001_event($data, $conn);
}

//doInsertMultieRecord($value);

$str = json_encode($value);
echo $str;
//$sql   = "INSERT INTO test_error (name, text) VALUES ('$temp[0]','$str')";
//$conn->query($sql);

$conn->close();

?>
<?php

function getConnection()
{
    $servername = "localhost";
    //sandbox
   /* $username   = "user_vpstartgrid";
    $password   = "*P{;vm-ZKOG@";
    $dbname     = "drc001_vpstartgrid";*/
    
    //local
    $username = "root";
    $password = "root";
    $dbname = "vpstar";
    
    // Create connection
    return new mysqli($servername, $username, $password, $dbname);
    
}

class Helper
{
    const VP_START_ID = 'VPStartID';
    const DATE_TIME = 'DateTime';
    const SWITHC_GEAR_STATE = 'switchgearState';
    const CURRENT_A = 'currentA';
    const CURRENT_B = 'currentB';
    const CURRENT_C = 'currentC';
    const CURRENT_GROUND = 'currentGround';
    const VOLTAGE_AB = 'voltageAB';
    const VOLTAGE_BC = 'voltageBC';
    const VOLTAGE_CA = 'voltageCA';
    const APPARENT_POWER = 'apparentPower';
    const REAL_POWER = 'realPower';
    const REACTIVE_POWER = 'reactivePower';
    const POWER_FACTOR = 'powerFactor';
    const FREQUENCY = 'frequency';
    
    const SERIAL_SWITCH = 'SerialSwitch';
    const DATA_TYPE = 'DataType';
    const DEVICE = 'Device';
    const NAME = 'Name';
    
    const POWER = 'power';
    const EVENT4 = 'event4';
    
    const SUCCESS = 'sucess';
    const MESSAGE = 'msg';
    const RECORDS = 'records';
    const CURRENT_INDEX = 'currentIndex';
    
    const MILLI_SECOND = 'millisecond';
    const SOURCE = 'source';
    const EVENT_TEXT = 'eventText';
    
    const DAILY2 = 'DAILY2';
    const WEEKLY2 = 'WEEKLY2';
    const MONTHLY2 = 'MONTHLY2';
    const CURRENT_DATE = 'currentDate';
    const TOTAL_KWH = 'TotalkWh';
    const PEAK_TIME = 'PeakTime';
    const PEAK_KW = 'PeakkW';
    
    
}



function doInsertMultieRecord($records)
{
    
    if (!empty($records)) {
        $errorsList          = array();
        $vpstarIDs           = array();
        $devices             = array();
        $eventRecords        = array();
        $reportRecords       = array();
        $existingList        = array();
        //Data type that valid for table Report: WEEKLY2,MONTHLY2,DAILY2
        $reportValidDataType = array(strtolower(Helper::WEEKLY2), strtolower(Helper::MONTHLY2), strtolower(Helper::DAILY2));
        
        foreach ($records as &$record) {
            //phone
            $vpstarId = $record[Helper::VP_START_ID];
            //check data type: ['Power','DAILY2','WEEKLY2','MONTHLY2','EVENT4']
            $dataType = strtolower($record[Helper::DATA_TYPE]);
            
            if (!in_array($vpstarId, $existingList, true)) {
                array_push($vpstarIDs, $vpstarId);
                array_push($existingList, $vpstarId);
            }
            //device key
            $deviceKey = $record[Helper::NAME] . $record[Helper::DEVICE] . $record[Helper::SERIAL_SWITCH] . $record[Helper::DATA_TYPE];
            if (!in_array($deviceKey, $existingList, true)) {
                $devRecord = array(
                    "name" => $record[Helper::NAME],
                    "type" => $record[Helper::DATA_TYPE],
                    "device" => $record[Helper::DEVICE],
                    "serialSwitch" => $record[Helper::SERIAL_SWITCH],
                    "createdDate" => date('Y-m-d G:i:s'),
                    "lastModifiedDate" => date('Y-m-d G:i:s'),
                     "VPstartID" => $record[Helper::VP_START_ID]
                );
                array_push($devices, $devRecord);
                array_push($existingList, $deviceKey);
                
            }
            
            //event 
            if ($dataType == strtolower(Helper::EVENT4)) {
                $eventKey = $vpstarId . $record[Helper::CURRENT_INDEX];
                if (!in_array($eventKey, $existingList, true)) {
                    array_push($existingList, $eventKey);
                    array_push($eventRecords, $record[Helper::VP_START_ID]);
                }
            }
            
            //report
            if (in_array($dataType, $reportValidDataType, true)) {
                $reportKey = $vpstarId . $record[Helper::CURRENT_INDEX] . $dataType;
                if (!in_array($reportKey, $existingList, true)) {
                    array_push($existingList, $reportKey);
                    array_push($reportRecords, $record[Helper::VP_START_ID]);
                }
            }
            
        }
        
        //check for insert into Phone
        if (!empty($vpstarIDs)) {
            $phoneRecords = queryRecord("phone", " where number IN('" . implode("','", $vpstarIDs) . "')");
            if ($phoneRecords[Helper::SUCCESS]) {
                $exisingPhones    = $phoneRecords[Helper::RECORDS];
                $recordsForInsert = array();
                if (!empty($exisingPhones)) {
                    $tmpExisingPhone = array();
                    foreach ($exisingPhones as &$record) {
                        array_push($tmpExisingPhone, $record['number']);
                    }
                    
                    foreach ($vpstarIDs as &$record) {
                        if (!in_array($record, $tmpExisingPhone, true)) {
                            array_push($recordsForInsert, $record);
                        }
                    }
                } else {
                    $recordsForInsert = $vpstarIDs;
                }
                foreach ($recordsForInsert as &$record) {
                    $result = insertRecord('phone', array(
                        "userId" => '1',
                        "number" => $record
                    ));
                    if (!$result[Helper::SUCCESS]) {
                        array_push($errorsList, 'inser phone====' . $result[Helper::MESSAGE]);
                    }
                }
            } else {
                array_push($errorsList, 'error query phone====' . $phoneRecords[Helper::MESSAGE]);
            }
            
            
        }
        
        $existingDevices = array();
        //check for insert device
        if (!empty($devices)) {
            $deviceKeys = array();
            foreach ($devices as &$record) {
                array_push($deviceKeys, $record['device']);
            }
            $deviceRecords = queryRecord("device", " where device IN('" . implode("','", $deviceKeys) . "')");
            if ($deviceRecords[Helper::SUCCESS]) {
                $exisingDevice    = $deviceRecords[Helper::RECORDS];
                $recordsForInsert = array();
                
                if (!empty($exisingDevice)) {
                    $tmpExisingPhone = array();
                    foreach ($exisingDevice as &$record) {
                        
                        $key                   = $record['name'] . $record['device'] . $record['serialSwitch'] . strtolower($record['type']);
                        $recordId              = $record['Id'];
                        $existingDevices[$key] = $recordId;
                        array_push($tmpExisingPhone, $key);
                    }
                    
                    foreach ($devices as &$record) {
                        $key = $record['name'] . $record['device'] . $record['serialSwitch'] . strtolower($record['type']);
                        if (!in_array($key, $tmpExisingPhone, true)) {
                            array_push($recordsForInsert, $record);
                        }
                    }
                    
                    
                } else {
                    $recordsForInsert = $devices;
                }
                
                foreach ($recordsForInsert as &$record) {
                    $result = insertRecord('device', $record);
                    
                    if ($result[Helper::SUCCESS]) {
                        $key                   = $record['name'] . $record['device'] . $record['serialSwitch'] . strtolower($record['type']);
                        $existingDevices[$key] = $result['recordId'];
                        
                    } else {
                        array_push($errorsList, 'insert device====' . $result[Helper::MESSAGE]);
                    }
                }
                
            } else {
                array_push($errorsList, 'empty device====' . $deviceRecords[Helper::MESSAGE]);
            }
            
        }
        
        //check for table event
        $existingEvent = array();
        if (!empty($eventRecords)) {
            $exEventRecords = queryRecord("event", " where VPstartID IN('" . implode("','", $eventRecords) . "')");
            if ($exEventRecords[Helper::SUCCESS]) {
                $exisingEvent = $exEventRecords[Helper::RECORDS];
                if (!empty($exisingEvent)) {
                    foreach ($exisingEvent as &$record) {
                        $keyEvent = (string) $record['VPstartID'] . (string) $record['currentIndex'];
                        array_push($existingEvent, $keyEvent);
                        
                    }
                }
            }
            
            
        }
        
        //check for table report
        $existingReport = array();
        if (!empty($reportRecords)) {
            $exReportRecords = queryRecord("report", " where VPstartID IN('" . implode("','", $reportRecords) . "')");
            if ($exReportRecords[Helper::SUCCESS]) {
                $exisingReport = $exReportRecords[Helper::RECORDS];
                if (!empty($exisingReport)) {
                    foreach ($exisingReport as &$record) {
                        $dataType = (string) $record['VPstartID'] . (string) $record['currentIndex'] . (string) $record['deviceId'];
                        array_push($existingReport, $dataType);
                        
                    }
                }
            }
            
            
        }
        
        foreach ($records as &$record) {
            //check data type: ['Power','DAILY2','WEEKLY2','MONTHLY2','EVENT4']
            $dataType = strtolower($record[Helper::DATA_TYPE]);
            
            //get device Id
            $key              = $record[Helper::NAME] . $record[Helper::DEVICE] . $record[Helper::SERIAL_SWITCH] . $dataType;
            $deviceId         = $existingDevices[$key];
            $vpstarId         = $record[Helper::VP_START_ID];
            $createDate       = date('Y-m-d G:i:s');
            $lastModifiedDate = date('Y-m-d G:i:s');
            $sql              = '';
            if ($dataType == strtolower(Helper::POWER)) {
                $sql = "INSERT INTO power (createdDate,lastModifiedDate,deviceId,switchgearState,currentA,currentB,currentC,currentGround,voltageAB,voltageBC, voltageCA,apparentPower,realPower,reactivePower,powerFactor,frequency,VPstartID,datetime) ";
                $sql .= "VALUES('" . $createDate . "'";
                $sql .= ",'" . $lastModifiedDate . "'";
                $sql .= ",'" . $deviceId . "'";
                $sql .= ",'" . $record[Helper::SWITHC_GEAR_STATE] . "'";
                $sql .= "," . $record[Helper::CURRENT_A];
                $sql .= "," . $record[Helper::CURRENT_B];
                $sql .= "," . $record[Helper::CURRENT_C];
                $sql .= "," . $record[Helper::CURRENT_GROUND];
                $sql .= "," . $record[Helper::VOLTAGE_AB];
                $sql .= "," . $record[Helper::VOLTAGE_BC];
                $sql .= "," . $record[Helper::VOLTAGE_CA];
                $sql .= "," . $record[Helper::APPARENT_POWER];
                $sql .= "," . $record[Helper::REAL_POWER];
                $sql .= "," . $record[Helper::REACTIVE_POWER];
                $sql .= "," . $record[Helper::POWER_FACTOR];
                $sql .= "," . $record[Helper::FREQUENCY];
                $sql .= ",'" . $record[Helper::VP_START_ID] . "'";
                $sql .= ",'" . $record[Helper::DATE_TIME] . "'";
                $sql .= ")";
            }
            if ($dataType == strtolower(Helper::EVENT4)) {
                $keyEvent = (string) $vpstarId . (string) $record[Helper::CURRENT_INDEX];
                if (!in_array($keyEvent, $existingEvent)) {
                    $sql = "INSERT INTO event (createdDate,lastModifiedDate,dateTime,deviceId,currentIndex,millisecond,source,VPstartID,eventText) ";
                    $sql .= "VALUES('" . $createDate . "'";
                    $sql .= ",'" . $lastModifiedDate . "'";
                    $sql .= ",'" . $record[Helper::DATE_TIME] . "'";
                    $sql .= ",'" . $deviceId . "'";
                    $sql .= "," . $record[Helper::CURRENT_INDEX];
                    $sql .= ",'" . $record[Helper::MILLI_SECOND] . "'";
                    $sql .= ",'" . $record[Helper::SOURCE] . "'";
                    $sql .= ",'" . $vpstarId . "'";
                    $sql .= ",'" . $record[Helper::EVENT_TEXT] . "'";
                    $sql .= ")";
                    array_push($existingEvent, $keyEvent);
                }
            }
            if (in_array($dataType, $reportValidDataType, true)) {
                $keyReport = (string) $vpstarId . (string) $record[Helper::CURRENT_INDEX] . (string) $deviceId;
                if (!in_array($keyReport, $existingReport)) {
                    $sql = "INSERT INTO report (createdDate,lastModifiedDate,peakTime,deviceId,currentDate,currentIndex,totalkWh,VPstartID,powerFactor,peakkW) ";
                    $sql .= "VALUES('" . $createDate . "'";
                    $sql .= ",'" . $lastModifiedDate . "'";
                    $sql .= ",'" . $record[Helper::PEAK_TIME] . "'";
                    $sql .= ",'" . $deviceId . "'";
                    $sql .= ",'" . $record[Helper::CURRENT_DATE] . "'";
                    $sql .= "," . $record[Helper::CURRENT_INDEX];
                    $sql .= ",'" . $record[Helper::TOTAL_KWH] . "'";
                    $sql .= ",'" . $vpstarId . "'";
                    $sql .= ",'" . $record[Helper::POWER_FACTOR] . "'";
                    $sql .= ",'" . $record[Helper::PEAK_KW] . "'";
                    $sql .= ")";
                    array_push($existingReport, $keyReport);
                }
                
            }
            
            if ($sql != '') {
                $result = insertRecordBySQL($sql);
                if (!$result[Helper::SUCCESS]) {
                    array_push($errorsList, 'insert ' . $dataType . $result[Helper::MESSAGE]);
                }
                
            }
            
        }
        
        return array(
            Helper::SUCCESS => true,
            "errors" => $errorsList
        );
        
    } else {
        
        return array(
            Helper::SUCCESS => false,
            Helper::MESSAGE => "Empty list."
        );
    }
    
    
}

function queryRecordDevice($tblName, $condition){
	$result = queryRecord($tblName, $condition);
	
	if($result[Helper::SUCCESS]) {
	 	$curRecords = $result[Helper::RECORDS];
	 	$tmpRecord = array();
	 	$records = array();
        if (!empty($curRecords)) {
        	foreach ($curRecords as &$record) {
            	$dataType = (string) $record['name'].(string) $record['deviceId'];
            	if (!in_array($dataType, $tmpRecord)) {
            		array_push($tmpRecord, $dataType);
            		array_push($records, $record);
            	}
             }
             $result[Helper::RECORDS] = $records;
        }
		
	}
	
	return $result;
	
}

function queryPowerChart($devName, $name, $condition){

	$result  = array();
	$devices = queryRecord("device", " where name='".$name ."' AND device='".$devName ."' AND type ='Power' ");

	if($devices[Helper::SUCCESS]) {
		$result[Helper::SUCCESS] = true;
		$curRecords = $devices[Helper::RECORDS];
		foreach ($curRecords as &$record) {
			$deviceId = $record['Id'];
			$queryResult = array();
            
            if( $condition == 'daily') {
                $curentDate = date('Y-m-d');
                $queryResult = queryRecord('power', " where deviceId ='".$deviceId ."' AND date(createdDate) = date('". $curentDate ."')");

                if($queryResult[Helper::SUCCESS]) {
                    $powRecords = $queryResult[Helper::RECORDS];
                    $records = array();
                    $recordsPower = array();
                    foreach ($powRecords as &$powrecord) {
                        $key = $powrecord['createdDate'];//str_replace($curentDate,"",$powrecord['createdDate']);
                        $records[$key] = $powrecord['apparentPower'];
                        array_push($recordsPower, $powrecord);
                    }

                    $result['label'] = array_keys($records);
                    $result['data'] = array_values($records);
                    $result['power'] = $recordsPower;


                }else {
                    $result[Helper::MESSAGE] = $queryResult[Helper::MESSAGE];
                }


            } else if($condition == 'monthly') {

                $numberOfDays = cal_days_in_month(CAL_GREGORIAN,date("m"),date("Y"));
                $startDate = strtotime(date("Y-m-01"));
                $endDate = strtotime(date("Y-m-").$numberOfDays);
                $date = $startDate;
                $records = array();
                $recordsPower = array();
                while($date < $endDate) { 
                   $endWeek = date('Y-m-d',strtotime("+6 day", $date));
                   $startWeek = date('Y-m-d',$date);
                   if( $endWeek > date('Y-m-d',$endDate)) $endWeek = date('Y-m-d',$endDate);
                   $queryResult = queryRecordBySQL(" SELECT SUM(apparentPower) AS TotalPower FROM power where deviceId ='".$deviceId ."' AND date(createdDate) BETWEEN date('". $startWeek ."') AND date('". $endWeek ."')");

                   if($queryResult[Helper::SUCCESS]) {
                        $powRecords = $queryResult[Helper::RECORDS];
                        foreach ($powRecords as &$powrecord) {
                            $records[$startWeek ."-" . $endWeek] = is_null($powrecord['TotalPower']) ? 0 : $powrecord['TotalPower'];
                            array_push($recordsPower, $powrecord);
                        }

                    }else {
                        $result[Helper::MESSAGE] = $queryResult[Helper::MESSAGE];
                    }

                    $date = strtotime("+7 day", $date);
                } 
                $result['label'] = array_keys($records);
                $result['data'] = array_values($records);
                $result['power'] = $recordsPower;

            } else {
                 $startDate = date('Y-01-01');
                 $endDate = date('Y-12-31');

                $queryResult = queryRecordBySQL(" SELECT SUM(apparentPower) AS TotalPower,MONTH(createdDate) as month, YEAR(createdDate) as year FROM power where deviceId ='".$deviceId ."' AND date(createdDate) BETWEEN date('". $startDate ."') AND date('". $endDate ."') GROUP BY YEAR(createdDate), MONTH(createdDate)
");
                 if($queryResult[Helper::SUCCESS]) {
                    $powRecords = $queryResult[Helper::RECORDS];
                    $records = array();
                    $recordsPower = array();
                    foreach ($powRecords as &$powrecord) {
                        $key = "01-".$powrecord['month'] ."-". $powrecord['year'] ;//str_replace($curentDate,"",$powrecord['createdDate']);
                        $records[$key] = is_null($powrecord['TotalPower']) ? 0 : $powrecord['TotalPower'];
                        array_push($recordsPower, $powrecord);
                    }

                    $result['label'] = array_keys($records);
                    $result['data'] = array_values($records);
                    $result['power'] = $recordsPower;


                }else {
                    $result[Helper::MESSAGE] = $queryResult[Helper::MESSAGE];
                }



            }

			

		}


	}else {
		$result = $devices;
	}

	return $result;

}


function queryPowEveReport($devName, $name){
	$result  = array();
	$devices = queryRecord("device", " where name='".$name ."' AND device='".$devName ."' AND type In ('Power','DAILY2','Event4' )");
	
	if($devices[Helper::SUCCESS]) {
		$result[Helper::SUCCESS] = true;
		$curRecords = $devices[Helper::RECORDS];
		$errorsList = array();
		foreach ($curRecords as &$record) {
			$dataType = strtolower($record['type']);
			$deviceId = $record['Id'];
			$tblName = $dataType == strtolower(Helper::POWER) ? 'power' :($dataType == strtolower(Helper::EVENT4) ? 'event' :'report');
			$curentDate = date('Y-m-d');
            //$queryResult = queryRecord($tblName, " where deviceId ='".$deviceId  ."' AND date(createdDate) = date('". $curentDate ."') order by createdDate DESC");

			$queryResult = queryRecord($tblName, " where deviceId ='".$deviceId  ."' order by createdDate DESC");
			
			if($queryResult[Helper::SUCCESS]) {
				$result[$tblName] = $queryResult[Helper::RECORDS];
			} else {
				array_push($errorsList,  $queryResult[Helper::MESSAGE]);
				
			}
			//array_push($result,  "$dataType===". $dataType ."$tblName===" .$tblName ."$deviceId==".$deviceId);
		}
		
	} else {
		$result = $devices;
		
	}
	
	return $result;
	
}


function queryRecordBySQL($sql)
{
    
    // Create connection
    $conn = getConnection();
    
    // Check connection
    if ($conn->connect_error) {
        $conn->close();
        return array(
            Helper::SUCCESS => false,
            Helper::MESSAGE => "Connection failed: "
        );
        
    } else {
        
        $result = $conn->query($sql);
        if ($result) {
            
            $records = array();
            if ($result->num_rows > 0) {
                // output data of each row
                while ($record = $result->fetch_assoc()) {
                    array_push($records, $record);
                }
            }
            $conn->close();
            return array(
                Helper::SUCCESS => true,
                "records" => $records
            );
            
        } else {
            $errorMsg = $conn->error;
            $conn->close();
            return array(
                Helper::SUCCESS => false,
                Helper::MESSAGE => "Error: " . $sql . "<br>" . $errorMsg
            );
            
        }
        
        
        
        
    }
    
}


function queryRecord($tblName, $condition)
{
    
    // Create connection
    $conn = getConnection();
    
    // Check connection
    if ($conn->connect_error) {
        $conn->close();
        return array(
            Helper::SUCCESS => false,
            Helper::MESSAGE => "Connection failed: "
        );
        
    } else {
        $sql    = "SELECT * FROM " . $tblName . " " . $condition;
        $result = $conn->query($sql);
        if ($result) {
            
            $records = array();
            if ($result->num_rows > 0) {
                // output data of each row
                while ($record = $result->fetch_assoc()) {
                    array_push($records, $record);
                }
            }
            $conn->close();
            return array(
                Helper::SUCCESS => true,
                "records" => $records
            );
            
        } else {
            $errorMsg = $conn->error;
            $conn->close();
            return array(
                Helper::SUCCESS => false,
                Helper::MESSAGE => "Error: " . $sql . "<br>" . $errorMsg
            );
            
        }
        
        
        
        
    }
    
}

function insertRecordBySQL($sql)
{
    
    // Create connection
    $conn = getConnection();
    
    // Check connection
    if ($conn->connect_error) {
        $conn->close();
        return array(
            Helper::SUCCESS => false,
            Helper::MESSAGE => "Connection failed: "
        );
        
    } else {
        
        if ($conn->query($sql) === TRUE) {
            $last_id = $conn->insert_id;
            $conn->close();
            return array(
                Helper::SUCCESS => true,
                "recordId" => $last_id
            );
        } else {
            $errorMsg = $conn->error;
            $conn->close();
            return array(
                Helper::SUCCESS => false,
                Helper::MESSAGE => "Error: " . $sql . "<br>" . $errorMsg
            );
            
        }
        
    }
    
}


function insertRecord($tblName, $data)
{
    
    // Create connection
    $conn = getConnection();
    
    // Check connection
    if ($conn->connect_error) {
        $conn->close();
        return array(
            Helper::SUCCESS => false,
            Helper::MESSAGE => "Connection failed: "
        );
        
    } else {
        $key   = array_keys($data);
        $value = array_values($data);
        $sql   = "INSERT INTO " . $tblName . "( " . implode(',', $key) . ") VALUES('" . implode("','", $value) . "')";
        
        if ($conn->query($sql) === TRUE) {
            $last_id = $conn->insert_id;
            $conn->close();
            return array(
                Helper::SUCCESS => true,
                "recordId" => $last_id
            );
        } else {
            $errorMsg = $conn->error;
            $conn->close();
            return array(
                Helper::SUCCESS => false,
                Helper::MESSAGE => "Error: " . $sql . "<br>" . $errorMsg
            );
            
        }
        
    }
    
}

?>
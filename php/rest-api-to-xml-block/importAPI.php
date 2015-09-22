<?php
function to_xml(SimpleXMLElement $object, array $data) {   
    foreach ($data as $key => $value) {   
		if (is_int($key) === true) $key = 'item';
        if (is_array($value)) {   
            $new_object = $object->addChild($key);
            to_xml($new_object, $value);
        } else {   
            $object->addChild($key, $value);
        }   
    }   
}   

function createCascadeCareer($cascade,$auth,$blockPath,$blockName,$theData,$dataType) {
	$path = array('path' => $blockPath.'/'.$blockName, 'siteName' => 'www.svcc.edu');
	$id = array('path' => $path, 'type' => 'block');
	$readParams = array( 'authentication' => $auth, 'identifier' => $id );	
	if($dataType == 'xml') {
		$xmlData = $theData;
		$xmlData = str_replace(' xmlns:xsd="http://www.w3.org/2001/XMLSchema"','',
							str_replace(' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"','',
							str_replace(' xmlns="http://www.careerinfonet.org/webservices/occupationData"','',
							str_replace(' xmlns="http://www.careerinfonet.org/WebServices/OccWages_WebService/OccWagesService.asmx"','',
							str_replace('<?xml version="1.0" encoding="utf-8"?>','',$xmlData)
						  ))));
	} elseif ($dataType == 'json') {
		$jsonData = json_decode($theData,true);
		$xml = new SimpleXMLElement('<root/>');
		to_xml($xml, $jsonData);
		$xmlData = $xml->asXML();
	}

	$blockRead = $cascade->read($readParams);
	if ( $blockRead->readReturn->success != 'true' ) {
		$parentFolderPath = $blockPath;
		$name = $blockName;
		$template_params = array(
			'authentication' => $auth,
				'asset' => array(
					'xmlBlock' => array(
						'xml' => $xmlData,
						'parentFolderPath' => $parentFolderPath,
						'name' => $name,
						'siteName' => 'www.svcc.edu'
					)
				)
			);
		$cascade->create($template_params);
		$result = $cascade->__getLastResponse();
		getResultAction($result,$blockPath,' created.');
	} else {
		$asset = $blockRead->readReturn->asset->xmlBlock;
		$asset->xml = $xmlData;
		$editParams = array('authentication' => $auth, 'asset' => array('xmlBlock' => $asset));
		$cascade->edit($editParams);
		$result = $cascade->__getLastResponse();
		getResultAction($result,$asset->name,' updated.');
	}
}

$restURL = 'https://api.data.gov/ed/collegescorecard/v1/schools.json?id=148672,147703&_fields=school.name,school.city,school.state,id&api_key={{secret}}';
$scJSON = file_get_contents($restURL);
createCascadeCareer($cascade,$auth, '/-blocks/CareerOneStop', 'scorecard', $scJSON,'json');
		$jsonData = json_decode($scJSON);


$zipCode = '99999';
$apiKey = '[[secret}}';


$query = 'SELECT DISTINCT socCode FROM cipPrograms LEFT JOIN cipClusters ON cipPrograms.cipCode = cipClusters.cipCode WHERE NOT ISNULL(socCode)';
$result = mysqli_query($mysqli,$query);
while ($row = mysqli_fetch_assoc($result)) {
	$socCode = str_replace('-','',$row['socCode']);
	$soapURL = 'http://www.careerinfonet.org/webservices/occwages_webservice/occwagesservice.asmx/GetWagesByZip?userID='.$apiKey.'&soccode='.$socCode.'&zip='.$zipCode;
	$wagesXML = file_get_contents($soapURL);
	createCascadeCareer($cascade,$auth, '/-blocks/CareerOneStop/Wage', $row['socCode'], $wagesXML,'xml');
	
	$soapURL = 'http://www.careerinfonet.org/webservices/occupationdata/occupationdata.asmx/getOccT2Data?userID='.$apiKey.'&onetcode='.$row['socCode'].'.00';
	$profileXML = file_get_contents($soapURL);
	createCascadeCareer($cascade,$auth, '/-blocks/CareerOneStop/Profile', $row['socCode'], $profileXML,'xml');
}

?>
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

function createCascadeCareer($cascade,$auth,$blockPath,$blockName,$siteName,$theData,$dataType) {
	$path = array('path' => $blockPath.'/'.$blockName, 'siteName' => $siteName);
	$id = array('path' => $path, 'type' => 'block');
	$readParams = array( 'authentication' => $auth, 'identifier' => $id );	
	if($dataType == 'xml') {
		$xmlData = $theData;
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
createCascadeCareer($cascade,$auth, '/-blocks/CareerOneStop', 'scorecard', $siteName, $scJSON,'json');
		$jsonData = json_decode($scJSON);


$zipCode = '99999';
$apiKey = '{{secret}}';
$socCodes = array('17-2011','41-9041');

foreach($socCodes as $value) {
	$socCode = str_replace('-','',$value);
	$soapURL = 'http://www.careerinfonet.org/webservices/occwages_webservice/occwagesservice.asmx/GetWagesByZip?userID='.$apiKey.'&soccode='.$socCode.'&zip='.$zipCode;
	$wagesXML = file_get_contents($soapURL);
	createCascadeCareer($cascade,$auth, '/-blocks/CareerOneStop/Wage', $value, $siteName, $wagesXML,'xml');
	
	$soapURL = 'http://www.careerinfonet.org/webservices/occupationdata/occupationdata.asmx/getOccT2Data?userID='.$apiKey.'&onetcode='.$value.'.00';
	$profileXML = file_get_contents($soapURL);
	createCascadeCareer($cascade,$auth, '/-blocks/CareerOneStop/Profile', $row['socCode'], $siteName, $profileXML,'xml');
}

?>

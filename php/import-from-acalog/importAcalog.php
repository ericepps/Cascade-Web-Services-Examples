<?php
// Set variables
// Acalog info
$apiURL = ''; // prefix for your acalog API url, http://___.apis.acalog.com
$apiKey = ''; // your acalog API key

// Cascade Server Authentication
$username = ""; 
$password = ""; 
$cascadePath = "";

// parent path for blocks
$parentFolderPath = '';

//-----------------------------------------------------------------------------------
function getResult($result,$fullName) {
	if (!isSuccess($result)) {
		echo "\nError";
		echo "\n".extractMessage($result)."\n";
	} else {
		echo $fullName." - XML Block Updated\n";
	}
}
function isSuccess($text) {
	return substr($text, strpos($text, "<success>")+9,4)=="true";
}
function extractMessage($text) {
	return substr($text, strpos($text, "<message>")+9,strpos($text, "</message>")-(strpos($text, "<message>")+9));
}
function updateCascade($cascade,$auth,$parentFolderPath,$blockName,$siteName,$xmlData) {
	$path = array('path' => $parentFolderPath.'/'.$blockName, 'siteName' => $siteName);
	$id = array('path' => $path, 'type' => 'block');
	$readParams = array( 'authentication' => $auth, 'identifier' => $id );				

	$blockRead = $cascade->read($readParams);
	if ( $blockRead->readReturn->success != 'true' ) {
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

$cascade = new SoapClient($cascadePath . "/ws/services/AssetOperationService?wsdl",array('trace' => 1));
$auth = array('username' => $username, 'password' => $password);


// get catalogs, select current (published, not archived)
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, 'http://' . $apiURL . '.apis.acalog.com/v1/content?key=' . $apiKey . '&format=xml&method=getCatalogs'); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$catalogReturn = curl_exec($ch);
$catalogXML = simplexml_load_string(str_replace(' xmlns="http://acalog.com/catalog/1.0"','',$catalogReturn));
curl_close($ch); 
$selCatalog = $catalogXML->xpath('//catalog[state/published = "Yes" and state/archived = "No"]/@id');
updateCascade($cascade,$auth,$parentFolderPath,'Acalog Catalogs',$siteName,$catalogReturn);
$selCatalog = str_replace('acalog-catalog-','',$selCatalog[0][0][0]);

// ****************************************
// **             Programs               **
// ****************************************
// get list of programs from acalog API
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, 'http://' . $apiURL . '.apis.acalog.com/v1/search/programs?key=' . $apiKey . '&format=xml&method=listing&catalog=' . $selCatalog . '&options[limit]=0&options[group]=type'); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$programReturn = curl_exec($ch); 
curl_close($ch); 
updateCascade($cascade,$auth,$parentFolderPath,'Acalog Programs',$siteName,$programReturn);

// get IDs from list of programs
$programXML = simplexml_load_string($programReturn);
$programXMLIDs = $programXML->xpath('//result');
$programIDs = '';
while(list( , $node) = each($programXMLIDs)) {
	$programIDs .= '&ids[]='.$node->id[0];
	$programID = $node->id[0];
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, 'http://' . $apiURL . '.apis.acalog.com/v1/content?key=' . $apiKey . '&format=xml&method=getItems&type=programs&ids[]=' . $programID . '&catalog=' . $selCatalog); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$programIndDetailReturn = curl_exec($ch); 
	curl_close($ch); 
	updateCascade($cascade,$auth,$parentFolderPath.'/Programs',$programID,str_replace(' xmlns="http://acalog.com/catalog/1.0"','',$programIndDetailReturn));
}
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, 'http://' . $apiURL . '.apis.acalog.com/v1/content?key=' . $apiKey . '&format=xml&method=getItems&type=programs' . $programIDs . '&catalog=' . $selCatalog . '&options[full]=1'); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$programDetailReturn = curl_exec($ch); 
curl_close($ch); 
updateCascade($cascade,$auth,$parentFolderPath,'Acalog Programs Detail',$siteName,str_replace(' xmlns="http://acalog.com/catalog/1.0"','',$programDetailReturn));


// ****************************************
// **              Courses               **
// ****************************************
// get list of courses from acalog API
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, 'http://' . $apiURL . '.apis.acalog.com/v1/search/courses?key=' . $apiKey . '&format=xml&method=listing&catalog=' . $selCatalog . '&options[sort]=alpha&options[group]=type'); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$courseReturn = curl_exec($ch); 
curl_close($ch); 
updateCascade($cascade,$auth,$parentFolderPath,'Acalog Courses',$siteName,$courseReturn);

// get IDs from list of courses
$courseXML = simplexml_load_string($courseReturn);
$courseXMLIDs = $courseXML->xpath('//result');
$courseIDs = '';
while(list( , $node) = each($courseXMLIDs)) {
	$courseIDs .= '&ids[]='.$node->id[0];
	// TODO: add individual blocks per course
}
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, 'http://' . $apiURL . '.apis.acalog.com/v1/content?key=' . $apiKey . '&format=xml&method=getItems&type=courses' . $courseIDs . '&catalog=' . $selCatalog . '&options[full]=1'); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$courseDetailReturn = curl_exec($ch); 
curl_close($ch); 
updateCascade($cascade,$auth,$parentFolderPath,'Acalog Courses Detail',$siteName,str_replace(' xmlns="http://acalog.com/catalog/1.0"','',$courseDetailReturn));
?> 

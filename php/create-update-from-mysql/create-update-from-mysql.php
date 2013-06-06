<?php
$cascade = new SoapClient($cascadePath . "/ws/services/AssetOperationService?wsdl",array('trace' => 1));
$auth = array('username' => $username, 'password' => $password);

$mySQLServer = 'mysql.server.host';
$mySQLUsername = 'theuser';
$mySQLPassword = 'supersecret';
$mySQLSchema = 'schemaname';
$link = mysql_connect($mySQLServer, $mySQLUsername, $mySQLPassword) or die('Could not connect: ' . mysql_error());
mysql_set_charset ('UTF-8', $link);
mysql_select_db($mySQLSchema) or die('Could not select database');

$cascadeDir =  '/cascade/folder';
$siteName = 'siteInCascade';

$query = "SELECT rowText, rowHTML, rowCheckBox, rowImage FROM theTable";
$result = mysql_query($query);
$sport = '';
$schoolYear = '';

//looping through the mySQL rows
while ($row = mysql_fetch_assoc($result)) {
	$row['rowHTML'] = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $row['rowHTML']);
	$title = 'The Page Title';
	$name = 'the-system-name';
	
	$ddArray = array();
	$ddArray[] = array ('type' => 'text', 'identifier' => 'text-field', 'text' => $row['rowText']);
	$ddArray[] = array ('type' => 'text', 'identifier' => 'wysiwyg-field', 'text' =>  $row['rowHTML']);
	if ($row['rowCheckBox'] != 0) $ddArray[] = array('type' => 'text', 'identifier' => 'checkbox-field', 'text' => '::CONTENT-XML-CHECKBOX::Yes');
	
	if($row['rowImage'] != '') {
		// check to see if photo exists
		$folderImgName = '/images/';
		$photoFileName = $row['rowImage'];
		
		$path = array('path' => $cascadeDir.'/'.$folderImgName.'/'.$photoFileName, 'siteName' => $siteName);
		$id = array('path' => $path, 'type' => 'file');
		$readParams = array( 'authentication' => $auth, 'identifier' => $id );				
		$fileRead = $cascade->read($readParams);
		// if it doesn't exist, create it
		if ( $fileRead ->readReturn->success != 'true' ) {
			$theImageID = uploadActionPhoto($auth, $cascade, $cascadeDir.'/'.$folderName, $siteName, $photoFileName);
		} else {
			$theImageID = $folderRead->readReturn->asset->file->id;
		}
		$ddArray[] = array('type' => "asset",
				 'identifier' => "image",
				 'assetType' => "file",
				 'fileId' => $theImageID);
	}

	updateNewsPage($auth, $cascade, $cascadeDir, $siteName, $title, $name, $ddArray);
}

function uploadActionPhoto ($auth, $cascade, $cascadeDir, $siteName, $photoFileName) {
	$cascadeDir = str_replace('//','/',$cascadeDir);
	$metadata = array(
		'displayName' => $photoFileName);
	$asset = array( 'file' => array (
				'siteName' => $siteName,
				'parentFolderPath' => $cascadeDir,
				'name' => $photoFileName,
		'metadata' => $metadata,
				'data' => file_get_contents('http://website.com/path/to/the/file/'.$photoFileName)));
	$template_params = array(
		'authentication' => $auth,
		'asset' => $asset);
	$cascade->create($template_params);
	$result = $cascade->__getLastResponse();
	getResult($result,$cascadeDir.'/'.$photoFileName,'File');
	if (isSuccess($result)) $theImageID = extractID($result);

	return $theImageID;
}

function updateNewsPage($auth, $cascade, $cascadeDir, $siteName, $title, $name, $dataNodes) {
	$contentTypeId = '32digitidnumberofcontenttype';
	$structuredData = array(    
		'definitionPath' => "/Cascade/Data Definition",    
		'structuredDataNodes' => array('structuredDataNode' => $dataNodes)
	);
	$parentFolderPath = $cascadeDir;
	$metadata = array(
			'displayName' => $title,
			'title' => $title);
	$template_params = array(
		'authentication' => $auth,
			'asset' => array(
				'page' => array(
					'contentTypeId' => $contentTypeId,
					'siteName' => $siteName,
					'structuredData' => $structuredData,
					'parentFolderPath' => $parentFolderPath,
					'name' => $name,
					'metadata' => $metadata
				)
			)
		);

	$path = array('path' => $cascadeDir, 'siteName' => $siteName);
	$id = array('path' => $path, 'type' => 'page');
	$readParams = array( 'authentication' => $auth, 'identifier' => $id );				
	$fileRead = $cascade->read($readParams);
	// if it doesn't exist, create it; otherwise, update it
	if ( $fileRead ->readReturn->success != 'true' ) {
		$cascade->create($template_params);
	} else {
		$cascade->edit($template_params);
	}
	$result = $cascade->__getLastResponse();
	getResult($result,$cascadeDir.'/'.$name,'Page');
}

function getResult($result,$fullName) {
	if (!isSuccess($result)) {
		echo "\nError";
		echo "\n".extractMessage($result)."\n";
	} else {
		echo $fullName." Added ".date("H:i:s",time())."\n";
	}
}
function getResultAction($result,$fullName,$action) {
	if (!isSuccess($result))
	{
		echo "\nError";
		echo "\n".extractMessage($result)."\n";
	}	
	else
	{
		echo $fullName." - ".$action." ".date("H:i:s",time())."\n";
	}
}
function isSuccess($text) {
	return substr($text, strpos($text, "<success>")+9,4)=="true";
}
function extractMessage($text) {
	return substr($text, strpos($text, "<message>")+9,strpos($text, "</message>")-(strpos($text, "<message>")+9));
}
function extractID($text) {
	return substr($text, strpos($text, "<createdAssetId>")+16,strpos($text, "</createdAssetId>")-(strpos($text, "<createdAssetId>")+16));
}

?>
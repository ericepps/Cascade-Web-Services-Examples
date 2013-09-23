<?php
date_default_timezone_set('America/Chicago');
function getResult($result,$fullName) {
	if (!isSuccess($result)) {
		echo "<br/>Error";
		echo "<br/>".extractMessage($result)."<br/>";
	} else {
		echo $fullName." Added ".date("H:i:s",time())."<br/>";
	}
}
function getResultAction($result,$fullName,$action) {
	if (!isSuccess($result))
	{
		echo "<br/>Error";
		echo "<br/>".extractMessage($result)."<br/>";
	}	
	else
	{
		echo $fullName." - ".$action." ".date("H:i:s",time())."<br/>";
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

$username = ""; // username with appropriate permissions
$password = ""; // super secret password
$cascadePath = ""; // Cascade Server URL
$cascadeDir = "/community/athletics"; // base URL in Cascade
$siteName = ""; // Cascade site
$cascade = new SoapClient($cascadePath . "/ws/services/AssetOperationService?wsdl",array('trace' => 1));
$auth = array('username' => $username, 'password' => $password);

function updateFacPage($auth, $cascade, $cascadeDir, $siteName, $num, $fieldHead, $fieldValue) {
	$dataNodes = array();
	for ($c=0; $c < $num; $c++) {
		if(strtolower($fieldHead[$c]) == 'schoolyear') {
			$schoolYear = trim($fieldValue[$c]);
		} elseif(strtolower(trim($fieldHead[$c])) == 'sport') {
			$theSport = trim(strtolower($fieldValue[$c]));
		} elseif (strtolower($fieldHead[$c]) == 'startdate') {
			$startDate = strtotime(trim(str_replace('Tues.','Tue.',$fieldValue[$c])));
			$dataNodes[] = array ('type' => 'text', 'identifier' => $fieldHead[$c], 'text' => date('m-d-Y',$startDate));
		} elseif (strtolower($fieldHead[$c]) == 'preseason' && trim($fieldValue[$c]) != '') {
			$dataNodes[] = array ('type' => 'text', 'identifier' => $fieldHead[$c], 'text' => '::CONTENT-XML-CHECKBOX::Yes');
		} else {
			if(strtolower($fieldHead[$c]) == 'opponent') {
				$opponent = trim($fieldValue[$c]);
			}
			$dataNodes[] = array ('type' => 'text', 'identifier' => $fieldHead[$c], 'text' => $fieldValue[$c]);
		}
	}
	switch ($theSport) {
		case 'baseball': $theSportDisplay = 'Baseball';break;
		case 'basketball-men': $theSportDisplay = "Men's Basketball";break;
		case 'basketball-women': $theSportDisplay = "Women's Basketball";break;
		case 'crosscountry': $theSportDisplay = 'Cross-Country';break;
		case 'golf': $theSportDisplay = 'Golf';break;
		case 'softball': $theSportDisplay = 'Softball';break;
		case 'tennis-men': $theSportDisplay = "Men's Tennis";break;
		case 'tennis-women': $theSportDisplay = "Women's Tennis";break;
		case 'volleyball': $theSportDisplay = 'Volleyball';break;
		case '': $theSportDisplay = 'Athletics';break;
	}
	if ($startDate != '' && $opponent != '') {
		$nameOpp = str_replace(',','',str_replace('"','',str_replace('&','',str_replace('*','',str_replace('(','',str_replace(')','',str_replace('--','-',str_replace('---','-',str_replace('/','-',str_replace(' ','-',$opponent))))))))));
		$nameDate = date('Y-m-d',$startDate);
		$name = $nameDate.'_'.$nameOpp;
		$displayDate = date('n-j-Y',$startDate);

		$cascadeDir = $cascadeDir.'/'.$theSport.'/schedules';
		
		$path = array('path' => str_replace('//','/',$cascadeDir.'/'.$schoolYear), 'siteName' => $siteName);
		$id = array('path' => $path, 'type' => 'folder');
		$readParams = array( 'authentication' => $auth, 'identifier' => $id );				
		$folderRead = $cascade->read($readParams);
		if ( $folderRead->readReturn->success != 'true' ) {
			createYrFolder($auth, $cascade, $cascadeDir, $siteName, $schoolYear);
			createYrIndexPage($auth, $cascade, $cascadeDir, $siteName, $theSport, $theSportDisplay, $schoolYear);
		}

		$cascadeDir = $cascadeDir.'/'.$schoolYear;
			
		$contentTypeId = 'f313571f6c3b961000605a287ae48b8c';
		$wfParams = array('workflowName' => 'General Create-Edit', 'workflowDefinitionId' => 'a929b146d87de41100c314a4a8e17087', 'workflowComments' => 'Edited via Web Services');
		$structuredData = array(    
			'definitionPath' => "/Athletics/Schedule",    
			'structuredDataNodes' => array('structuredDataNode' => $dataNodes)
		);
		$parentFolderPath = $cascadeDir;
		$metadata = array(
				'displayName' => $displayDate . ' - ' . $opponent,
				'title' => $displayDate . ' - ' . $opponent);
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
		
		$cascade->create($template_params);
		$result = $cascade->__getLastResponse();
		getResult($result,$cascadeDir.'/'.$name,'Page');
	}
}

if ($_POST['isSubmitted'] == 'yes') {
	function removeFunkyStuff($val) {
		$val = preg_replace('/[^a-zA-Z0-9.()\/\'"*#,;:#-y\-\=\+ ]/', '', $val);
		return $val;
	}
	$athleticsHeaders = Array();
	$path_parts = pathinfo($_FILES['userfile']['name']);
	if (strpos('.csv',$path_parts['extension'])) {
		$fileHandle = fopen($_FILES['userfile']['tmp_name'],'r');

		$row=0;
		$errors="";
		
		while (($data = fgetcsv($fileHandle, 1000, ",")) !== FALSE) {
			$num = count($data);
			if ($row > 0) {
				updateFacPage($auth, $cascade, $cascadeDir, $siteName, $num, $header, $data);
			} else {
				$header = $data;
			}
			$row++;
		}
		if ($errors !== "") {
			echo "<p style=\"color:red;\">Errors in the following rows:<br />" . $errors . "</p>";
		}
		echo "<p style=\"color:red;\">".($row-1)." games added.</p>";
		fclose($fileHandle);
	} else {
		echo "wrong file type";
	}
}

function createYrFolder($auth, $cascade, $cascadeDir, $siteName, $schoolYear) {
	$parentFolderPath = str_replace('//','/',$cascadeDir);
	$name = $schoolYear;
	$displayYear = $schoolYear-1;
	$displayYear .= "-".substr($schoolYear,2,2);
	
	$metadata = array(
			'displayName' => $displayYear,
			'title' => $displayYear,
				'dynamicFields' => array(
					'dynamicField' => array(
						'name' => 'excludeSitemap',
						'fieldValues' => array(
							'fieldValue' => array(
								'value' => 'Exclude')))));
	
	$template_params = array(
		'authentication' => $auth,
			'asset' => array(
				'folder' => array(
					'parentFolderPath' => $parentFolderPath,
					'name' => $name,
					'metadata' => $metadata,
					'siteName' => $siteName
				)
			)
		);
				
	$cascade->create($template_params);
	$result = $cascade->__getLastResponse();
	getResult($result,$cascadeDir.'/'.$schoolYear,'Folder');
}
function createYrIndexPage($auth, $cascade, $cascadeDir, $siteName, $theSport, $theSportDisplay, $schoolYear) {
	
	$styleSheetId = 'ef71a7bc6c3b961000605a2854d30210';
	$indexBlockPath = '/community/athletics/-system/Schedule/Index - '.$theSport;
	
	$parentFolderPath = str_replace('//','/',$cascadeDir . '/' . $schoolYear);

	$name = 'index';
	
	$displayYear = $schoolYear-1;
	$displayYear .= "-".substr($schoolYear,2,2);

	$metadata = array(
			'displayName' => $displayYear . ' ' . $theSportDisplay . ' Schedule',
			'title' => $displayYear . ' ' . $theSportDisplay . ' Schedule'
		);
			
	$template_params = array(
		'authentication' => $auth,
			'asset' => array(
				'page' => array(
					'contentTypePath' => 'Athletics/Schedule Index/' . $theSport,
					'parentFolderPath' => $parentFolderPath,
					'name' => $name,
					'metadata' => $metadata,
					'siteName' => $siteName
				)
			)
		);
				
	$cascade->create($template_params);
	$result = $cascade->__getLastResponse();
	getResult($result,$parentFolderPath.'/index');
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Upload Athletics Roster</title>
<style type="text/css">
<!--
	body, html { font-family:Calibri, Verdana, Arial, Helvetica, sans-serif; }
-->
</style>
</head>

<body>
<h1>Upload Athletics Roster</h1>
<div id="uploadCSV" style="background-color:#CCCCCC;padding:5px;">
<form enctype="multipart/form-data" action="uploadCal.php" method="POST">
<input type="hidden" name="isSubmitted" value="yes" />
<p><label for="userfile">Upload a .CSV file (file specifications below)<br />
<input name="userfile" type="file" id="userfile" /></label>&nbsp;&nbsp;&nbsp;<input type="submit" value=" Upload " /></p>
</form>
</div>
<h2>File Specifications</h2>
<p>The file must be a .CSV (Comma Separated Values) file. .CSV files can be created using Microsoft Excel or any text editor. Other requirements are:</p>
<ul>
	<li>The first row must be the column headers. Choices for the header are: <br/>
    <em>sport,schoolYear,startdate,starttime,preseason,opponent,location,results</em><br/><a href="schedule-template.csv">Download a template</a></li>
	<li>Required columns are: schoolYear,sport,opponent</li>
	<li>schoolYear must be in a 4-digit year format: 2012-13 would be 2013</li>
	<li>&quot;sport&quot; must be one of the following values: baseball, basketball-men, basketball-women, crosscountry, golf, softball, tennis-men, tennis-women, volleyball</li>
	<li>Columns may be in any order or omitted.</li>
</ul>
</body>
</html>

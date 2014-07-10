<?php
// Parts of this script that you would need to customize are marked with "CHECK" over to the right. 
// Default Cascade Server web services values																					CHECK
$username = "some.user"; 
$password = "password1"; 
$cascadePath = "http://cascade.server.url"; 
$cascade = new SoapClient($cascadePath . "/ws/services/AssetOperationService?wsdl",array('trace' => 1));
$auth = array('username' => $username, 'password' => $password);

// kraken.io API Key, API Secret, and URL to Kraken.php																			CHECK
$apiKey = 'secret';
$apiSecret = 'evenmoresecret';
// Kraken.php file is required -- https://github.com/kraken-io/kraken-php
require_once('/path/to/Kraken.php');
$kraken = new Kraken($apiKey,$apiSecret);

// set folders to resize images and/or create thumbnail images																	CHECK
$resizeFolders = array();
$resizeFolders[] = array('folder' => 'folder/to/resize', 'fullSize' => 1000);
$resizeFolders[] = array('folder' => 'folder/to/create/thumbs', 'thumbSize' => 100);
$resizeFolders[] = array('folder' => 'folder/to/resize/and/create/thumbs', 'thumbSize' => 100, 'fullSize' => 1000);

// array of folders to exclude																									CHECK
$excludeFolders = array();
$excludeFolders[] = 'folder/to/exclude/from optimization';

// Start the process with the parent folder's (or folders') 32-digit id numbers. 												CHECK
readFolder('32digitidnumberofstartingfolder', $auth, $cascade, $resizeFolders, $excludeFolders, $kraken);

function readFolder ($folderReadID, $auth, $cascade, $resizeFolders, $excludeFolders, $kraken, $thumbSize=0, $fullSize=0) {
	$id = array('id' => $folderReadID, 'type' => 'folder');
	$readParams = array( 'authentication' => $auth, 'identifier' => $id );	
	$folderRead=$cascade->read($readParams);
	if ( $folderRead->readReturn->success != 'true' ) {
		echo "Error reading folder: ".$folderReadID;
	} else {
		$folderChildren = $folderRead->readReturn->asset->folder->children->child;
		$thisFolder = $folderRead->readReturn->asset->folder->parentFolderPath."/".$folderRead->readReturn->asset->folder->name;
		$excludeThisFolder = false;
		foreach($excludeFolders as $value) {
			if (strpos($thisFolder,$value) !== false) $excludeThisFolder = true;
		}
		foreach($resizeFolders as $value) {
			if (strpos($thisFolder,$value['folder']) !== false) {
				if(array_key_exists('thumbSize',$value)) $thumbSize = $value['thumbSize'];
				if(array_key_exists('fullSize',$value)) $fullSize = $value['fullSize'];
			}
		}
		echo "Folder: ".$thisFolder." (".count($folderChildren).")".($excludeThisFolder ? ' - Excluded' : '').($thumbSize > 0 ? ' - Thumbnail:'.$thumbSize : '').($fullSize > 0 ? ' - Resize:'.$fullSize : '');
		// If folder is excluded or there are no children, stop processing this and child folders.
		if($excludeThisFolder === false && !is_null($folderChildren)) {
		// if there's just one child, put it in an array
			if (count($folderChildren) == 1) {
				$childrenArray[0] = $folderChildren;
			} elseif (count($folderChildren) > 1) $childrenArray = $folderChildren;
			// loop through children to find other folders and images
			foreach ($childrenArray as $value) {
				$childPath = $value->path->path;
				if ($value->type == 'folder') {
					readFolder($value->id, $auth, $cascade, $resizeFolders, $excludeFolders, $kraken, $thumbSize, $fullSize);
				} elseif ($value->type == 'file' && (substr(strtolower($childPath),-4) == 'jpeg' || substr(strtolower($childPath),-3) == 'jpg' || substr(strtolower($childPath),-3) == 'png' || substr(strtolower($childPath),-3) == 'gif') ) {
					readImage($value->id, $auth, $cascade, $kraken, $thumbSize, $fullSize);
				}
			}
		}
	}
}

function readImage($fileReadID, $auth, $cascade, $kraken, $thumbSize=0, $fullSize=0) {
	$id = array('id' => $fileReadID, 'type' => 'file');
	$readParams = array( 'authentication' => $auth, 'identifier' => $id );	
	$fileRead=$cascade->read($readParams);
	if ( $fileRead->readReturn->success != 'true' ) {
		echo "Error reading image: ".$fileReadID;
	} else {
		// Retrieve date of optimization for comparison. It's the second Dynamic Metadata field, so may not be true for all. 	CHECK
		$imgOptimizeDate = date('U',$fileRead->readReturn->asset->file->metadata->dynamicFields->dynamicField[1]->fieldValues->fieldValue->value*.001);
		$imgModifiedDate = date('U',strtotime($fileRead->readReturn->asset->file->lastModifiedDate));
		$imgPublishedDate = date('U',strtotime($fileRead->readReturn->asset->file->lastPublishedDate));
		// If it's already been optimized or never been published, skip it
		if((!$imgOptimizeDate || $imgOptimizeDate < $imgModifiedDate) && $imgPublishedDate) {
			$imgPath = $fileRead->readReturn->asset->file->path;
			$imgSite = $fileRead->readReturn->asset->file->siteName;
			$imgName = $fileRead->readReturn->asset->file->name;
			$imgAsset = $fileRead->readReturn->asset;
			$fileExt = substr($imgName,strrpos($imgName,'.'));
		
			// Kraken parameters to retrieve image from website
			//    This assumes that Cascade Server site name is same as host name and using https. YMMV.						CHECK
			$params = array(
				"url" => "https://".$imgSite.'/'.$imgPath,
				"wait" => true,
				"lossy" => true
			);
			// Set up Kraken to resize if selected. Will resize to maximum dimensions, keeping aspect ratio. 
			//    For example, if $fullSize = 1000, a 2000x1500 image will be resized to 1000x750.
			if($fullSize > 0) $params['resize'] = array("width" => $fullSize, "height" => $fullSize, "strategy" => "auto");
			$retKrake = getKrakedImage($params,$kraken);
			
			if($retKrake !== false) {
				$metadata = array(
					'dynamicFields' => array(
						'dynamicField' => array(
							array(
								'name' => 'imgOptimized',
								'fieldValues' => array('fieldValue' => array('value' => round((microtime(true)+10)*1000)))
								)
							)
						)
					);
				$asset = array( 'file' => array (
							'id' => $fileReadID,
							'siteName' => $imgSite,
							'parentFolderPath' => str_replace('/'.$imgName,'',$imgPath),
							'name' => $imgName,
							'metadata' => $metadata,
							'data' => $retKrake));
				$template_params = array(
					'authentication' => $auth,
					'asset' => $asset);
				
				$editParams = array('authentication' => $auth, 'asset' => array('file' => $asset));
				$cascade->edit($template_params);
				$result = $cascade->__getLastResponse();
				getResultAction($result,$imgPath,"Optimized.");
				
				$identifier = array('id' => $fileReadID, 'type' => 'file');
				$publishInformation = array('identifier' => $identifier);
				$cascade->publish(array('authentication' => $auth, 'publishInformation' => $publishInformation));
				$result = $cascade->__getLastResponse();
				getResultAction($result,$imgPath,"published.");
			}
		
			if($thumbSize > 0) {
				$newImgName = str_replace($fileExt,'',$imgName).'-x'.$thumbSize.$fileExt;
				$newImgPath = str_replace($fileExt,'',$imgPath).'-x'.$thumbSize.$fileExt;
				if (strpos($imgName,'x'.$thumbSize) === false) {
					$pathThumb = array('path' => $fileRead->readReturn->asset->file->parentFolderPath.'/'.$newImgName, 'siteName' => $imgSite);
					$idThumb = array('path' => $pathThumb, 'type' => 'file');
					$readThumbParams = array( 'authentication' => $auth, 'identifier' => $idThumb );	
					$pageThumbRead=$cascade->read($readThumbParams);
			
					if ($pageThumbRead->readReturn->success != 'true') {
						// Kraken parameters to retrieve image from website
						//    This assumes that Cascade Server site name is same as host name and using https. YMMV.			CHECK
						$paramsThumb = array(
							"url" => "https://".$imgSite.'/'.$imgPath,
							"wait" => true,
							"lossy" => true,
							"resize" => array("width" => $thumbSize, "height" => $thumbSize, "strategy" => "auto")
						);
						$retKrakeT = getKrakedImage($paramsThumb,$kraken);
				
						$metadata = array(
							'dynamicFields' => array(
								'dynamicField' => array(
									array(
										'name' => 'imgOptimized',
										'fieldValues' => array('fieldValue' => array('value' => round((microtime(true)+10)*1000)))
										)
									)
								)
							);
						$asset = array( 'file' => array (
									'siteName' => $imgSite,
									'parentFolderPath' => $fileRead->readReturn->asset->file->parentFolderPath,
									'name' => $newImgName,
									'metadata' => $metadata,
									'data' => $retKrakeT));
						$template_params = array(
							'authentication' => $auth,
							'asset' => $asset);
						
						$editParams = array('authentication' => $auth, 'asset' => array('file' => $asset));
						$cascade->create($template_params);
						$result = $cascade->__getLastResponse();
						getResultAction($result,$newImgName,"Added.");
					}
				}
			}
		}
	}
}
function getKrakedImage($params,$kraken) {
	$image = false;
	$data = $kraken->url($params);
	if($data['success'] == true) {
		ob_start();
			if (substr(strtolower($data['file_name']),-4) == 'jpeg' || substr(strtolower($data['file_name']),-3) == 'jpg') {
				$resImage = imagecreatefromjpeg($data['kraked_url']);
				imagejpeg($resImage);
				$image = true;
			}
			if (substr(strtolower($data['file_name']),-3) == 'png') {
				$resImage = imagecreatefrompng($data['kraked_url']);
				imagepng($resImage);
				$image = true;
			}
			if (substr(strtolower($data['file_name']),-3) == 'gif') {
				$resImage = imagecreatefromgif($data['kraked_url']);
				imagegif($resImage);
				$image = true;
			}
			if ($image) $dbImage = ob_get_contents();
		ob_end_clean();
	}
	if ($image) { 
		return $dbImage;
	} else { 
		return false; 
	}
}

// general functions for Cascade return values
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

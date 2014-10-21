<?php

// First, what are we trying to do?
if (isset($argv[1])) {
	switch($argv[1]) {
		case 'upload_photos':
			$job = "upload_photos";
			break;
		case 'get_collections':
			$job = "get_collections";
			break;
		default:
			print "Not sure what you want me to do, either: upload_photos, get_collections";
			exit(1);
	}

}
else {
	print "Use like so %> php -f sync.php [upload_photos|get_collections] [(optional)specific collection to upload]";
	exit(1);
}

// Auth and stuff
include('config.php');
require_once("phpFlickrExt.php");
require_once('picturestorage.php');
require_once("flickrSync.php");

// Get phpFlickr
$f = new phpFlickrExt($api_key, $api_secret);
$f->setToken($api_token);

// Get Sync and inject storage
$sync = new FlickrSync(
	new PictureStorage(),
	$f
);

$sync->setPhotoRoot($directory);
$sync->setIgnores($ignore_dirs);
if (isset($argv[2])) $sync->setSpecificCollection($argv[2]);
$sync->setPrivacy($public, $friend, $family);

/**
 * Now, what have we got to do?
 **/
if ($job == "upload_photos") {

	$sync->uploadPhotos();
	print "Ran uploads\n";

}
else if ($job == "get_collections") {
	$sync->storeCollectionsAndSets();
	print "Pulled collections and sets\n";
}

if ($sync->getErrors() != false) {
    print "\n\n--- These were errors, please check the log: ". $sync->getLogFile() ." \n";
}
print "\n\nfin.\n";

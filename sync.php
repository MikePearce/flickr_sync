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
			print "Not sure what you want me to do, either: check_uploads, get_collections";
			exit(1);
	}	
}
else {
	print "Not sure what you want me to do, either: check_uploads, get_collections";
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


print "fin.\n";
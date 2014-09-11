<?php

	class FlickrSync {

		/**
		 * Stores the storage method
		 **/
		public $storage;

		/**
		* Stores the phpFlickr Object
		**/
		public $f;

		/**
		 * Where do we want to look for photos
		 **/
		public $photoRoot;

		/**
		 * Stores history
		 **/
		public $log;

        /**
         * @var Whether to make the photo public
         */
        public $public;

        /**
         * @var Whether to allow friends to see it
         */
        public $friends;

        /**
         * @var Whether to allow family to see it.
         */
        public $family;

        /**
         * @var Errors
         */
        public $errors;

		/**
		 * Debug level
		 **/
		public $debug;

        /**
         * @var Array of directories to ignore
         */
        public $ignores;

        /**
         * @var file that we write the logs to
         */
        public $logfile;

		/**
		 * Setup the class vars
		 * @var $stoage Picturestorage
         * @var $phpFlickrExt phpFlickrExt
		 **/
		public function __construct($storage, $phpFlickrExt) {

			$this->storage = $storage;
			$this->f = $phpFlickrExt;
			//$this->f->enableCache("fs", "/tmp/");
			$this->setPhotoRoot();
			$this->log = array();
			$this->debug = "low";
            $this->setPrivacy();
            $this->setIgnores();
            $this->setLogFile();

		}

        public function setLogFile($logfile = 'log.txt') {
            $time = time();
            $this->logfile = $time."-". $logfile;
            // Create the file
            $fp = fopen('data.txt', 'a');
            fwrite($fp, '-- New Log: '. $time ." --\n");
            fclose($fp);
        }

        /**
         * @param string $root The root of photos you want to upload
         */
        public function setPhotoRoot($root = "../") {
			$this->photoRoot = $root;
		}

        /**
         * @param $ignores Array of directories to ignore
         */
        public function setIgnores($ignores = array()) {
            $this->ignores = $ignores;
        }

        /**
         * What kind of privacy is on the photo?
         * @param int $p public
         * @param int $f friends
         * @param int $fam family
         */
        public function setPrivacy($p = 0, $f = 0, $fam = 0) {
            $this->public = $p;
            $this->friends = $f;
            $this->family = $fam;
        }

        /**
         * Return all captured errors as string
         * @return string of errors
         */
        public function getErrors() {
            $errors = "";
            if (is_array($this->errors)) {
                foreach($this->errors AS $error) {
                    $errors .= $error ."\n";
                }
            }
            return $errors;
        }

        /**
         * Grab all colletions and sets from the API and store them in sqllite3
         */
        public function storeCollectionsAndSets() {

            $this->log("---- Beginning Download of collections and sets ----\n");

			// Clear collections and sets
			$this->storage->clearData(FALSE);

			// Get the collections
			$c = $this->f->collections_getTree();
			$collections = array();
			foreach( $c['collections']['collection'] AS $collection ) {

				$sets = array();
				if (isset($collection['set'])) {
					foreach ($collection['set'] as $set) {
						$sets[$set['id']] = $set['title'];
					}
				}
				
				$collections[] = array(
					'id' => $collection['id'],
					'title' => $collection['title'],
					'sets' => $sets,
					);
			}

			foreach($collections AS $collection) {

				// Save collection
				$this->storage->saveCollection($collection['id'], $collection['title']);
				$this->log('Saved collection to sql: '. $collection['title']);
				foreach($collection['sets'] AS $set_id => $set) {

					// Save the set...
					$this->storage->saveSet($set_id, str_replace("'", "''", $set), $collection['id']);
					$this->log('Saved set to sql: '. $set);

				}
			}
		}

        /**
         * Run through the specified directory and upload the photos
         */
        public function uploadPhotos() {

            $this->storeCollectionsAndSets();

			$this->log("---- Beginning Upload ----\n");

			// Now we need to iterate through the folders
			$photos = new RecursiveDirectoryIterator($this->photoRoot);
			$filetypes = array("jpg", "mov", "3gp", "avi");

			$collections = $no_flickr_collection = $collections_and_sets = array();
			$sets = array();

			foreach( new RecursiveIteratorIterator($photos) as $file ) {
				$filetype = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				if (in_array(strtolower($filetype), $filetypes)) {

					// First, remove the root
					$file = str_replace($this->photoRoot, "", $file);

					// For each file break it into ../[collection]/[set]/[photo]
                    $file_array = explode("/", $file);

                    if (count($file_array) == 3) {
                        list($collection, $set, $photo) = $file_array;
                    }
                    else {
                        list($set, $photo) = $file_array;
                        $collection = "Misc";
                    }


                    // If this is an ignored connection, go to the next one
                    if (in_array($collection, $this->ignores)) continue;

					// Have we already uploaded the photo?
					if ($this->storage->photoUploaded($photo)) continue;

					// We haven't, let's check we have a collection...
					if ( !$collection_id = $this->storage->collectionExists($collection) ) {
						
						if (!in_array($collection, $no_flickr_collection)) {
							$no_flickr_collection[] = $collection;
						}
						continue;
					}

					// We have a collection, do we have a set?
					if ( !$set_id = $this->storage->setExists($set) ) {

						// First, upload this photo, so we have an ID for the primary_photo_id
						$photo_id = $this->doUploadPhoto($this->photoRoot.$file, $photo);

						// Then make the set
						$set_id = $this->makeSet($set, $photo_id, $collection_id);

						// Then add the photo to it
						$this->addPhotoToSet($set_id, $photo_id);

					}
					// We have a set, 
					else {
						// so let's upload to it
						$photo_id = $this->doUploadPhoto($this->photoRoot.$file, $photo);

						// Then add to set
						$this->addPhotoToSet($set_id, $photo_id);
					}

					// Then store the collections and sets
					$collections_and_sets[$collection_id][] = $set_id;					
				}
			}

			$this->log("---- Upload Complete ----\n\n");
			
			// Now, add the sets to teh collections
			$this->log("---- Adding Sets to Collections ----\n");
			$this->addSetsToCollections($collections_and_sets);
			$this->log("---- Adding sets to collections complete ----\n\n");

			// If we had unknown folders (folders that aren't sets) let the user know.
			if (!empty($no_flickr_collection)) {
				print "These collections don't exist, you'll need to make it before uploading anything: ". 
					implode(", ", $no_flickr_collection) .". Photos in these collections were not uploaded. \n\n".
					"Run: `php -f sync.php get_collections` once you've made the sets.";
			}
			
		}

        /**
         * Do the actual upload
         *
         * @param $filepath The full path to the file
         * @param $filename The name of the file
         * @return mixed
         */
        private function doUploadPhoto($filepath, $filename) {
			$r = $this->f->sync_upload(
					$filepath,
					null, // Title
					null, // description
					str_replace(" ", "-", $filename), // tags
					$this->public, // is_public
					$this->friends, // is_friend
					$this->family // if_family
				);

			if (!$r) {
				// Something went wrong!
				$this->log("something went wrong with the upload: ". $this->f->getErrorMsg() .'('. $filepath .')', TRUE);
			}
			else {
				// It was successful, so we should save that it was
				$this->storage->savePhoto($r, $filename);
				$this->log('Uploaded photo: '. $filename);
				return $r;
			}

		}

        /**
         * Unused function as the API is a bit flakey
         *
         * @param $collection_name
         * @return mixed
         */
        private function createCollection($collection_name) {

			// Make collection
			$r = $this->f->collections_createCollection($collection_name);

			if (!$r) {
				// Something went wrong!
				$this->log("something went wrong with making the collection: ". $this->f->getErrorMsg(), TRUE);
			}
			else {
				// It was successful, so we should save that it was
				$this->storage->saveCollection($r['collection']['id'], $collection_name);
				$log = 'Made collection: '. $collection_name;
					if ($this->debug = "high") $log .= '('. $r['collection']['id'] .')';
				$this->log($log);
				return $r['collection']['id'];
			}	

		}

        /**
         * Makes use of an undocumented API feature to add sets (albums) to collections
         *
         * @param $collections_and_sets
         */
        private function addSetsToCollections($collections_and_sets) {

			foreach ($collections_and_sets AS $collection => $sets) {

				// First, get all existing sets
				$collection_sets = $this->storage->getCollectionSets($collection);
                foreach($collection_sets AS $set) {
                    $sets[] = $set['id'];
                }

				// Then add them all to the collection
				if (!$this->f->collections_editCollection($collection, implode(",", $sets))) {
					// Something went wrong!
					$this->log("something went wrong with adding the set to the collection: ". $this->f->getErrorMsg(), TRUE);
				}
				else {
					// It was successful, so we should save that it was
					$this->log('set: '. implode(",", $sets) .' added to '. $collection .'');
				}								
			}
		}

        /**
         * Make a set
         *
         * @param $set_name string
         * @param $primary_photo_id int
         * @param $collection_id int
         * @return mixed
         */
        private function makeSet($set_name, $primary_photo_id, $collection_id) {

			// Make the set
			$r = $this->f->photosets_create($set_name, '', $primary_photo_id);

			

			if (!$r) {
				// Something went wrong!
				$this->log("something went wrong with making the set: ". $this->f->getErrorMsg(), TRUE);
			}
			else {
				// It was successful, so we should save that it was
				$this->storage->saveSet($r['id'], $set_name, $collection_id);
				$log = 'Made set: '. $set_name; 
					if ($this->debug == "high") $log .= '('. $r['id'] .')';
				$this->log($log);
				return $r['id'];
			}			
		}

        /**
         * Add a photo to a set
         *
         * @param $set_id int
         * @param $photo_id int
         * @return mixed
         */
        private function addPhotoToSet($set_id, $photo_id) {
			if ($this->debug == "high") $this->log('Added '. $photo_id .' to '. $set_id);
			return $this->f->photosets_addPhoto($set_id, $photo_id);
		}

        /**
         * Just a logger
         *
         * @param $text string
         * @param bool $error
         */
        private function log($text, $error = FALSE) {
			$this->log[] = $text;
            if ($error) $this->errors[] = $text;
			print $text ."\n";
            $this->writeLogFile($text ."\n");
		}

        private function writeLogFile($log) {
            $fp = fopen($this->logfile, 'a');
            fwrite($fp, $log);
            fclose($fp);
        }
	}
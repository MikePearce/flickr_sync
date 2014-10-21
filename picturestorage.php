<?php

class PictureStorage {

	public $db;
	

	public function __construct() {

		$this->db = new PDO('sqlite:db/flickr_sync');
		// Set errormode to exceptions
    	$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->query('CREATE TABLE IF NOT EXISTS "photos" ("id" varchar(40) NULL, "filename" varchar(40) NULL);');
    }

    public function __destruct() {
    	$this->db = null;
    }

    public function sanitizeString($string) {


        return $string;
    }

    public function getPhotos() {
    	return $this->db->query('SELECT * FROM photos');
    }

    public function getCollectionSets($collection_id) {
        $res = $this->db->query('SELECT id FROM sets WHERE collection_id = "'. $collection_id .'"');
        $res->setFetchMode(PDO::FETCH_ASSOC);
        return $res->fetchAll();
    }

    public function savePhoto($id, $filename) {
        $query = "INSERT into photos (id, filename) VALUES (:id, :filename)";
    	$sth = $this->db->prepare($query);
        return $sth->execute(array(
                    ':id' => $id,
                    ':filename' => $filename
                ));
    }

    public function saveCollection($id, $collection_name) {
        $query = "INSERT into collections (id, collection_name) VALUES (:id, :collection_name)";
        $sth = $this->db->prepare($query);
        return $sth->execute(array(
                ':id' => $id,
                ':collection_name' => $collection_name
            ));
    }

    public function saveSet($id, $set_name, $collection_id) {
        $query = "INSERT into sets (id, setname, collection_id) VALUES (:id, :set_name, :collection_id)";
        $sth = $this->db->prepare($query);
        return $sth->execute(array(
                ':id' => $id,
                ':set_name' => $set_name,
                ':collection_id' => $collection_id
            ));
    }

    public function clearData($delete_photos = FALSE) {
        if ($delete_photos){
            $this->db->query("DROP TABLE IF EXISTS photos;");
            $this->db->query('CREATE TABLE "photos" ("id" varchar(40) NULL, "filename" varchar(40) NULL);');
        } 
        $this->db->query("DROP TABLE IF EXISTS collections;");
		$this->db->query("DROP TABLE IF EXISTS sets;");
    	$this->db->query('CREATE TABLE "collections" ("id" varchar(40) NULL, "collection_name" varchar(40) NULL);');
		return $this->db->query('CREATE TABLE "sets" ("id" varchar(40) NULL, "setname" varchar(40) NULL, "collection_id" varchar(40) NULL);');
    	
    }

    public function collectionExists($collection_name) {
        $stmt = $this->db->prepare("SELECT id FROM collections where collection_name = ?");
        $stmt->execute(array($collection_name));
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $row = $stmt->fetch();
        return $row['id'];

//        $res = $this->db->query('SELECT id FROM collections WHERE collection_name = "'. str_replace("'", "''", $collection_name) .'"');
//        $res->setFetchMode(PDO::FETCH_ASSOC);
//        $col = $res->fetch();
//        return $col['id'];

    }

    public function setExists($set_name) {
        $stmt = $this->db->prepare("SELECT id FROM sets where setname = ?");
        $stmt->execute(array($set_name));
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $row = $stmt->fetch();
        return $row['id'];
//
//        $res = $this->db->query('SELECT id FROM sets WHERE setname = "'. str_replace("'", "''", $set_name) .'"');
//        $res->setFetchMode(PDO::FETCH_ASSOC);
//        $col = $res->fetch();
//        return $col['id'];
    }

    public function photoUploaded($filename) {
        $res = $this->db->query('SELECT id FROM photos WHERE filename = "'. $filename .'"');
        $res->setFetchMode(PDO::FETCH_ASSOC);
        return $res->fetch();
    }    


}
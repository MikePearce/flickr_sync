<?php

    include_once(__DIR__. '/phpFlickr/phpFlickr.php');

/**
 * This class contains a few extra methods and an override
 * for the upload classes (as they're using a deprecated curl method)
 */

class phpFlickrExt extends phpFlickr {

    public function __construct($api_key, $api_secret) {
        parent::__construct($api_key, $api_secret);
    }

    /**
     * Identical to the phpFlickr sync_upload, except it uses the new cURL method
     *
     * @param $photo
     * @param null $title
     * @param null $description
     * @param null $tags
     * @param null $is_public
     * @param null $is_friend
     * @param null $is_family
     * @return bool
     */
    function sync_upload ($photo, $title = null, $description = null, $tags = null, $is_public = null, $is_friend = null, $is_family = null) {
        if ( function_exists('curl_init') ) {
            // Has curl. Use it!

            //Process arguments, including method and login data.
            $args = array("api_key" => $this->api_key, "title" => $title, "description" => $description, "tags" => $tags, "is_public" => $is_public, "is_friend" => $is_friend, "is_family" => $is_family);
            if (!empty($this->token)) {
                $args = array_merge($args, array("auth_token" => $this->token));
            } elseif (!empty($_SESSION['phpFlickr_auth_token'])) {
                $args = array_merge($args, array("auth_token" => $_SESSION['phpFlickr_auth_token']));
            }

            ksort($args);
            $auth_sig = "";
            foreach ($args as $key => $data) {
                if ( is_null($data) ) {
                    unset($args[$key]);
                } else {
                    $auth_sig .= $key . $data;
                }
            }
            if (!empty($this->secret)) {
                $api_sig = md5($this->secret . $auth_sig);
                $args["api_sig"] = $api_sig;
            }

            $photo = realpath($photo);
            $args['photo'] = new CurlFile($photo, mime_content_type($photo), $photo);


            $curl = curl_init($this->upload_endpoint);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $args);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl);
            $this->response = $response;
            curl_close($curl);

            $rsp = explode("\n", $response);
            foreach ($rsp as $line) {
                if (preg_match('|<err code="([0-9]+)" msg="(.*)"|', $line, $match)) {
                    if ($this->die_on_error)
                        die("The Flickr API returned the following error: #{$match[1]} - {$match[2]}");
                    else {
                        $this->error_code = $match[1];
                        $this->error_msg = $match[2];
                        $this->parsed_response = false;
                        return false;
                    }
                } elseif (preg_match("|<photoid>(.*)</photoid>|", $line, $match)) {
                    $this->error_code = false;
                    $this->error_msg = false;
                    return $match[1];
                }
            }

        } else {
            die("Sorry, your server must support CURL in order to upload files");
        }

    }

    /**
     * Identical to the phpFlickr async_upload, except it uses the new cURL method
     *
     * @param $photo
     * @param null $title
     * @param null $description
     * @param null $tags
     * @param null $is_public
     * @param null $is_friend
     * @param null $is_family
     * @return bool
     */
    function async_upload ($photo, $title = null, $description = null, $tags = null, $is_public = null, $is_friend = null, $is_family = null) {
        if ( function_exists('curl_init') ) {
            // Has curl. Use it!

            //Process arguments, including method and login data.
            $args = array("async" => 1, "api_key" => $this->api_key, "title" => $title, "description" => $description, "tags" => $tags, "is_public" => $is_public, "is_friend" => $is_friend, "is_family" => $is_family);
            if (!empty($this->token)) {
                $args = array_merge($args, array("auth_token" => $this->token));
            } elseif (!empty($_SESSION['phpFlickr_auth_token'])) {
                $args = array_merge($args, array("auth_token" => $_SESSION['phpFlickr_auth_token']));
            }

            ksort($args);
            $auth_sig = "";
            foreach ($args as $key => $data) {
                if ( is_null($data) ) {
                    unset($args[$key]);
                } else {
                    $auth_sig .= $key . $data;
                }
            }
            if (!empty($this->secret)) {
                $api_sig = md5($this->secret . $auth_sig);
                $args["api_sig"] = $api_sig;
            }

            $photo = realpath($photo);
            $args['photo'] = new CurlFile($photo, mime_content_type($photo), $photo);


            $curl = curl_init($this->upload_endpoint);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $args);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl);
            $this->response = $response;
            curl_close($curl);

            $rsp = explode("\n", $response);
            foreach ($rsp as $line) {
                if (preg_match('/<err code="([0-9]+)" msg="(.*)"/', $line, $match)) {
                    if ($this->die_on_error)
                        die("The Flickr API returned the following error: #{$match[1]} - {$match[2]}");
                    else {
                        $this->error_code = $match[1];
                        $this->error_msg = $match[2];
                        $this->parsed_response = false;
                        return false;
                    }
                } elseif (preg_match("/<ticketid>(.*)</", $line, $match)) {
                    $this->error_code = false;
                    $this->error_msg = false;
                    return $match[1];
                }
            }
        } else {
            die("Sorry, your server must support CURL in order to upload files");
        }
    }

    function collections_createCollection($collection_name = NULL) {
        return $this->call('flickr.collections.create', array('title' => $collection_name));
    }

    function collections_editCollection($collection_id = NULL, $photosets = NULL) {
        /* https://www.flickr.com/services/api/flickr.collections.getTree.html */
        return $this->call('flickr.collections.editSets', array('collection_id' => $collection_id, 'photoset_ids' => $photosets));
    }

}
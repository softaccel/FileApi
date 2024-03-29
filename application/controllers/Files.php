<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Files extends CI_Controller {
	private $upload_dir = "uploads/";

	function __construct()
	{
		parent::__construct();

	}

	function  index() {
		$this->load->view('uploadform');
	}

	function upload() {
		$public_dir = date("Y/m/d")."/";
		$target_dir = $this->upload_dir.$public_dir;
		$filename = basename($_FILES["file"]["name"]);
		if(!is_dir($target_dir)) {
			if(!@mkdir($target_dir,0777,true))  {
				http_response_code(500);
				die("Could not create directory");
			}
		}

		$target_file = $target_dir . $filename;
		if(is_file($target_file)) {
			http_response_code(409);
			die("File $filename already exists");
		}
		$tn_file = $target_dir . "tn_".$filename;
		$file_id = $public_dir. $filename;
		$tn_file_id = $public_dir. "tn_".$filename;


		if(!move_uploaded_file($_FILES["file"]["tmp_name"],$target_file)) {
			http_response_code(500);
			die("Could not save file");
		}
		chmod($target_file,0666);
		$mime = mime_content_type($target_file);

		$response = [
			"name"=>$_FILES["file"]["name"],
			"id"=>str_replace("=","",self::encrypt_decrypt("encrypt",$file_id)),
			"mime"=>$mime,
			"size"=>filesize($target_file)
		];

		if(preg_match('/image\/(.+)$/',$mime,$matches)) {
			if(self::create_thumbnail($target_file,$tn_file,$matches[1]))
				$response["thumbnail"] = str_replace("=","",self::encrypt_decrypt("encrypt",$tn_file_id));

		}

		header("Content-type: application/json");
		echo json_encode($response,JSON_PRETTY_PRINT);
	}

	function get($fileId) {

		$file = $this->upload_dir.self::encrypt_decrypt("decrypt",$fileId);
		if(!is_file($file)) {
			http_response_code(404) ;
			echo "File not found";
		}
		header("Content-type: ".mime_content_type($file));
		header('Content-Disposition: inline; filename="'.basename($file).'"');
		echo file_get_contents($file);
	}

	function delete($fileId) {
		$file = $this->upload_dir.self::encrypt_decrypt("decrypt",$fileId);
		if(!is_file($file)) {
			http_response_code(404) ;
			die();
		}
		$tmp = pathinfo($file);
		$tn_file = $tmp["dirname"]."/tn_".$tmp["basename"];
		if(unlink($file)) {
			unlink($tn_file);
			http_response_code(200);
			echo $tn_file;
		}
		else {
			http_response_code(500);
			echo "NOK";
		}
	}

	private static function create_thumbnail($image_file,$tn_file,$ext) {
		list($width, $height) = getimagesize($image_file);
		$tn_max_size = 200;
		$p1 = $width>$tn_max_size ? $width/$tn_max_size : 1;
		$p2 = $height>$tn_max_size ? $height/$tn_max_size : 1;
		$p = $p1>$p2 ? $p1 : $p2;

		switch ($ext) {
			case "png":
				$src = imagecreatefrompng($image_file);
				break;
			case "jpg":
			case "jpeg":
				$src = imagecreatefromjpeg($image_file);
				break;
			case "gif":
				$src = imagecreatefromgif($image_file);
				break;
			default:
				return false;
		}

		$new_width = $width/$p;
		$new_height = $height/$p;
		$thumb = imagecreatetruecolor($new_width, $new_height);
		if(!imagecopyresized($thumb,$src,0,0,0,0,$new_width,$new_height,$width,$height))
			return false;
		switch ($ext) {
			case "png":
				return imagepng($thumb,$tn_file);
				break;
			case "jpg":
			case "jpeg":
				return imagejpeg($thumb,$tn_file);
				break;
			case "gif":
				return imagegif($thumb,$tn_file);
				break;
			default:
				return false;
		}

	}

	private static function encrypt_decrypt($action, $string)
	{
		$output = false;
		$encrypt_method = "AES-256-CBC";
		$secret_key = 'This is my secret key';
		$secret_iv = 'This is my secret iv';
		// hash
		$key = hash('sha256', $secret_key);

		// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
		$iv = substr(hash('sha256', $secret_iv), 0, 16);
		if ($action == 'encrypt') {
			$output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
			$output = base64_encode($output);
		} else if ($action == 'decrypt') {
			$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
		}
		return $output;
	}
}

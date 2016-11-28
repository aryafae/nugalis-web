<?php

class UrlController extends ProcessTemplate{
	function execute($id,$nombre=''){
		$archivo=model()->files($id);
		
		header("Content-type: ".$archivo->tipo);
		
		header('Pragma: ');
		header('Cache-Control: private');
		header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60 * 60 * 24) . " GMT"); 
		header("Last-Modified: ".gmdate("D, d M Y H:i:s", time() - 60 * 60 * 24)." GMT"); 
		///header('ETag: "asset-'.$dir.'"');
		//header('Content-Length: '.filesize($dir));
				$nombre=explode('.',$archivo->nombre);
				$file_name=app_dir().'files/'.$archivo->id.'.'.$nombre[count($nombre)-1];
		echo file_get_contents($file_name);;
	}
}
?>
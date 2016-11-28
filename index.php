<?php
$benchmark_time=microtime(true);
ini_set('display_errors', 1); 
error_reporting(E_ALL);

session_start();

// Obtenemos los segmentos de la URL
/*if(isset($_SERVER['REDIRECT_URL'])) $url= $_SERVER['REDIRECT_URL'];
else $url= $_SERVER['REQUEST_URI'];*/
$url= $_SERVER['REQUEST_URI'];

require_once 'lib/general/functions.php';
require_once 'lib/dao/model.php';
require_once 'lib/model.php';

			if(getParam('fw_text')){
				echo 1;
				exit;
			}

// Si  no existe el archivo de configuración, lo creamos
if(!file_exists('config.php')) require_once 'lib/general/setup_config.php';

date_default_timezone_set('Europe/Madrid');

// Conectamos con la base de datos
require_once 'config.php';
if (!($mysqli=new mysqli($config['db_server'],$config['db_user'],$config['db_password'])))
  trigger_error('Error conectando a la base de datos.');
if (!$mysqli->select_db($config['db_database'])) trigger_error('Error seleccionando la base de datos.',E_USER_ERROR);

function db(){
	global $mysqli;
	return $mysqli;
}

$domain_dir=null;

if( $_SERVER['SERVER_NAME']!='127.0.0.1' and $_SERVER['SERVER_NAME']!='localhost' and
	substr($_SERVER['SERVER_NAME'],strlen($_SERVER['SERVER_NAME'])-14)!='legendarya.com'){
	$domain=$_SERVER['SERVER_NAME'];
	$domains=query('select * from fw_domain where url_dir="" and domain="'.$domain.'"');
	if(count($domains)){
		$domain_dir=$domains[0]['dir'];
	}
	else{
		$domain_parts=explode('.',$_SERVER['SERVER_NAME']);
		$domain=$domain_parts[count($domain_parts)-2].'.'.$domain_parts[count($domain_parts)-1];
		if($domain!=$_SERVER['SERVER_NAME']){
			header("HTTP/1.1 301 Moved Permanently"); 
			header("Location: http://".$domain.$_SERVER['REQUEST_URI']); 
		};
		
		$domains=query('select * from fw_domain where url_dir="" and domain="'.$domain.'"');
		if(count($domains)){
			$domain_dir=$domains[0]['dir'];
		}
	}
}

$url=explode('?',$url);
$segments=explode('/',$url[0]);

$segment_base=2;
if($domain_dir){
	$segment_base=0;
	
	if($segments[$segment_base+1]){
		$domains=query('select * from fw_domain where url_dir="'.$segments[$segment_base+1].'" and domain="'.$domain.'"');
		if(count($domains)){
			$domain_dir=$domains[0]['dir'];
		}
	}
}

$url_path=implode('/',array_slice($segments,$segment_base+1));

if($segments[$segment_base+1]){
	if($domain_dir) $file='apps/'.$domain_dir.'/'.implode('/',array_slice($segments,$segment_base));
	else $file='apps/'.implode('/',array_slice($segments,$segment_base));
	
	$exist=file_exists($file);
	if(!file_exists($file)) 
		$file=implode('/',array_slice($segments,$segment_base+1));
	
	if(file_exists($file)){
		require_once('mime_type_lib.php');
		$mime = get_file_mime_type( $file );
		
$mimeTypes2 = array(
	"js"	=> "application/x-javascript",
	"css"	=> "text/css",
	"htm"	=> "text/html",
	"html"	=> "text/html",
	"xml"	=> "text/xml",
	"txt"	=> "text/plain",
	"jpg"	=> "image/jpeg",
	"jpeg"	=> "image/jpeg",
	"png"	=> "image/png",
	"gif"	=> "image/gif",
	"swf"	=> "application/x-shockwave-flash",
	"ico"	=> "image/x-icon",
);

		if (in_array($mime,$mimeTypes2)){
			require_once 'smartoptimizer/index.php';exit;
		}
		
		header('Pragma: ');
		header('Cache-Control: private');
		header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60 * 60 * 24) . " GMT"); 
		header("Last-Modified: ".gmdate("D, d M Y H:i:s", time() - 60 * 60 * 24)." GMT"); 
				
		header("Content-type: ".$mime);
		readfile($file);
		exit;
	}
}

function app_dir(){
	global $app_dir;
	return $app_dir;
}

if($domain_dir)
	$app_dir='apps/'.$domain_dir.'/';
else $app_dir='apps/'.$segments[2].'/';

if(!file_exists($app_dir)){
	header("HTTP/1.0 404 Not Found");
	die();
	//include($_SERVER['DOCUMENT_ROOT'].'/404.shtml');
	//trigger_error('no existe el archivo o directorio "'.$route.$segments[$segment_pos].'"',E_USER_ERROR);
}


if($domain_dir)
	$prefix=$domain_dir;
else $prefix=$segments[2];

function getTablesPrefix(){
	global $prefix;
	return $prefix;
}

require_once 'lib/general/error_control.php';
require_once 'lib/view/HTMLRedirectGenerator.php';

require_once 'lib/general/languages.php';
require_once $app_dir.'model/general.php';


$mysqli->query("SET NAMES 'utf8'");

$dominio=explode('.',$_SERVER['HTTP_HOST']);
$idioma=2;
if($dominio[0]=='es' || $dominio[0]=='127') $idioma=1;

$segment_pos=substr_count(getConfig('base'),'/')+($domain_dir?-1:1);
	
if(file_exists($app_dir.'redirections.php')) require_once $app_dir.'redirections.php';

$route=$app_dir.'application/';
$notfile=1;
$className='';


// Aquí gestionamos la nueva forma de cargar páginas
if(count(query("SHOW TABLES LIKE '".$prefix."_fw_pagina'"))==1) {
	
	
	$page_url=model()->fw_urls->urlIs($url_path)->getFirst();
	if($page_url){
		require_once $app_dir.'templates/page_template.php';
		require_once $app_dir.'code/'.$page_url->controlador;
		
		$parametro=null;
		try{
			$parametro=new Persistent($prefix.'_'.$page_url->clase,$page_url->parametro);
		}
		catch(Exception $ex){
		}
					
		// Llamamos al método
		call_user_func_array(array(new UrlController(array($parametro),0), 'execute'), array($parametro));
		exit;
	}
		
	$pagina_actual=query("select * from ".$prefix."_fw_pagina where padre=0");
	$pagina_actual=$pagina_actual[0];
	$pagina_anterior=$pagina_actual;
	$new_segment_pos=$segment_pos;
	$parametro=0;
	while($new_segment_pos<count($segments)){
		if($segments[$new_segment_pos]){
			$pagina_actual=query('select p.* from '.$prefix.'_fw_pagina p,'.$prefix.'_fw_traduccion t where p.id=t.pagina and t.idioma='.$idioma.' and t.traduccion="'.$segments[$new_segment_pos].'" and padre='.$pagina_actual['id']);

			
				
			if(count($pagina_actual)) $pagina_actual=$pagina_actual[0];
			else{
				$pagina_actual=query('select * from '.$prefix.'_fw_pagina p where url="" and parametro!="" and padre='.$pagina_anterior['id']);
				if(count($pagina_actual)){
					$pagina_actual=$pagina_actual[0];
					$info=explode('-',$segments[$new_segment_pos]);
					
					try{
						$parametro=new Persistent($prefix.'_'.$pagina_actual['parametro'],$info[0]);
					}
					catch(Exception $ex){
						$pagina_actual=null;
						break;
					}
					
					if($parametro->id){
						require_once $app_dir.'templates/page_template.php';
						require_once $app_dir.$pagina_actual['controlador'];
						
						// Llamamos al método
						$params=array_splice($segments,$new_segment_pos);
						$params[0]=$parametro;
						call_user_func_array(array(new UrlController($params,model()->fw_paginas($pagina_actual['id'])), 'execute'), $params);
						exit;
					}
				}
			
				$pagina_actual=null;
				break;
			}
			$pagina_anterior=$pagina_actual;
		}
		$new_segment_pos++;
	}
	
	if($pagina_actual or ($pagina_anterior and is_numeric($segments[$new_segment_pos]))){
		require_once $app_dir.'templates/page_template.php';
		require_once $app_dir.$pagina_anterior['controlador'];
		
		// Llamamos al método
		$params=array_splice($segments,$new_segment_pos);
		call_user_func_array(array(new UrlController($params,model()->fw_paginas($pagina_anterior['id'])), 'execute'), $params);
		exit;
	}
}

if(file_exists($app_dir.'model/urls.php')) require_once $app_dir.'model/urls.php';

// Aquí termina la nueva

// Buscamos el archivo al que corresponde la URL
while($segment_pos<count($segments)){
	if(!$segments[$segment_pos]) $segments[$segment_pos]='index';
	if(file_exists($route.$segments[$segment_pos].'.php')){
		$route.=$segments[$segment_pos].'.php';
		$notfile=0;
		$segment_pos++;
		break;
	}
	else if(file_exists($route.$segments[$segment_pos])){
		$route.=$segments[$segment_pos].'/';
	}
	else if(file_exists($route.'index.php')){
		$route.='index.php';
		$notfile=0;
		break;
	}
	else{
		header("HTTP/1.0 404 Not Found");
		die();
		//include($_SERVER['DOCUMENT_ROOT'].'/404.shtml');
		//trigger_error('no existe el archivo o directorio "'.$route.$segments[$segment_pos].'"',E_USER_ERROR);
	}
	$segment_pos++;
}

if($notfile){
	$route.='index.php';
	if(!file_exists($route)){
		header("HTTP/1.0 404 Not Found");
		die();
		//include($_SERVER['DOCUMENT_ROOT'].'/404.shtml');
		//trigger_error('no existe el archivo o directorio "'.$route.'"',E_USER_ERROR);
	}
}

require_once $app_dir.'templates/page_template.php';
require_once $route;

// Llamamos al método
$params=array_splice($segments,$segment_pos);
call_user_func_array(array(new UrlController($params), 'execute'), $params);
?>
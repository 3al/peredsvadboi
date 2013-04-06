<?php
define('SLA_VERSION', '5');
require_once(dirname(__FILE__) . "/slconfig.php");

class SLArticlesClient {
    var $Config;
    var $_data = array();
    var $host = '';
    var $uri = false;
    var $servercachetime = 0;
    var $cachetime = 0;
    var $errortime = 0;
    var $article_id = false;
    var $catalog_url = '';
    var $code_comment = '';
    var $a_str = array();
    var $uricrc = '';

    function SLArticlesClient($uri = '') {
        $this->Config = new SLConfig;

        if (!empty($uri))
            $this->uri = $uri;
        else
            $this->uri = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $HTTP_SERVER_VARS['REQUEST_URI']);
        if (strlen(session_id()) > 0) {
            $session = session_name() . "=" . session_id();
            $this->uri = str_replace(array('?' . $session, '&' . $session), '', $this->uri);
        }

        if ($this->Config->connecttype == 'CURL' AND !function_exists('curl_init'))
            $this->Error('CURL not found!');
        else if ($this->Config->connecttype == 'SOCKET' AND !function_exists('fsockopen'))
            $this->Error('fsockopen not found!');
        else if ($this->Config->connecttype == 'NONE'
            );
        else {
            if (function_exists('curl_init'))
                $this->Config->connecttype = 'CURL';
            else if (function_exists('fsockopen'))
                $this->Config->connecttype = 'SOCKET';
            else
                $this->Config->connecttype = 'NONE';
        }
        $this->host = $_SERVER['HTTP_HOST'];

        if (substr($this->host, 0, 4) == 'www.')
            $this->host = substr($this->host, 4);
        if (isset($this->Config->aliases[$this->host]))
            $this->host = $this->Config->aliases[$this->host];
        if (empty($this->Config->cachedir))
            $this->Config->cachedir = dirname(__FILE__) . "/cache/";
        if (!is_dir($this->Config->cachedir))
            $this->Error("Can't open cache dir!");
        else if (!is_writable($this->Config->cachedir))
            $this->Error("Cache dir: Permission denied!");
        $this->uricrc = sprintf("%u", crc32($this->host . $this->uri));

        if (empty($this->cachefile))
            $this->cachefile = strtolower($this->host) . '.' . $this->uricrc . '.article';

        $this->code_comment = '<!--' . substr($this->Config->password, 0, 4) . '-->';

        if (!$this->IsCached()) {
            if (!$this->DownloadArticles()) {
                $this->Error("host error");
            }
        }
        //var_dump ($this);
    }

    // public
    function print_article() {
        echo $this->getArticle();
    }

    function getArticle() {
        if (stristr($this->_data,"SetLinks Article error") === false) {
            return $this->_data;
        } elseif (isset($_COOKIE['sl_bot']) && $_COOKIE['sl_bot']==$this->Config->password) {
            return $this->_data;
        } else {
            return header('HTTP/1.x 404 Not Found');
        }

    }

    // private
    function IsCached() {
        if (!is_file($this->Config->cachedir . $this->cachefile))
            return false;
        $h = @file_get_contents($this->Config->cachedir . $this->cachefile);
        if ($h) {
            $retrn = true;
        } else {
            $retrn = false;
        }

        if ($retrn && ($this->cachetime + $this->Config->cachetimeout > time()))
            $retrn = true;
        else
            $retrn = false;
        if ($retrn) {
            $this->_data = $h;
        } else {
            $this->_data = '<html><title>SetLinks error</title><body><font color="red">SetLinks Article error </font><br></body></html>';
        }
        return $retrn;
    }

    //private
    function Error($error) {
        print('<html><title>SetLinks error</title><body><font color="red">SetLinks Article error: ' . $error . " </font><br>\n</body></html>");
    }

    // private
    function DownloadArticles() {
        $page = false;
        $path = $this->Config->path .
                '?user_id=' . $this->Config->userId .
                '&hash=' . $this->Config->password .
                '&host=' . $this->host .
                '&time=' . time() .
                '&uri=' . urlencode($this->uri) .
                '&uricrc=' . $this->uricrc .
                '&v=' . SLA_VERSION;
        //var_dump ($path);
        //var_dump ($this);
        if ($this->Config->connecttype == "CURL") {
            $curl = curl_init($this->Config->server . $path);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->Config->sockettimeout);
            curl_setopt($curl, CURLOPT_TIMEOUT, $this->Config->sockettimeout);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $page = curl_exec($curl);
            if (curl_error($curl) OR curl_getinfo($curl, CURLINFO_HTTP_CODE) != '200') {
                curl_close($curl);
                return false;
            }
            curl_close($curl);
        } else if ($this->Config->connecttype == "SOCKET") {
            $fp = @fsockopen($this->Config->server, 80);
            if (!$fp) {
                return false;
            } else {
                fputs($fp, "GET " . $path .
                        " HTTP/1.0\r\nHost: " . $this->Config->server . "\r\nConnection: Close\r\n\r\n");
                socket_set_timeout($fp, $this->Config->sockettimeout);
                $page = '';
                while (!feof($fp)) {
                    $page .= fread($fp, 2048);
                }
                $status = socket_get_status($fp);
                fclose($fp);
                if ($status['unread_bytes'] == 0 && $status['timed_out'] != 1) {
                    $page = substr($page, strpos($page, "\r\n\r\n") + 4);
                } else
                    return false;
            }
        } else
            return false;

        $page = trim($page);
        if (strlen($page) < 20)
            return false;
        $this->SaveArticles($page);
        //var_dump ($page);
        return true;
    }

    //private
    function SaveArticles($page) {
        $h = @fopen($this->Config->cachedir . $this->cachefile, "w+");
        if ($h) {
            @fwrite($h, $page);
            @fclose($h);
            $this->_data = $page;
        } else {
            $this->Error('Can\'t open cache file!');
        }
        return true;
    }

}

$art = new SLArticlesClient;
$art->print_article();
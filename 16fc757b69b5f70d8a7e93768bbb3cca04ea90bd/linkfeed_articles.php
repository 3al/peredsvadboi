<?php
class LinkfeedCommon{
  var $lc_version           = '0.4.2a';
  var $lc_verbose           = false;
  var $lc_charset           = 'DEFAULT';
  var $lc_error             = '';
  var $lc_host              = '';
  var $lc_is_static         = false;
  var $lc_request_uri       = '';
  var $lc_multi_site        = false;
  var $lc_fetch_remote_type = '';
  var $lc_socket_timeout    = 6;
  var $lc_force_show_code   = false;
  var $lc_cache_lifetime    = 3600;
  var $lc_template_cache_lifetime = 86400;
  var $lc_cache_reloadtime  = 300;
  var $lc_use_ssl           = false;
  var $lc_ignore_tailslash  = false;  

  function LinkfeedCommon($options = null){
        if (!defined('LINKFEED_USER')) {
            return $this->raise_error("Constant LINKFEED_USER is not defined.");
        }
        $host = '';

        if (is_array($options)) {
            if (isset($options['host'])) {
                $host = $options['host'];
            }
        } elseif (strlen($options) != 0) {
            $host = $options;
            $options = array();
        } else {
            $options = array();
        }

        if (strlen($host) != 0) {
            $this->lc_host = $host;
        } else {
            $this->lc_host = $_SERVER['HTTP_HOST'];
        }

        $this->lc_host = preg_replace('{^https?://}i', '', $this->lc_host);
        $this->lc_host = preg_replace('{^www\.}i', '', $this->lc_host);
        $this->lc_host = strtolower( $this->lc_host);

        if (isset($options['is_static']) && $options['is_static']) {
            $this->lc_is_static = true;
        }

        if (isset($options['ignore_tailslash']) && $options['ignore_tailslash']) {
            $this->lc_ignore_tailslash = true;
        }

        if (isset($options['request_uri']) && strlen($options['request_uri']) != 0) {
            $this->lc_request_uri = $options['request_uri'];
        } else {
            if ($this->lc_is_static) {
                $this->lc_request_uri = preg_replace( '{\?.*$}', '', $_SERVER['REQUEST_URI']);
                $this->lc_request_uri = preg_replace( '{/+}', '/', $this->lc_request_uri);
            } else {
                $this->lc_request_uri = $_SERVER['REQUEST_URI'];
            }
        }

        $this->lc_request_uri = rawurldecode($this->lc_request_uri);

        if (isset($options['multi_site']) && $options['multi_site'] == true) {
            $this->lc_multi_site = true;
        }

        if (isset($options['verbose']) && $options['verbose']){
            $this->lc_verbose = true;
        }

        if (isset($options['charset']) && strlen($options['charset']) != 0) {
            $this->lc_charset = $options['charset'];
        }

        if (isset($options['fetch_remote_type']) && strlen($options['fetch_remote_type']) != 0) {
            $this->lc_fetch_remote_type = $options['fetch_remote_type'];
        }

        if (isset($options['socket_timeout']) && is_numeric($options['socket_timeout']) && $options['socket_timeout'] > 0) {
            $this->lc_socket_timeout = $options['socket_timeout'];
        }

        if (isset($options['force_show_code']) && $options['force_show_code']) {
            $this->lc_force_show_code = true;
        }

  }
    function fetch_remote_file($host, $path) {
        $user_agent = 'Linkfeed Client PHP ' . $this->lc_version;

        @ini_set('allow_url_fopen', 1);
        @ini_set('default_socket_timeout', $this->lc_socket_timeout);
        @ini_set('user_agent', $user_agent);

        if (
            $this->lc_fetch_remote_type == 'file_get_contents' || (
                $this->lc_fetch_remote_type == '' && function_exists('file_get_contents') && ini_get('allow_url_fopen') == 1
            )
        ) {
            if ($data = @file_get_contents('http://' . $host . $path)) {
                return $data;
            }
        } elseif (
            $this->lc_fetch_remote_type == 'curl' || (
                $this->lc_fetch_remote_type == '' && function_exists('curl_init')
            )
        ) {
            if ($ch = @curl_init()) {
                @curl_setopt($ch, CURLOPT_URL, 'http://' . $host . $path);
                @curl_setopt($ch, CURLOPT_HEADER, false);
                @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->lc_socket_timeout);
                @curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

                if ($data = @curl_exec($ch)) {
                    return $data;
                }

                @curl_close($ch);
            }
        } else {
            $buff = '';
            $fp = @fsockopen($host, 80, $errno, $errstr, $this->lc_socket_timeout);
            if ($fp) {
                @fputs($fp, "GET {$path} HTTP/1.0\r\nHost: {$host}\r\n");
                @fputs($fp, "User-Agent: {$user_agent}\r\n\r\n");
                while (!@feof($fp)) {
                    $buff .= @fgets($fp, 128);
                }
                @fclose($fp);

                $page = explode("\r\n\r\n", $buff);

                return $page[1];
            }
        }

        return $this->raise_error("Cann't connect to server: " . $host . $path);
    }

  function lc_read($filename) {
        $fp = @fopen($filename, 'rb');
        @flock($fp, LOCK_SH);
        if ($fp) {
            clearstatcache();
            $length = @filesize($filename);
            $mqr = get_magic_quotes_runtime();
            set_magic_quotes_runtime(0);
            if ($length) {
                $data = @fread($fp, $length);
            } else {
                $data = '';
            }
            set_magic_quotes_runtime($mqr);
            @flock($fp, LOCK_UN);
            @fclose($fp);

            return $data;
        }

        return $this->raise_error("Cann't get data from the file: " . $filename);
    }

    function lc_write($filename, $data) {
        $fp = @fopen($filename, 'wb');
        if ($fp) {
            @flock($fp, LOCK_EX);
            $length = strlen($data);
            @fwrite($fp, $data, $length);
            @flock($fp, LOCK_UN);
            @fclose($fp);

            if (md5($this->lc_read($filename)) != md5($data)) {
                return $this->raise_error("Integrity was breaken while writing to file: " . $filename);
            }

            return true;
        }

        return $this->raise_error("Cann't write to file: " . $filename);
    }

    function raise_error($e) {
        $this->lc_error = '<!--ERROR: ' . $e . '-->';
        return false;
    }  
}


class LinkfeedClient extends LinkfeedCommon{
    var $lc_server            = 'db.linkfeed.ru';
    var $lc_links_db_file     = '';
    var $lc_links             = array();
    var $lc_links_page        = array();
    var $lc_links_delimiter   = '';

    function LinkfeedClient($options = null) {
        parent::LinkfeedCommon($options);
        $this->load_links();
        if (isset($this->lc_links['__linkfeed_debug__'])) {
            $this->lc_force_show_code = true;
        }
    }

    function load_links() {
        if ($this->lc_multi_site) {
            $this->lc_links_db_file = dirname(__FILE__) . '/linkfeed.' . $this->lc_host . '.links.db';
        } else {
            $this->lc_links_db_file = dirname(__FILE__) . '/linkfeed.links.db';
        }

        if (!is_file($this->lc_links_db_file)) {
            if (@touch($this->lc_links_db_file, time() - $this->lc_cache_lifetime)) {
                @chmod($this->lc_links_db_file, 0666);
            } else {
                return $this->raise_error("There is no file " . $this->lc_links_db_file  . ". Fail to create. Set mode to 777 on the folder.");
            }
        }

        if (!is_writable($this->lc_links_db_file)) {
            return $this->raise_error("There is no permissions to write: " . $this->lc_links_db_file . "! Set mode to 777 on the folder.");
        }

        @clearstatcache();

        if (filemtime($this->lc_links_db_file) < (time()-$this->lc_cache_lifetime) ||
           (filemtime($this->lc_links_db_file) < (time()-$this->lc_cache_reloadtime) && filesize($this->lc_links_db_file) == 0)) {

            @touch($this->lc_links_db_file, time());

            $path = '/' . LINKFEED_USER . '/' . strtolower( $this->lc_host ) . '/' . strtoupper( $this->lc_charset);

            if ($links = $this->fetch_remote_file($this->lc_server, $path)) {
                if (substr($links, 0, 12) == 'FATAL ERROR:') {
                    $this->raise_error($links);
                } else if (@unserialize($links) !== false) {
                    $this->lc_write($this->lc_links_db_file, $links);
                } else {
                    $this->raise_error("Cann't unserialize received data.");
                }
            }
        }

        $links = $this->lc_read($this->lc_links_db_file);
        $this->lc_file_change_date = gmstrftime ("%d.%m.%Y %H:%M:%S",filectime($this->lc_links_db_file));
        $this->lc_file_size = strlen( $links);
        if (!$links) {
            $this->lc_links = array();
            $this->raise_error("Empty file.");
        } else if (!$this->lc_links = @unserialize($links)) {
            $this->lc_links = array();
            $this->raise_error("Cann't unserialize data from file.");
        }

        if (isset($this->lc_links['__linkfeed_delimiter__'])) {
            $this->lc_links_delimiter = $this->lc_links['__linkfeed_delimiter__'];
        }

        $lc_links_temp=array();
        foreach($this->lc_links as $key=>$value){
          $lc_links_temp[rawurldecode($key)]=$value;
        }
        $this->lc_links=$lc_links_temp;


        if ($this->lc_ignore_tailslash && $this->lc_request_uri[strlen($this->lc_request_uri)-1]=='/') $this->lc_request_uri=substr($this->lc_request_uri,0,-1);
	    $this->lc_links_page=array();
        if (array_key_exists($this->lc_request_uri, $this->lc_links) && is_array($this->lc_links[$this->lc_request_uri])) {
            $this->lc_links_page = array_merge($this->lc_links_page, $this->lc_links[$this->lc_request_uri]);
        }
	    if ($this->lc_ignore_tailslash && array_key_exists($this->lc_request_uri.'/', $this->lc_links) && is_array($this->lc_links[$this->lc_request_uri.'/'])) {
            $this->lc_links_page =array_merge($this->lc_links_page, $this->lc_links[$this->lc_request_uri.'/']);
        }

        $this->lc_links_count = count($this->lc_links_page);
    }

    function return_links($n = null) {
        $result = '';
        if (isset($this->lc_links['__linkfeed_start__']) && strlen($this->lc_links['__linkfeed_start__']) != 0 &&
            (in_array($_SERVER['REMOTE_ADDR'], $this->lc_links['__linkfeed_robots__']) || $this->lc_force_show_code)
        ) {
            $result .= $this->lc_links['__linkfeed_start__'];
        }

        if (isset($this->lc_links['__linkfeed_robots__']) && in_array($_SERVER['REMOTE_ADDR'], $this->lc_links['__linkfeed_robots__']) || $this->lc_verbose) {

            if ($this->lc_error != '') {
                $result .= $this->lc_error;
            }

            $result .= '<!--REQUEST_URI=' . $_SERVER['REQUEST_URI'] . "-->\n";
            $result .= "\n<!--\n";
            $result .= 'L ' . $this->lc_version . "\n";
            $result .= 'REMOTE_ADDR=' . $_SERVER['REMOTE_ADDR'] . "\n";
            $result .= 'request_uri=' . $this->lc_request_uri . "\n";
            $result .= 'charset=' . $this->lc_charset . "\n";
            $result .= 'is_static=' . $this->lc_is_static . "\n";
            $result .= 'multi_site=' . $this->lc_multi_site . "\n";
            $result .= 'file change date=' . $this->lc_file_change_date . "\n";
            $result .= 'lc_file_size=' . $this->lc_file_size . "\n";
            $result .= 'lc_links_count=' . $this->lc_links_count . "\n";
            $result .= 'left_links_count=' . count($this->lc_links_page) . "\n";
            $result .= 'n=' . $n . "\n";
            $result .= '-->';
        }

        if (is_array($this->lc_links_page)) {
            $total_page_links = count($this->lc_links_page);

            if (!is_numeric($n) || $n > $total_page_links) {
                $n = $total_page_links;
            }

            $links = array();

            for ($i = 0; $i < $n; $i++) {
                $links[] = array_shift($this->lc_links_page);
            }

            if ( count($links) > 0 && isset($this->lc_links['__linkfeed_before_text__']) ) {
               $result .= $this->lc_links['__linkfeed_before_text__'];
            }

            $result .= implode($this->lc_links_delimiter, $links);

            if ( count($links) > 0 && isset($this->lc_links['__linkfeed_after_text__']) ) {
               $result .= $this->lc_links['__linkfeed_after_text__'];
            }
        }
        if (isset($this->lc_links['__linkfeed_end__']) && strlen($this->lc_links['__linkfeed_end__']) != 0 &&
            (in_array($_SERVER['REMOTE_ADDR'], $this->lc_links['__linkfeed_robots__']) || $this->lc_force_show_code)
        ) {
            $result .= $this->lc_links['__linkfeed_end__'];
        }
        return $result;
    }    
}


class LinkfeedArticlesClient extends LinkfeedCommon{
    var $lc_server            = 'db.linkfeed.ru';    
    var $lc_meta              = array();
    var $lc_article           = array();
    var $lc_templates         = array();
    var $lc_data_filename     = '';
    var $lc_request_mode      = '';
    var $lc_db_file           = '';
    var $lc_delimiter         = '';
    var $lc_announcements_page= array();
    var $lc_announcements_count = 0;
    var $lc_mask_code         = '';

    function LinkfeedArticlesClient($options = null){
        parent::LinkfeedCommon($options);
        $this->get_meta();
        if (isset($this->lc_meta['__linkfeed_debug__'])) {
            $this->lc_verbose = true;
        }
        if (!empty($this->lc_meta['delimiter'])) {
            $this->lc_delimiter = $this->lc_meta['delimiter'];
        }
    }

    function load_data($artid=null) {
        if ($this->lc_multi_site){
            $this->lc_db_file = dirname(__FILE__) . '/' . $this->_host . '.' . $this->lc_data_filename;
        }
        else{
            $this->lc_db_file = dirname(__FILE__) . '/' . $this->lc_data_filename;
        }
        $new=false;
        if (!is_file($this->lc_db_file)) {
            $new=true;
            if (@touch($this->lc_db_file)) {
                @chmod($this->lc_db_file, 0666);
            } else {
                return $this->raise_error("There is no file " . $this->lc_db_file  . ". Fail to create. Set mode to 777 on the folder.");
            }
        }

        if (!is_writable($this->lc_db_file)) {
            return $this->raise_error("There is no permissions to write: " . $this->lc_db_file . "! Set mode to 777 on the folder.");
        }

        @clearstatcache();

        if (filemtime($this->lc_db_file) < (time()-$this->lc_cache_lifetime) ||
           (filemtime($this->lc_db_file) < (time()-$this->lc_cache_reloadtime) && filesize($this->lc_db_file) == 0) || $new) {

            @touch($this->lc_links_db_file, time());
            if ($artid || $artid == '0')
                $path = '/' . LINKFEED_USER . '/' . strtolower( $this->lc_host ) . '/' . strtolower($this->lc_request_mode).'_'.strtoupper( $this->lc_charset).'_'.$artid;
            else
                $path = '/' . LINKFEED_USER . '/' . strtolower( $this->lc_host ) . '/' . strtolower($this->lc_request_mode).'_'.strtoupper( $this->lc_charset);
                if ($data = $this->fetch_remote_file($this->lc_server, $path)) {
                if (substr($data, 0, 12) == 'FATAL ERROR:') {
                    $this->raise_error($data);
                } else if (@unserialize($data) !== false) {
                    $this->lc_write($this->lc_db_file, $data);
                } else {
                    $this->raise_error("Cann't unserialize received data.");
                }
            }
        }


        $data = $this->lc_read($this->lc_db_file);
        $this->lc_file_change_date = gmstrftime ("%d.%m.%Y %H:%M:%S",filectime($this->lc_db_file));
        $this->lc_file_size = strlen( $data);
        if (!$data) {
            if ($this->lc_request_mode=='article_meta'){
                $this->lc_meta = array();
            }else{
                $this->lc_article = array();
            }
            $this->raise_error("Empty file.");
        } else {
            if (!$unserialized_data = @unserialize($data)) {

                if ($this->lc_request_mode=='article_meta'){
                    $this->lc_meta = array();
                }else{
                    $this->lc_article = array();
                }
                $this->raise_error("Cann't unserialize data from file.");
            }
            else{
              if ($this->lc_request_mode=='article_meta'){
                    $this->lc_meta = $unserialized_data;
                }else{
                    $this->lc_article = $unserialized_data;
               }
            }
        }

        if ($this->lc_request_mode=='article_meta'){
            if (isset($this->lc_meta['__linkfeed_delimiter__'])) {
                $this->lc_delimiter = $this->lc_meta['__linkfeed_delimiter__'];
            }

            $lc_announcements_temp=array();
            foreach($this->lc_meta['announcements'] as $key=>$value){
              $lc_announcements_temp[rawurldecode($key)]=$value;
            }
            $this->lc_meta['announcements']=$lc_announcements_temp;

            $lc_articles_temp=array();
            foreach($this->lc_meta['articles'] as $key=>$value){
              $lc_articles_temp[rawurldecode($key)]=$value;
            }
            $this->lc_meta['articles']=$lc_articles_temp;

            if (is_array($this->lc_meta) && is_array($this->lc_meta['announcements']) && array_key_exists($this->lc_request_uri, $this->lc_meta['announcements']) && is_array($this->lc_meta['announcements'][$this->lc_request_uri])) {
                $this->lc_announcements_page = $this->lc_meta['announcements'][$this->lc_request_uri];
            }
            $this->lc_announcements_count = count($this->lc_announcements_page);
        }
    }


    function return_announcements($n = null, $offset = 0){
        $result = '';
        if (isset($this->lc_meta['__linkfeed_start__']) && strlen($this->lc_meta['__linkfeed_start__']) != 0 &&
            (in_array($_SERVER['REMOTE_ADDR'], $this->lc_meta['__linkfeed_robots__']) || $this->lc_force_show_code)
        ) {
            $result .= $this->lc_meta['__linkfeed_start__'];
        }                

        if (isset($this->lc_meta['__linkfeed_robots__']) && in_array($_SERVER['REMOTE_ADDR'], $this->lc_meta['__linkfeed_robots__']) || $this->lc_verbose) {

            if ($this->lc_error != '') {
                $result .= $this->lc_error;
            }
            $result .= '<!--REQUEST_URI=' . $_SERVER['REQUEST_URI'] . "-->\n";
            $result .= "\n<!--\n";
            $result .= 'L ' . $this->lc_version . "\n";
            $result .= 'REMOTE_ADDR=' . $_SERVER['REMOTE_ADDR'] . "\n";
            $result .= 'request_uri=' . $this->lc_request_uri . "\n";
            $result .= 'charset=' . $this->lc_charset . "\n";
            $result .= 'is_static=' . $this->lc_is_static . "\n";
            $result .= 'multi_site=' . $this->lc_multi_site . "\n";
            $result .= 'file change date=' . $this->lc_file_change_date . "\n";
            $result .= 'lc_file_size=' . $this->lc_file_size . "\n";
            $result .= 'lc_announcements_count=' . $this->lc_announcements_count . "\n";
            $result .= 'left_announcements_count=' . count($this->lc_announcements_page) . "\n";
            $result .= 'n=' . $n . "\n";
            $result .= '-->';
        }

        if (is_array($this->lc_announcements_page)) {
            $total_page_announcements = count($this->lc_announcements_page);

            if (!is_numeric($n) || $n > $total_page_announcements) {
                $n = $total_page_announcements;
            }

            $announcements = array();

            for ($i = 0; $i < $n; $i++) {
                $announcements[] = array_shift($this->lc_announcements_page);
            }

            if ( count($announcements) > 0 && isset($this->lc_meta['__linkfeed_before_text__']) ) {
               $result .= $this->lc_meta['__linkfeed_before_text__'];
            }

            $result .= implode($this->lc_delimiter, $announcements);

            if ( count($announcements) > 0 && isset($this->lc_meta['__linkfeed_after_text__']) ) {
               $result .= $this->lc_meta['__linkfeed_after_text__'];
            }
        }
        if (isset($this->lc_meta['__linkfeed_end__']) && strlen($this->lc_meta['__linkfeed_end__']) != 0 &&
            (in_array($_SERVER['REMOTE_ADDR'], $this->lc_meta['__linkfeed_robots__']) || $this->lc_force_show_code)
        ) {
            $result .= $this->lc_meta['__linkfeed_end__'];
        }
        return $result;
    }

    function get_meta(){
        $this->lc_request_mode='article_meta';
        $this->lc_data_filename = 'articles.db';
        $this->load_data();
    }

    function return_article(){
        $result = '';
        if (isset($this->lc_meta['__linkfeed_start__']) && strlen($this->lc_meta['__linkfeed_start__']) != 0 &&
            (in_array($_SERVER['REMOTE_ADDR'], $this->lc_meta['__linkfeed_robots__']) || $this->lc_force_show_code)
        ) {
            $result .= $this->lc_meta['__linkfeed_start__'];
        }

        if (isset($this->lc_meta['__linkfeed_robots__']) && in_array($_SERVER['REMOTE_ADDR'], $this->lc_meta['__linkfeed_robots__']) || $this->lc_verbose) {

            if ($this->lc_error != '') {
                $result .= $this->lc_error;
            }
            $result .= '<!--REQUEST_URI=' . $_SERVER['REQUEST_URI'] . "-->\n";
            $result .= "\n<!--\n";
            $result .= 'L ' . $this->lc_version . "\n";
            $result .= 'REMOTE_ADDR=' . $_SERVER['REMOTE_ADDR'] . "\n";
            $result .= 'request_uri=' . $this->lc_request_uri . "\n";
            $result .= 'charset=' . $this->lc_charset . "\n";
            $result .= 'is_static=' . $this->lc_is_static . "\n";
            $result .= 'multi_site=' . $this->lc_multi_site . "\n";
            $result .= 'file change date=' . $this->lc_file_change_date . "\n";
            $result .= 'lc_file_size=' . $this->lc_file_size . "\n";            
            $result .= '-->';
        }


        if (!empty($this->lc_meta) and isset($this->lc_meta['articles'][$this->lc_request_uri])) {
            $result .= $this->fetch_article();
        } else if ($this->lc_verbose && isset($_GET['tpl_id'])) {
            $result .= $this->fetch_test_article();
        }

        if (isset($this->lc_meta['__linkfeed_end__']) && strlen($this->lc_meta['__linkfeed_end__']) != 0 &&
            (in_array($_SERVER['REMOTE_ADDR'], $this->lc_meta['__linkfeed_robots__']) || $this->lc_force_show_code)
        ) {
            $result .= $this->lc_meta['__linkfeed_end__'];
        }
        return $result;
    }

    function fetch_test_article(){
        $tpl_id = $_GET['tpl_id'];
        $this->lc_request_mode='article';
        $this->lc_data_filename = '0.article.db';
        $this->load_data("0");
        $template = $this->load_template($this->lc_meta['templates'][$tpl_id]['url'], $tpl_id);
        $this->http_wrap();
        return $this->prepare_article($template);
    }

    function fetch_article(){
        $article_meta = $this->lc_meta['articles'][$this->lc_request_uri];
        $this->lc_request_mode='article';
        $this->lc_data_filename = $article_meta['id'] . '.article.db';
        $this->load_data($article_meta['id']);
        $template = $this->load_template($this->lc_meta['templates'][$article_meta['template_id']]['url'], $article_meta['template_id']);
        $this->http_wrap();
        return $this->prepare_article($template);
    }

    function prepare_article($template){
        if (strlen($this->lc_charset)) {
            $template = str_replace('{meta_charset}',  $this->lc_charset, $template);
        }
        foreach ($this->lc_meta['template_fields'] as $field){
            if (isset($this->lc_article[$field])) {
                $template = str_replace('{' . $field . '}',  $this->lc_article[$field], $template);
            } else {
                $template = str_replace('{' . $field . '}',  '', $template);
            }
        }
        return ($template);
    }

    function load_template($template_url, $templateId){
        $this->lc_data_filename = dirname(__FILE__).'/templates.db';
        if (file_exists($this->lc_data_filename)) {
            $this->lc_templates = unserialize($this->lc_read($this->lc_data_filename));
        }

        if (!isset($this->lc_templates[$template_url])
            or (time() - $this->lc_templates[$template_url]['date_updated']) > $this->lc_template_cache_lifetime) {
            $this->reload_template($template_url, $this->lc_data_filename);
        }

        if (!isset($this->lc_templates[$template_url])) {
            return $this->raise_error('Не найден шаблон для статьи');
        }

        return $this->lc_templates[$template_url]['template'];
    }

    function reload_template($template_url, $save_file){
        $url = parse_url($template_url);

        $clean_url = '';
        if ($url['path']) {
            $clean_url .= $url['path'];
        }
        if (isset($url['query'])) {
            $clean_url .= '?' . $url['query'];
        }

        $template = $this->fetch_remote_file($this->lc_host, $clean_url);

        if (!$this->validate_template($template)){
            return false;
        }

        $template = $this->cut_template_links($template);
        $this->lc_templates[$template_url] = array( 'template' => $template, 'date_updated' => time());
        $this->lc_write($this->lc_data_filename, serialize($this->lc_templates));
    }

    function fill_mask ($data) {
        global $unnecessary;
        $len = strlen($data[0]);
        $mask = str_repeat($this->lc_mask_code, $len);
        $unnecessary[$this->lc_mask_code][] = array(
            'mask' => $mask,
            'code' => $data[0],
            'len'  => $len
        );

        return $mask;
    }

    function cut_unnecessary(&$contents, $code, $mask) {
        global $unnecessary;
        $this->lc_mask_code = $code;
        $_unnecessary[$this->lc_mask_code] = array();
        $contents = preg_replace_callback($mask, array($this, 'fill_mask'), $contents);
    }

    function restore_unnecessary(&$contents, $code) {
        global $unnecessary;
        $offset = 0;
        if (!empty($unnecessary[$code])) {
            foreach ($unnecessary[$code] as $meta) {
                $offset = strpos($contents, $meta['mask'], $offset);
                $contents = substr($contents, 0, $offset)
                    . $meta['code'] . substr($contents, $offset + $meta['len']);
            }
        }
    }

    function cut_template_links($template_body){
        $link_pattern    = '~(\<a [^\>]*?href[^\>]*?\=["\']{0,1}http[^\>]*?\>.*?\</a[^\>]*?\>)~si';
        $link_subpattern = '~\<a~si';
        $rel_pattern     = '~[\s]{1}rel\=["\']{1}[^ "\'\>]*?["\']{1}| rel\=[^ "\'\>]*?[\s]{1}~si';
        $href_pattern    = '~[\s]{1}href\=["\']{0,1}(http[^ "\'\>]*)?["\']{0,1} {0,1}~si';
        $noindex_or_script_pattern = "~
                        (
                            \<noindex.*?\> .*?\<\/noindex.*?\>
                        )
                   ~six";
        $allowed_domains = $this->_data['index']['ext_links_allowed'];
        $allowed_domains[] = $this -> _host;
        $allowed_domains[] = 'www.' . $this -> _host;
        $this->cut_unnecessary($template_body, 'C', '|<!--(.*?)-->|smi');
        $this->cut_unnecessary($template_body, 'S', '|<script[^>]*>.*?</script>|si');
        $noindex_slices = @preg_split($noindex_or_script_pattern, $template_body, -1, PREG_SPLIT_DELIM_CAPTURE );
        if (is_array($noindex_slices)) {
            foreach ($noindex_slices as $nid => $ni_slice) {
                if ($nid % 2 != 0){
                    $is_noindex = true;
                } else {
                    $is_noindex = false;
                }

                $slices = preg_split($link_pattern, $ni_slice, -1,  PREG_SPLIT_DELIM_CAPTURE );
                if(is_array($slices)) {
                    foreach ($slices as $id => $link) {
                        if ($id % 2 == 0){
                            continue;
                        }
                        if (preg_match($href_pattern, $link, $urls)) {
                            $parsed_url = @parse_url($urls[1]);
                            $host = isset($parsed_url['host'])?$parsed_url['host']:false;
                            if (!in_array($host, $allowed_domains) || !$host){
                                $slices[$id] = preg_replace($rel_pattern, '', $link);
                                $slices[$id] = preg_replace($link_subpattern, '<a rel="nofollow" ', $slices[$id]);
                                if (!$is_noindex){
                                    $slices[$id] = '<noindex>' . $slices[$id] . '</noindex>';
                                }
                            }
                        }
                    }
                }
                $noindex_slices[$nid] = implode('', $slices);
            }
        }

        $template_body = implode('', $noindex_slices);
        $this->restore_unnecessary($template_body, 'S');
        $this->restore_unnecessary($template_body, 'C');
        return $template_body;
    }

    function validate_template($template){
        foreach ($this->lc_meta['template_fields'] as $field){
            if (strpos($template, '{' . $field . '}') === false){
                return $this->raise_error($field . 'field not fount in the template');
                return false;
            }
        }
        return true;
    }

    function http_wrap(){
        @header('HTTP/1.x 200 OK');
        if (!empty($this->_charset)){
           @header('Content-Type: text/html; charset=' . $this->lc_charset);
        }
    }

}
?>

<?php
  define('LINKFEED_USER', '16fc757b69b5f70d8a7e93768bbb3cca04ea90bd');
  require_once($_SERVER['DOCUMENT_ROOT'].'/'.LINKFEED_USER.'/linkfeed_articles.php');      
  $linkfeed = new LinkfeedArticlesClient();
  echo $linkfeed->return_article();
?>
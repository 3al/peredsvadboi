<?php
/*
 Код вызова ссылок SetLinks.ru.
 Версия 3.2.8.
*/
require_once(dirname(__FILE__)."/slclient.php");

if(isset($_GET['SSIURI'])) {
    $query = (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : $HTTP_SERVER_VARS['QUERY_STRING']);
    $uri = substr($query, strpos($query, 'SSIURI=')+7);
} else die("SSI error!");
$sl = new SLClient($uri);
if(isset($_GET['SSISTART'])) 
    $sl->SetCursorPosition($_GET['SSISTART']);
if(isset($_GET['SSICOUNT']))
    echo $sl->GetLinks($_GET['SSICOUNT']);
else
    echo $sl->GetLinks();
?>

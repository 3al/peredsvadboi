<?php
/*
 Код вызова объявлений SetLinks.ru.
 Версия 3.2.8.
*/
require_once(dirname(__FILE__)."/slclient.php");

$sl = new SLClient();
echo $sl->ForeverLinks();

?>

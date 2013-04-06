<?php
/*
 Код вызова ссылок SetLinks.ru.
 Вывод ссылок одним блоком
 Версия 3.2.8.
*/
require_once(dirname(__FILE__)."/slclient.php");

$sl = new SLClient();
echo $sl->GetLinks(); 

?>

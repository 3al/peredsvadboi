<?php
/*
 Код вызова ссылок SetLinks.ru.
 Версия 3.2.8.
*/
require_once(dirname(__FILE__)."/slclient.php");

$sl = new SLClient();

// For links
$smarty->register_function("sl_get_links", "print_sl_links");
function print_sl_links($params) {
    global $sl;
    
    if(isset($params['start']))
        $sl->SetCursorPosition($params['start']);
    if(isset($params['count']))
        return $sl->GetLinks($params['count']);
    else
        return $sl->GetLinks();        
}

// For offers
$smarty->register_function("sl_get_offers", "print_sl_offers");
function print_sl_offers($params) {
    global $sl;
    return $sl->ForeverLinks();
}
?>

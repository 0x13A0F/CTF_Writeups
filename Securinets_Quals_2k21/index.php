<?php

error_reporting(0);
function waf($str){
     for($i=0;$i<=strlen($str)-1;$i++){
        if ((ord($str[$i])<32) or (ord($str[$i])>126)){
            header("HTTP/1.1 403 Forbidden" );
                        exit;
        }
     }
     $blacklist = ['[A-Zb-df-km-uw-z]',' ', '\t', '\r', '\n','\'', '"', '`', '\[', '\]','\$','\\','\^','~'];
        foreach ($blacklist as $blackitem) {
                if (preg_match('/' . $blackitem . '/m', $str)) {
                        header("HTTP/1.1 403 Forbidden" );
                        exit;
                        //die('You are forbidden!');
                }
        }
}
if(!isset($_GET['cmd'])){
    show_source(__FILE__);
}else{
        $str = $_GET['cmd'];
       
        waf($str);
        eval('echo '.$str.';');
}
?>

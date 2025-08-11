<?php
     require_once __DIR__ . '/vendor/autoload.php'; 

     use TECWEB\MYAPI\READ\Read as Read;  

    $prodObj = new Read('marketzone'); 
    $prodObj->list(); 
 
    echo json_encode($prodObj->getData());
?>
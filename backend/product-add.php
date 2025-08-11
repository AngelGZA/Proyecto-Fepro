<?php
    require_once __DIR__ . '/vendor/autoload.php'; 

    use TECWEB\MYAPI\CREATE\Create as Create; 

    $prodObj = new Create('marketzone');
    $prodObj->add($prodObj); 
    echo json_encode($prodObj->getData());


?>
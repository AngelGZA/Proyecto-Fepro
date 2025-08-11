<?php
     require_once __DIR__ . '/vendor/autoload.php'; 

     use TECWEB\MYAPI\UPDATE\Update as Update;  
    
    $prodObj = new Update('marketzone'); 
    $prodObj->edit($prodObj);
    
    //echo json_encode ($prodObj->getData()); 
?>
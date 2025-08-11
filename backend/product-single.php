<?php
    
    require_once __DIR__ . '/vendor/autoload.php'; 

     use TECWEB\MYAPI\READ\Read as Read;; 
    
    $prodObj = new Read('marketzone'); 
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    $prodObj->single($id);
    
    echo json_encode ($prodObj->getData());
?>
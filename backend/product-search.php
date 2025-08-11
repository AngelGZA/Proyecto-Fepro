<?php
    require_once __DIR__ . '/vendor/autoload.php'; 

    use TECWEB\MYAPI\READ\Read as Read;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $prodObj = new Read('marketzone'); 

    if(!empty($search)){
        $prodObj->search($search); 
    }
    else{
        $prodObj->singleByName($search); 
    }
    
    echo json_encode ($prodObj->getData()); 
?>
 
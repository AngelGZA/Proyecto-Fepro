<?php
    namespace TECWEB\MYAPI; 

    abstract class DataBase{
        protected $conexion; 
        protected $data = NULL; 

        public function  __construct($db, $user = 'root', $pass = "Mitelefono12"){
        $this->conexion = @mysqli_connect('localhost',$user, $pass, $db);

            if(!$this->conexion){ 
                die('¡Base de datos NO conectada!');
            }
        }

        public function getData(){
            header('Content-Type: application/json');
            return json_encode($this->data, JSON_PRETTY_PRINT); 
        }
    }
?>
<?php
    namespace TECWEB\MYAPI\READ;
    use TECWEB\MYAPI\DataBase as Database; 

    Class Read extends DataBase{

        public function __construct($db){ 
            $this->data = array(); 
            parent::__construct($db); 
        }

        public function list(){
            //CREAMOS EL ARREGLO A DEVOLVERSE EN FORMA JSON
            $this->data=array(); 

            //Query de búsqueda y validación de resultados 
            if($result = $this->conexion->query("SELECT * FROM productos WHERE eliminado = 0")){

                //obtenemos resultados
                $rows = $result->fetch_all(MYSQLI_ASSOC); 
                if(!is_null($rows)){
                    //codificación a UTF-8 de datos y mapeo al arreglo de respuesta
                    foreach($rows as $num => $row){
                        foreach($row as $key => $value){
                            $this ->data[$num][$key]=$value; 
                        }
                    }
                }
                $result->free(); 
            } else{
                die('Query Error: '.mysqli_error($this->conexion)); 
            }
            //$this->conexion->close(); 
        }

        public function search($search){
            // SE CREA EL ARREGLO QUE SE VA A DEVOLVER EN FORMA DE JSON
            $this->data = array();
            // SE VERIFICA HABER RECIBIDO EL ID
            if( isset($_GET['search']) ) {
                $search = $_GET['search'];
                // SE REALIZA LA QUERY DE BÚSQUEDA Y AL MISMO TIEMPO SE VALIDA SI HUBO RESULTADOS
                $sql = "SELECT * FROM productos WHERE (id = '{$search}' OR nombre LIKE '%{$search}%' OR marca LIKE '%{$search}%' OR detalles LIKE '%{$search}%') AND eliminado = 0";
                if ( $result = $this->conexion->query($sql) ) {
                    // SE OBTIENEN LOS RESULTADOS
                    $rows = $result->fetch_all(MYSQLI_ASSOC);

                    if(!is_null($rows)) {
                        // SE CODIFICAN A UTF-8 LOS DATOS Y SE MAPEAN AL ARREGLO DE RESPUESTA
                        foreach($rows as $num => $row) {
                            foreach($row as $key => $value) {
                                $this->data[$num][$key] = utf8_encode($value);
                            }
                        }
                    }
                    $result->free();
                } else {
                    die('Query Error: '.mysqli_error($this->conexion));
                }
                $this->conexion->close();
            }
        }

        public function single($id){
            $this->data = array();

            if( isset($_POST['id']) ) {
                $id = $_POST['id'];
                // SE REALIZA LA QUERY DE BÚSQUEDA Y AL MISMO TIEMPO SE VALIDA SI HUBO RESULTADOS
                if ( $result = $this->conexion->query("SELECT * FROM productos WHERE id = {$id}") ) {
                    // SE OBTIENEN LOS RESULTADOS
                    $row = $result->fetch_assoc();
        
                    if(!is_null($row)) {
                        // SE CODIFICAN A UTF-8 LOS DATOS Y SE MAPEAN AL ARREGLO DE RESPUESTA
                        foreach($row as $key => $value) {
                            $this->data[$key] = utf8_encode($value);
                        }
                    }
                    $result->free();
                } else {
                    die('Query Error: '.mysqli_error($this->conexion));
                }
                $this->conexion->close();
            }
        }

        public function singleByName($name){
            $this->data = []; 

            if($name){
                if($stmt = $this->conexion->prepare("SELECT * FROM productos WHERE nombre = ? AND eliminado = 0")){
                    $stmt->bind_param("s", $name); 
                    if($stmt->execute()){
                        $result = $stmt->get_result(); 
                        $this->data=$result->fetch_assoc() ?? []; 
                    }
                    $stmt->close(); 
                }
            }
            $this->conexion->close(); 

        }
    }
?>
<?php
    namespace TECWEB\MYAPI; 

    use TECWEB\MYAPI\DataBase as Database; 
    require_once __DIR__ .'/DataBase.php'; 

    class Products extends DataBase{
        private $data = NULL; 

        public function __construct($db, $user='root', $pass='GASCAE10M98'){
            $this->data = array(); 
            parent::__construct($user, $pass, $db); 
        }

        public function add ($prod){
            $this->data= $prod; 
        
            $producto = file_get_contents('php://input');
            $this->data = array(
                'status'  => 'error',
                'message' => 'Ya existe un producto con ese nombre' 
            );
            if(!empty($producto)) {

                $jsonOBJ = json_decode($producto);
 
                $sql = "SELECT * FROM productos WHERE nombre = '{$jsonOBJ->nombre}' AND eliminado = 0";
                $result = $this->conexion->query($sql);
                
                if ($result->num_rows == 0) {
                    $this->conexion->set_charset("utf8");
                    $sql = "INSERT INTO productos VALUES (null, '{$jsonOBJ->nombre}', '{$jsonOBJ->marca}', '{$jsonOBJ->modelo}', {$jsonOBJ->precio}, '{$jsonOBJ->detalles}', {$jsonOBJ->unidades}, '{$jsonOBJ->imagen}', 0)";
                    if($this->conexion->query($sql)){
                        $this->data['status'] =  "success";
                        $this->data['message'] =  "Producto agregado";
                    } else {
                        $this->data['message'] = "ERROR: No se ejecuto $sql. " . mysqli_error($this->conexion);
                    }
                }

                $result->free();
     
                $this->conexion->close();
            }
        }

        public function delete ($id){
 
            $this->data = array(
                'status'  => 'error',
                'message' => 'La consulta falló'
            );
 
            if( isset($_GET['id']) ) {
                $id = $_GET['id'];
      
                $sql = "UPDATE productos SET eliminado=1 WHERE id = {$id}";
                if ( $this->conexion->query($sql) ) {
                    $this->data['status'] =  "success";
                    $this->data['message'] =  "Producto eliminado";
                } else {
                    $data['message'] = "ERROR: No se ejecuto $sql. " . mysqli_error($this->conexion);
                }
                $this->conexion->close();
            } 
        }

        public function edit ($da){
            $this->data= $da;
            $input = file_get_contents("php://input");
            $this->data = json_decode($input, true);

            // Verificar que se recibieron los datos necesarios
            if (!isset($this->data['id'], $this->data['nombre'], $this->data['precio'], $this->data['unidades'], $this->data['modelo'], $this->data['marca'], $this->data['detalles'], $this->data['imagen'])) {
                echo json_encode(["status" => "error", "message" => "Datos incompletos o inválidos."]);
                exit;
            }

            // Extraer datos del array
            $id = $this->data['id'];
            $nombre = $this->data['nombre'];
            $precio = $this->data['precio'];
            $unidades = $this->data['unidades'];
            $modelo = $this->data['modelo'];
            $marca = $this->data['marca'];
            $detalles = $this->data['detalles'];
            $imagen = $this->data['imagen'];

            // Preparar la consulta para actualizar el producto
            $sql = "UPDATE productos SET nombre = ?, precio = ?, unidades = ?, modelo = ?, marca = ?, detalles = ?, imagen = ? WHERE id = ?";
            if ($stmt = $this->conexion->prepare($sql)) {
                $stmt->bind_param('sdissssi', $nombre, $precio, $unidades, $modelo, $marca, $detalles, $imagen, $id);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(["status" => "success", "message" => "Producto actualizado correctamente."]);
                    } else {
                        echo json_encode(["status" => "error", "message" => "No se actualizó el producto. Verifica el ID o los datos."]);
                    }
                } else {
                    echo json_encode(["status" => "error", "message" => "Error al ejecutar la consulta: " . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(["status" => "error", "message" => "Error preparando la consulta: " . $this->conexion->error]);
            }

            $this->conexion->close();
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
            $this->conexion_close(); 

        }

        public function getData(){
            header('Content-Type: application/json');
            return json_encode($this->data, JSON_PRETTY_PRINT); 
        }
    }
?>

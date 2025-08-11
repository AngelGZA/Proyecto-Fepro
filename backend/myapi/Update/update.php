<?php
    namespace TECWEB\MYAPI\UPDATE;
    use TECWEB\MYAPI\DataBase as Database; 
    
    class Update extends DataBase{
        
        public function __construct($db){ 
            $this->data = array(); 
            parent::__construct($db); 
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
    }
?>
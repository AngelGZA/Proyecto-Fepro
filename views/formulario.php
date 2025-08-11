<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\DB;
$is_invalid = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $db = new DB();
    $mysqli = $db->getConnection();

    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $is_invalid = true;
    } else {
        $stmt = $mysqli->prepare(
            "SELECT * FROM (
                SELECT 'estudiante' AS tipo, idest AS id, email, password_hash FROM estudiante
                UNION ALL
                SELECT 'empresa' AS tipo, idemp AS id, email, password_hash FROM empresa
            ) AS usuarios 
            WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user["password_hash"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_type"] = $user["tipo"];
    
            // Redirigir según tipo de usuario
            if ($user["tipo"] === "estudiante") {
                header("Location: ../public/index.php");
            } else {
                header("Location: ../public/index.php");
            }   
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel = "stylesheet" href = "../assets/styleFormulario.css">
    <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
    <script src="https://unpkg.com/just-validate@latest/dist/just-validate.production.min.js" defer></script>
    <script src = "../funciones/validationFormulario.js" defer></script>
    <script src = "../funciones/scriptFormulario.js" defer></script>
</head>
<body>
        <!-- BARRA DE NAVEGACIÓN  -->
    <div class="barra-lateral">
        <div>
            <div class="nombre-pagina">
                <div class="image">
                    <img id="Lobo" src="../multimedia/logo_pagina.png" alt="Logo">
                </div>
                <span>Lobo Chamba</span>
            </div>
        </div>
        <nav class="navegacion">
            <ul class="menu-superior">
                <li>
                    <a href="../public/index.php">
                        <ion-icon name="home-outline"></ion-icon>
                        <span>Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="empresa.php">
                        <ion-icon name="briefcase"></ion-icon>
                        <span>Empresa</span>
                    </a>
                </li>
                <li>
                    <a href="estudiante.php">
                        <ion-icon name="school"></ion-icon>
                        <span>Estudiante</span>
                    </a>
                </li>
                <li>
                    <a href="graficos.php">
                        <ion-icon name="podium"></ion-icon>
                        <span>Graficos</span>
                    </a>
                </li>
            </ul>
            <ul class="menu-inferior">
                <li>
                    <a id="perfil" href="../views/formulario.php" class="<?= basename($_SERVER['PHP_SELF']) == 'formulario.php' ? 'active' : '' ?>">
                        <ion-icon name="person-add"></ion-icon>
                        <span>Iniciar Sesion</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <main>
        <div class="container">
            <div class = "container-form">
                <form class = "sign-in" method="post">
                    <h2>Iniciar Sesion</h2>
                    <?php if ($is_invalid) : ?>
                    <em>Invalid login</em>
                    <?php endif; ?>
                    <span>Ingrese su correo y contrasena</span>
                    <div class = "container-input">
                        <ion-icon name="mail-outline"></ion-icon>
                        <input type="email" name = "email" id = "email" placeholder = "Email" value= "<?= htmlspecialchars($_POST["email"] ?? "") ?>">
                    </div>
                    <div class = "container-input">
                        <ion-icon name="lock-closed-outline"></ion-icon>
                        <input type = "password" name = "password" id = "password"  placeholder = "Password">
                    </div>
                    <button class="button">Iniciar sesion</button>
                </form>
            </div>
            <div class = "container-form">
                <!--formulario de sign up -->
                <form class="sign-up" action="../public/process-signup.php" method="post" id="signup" enctype="multipart/form-data" novalidate>
                    <h2>Registrarse</h2><span>Ingrese sus datos</span>
                    <!-- Selector de tipo de usuario -->
                    <div class="container-input">
                        <ion-icon name="people-outline"></ion-icon>
                        <label for="tipo_usuario">Tipo:</label>
                        <select id="tipo_usuario" name="tipo_usuario" required onchange="mostrarCamposAdicionales()">
                            <option value="estudiante">Estudiante</option>
                            <option value="empresa">Empresa</option>
                        </select>
                    </div>
                    <!-- Campos comunes (para ambos tipos) -->
                    <div class="container-input">
                        <ion-icon name="person-outline"></ion-icon>
                        <input type="text" id="name" name="name" placeholder="Nombre completo" required>
                    </div>
                    <div class="container-input">
                        <ion-icon name="mail-outline"></ion-icon>
                        <input type="email" id="email" name="email" placeholder="Correo electrónico" required>
                    </div>
                    <div class="container-input">
                        <ion-icon name="lock-closed-outline"></ion-icon>
                        <input type="password" id="password" name="password" placeholder="Contraseña" required>
                    </div>
                    <div class="container-input">
                        <ion-icon name="lock-closed-outline"></ion-icon>
                        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirmar contraseña" required>
                    </div>
                    <!-- Campos DINÁMICOS (se muestran según selección) -->
                    <div id="campos-estudiante" style="display: none;">
                        <div class="container-input">
                            <ion-icon name="call-outline"></ion-icon>
                            <input type="tel" name="telefono_estudiante" placeholder="Teléfono" maxlenght="10">
                        </div>
                        <div class="container-input  textarea-wrapper" >
                            <ion-icon name="document-text-outline"></ion-icon>
                            <textarea name="descripcion_estudiante" placeholder="Descripción personal" maxlenght="300" ></textarea>
                        </div>
                        <div class="container-input file-input">
                            <ion-icon name="document-attach-outline"></ion-icon>
                            <span class="file-label" id="file-label">Subir CV (PDF)</span>
                            <input type="file" name="cv" accept=".pdf" id="cv-upload">
                        </div>
                    </div>
                    <div id="campos-empresa" style="display: none;">
                        <div class="container-input">
                            <ion-icon name="call-outline"></ion-icon>
                            <input type="tel" name="telefono_empresa" placeholder="Teléfono" maxlenght="10">
                        </div>
                        <div class="container-input">
                            <ion-icon name="business-outline"></ion-icon>
                            <input type="text" name="rfc" placeholder="RFC" maxlenght="13">
                        </div>
                        <div class="container-input  textarea-wrapper" >
                            <ion-icon name="location-outline"></ion-icon>
                            <textarea name="direccion_empresa" placeholder="Dirección" maxlenght="300"></textarea>
                        </div>
                    </div>
                    <button class="button">Registrarse</button>
                </form>
            </div>
            <div class = "container-welcome">
                <div class = "welcome-sign-up welcome">
                    <h3>Bienvenido</h3>
                    <p>Ingrese sus datos personales para usar las funciones del sitio</p>
                    <button class = "button" id = "btn-sign-up">Resgistrarse</button>
                </div>
                <div class = "welcome-sign-in welcome">
                    <h3>Hola</h3>
                    <p>Registrese con sus datos personales para usar todas las funciones del sitio</p>
                    <button class = "button" id = "btn-sign-in">Iniciar Sesion</button>
                </div>
            </div>
        </div>
    </main>
    <footer>
        <p>&copy; Error 404 | Todos los derechos reservados.</p>
        <p>
                Síguenos en nuestras redes:
            <a href="https://www.facebook.com/profile.php?id=61569699028545&mibextid=ZbWKwL" target="_blank">
                <ion-icon name="logo-facebook"></ion-icon>
            </a>
            <a href="https://www.instagram.com/error404_ods7?igsh=MTU4dHJrajBybWFxeQ==" target="_blank">
                <ion-icon name="logo-instagram"></ion-icon>
            </a>
            <a href="https://youtube.com/@gabrielcorona2000?si=As0KyE0q-QfsmlW0" target="_blank">
                <ion-icon name="logo-youtube"></ion-icon>
            </a>
            <a href="https://x.com/Error_404_ODS7?t=YAwltMat_BqnCXRHr-tIYQ&s=08" target="_blank">
                <ion-icon name="logo-twitter"></ion-icon>
            </a>
        </p>
    </footer>

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/scrollreveal"></script>

</body>
</html>
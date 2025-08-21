<?php require __DIR__ . '/src/bootstrap.php'; ?>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
session_start();

use App\Controllers\AuthController;

$auth = new AuthController();

$loggedIn = $auth->isLogged();
$user = $auth->getCurrentUser(); // devuelve null si no hay usuario

$username = $user['nombre'] ?? null; // o el campo que necesites mostrar

?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="stylesheet" href="/assets/modern.css">
  <link rel="stylesheet" href="/assets/animations.css">
    <meta charset="UTF-8">
    <title>CodEval</title>
    <link rel="icon" href="multimedia/logo_pagina.png" type="image/png">
    <link rel="stylesheet" href="assets/style.css">
  </head>
  
  <body>

    <!-- BARRA DE NAVEGACIÓN  -->
    <div class="barra-lateral">
        <div>
            <div class="nombre-pagina">
                <div class="image">
                    <img id="Lobo" src="multimedia/logo_pagina.png" alt="Logo">
                </div>
                <span style="color: #0097b2;">CodEval</span>
            </div>
        </div>
        <nav class="navegacion">
            <ul class="menu-superior">
                <li>
                    <a  id="house" href="index.php">
                        <ion-icon name="home-outline"></ion-icon>
                        <span>Inicio</span>
                    </a>
                </li>
                <?php if (!$loggedIn || $user['tipo'] !== 'empresa' && 'docente'): ?>
            <!-- Mostrar opción Estudiante si no está logueado o si es estudiante -->
            <li>
                <a href="views/estudiante.php">
                    <ion-icon name="school"></ion-icon>
                    <span>Estudiante</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (!$loggedIn || $user['tipo'] !== 'empresa' && 'estudiante'): ?>
            <!-- Mostrar opción Docente si no está logueado o si es docente-->
            <li>
                <a href="views/graficos.php">
                    <ion-icon name="create-outline"></ion-icon>
                    <span>Docentes</span>
                </a>
            </li>
            <?php endif; ?>

                <?php if (!$loggedIn || $user['tipo'] !== 'estudiante' && 'docente'): ?>
            <!-- Mostrar opción Empresa si no está logueado o si es empresa -->
            <li>
                <a href="views/empresa.php">
                    <ion-icon name="library-outline"></ion-icon>
                    <span>Instituciones</span>
                </a>
            </li>
            <?php endif; ?>
            
            
                <li>
                    <a href="views/graficos.php">
                        <ion-icon name="podium"></ion-icon>
                        <span>Graficos</span>
                    </a>
                </li>
            </ul>
            <ul class="menu-inferior">
                <li class="menu-item">
                    <?php if ($loggedIn && $user): ?>
                    <!-- Opción Mi Perfil -->
                    <a href="<?= htmlspecialchars($user['tipo'] === 'estudiante' ? 'views/estudiante_perfil.php' : 'views/empresa_perfil.php') ?>" class="menu-link">
                        <ion-icon name="<?= htmlspecialchars($user['tipo'] === 'estudiante' ? 'person-circle-outline' : 'business-outline') ?>"></ion-icon>
                        <span>Mi Perfil</span>
                    </a>
                    <?php else: ?>
                    <!-- Opción Iniciar Sesión -->
                    <a href="views/formulario.php" class="menu-link">
                        <ion-icon name="person-add"></ion-icon>
                        <span>Iniciar Sesión</span>
                    </a>
                    <?php endif; ?>
                </li>
                <?php if ($loggedIn && $user): ?>
                <!-- Opción Cerrar Sesión (solo visible cuando hay sesión) -->
                <li class="menu-item">
                    <a href="logout.php" class="menu-link logout-link">
                        <ion-icon name="log-out-outline"></ion-icon>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <main>
        <header>
            <div class="intro">
                <img src="multimedia/logo_pagina.png" alt="Logo de la pagina" height="150">
                <h1 style="color: #0097b2;">CodEval</h1>
               <!-- <h2>Bienvenido</h2> -->
                <p>
                    "Conocimiento asegurado, futuro verificado."
                </p>
            </div>
        </header>
        <div class="animacion-ods">
            <div class="icono">
                <ion-icon name="school-outline"></ion-icon>
            </div>
            <div class="contenido-texto">
                <div class="numero-ods">4</div>
                    <div class="texto">
                        <h2>EDUCACIÓN DE CALIDAD</h2>
                        <!--<h2>DE CALIDAD</h2>-->
                    </div>
                </div>
            </div>
        </div>
        <section class="ods-section">
            <h2>¿Por qué surge CodEval?</h2>
            <p>
                Nuestro sistema de evaluación nace de conocimientos adquiridos en la carrera <strong>Tecnologías de la Información </strong> encaminado al propósito de mejorar la gestión, accesibilidad y confiabilidad de la información académica
                mediante la administración, digitalización y verificación de credenciales buscamos impulsar una educación más segura, inclusiva y de calidad, en línea con el <a href="https://www.un.org/sustainabledevelopment/es/education/">ODS 4: "Educación de Calidad"</a>
                siguiendo la meta: <i><em>"Garantizar una educación inclusiva, equitativa y de calidad y promover oportunidades de aprendizaje durante toda la vida para todos."</em></i>

            </p>
        </section>
        <!-- Sección de tarjetas -->
        <section class="cards-section">
            <h2>¿Para qué lo hacemos?</h2>
            <div class="card-container">
                <!-- Tarjeta 1: Gestión académica eficiente -->
                <div class="card">
                    <ion-icon name="school-outline"></ion-icon>
                    <h3>Gestión académica eficiente.</h3>
                    <p>Organiza registros académicos con mejor acceso.</p>
                    <!--<a href="#" class="btn">Explorar</a>-->
                </div>
                                
                <!-- Tarjeta 2: Transpariencia y seguridad en credenciales -->
                <div class="card">
                    <ion-icon name="shield-checkmark-outline"></ion-icon>
                    <h3>Transparencia y seguridad en credenciales.</h3>
                    <p>Verifica y evita fraudes. ¡Valida tus logros!</p>
                    <!--<a href="#" class="btn">Publicar</a>-->
                </div>

                <!-- Tarjeta 3: Registro de logros académicos-->
                <div class="card">
                    <ion-icon name="trophy-outline"></ion-icon>
                    <h3>Registro de logros académicos.</h3>
                    <p>Reconoce el aprendizaje continuo.</p>
                    <!--<a href="#" class="btn">Explorar</a>-->
                </div>
            </div>
        </section>
        <section class="ods-section1">
            <h2>¿Para quién está diseñado?</h2>
            <div class="info">
                <!--Para estudiantes-->
                <div class="bloque">
                    <div class="content">
                        <a href="views/estudiante.php"><ion-icon name="school-outline"></ion-icon></a>
                        <span class="tag">ESTUDIANTES</span>
                        <h3>Encuentra un espacio que valide y organice cada uno de tus logros.</h3>
                        <p>Pon a prueba un sistema que centraliza y valida sus logros educativos de forma segura y fácil de consultar.</p>
                    </div>
                    <div class="image">
                        <img src="multimedia/estudiantes.jpeg" alt="Ilustración estudiantes">
                    </div>
                </div>
                <!--Para Educadores-->
                <div class="bloque">
                    <div class="content">
                        <a href="/views/graficos.php"><ion-icon name="create-outline"></ion-icon></a>
                        <span class="tag">DOCENTES</span>
                        <h3>¿Buscas facilitar el seguimiento académico de tus estudiantes?</h3>
                        <p>Con CodEval puedes registrar, gestionar y validar los logros de tus alumnos garantizando transpariencia y calidad educativa.</p>
                    </div>
                    <div class="image">
                        <img src="multimedia/maestro.jpeg" alt="Ilustración maestros.">
                    </div>
                </div>
                <!--Para Empresas (Universidades)-->
                <div class="bloque">
                    <div class="content">
                         <a href="views/empresa.php"><ion-icon name="library-outline"></ion-icon></a>
                        <span class="tag">INSTITUCIONES</span>
                        <h3>Garantiza la calidad educativa.</h3>
                        <p>Implementa un sistema que protege la validez de títulos fomentando la confianza entre empleadores y estudiantes.</p>
                    </div>
                    <div class="image">
                        <img src="multimedia/empresa.jpeg" alt="Ilustración instituciones.">
                    </div>
                </div>
            </div>
        </section>

        <section class="about-us">
            <h2>¿Quienes somos?</h2>
            <p>Somos un grupo de estudiantes que se encuentran cursando la carrera Ingeniería en Tecnologías de la Información, con ayuda de los conocimientos 
                adquiridos en nuestra facultad y orientados por nuestros maestros desarrollamos esta app web como una ayuda al dilema que hemos visualizado día con día 
                acerca de la eficiencia en el almacenamiento de proyectos recabados en cada curso con potencial curricular o la progresiva mejora del mismo de manera iterativa.
                Gracias a ello surgue CodEval, nuestra aplicación se plantea brindar el fácil acceso y almacenamiento de proyectos, logros o credenciales, tanto para docentes, estudiantes
                o instituciones, aportando así a nuestro perfil profesional retroalimentaciones por parte de docentes de cualquier institución escolar.<br>
                CodEval propone el reclutamiento por parte de empresas, quienes buscan en los estudiantes habilidades o conocimientos previos, funcionales a sus principios laborales, permitiendo a los 
                egresados una opción laboral dentro de una organización.
            </p>
        </section>
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
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"
      integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
      crossorigin="anonymous"></script>
    <!-- Lógica del Frontend -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/scrollreveal"></script>
    <script src="funciones/script.js"></script>
    <script src="assets/pwa.js"></script>
</body>
</html>
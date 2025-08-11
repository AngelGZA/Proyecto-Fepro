<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Models\Estudiante;
use App\Models\DB;

$db = new DB();
$conn = $db->getConnection();

$auth = new AuthController();

// Verifica si el usuario está logueado y es estudiante
if (!$auth->isLogged() || $auth->getUserType() !== 'estudiante') {
    header("Location: ../views/formulario.php");
    exit;
}

// Obtener el usuario actual
$user = $auth->getCurrentUser();
$loggedIn = $auth->isLogged();
$userType = $auth->getUserType();
$username = $user['name'] ?? null;

$estudianteData = Estudiante::findById($user['idest'] ?? 0);

$sql = "
    SELECT e.idof, e.puesto, e.descripcion, e.requisitos, 
           e.salario, e.horario, e.modalidad, e.carrera_deseada,
           emp.name AS empresa, e.fecha_publicacion
    FROM empleos e
    JOIN empresa emp ON e.idemp = emp.idemp
    ORDER BY e.fecha_publicacion DESC
";
$result = $conn->query($sql);

$empleosDisponibles = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $empleosDisponibles[] = $row;
    }
}

// Procesar búsqueda si hay término
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "
    SELECT e.idof, e.puesto, e.descripcion, e.requisitos, 
           e.salario, e.horario, e.modalidad, e.carrera_deseada,
           emp.name AS empresa, e.fecha_publicacion
    FROM empleos e
    JOIN empresa emp ON e.idemp = emp.idemp
";

if (!empty($searchTerm)) {
    $searchTerm = $conn->real_escape_string($searchTerm);
    $sql .= " WHERE e.puesto LIKE '%$searchTerm%' 
              OR e.descripcion LIKE '%$searchTerm%'
              OR e.carrera_deseada LIKE '%$searchTerm%'
              OR e.horario LIKE '%$searchTerm%'
              OR e.modalidad LIKE '%$searchTerm%'
              OR e.requisitos LIKE '%$searchTerm%'
              OR e.salario LIKE '%$searchTerm%'
              OR emp.name LIKE '%$searchTerm%'";
}

$sql .= " ORDER BY e.fecha_publicacion DESC";
$result = $conn->query($sql);

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    $updateData = [
        'name' => $_POST['nombre'] ?? '',
        'telefono' => $_POST['telefono'] ?? '',
        'descripcion' => $_POST['descripcion'] ?? ''
    ];
    
    if (Estudiante::updateProfile($user['idest'], $updateData)) {
        $mensajeExito = "Perfil actualizado correctamente";
        $estudianteData = Estudiante::findById($user['idest']); // Refrescar datos
    } else {
        $mensajeError = "Error al actualizar el perfil";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inicio Estudiante - Lobo Chamba</title>
    <link rel="icon" href="../multimedia/logo_pagina.png" type="image/png">
    <link rel="stylesheet" href="../assets/styleEstudianteIndex.css">
</head>
  
<body>
    <!-- BARRA DE NAVEGACIÓN -->
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
                    <a id="estudiante" href="estudiante.php" class="<?= basename($_SERVER['PHP_SELF']) == 'estudiante.php' ? 'active' : '' ?>">
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
                <li class="menu-item">
                    <?php if ($loggedIn): ?>
                    <!-- Opción Mi Perfil -->
                    <a href="<?= htmlspecialchars($userType === 'estudiante' ? 'estudiante_perfil.php' : 'empresa_perfil.php') ?>" class="menu-link">
                        <ion-icon name="<?= htmlspecialchars($userType === 'estudiante' ? 'person-circle-outline' : 'business-outline') ?>"></ion-icon>
                        <span>Mi Perfil</span>
                    </a>
                    <?php else: ?>
                    <!-- Opción Iniciar Sesión -->
                    <a href="formulario.php" class="menu-link">
                        <ion-icon name="person-add"></ion-icon>
                        <span>Iniciar Sesión</span>
                    </a>
                    <?php endif; ?>
                </li>
                <?php if ($loggedIn): ?>
                <!-- Opción Cerrar Sesión -->
                <li class="menu-item">
                    <a href="../public/logout.php" class="menu-link logout-link">
                        <ion-icon name="log-out-outline"></ion-icon>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <header>
    <div class="header-title">
        <h2>Portal de Empleos</h2>
    </div>
    <div class="search-container">
        <ion-icon name="search-outline"></ion-icon>
        <input type="text" id="searchInput" placeholder="Buscar empleos...">
    </div>
</header>
    
    <main>
        <div class="row" style="margin-top: 10px;">
        <!-- Columna izquierda: Perfil -->
            <div class="perfil-container">
                <div class="perfil-card">
                    <div class="perfil-header">
                        <div class="perfil-avatar">
                            <ion-icon name="person-circle-outline"></ion-icon>
                        </div>
                        <h3 class="perfil-titulo">Mi Perfil</h3>
                        <p class="perfil-subtitulo">Estudiante</p>
                    </div>
                    <div class="perfil-datos">
                        <div class="dato-item">
                            <ion-icon name="person-outline"></ion-icon>
                            <div>
                                <p class="dato-label">Nombre</p>
                                <p class="dato-valor"><?= htmlspecialchars($estudianteData['name'] ?? '') ?></p>
                            </div>
                        </div>
                        <div class="dato-item">
                            <ion-icon name="call-outline"></ion-icon>
                            <div>
                                <p class="dato-label">Teléfono</p>
                                <p class="dato-valor"><?= htmlspecialchars($estudianteData['telefono'] ?? 'No especificado') ?></p>
                            </div>
                        </div>
                        <div class="dato-item">
                            <ion-icon name="mail-outline"></ion-icon>
                            <div>
                                <p class="dato-label">Email</p>
                                <p class="dato-valor"><?= htmlspecialchars($estudianteData['email'] ?? '') ?></p>
                            </div>
                        </div>
                        <div class="dato-item descripcion">
                            <ion-icon name="document-text-outline"></ion-icon>
                            <div>
                                <p class="dato-label">Descripción</p>
                                <p class="dato-valor"><?= htmlspecialchars($estudianteData['descripcion'] ?? 'No hay descripción') ?></p>
                            </div>
                        </div>
                    </div> 
                    <form method="post" action="estudiante_perfil.php" class="perfil-acciones">
                        <input type="hidden" name="idest" value="<?= $estudianteData['idest'] ?>">
                        <button type="submit" name="editar" class="btn-principal">
                            <ion-icon name="create-outline"></ion-icon> Editar Perfil
                        </button>
                    </form>
                </div>
            </div>
            <!-- Columna derecha: Empleos -->
            <div style="flex: 3; padding: 20px;">
                <?php if (!empty($busqueda)): ?>
<div class="resultados-info">
    <div>
        <ion-icon name="search-outline"></ion-icon>
        <?php if (count($empleosDisponibles) > 0): ?>
            Mostrando <?= count($empleosDisponibles) ?> resultados para "<?= htmlspecialchars($busqueda) ?>" 
            (criterio: <?= htmlspecialchars($criterio) ?>)
        <?php else: ?>
            No se encontraron resultados para "<?= htmlspecialchars($busqueda) ?>"
        <?php endif; ?>
    </div>
    <a href="estudiante.php" class="clear-search">
        <ion-icon name="close-circle-outline"></ion-icon> Ver todos
    </a>
</div>
<?php endif; ?>
                <h2 style="color: #1976D2; font-size: 1.8rem; margin-bottom: 20px; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">Empleos Disponibles</h2> 
                <?php if (!empty($empleosDisponibles)): ?>
                <div class="empleos-lista">
                    <?php foreach ($empleosDisponibles as $empleo): 
                    // Determinar si es un empleo nuevo (menos de 7 días)
                    $esNuevo = false;
                    if (isset($empleo['fecha_publicacion'])) {
                        $fechaPublicacion = new DateTime($empleo['fecha_publicacion']);
                        $hoy = new DateTime();
                        $diferencia = $hoy->diff($fechaPublicacion);
                        $esNuevo = ($diferencia->days < 7);
                    } else {
                    $esNuevo = ($empleo['idof'] > 5);
                    }
                    // Formatear el salario
                    $salarioFormateado = ($empleo['salario'] > 0) ? 
                    '$' . number_format($empleo['salario'], 2) : 'No especificado.';
                    ?>
                    <div class="empleo-card">
                        <div class="encabezado-oferta">
                            <span class="id-oferta">
                                <ion-icon name="pricetag-outline"></ion-icon> ID: <?= $empleo['idof'] ?>
                            </span>
                            <?php if ($esNuevo): ?>
                                <span class="etiqueta-nueva">Nueva Oferta</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="titulo-empleo"><?= htmlspecialchars($empleo['puesto']) ?></h3>
                        <div class="empresa-info">
                            <ion-icon name="business-outline"></ion-icon>
                            <span class="empresa-nombre"><?= htmlspecialchars($empleo['empresa']) ?></span>
                        </div>
                        <div class="detalle">
                            <span><ion-icon name="time-outline"></ion-icon> <?= htmlspecialchars($empleo['horario']) ?></span>
                            <span><ion-icon name="location-outline"></ion-icon> <?= htmlspecialchars($empleo['modalidad']) ?></span>
                            <span><ion-icon name="cash-outline"></ion-icon> <?= $salarioFormateado ?></span>
                            <?php if (!empty($empleo['carrera_deseada'])): ?>
                            <span><ion-icon name="school-outline"></ion-icon> <?= htmlspecialchars($empleo['carrera_deseada']) ?></span>
                            <?php endif; ?>
                        </div> 
                        <div class="detalles-empleo">
                            <p><strong>Descripción:</strong><br><?= nl2br(htmlspecialchars($empleo['descripcion'])) ?></p>
                            <p><strong>Requisitos:</strong><br><?= nl2br(htmlspecialchars($empleo['requisitos'])) ?></p>
                        </div> 
                        <form method="post" action="estudiante_empleos.php">
                            <input type="hidden" name="idof" value="<?= $empleo['idof'] ?>">
                            <button type="submit" name="postular" class="boton-postularme">
                                <ion-icon name="send-outline"></ion-icon> Postularme
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-empleos">
                    <ion-icon name="briefcase-outline" style="font-size: 2rem; color: #9e9e9e;"></ion-icon>
                    <p>No hay empleos disponibles en este momento</p>
                    <p>Vuelve a revisar más tarde o intenta con otros filtros</p>
                </div>
                <?php endif; ?>
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
    
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"
        integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
        crossorigin="anonymous"></script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/scrollreveal"></script>
    <script src="../funciones/scriptEstudiante.js"></script>
</body>
</html>
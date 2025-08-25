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
    SELECT p.id, p.titulo, p.descripcion, p.repo_url, 
           p.video_url, p.archivo_zip, p.visibilidad, p.estado,
           p.created_at
    FROM proyectos p
    WHERE p.idest = " . intval($user['idest']) . "
    ORDER BY p.created_at DESC
";
$result = $conn->query($sql);

$proyectosDisponibles = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $proyectosDisponibles[] = $row;
    }
}

// Procesar búsqueda si hay término
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "
    SELECT p.id, p.titulo, p.descripcion, p.repo_url, 
           p.video_url, p.archivo_zip, p.visibilidad, p.estado,
           p.created_at
    FROM proyectos p
    WHERE p.idest = " . intval($user['idest'])."
";

if (!empty($searchTerm)) {
    $searchTerm = $conn->real_escape_string($searchTerm);
    $sql .= " AND (p.titulo LIKE '%$searchTerm%' 
        OR p.descripcion LIKE '%$searchTerm%'
        OR p.repo_url LIKE '%$searchTerm%'
        OR p.video_url LIKE '%$searchTerm%'
    )";
}

$sql .= " ORDER BY p.created_at DESC";
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

//logica para certificaciones: 
$certs = [];
$stmt = $conn->prepare("
  SELECT id, titulo, emisor, fecha_emision, archivo_pdf
  FROM certificaciones
  WHERE idest = ?
  ORDER BY created_at DESC
");
$stmt->bind_param("i", $estudianteData['idest']);
$stmt->execute();
$certs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inicio Estudiante - CodEval</title>
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
                <span>CodEval</span>
            </div>
        </div>
        <nav class="navegacion">
            <ul class="menu-superior">
                <li>
                    <a href="../index.php">
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
                    <a id="estudiante" href="estudiante_visualizacion.php" class="<?= basename($_SERVER['PHP_SELF']) == 'estudiante.php' ? 'active' : '' ?>">
                        <ion-icon name="telescope-outline"></ion-icon>
                        <span>Descubrir proyectos</span>
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
        <h2>Portal Informativo</h2>
    </div>
    <div class="search-container">
        <ion-icon name="search-outline"></ion-icon>
        <input type="text" id="searchInput" placeholder="Buscar proyectos...">
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
                            <ion-icon name="file-tray-full-outline"></ion-icon>
                            <div>
                                <p class="dato-label">Matrícula</p>
                                <p class="dato-valor"><?= htmlspecialchars($estudianteData['matricula'] ?? '') ?></p>
                            </div>
                        </div>
                        <div class="dato-item">
                            <ion-icon name="library-outline"></ion-icon>
                            <div>
                                <p class="dato-label">Carrera</p>
                                <p class="dato-valor"><?= htmlspecialchars($estudianteData['carrera'] ?? '') ?></p>
                            </div>
                        </div>
                        <div class="dato-item">
                            <ion-icon name="flask-outline"></ion-icon>
                            <div>
                                <p class="dato-label">Facultad</p>
                                <p class="dato-valor"><?= htmlspecialchars($estudianteData['facultad'] ?? '') ?></p>
                            </div>
                        </div>
                        <div class="dato-item">
                            <ion-icon name="business-outline"></ion-icon>
                            <div>
                                <p class="dato-label">Universidad</p>
                                <p class="dato-valor"><?= htmlspecialchars($estudianteData['universidad'] ?? '') ?></p>
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
                                <p class="dato-label">Perfiles</p>
                                <p class="dato-valor"><?= htmlspecialchars($estudianteData['descripcion'] ?? 'No hay enlaces') ?></p>
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
                <!-- Columna izquierda, debajo de perfil: MIS INSIGNIAS y CERTIFICACIONES-->
                 <div class="reconocimientos-container">
                    <!--Sección para INSIGNIAS-->
                    <div class="insignias-card">
                        <h3 class="perfil-titulo">
                            <ion-icon name="ribbon-outline"></ion-icon> Mis Insignias
                        </h3>
                        <div class="insignias-lista">
                            <?php
                            // Ejemplo: insignias desde la BD o en un array
                            $insignias = [
                                ['nombre' => 'Python', 'imagen' => '../multimedia/insignias/Logro Python.png'],
                                ['nombre' => 'C++', 'imagen' => '../multimedia/insignias/Logro C++.png'],
                                ['nombre' => 'JavaScript', 'imagen' => '../multimedia/insignias/Logro JS.png']
                            ];
                            if (!empty($insignias)):
                                foreach ($insignias as $insignia): ?>
                                    <div class="insignia-item">
                                        <img src="<?= htmlspecialchars($insignia['imagen']) ?>" alt="<?= htmlspecialchars($insignia['nombre']) ?>" class="badge-icon">
                                        <span><?= htmlspecialchars($insignia['nombre']) ?></span>
                                    </div>
                                <?php endforeach;
                            else: ?>
                                <p class="dato-valor">Aún no cuentas con insignias</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!--Sección para las certificaciones-->
                   <div class="certificaciones-card">
                        <h3 class="perfil-titulo">
                            <ion-icon name="document-text-outline"></ion-icon> Mis Certificaciones
                        </h3>

                        <div class="certificaciones-lista">
                            <?php if (!empty($certs)): ?>
                            <?php foreach ($certs as $c): ?>
                                <div class="certificacion-item">
                                <ion-icon name="school-outline"></ion-icon>
                                <div>
                                    <span class="cert-titulo"><?= htmlspecialchars($c['titulo']) ?></span>
                                    <span class="cert-año">
                                    <?= htmlspecialchars($c['fecha_emision']) ?> · <?= htmlspecialchars($c['emisor']) ?>
                                    </span>
                                    <?php if (!empty($c['archivo_pdf'])): ?>
                                    <div>
                                        <a href="<?= htmlspecialchars($c['archivo_pdf']) ?>" target="_blank">
                                        Ver certificado
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="dato-valor">Aún no tienes certificaciones</p>
                            <?php endif; ?>
                        </div>

                        <!-- Botón para ir a gestionar (crear/editar/eliminar) -->
                        <form method="get" action="estudiante_certificacion.php" style="margin-top:10px;">
                            <button class="btn-principal" type="submit">
                            <ion-icon name="add-circle-outline"></ion-icon> Gestionar certificaciones
                            </button>
                        </form>
                        </div>

                </div>
            </div>

                
            <!-- Columna derecha: PROYECTOS -->
            <div style="flex: 2; padding: 20px;">
                <!--BUSQUEDA-->
                <?php if (!empty($busqueda)): ?>
                    <div class="resultados-info">
                        <div>
                            <ion-icon name="search-outline"></ion-icon>
                            <?php if (count($proyectosDisponibles) > 0): ?>
                                Mostrando <?= count($proyectosDisponibles) ?> resultados para "<?= htmlspecialchars($busqueda) ?>"
                            <?php else: ?>
                                No se encontraron resultados para "<?= htmlspecialchars($busqueda) ?>"
                            <?php endif; ?>
                        </div>
                        <a href="estudiante.php" class="clear-search">
                            <ion-icon name="close-circle-outline"></ion-icon> Ver todos
                        </a>
                    </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                    <h2 style="color: #1976D2; font-size: 1.8rem;">Mis Proyectos</h2>
                    <form method="post" action="estudiante_proyecto.php">
                        <button type="submit" class="btn-principal">
                            <ion-icon name="add-circle-outline"></ion-icon> Subir Proyecto
                        </button>
                    </form>
                </div>
                
                <!--Mensaje de exito para el proyecto eliminado-->
                <?php if (isset($_GET['ok'])): ?>
                    <?php if ($_GET['ok'] === 'ProyectoEliminado'): ?>
                        <div id="flashMessage" class="alert-success">
                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                        Proyecto eliminado correctamente.
                        </div>
                    <?php elseif ($_GET['ok'] === 'ProyectoActualizado'): ?>
                        <div id="flashMessage" class="alert-success">
                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                        Proyecto editado correctamente.
                        </div>
                    <?php elseif ($_GET['ok'] == '1'): ?>
                        <div id="flashMessage" class="alert-success">
                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                        Proyecto subido correctamente.
                        </div>
                    <?php endif; ?>
                    <?php elseif (isset($_GET['err'])): ?>
                    <div id="flashMessage" class="alert-error">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        Ocurrió un error: <?= htmlspecialchars($_GET['err']) ?>
                    </div>
                <?php endif; ?>



                <?php if (!empty($proyectosDisponibles)): ?>
                <div class="empleos-lista" style="display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach ($proyectosDisponibles as $proyecto): 
                        // Profesor asociado
                        $profes = [];
                        $resProf = $conn->query("
                            SELECT m.nombre
                            FROM proyecto_asociacion_profesor pa
                            JOIN maestro m ON pa.idmae = m.idmae
                            WHERE pa.idproyecto = " . intval($proyecto['id']) . "
                            AND pa.estado = 'aceptada'    
                        ");

                        if ($resProf && $resProf->num_rows > 0) {
                            while ($profData = $resProf->fetch_assoc()) {
                                $profes[] = $profData['nombre'];
                            }
                        }

                        // Calificación promedio
                        $resCalif = $conn->query("
                            SELECT promedio, total_votos
                            FROM v_proyecto_rating_resumen
                            WHERE idproyecto = " . intval($proyecto['id'])
                        );

                        $avg = null; 
                        $totalVotos = 0; 
                        
                        if ($resCalif && $resCalif->num_rows > 0) {
                            $promData = $resCalif->fetch_assoc();
                            if ($promData['promedio'] !== null) {
                                $avg = floatval($promData['promedio']); 
                                $totalVotos = intval($promData['total_votos']); 
                            }
                        }

                        // Comentarios
                        $comentarios = [];
                        $resCom = $conn->query("
                            SELECT comentario, idest 
                            FROM proyecto_rating_estudiante 
                            WHERE idproyecto = " . intval($proyecto['id'])
                        );
                        if ($resCom && $resCom->num_rows > 0) {
                            while ($c = $resCom->fetch_assoc()) {
                                $comentarios[] = $c;
                            }
                        }
                    ?>
                    <div class="empleo-card">
                        <div class="encabezado-oferta">
                            <span class="id-oferta">
                                <ion-icon name="pricetag-outline"></ion-icon> ID: <?= $proyecto['id'] ?>
                            </span>
                            <span class="etiqueta-nueva"><?= ucfirst($proyecto['estado']) ?></span>
                        </div>

                        <h3 class="titulo-empleo"><?= htmlspecialchars($proyecto['titulo']) ?></h3>
                        <div class="detalle">
                            <ion-icon name="school-outline"></ion-icon>
                            <?php if (!empty($profes)): ?>
                                Profesor<?= count($profes) > 1 ? 'es' : '' ?> asociado<?= count($profes) > 1 ? 's' : '' ?>:
                                <?= htmlspecialchars(implode(', ', $profes)) ?>
                            <?php else: ?>
                                Sin profesor
                            <?php endif; ?>
                        </div>
                        <div class="detalle">
                            <p><?= nl2br(htmlspecialchars($proyecto['descripcion'])) ?></p>
                        </div>

                        <?php if (!empty($proyecto['repo_url'])): ?>
                        <div class="detalle">
                            <a href="<?= htmlspecialchars($proyecto['repo_url']) ?>" target="_blank">
                                <ion-icon name="logo-github"></ion-icon> Repositorio
                            </a>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($proyecto['video_url'])): ?>
                        <div class="detalle">
                            <iframe width="100%" height="200" src="<?= htmlspecialchars($proyecto['video_url']) ?>" 
                                    title="Video Proyecto" frameborder="0" allowfullscreen></iframe>
                        </div>
                        <?php endif; ?>
                        <!--Analizar con poncho porque no puedo descargar un  zip-->
                        <?php
                            $zip = $proyecto['archivo_zip'] ?? '';

                            if (!empty($zip)) {
                                // Asegura que comience con '/'
                                if ($zip[0] !== '/') {
                                    $zip = '/' . $zip;
                                }
                                // Si la ruta es '/uploads/...' añade '/pro' al inicio porque tu app vive en /pro
                                if (strpos($zip, '/pro/') !== 0) {
                                    // Evita duplicar '/pro' si ya viene incluido
                                    if (strpos($zip, '/uploads/') === 0) {
                                        $zip = '/pro' . $zip;
                                    }
                                }
                            }
                            ?>

                            <?php if (!empty($zip)): ?>
                            <a class="link-descarga" href="<?= htmlspecialchars($zip) ?>" download>
                                <ion-icon name="archive-outline"></ion-icon> Descargar ZIP
                            </a>
                        <?php endif; ?>


                        <?php if ($avg !== null): 
                            // porcentaje para llenar 0–100% (5 estrellas => 100%)
                            $pct = max(0, min(100, ($avg / 5) * 100));
                        ?>
                        <div class="detalle">
                        <div class="rating" title="<?= number_format($avg, 2) ?> de 5">
                            <div class="stars" aria-label="<?= number_format($avg, 2) ?> de 5">
                            <div class="bg">★★★★★</div>
                            <div class="fg" style="width: <?= $pct ?>%">★★★★★</div>
                            </div>
                            <span class="count">
                            <?= number_format($avg, 1) ?>/5<?= $totalVotos ? " · {$totalVotos} voto" . ($totalVotos>1 ? "s" : "") : "" ?>
                            </span>
                        </div>
                        </div>
                        <?php else: ?>
                        <div class="detalle">
                        <div class="rating">
                            <div class="stars">
                            <div class="bg">★★★★★</div>
                            </div>
                            <span class="count">Sin calificación</span>
                        </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($comentarios)): ?>
                        <div class="detalle">
                            <strong>Comentarios:</strong>
                            <ul>
                                <?php foreach ($comentarios as $c): ?>
                                    <li><?= htmlspecialchars($c['comentario']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <div class="form-action" style="display: flex; gap: 10px; margin-top: 10px;">
                            <!-- Botón para eliminar proyecto -->
                            <form method="post" action="eliminar_proyecto.php" class="form-eliminar">
                                <input type="hidden" name="idproyecto" value="<?= $proyecto['id'] ?>">
                                <button type="button" class="boton-eliminar" data-id="<?= $proyecto['id'] ?>">
                                    <ion-icon name="trash-outline"></ion-icon> Eliminar
                                </button>
                            </form>                      
                            <!-- Formulario para editar proyecto -->
                            <form method="post" action="editar_proyecto.php">
                                <input type="hidden" name="idproyecto" value="<?= $proyecto['id'] ?>">
                                <button type="submit" class="boton-postularme" style="background-color: #007bff;">
                                    <ion-icon name="create-outline"></ion-icon> Editar
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-empleos">
                    <ion-icon name="briefcase-outline" style="font-size: 2rem; color: #9e9e9e;"></ion-icon>
                    <p>No se encontraron proyectos en este momento</p>
                    <p>Agrega un nuevo proyecto usando el botón de arriba</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="confirmModal" class="modal-overlay" style="display:none;">
            <div class="modal">
                <h3>¿Seguro que quieres eliminar este proyecto?</h3>
                <p>Esta acción no se puede deshacer.</p>
                <div class="acciones">
                <form id="deleteForm" method="post" action="eliminar_proyecto.php">
                    <input type="hidden" name="idproyecto" id="deleteId">
                    <button type="submit" class="boton-eliminar">Sí, eliminar</button>
                </form>
                <button type="button" class="boton-cancelar" id="cancelBtn">Cancelar</button>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; CodEval | Todos los derechos reservados.</p>
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
    <script src="../funciones/scriptEstudiantes.js"></script>
    <script>
         document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('confirmModal');
        const deleteIdInput = document.getElementById('deleteId');
        const cancelBtn = document.getElementById('cancelBtn');

        document.querySelectorAll('.form-eliminar .boton-eliminar').forEach(btn => {
            btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            deleteIdInput.value = id;
            modal.style.display = 'flex';
            });
        });

        cancelBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        });

        document.addEventListener('DOMContentLoaded', () => {
        const flash = document.getElementById('flashMessage');
        if (flash) {
            if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete('ok');
            url.searchParams.delete('err');
            window.history.replaceState({}, document.title, url.pathname);
            }

            setTimeout(() => {
            flash.style.transition = 'opacity 0.5s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
            }, 4000);
        }
        });
    </script>

</body>
</html>
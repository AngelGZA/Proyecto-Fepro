const Code = document.getElementById("Code");
const barraLateral = document.querySelector(".barra-lateral");
const spans = document.querySelectorAll("span");
const main = document.querySelector("main");
const header = document.querySelector("header");

Code?.addEventListener("click", () => {
  barraLateral?.classList.toggle("mini-barra-lateral");
  main?.classList.toggle("min-main");
  spans.forEach(span => span.classList.toggle("oculto"));
});

// Animación de elementos al hacer scroll
function checkScroll() {
    const elements = document.querySelectorAll('.fade-in');
    
    elements.forEach(element => {
        const elementPosition = element.getBoundingClientRect().top;
        const screenPosition = window.innerHeight / 1.3;
        
        if(elementPosition < screenPosition) {
            element.classList.add('visible');
        }
    });
}

ScrollReveal().reveal('header > *', {
    distance: '50px',  // Distancia desde la que aparece
    duration: 1000,    // Duración de la animación (en ms)
    easing: 'ease-in-out', // Efecto de animación
    origin: 'bottom',  // Dirección desde la que aparece (top, bottom, left, right)
    interval: 200,     // Tiempo entre cada elemento que aparece
});

ScrollReveal().reveal('main > *', {
    distance: '50px',  // Distancia desde la que aparece
    duration: 1000,    // Duración de la animación (en ms)
    easing: 'ease-in-out', // Efecto de animación
    origin: 'bottom',  // Dirección desde la que aparece (top, bottom, left, right)
    interval: 200,     // Tiempo entre cada elemento que aparece
});

ScrollReveal().reveal('.container', {
    distance: '50px',
    origin: 'bottom',
    duration: 800,
    easing: 'ease-in-out',
    interval: 200,
    opacity: 0
});

ScrollReveal().reveal('.barra-lateral', {
    distance: '20px',
    origin: 'left',
    duration: 800,
    easing: 'ease-in-out'
});

console.log("script.js cargado correctamente");

// Búsqueda automática al escribir
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.querySelector('.search-container input');
  
  if (searchInput) {
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        this.form.submit();
      }, 500);
    });
  }
});

document.addEventListener("DOMContentLoaded", function () {
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('activo'); // activa la animación
      }
    });
  }, {
    threshold: 0.5 // ajusta según cuándo quieres disparar la animación
  });

  const elemento = document.querySelector('.animacion-ods');
  if (elemento) {
    observer.observe(elemento);
  }
});

document.addEventListener('DOMContentLoaded', () => {
  fetch('/tecweb/practicas/ProyectoFinal/public/check_login.php')
    .then(res => res.json())
    .then(data => {
      const link = document.getElementById('login-link');
      const icon = document.getElementById('login-icon');
      const text = document.getElementById('login-text');

      if (data.logged) {
        icon.setAttribute('name', 'person-circle-outline');
        text.textContent = data.username;
        link.href = '/public/profile.php';
      } else {
        icon.setAttribute('name', 'log-in-outline');
        text.textContent = 'Iniciar sesión';
        link.href = '/public/login.php';
      }
    });
});

// Búsqueda en tiempo real
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('busquedaInput');
  
  if (searchInput) {
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        buscarProyectos(this.value);
      }, 500);
    });
  }
});

// Función para buscar proyectos con AJAX
function buscarProyectos(termino) {
  $.ajax({
    url: 'empresa.php',
    type: 'GET',
    data: { busqueda: termino },
    success: function(response) {
      // Extraer solo la sección de proyectos del HTML response
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = response;
      const nuevosProyectos = tempDiv.querySelector('#gridProyectos');
      const nuevoPanelHeader = tempDiv.querySelector('.panel-header');
      
      if (nuevosProyectos && nuevoPanelHeader) {
        document.querySelector('#gridProyectos').innerHTML = nuevosProyectos.innerHTML;
        document.querySelector('.panel-header').innerHTML = nuevoPanelHeader.innerHTML;
        
        // Reaplicar listeners a los botones guardar y a las estrellas (ver #2)
        rewireGuardarYStars();
      }
    },
    error: function() {
      console.error('Error en la búsqueda');
    }
  });
}

// Función para guardar/eliminar proyecto con AJAX
function toggleGuardarProyecto(idProyecto, boton) {
  const accion = boton.classList.contains('activo') ? 'eliminar_proyecto' : 'guardar_proyecto';
  
  $.ajax({
    url: 'empresa.php',
    type: 'POST',
    data: {
      accion: accion,
      idproyecto: idProyecto
    },
    success: function(response) {
      if (accion === 'guardar_proyecto') {
        boton.classList.add('activo');
        boton.title = 'Eliminar de guardados';
        
        // Actualizar la lista de proyectos guardados
        actualizarProyectosGuardados();
      } else {
        boton.classList.remove('activo');
        boton.title = 'Guardar proyecto';
        
        // Si está en la vista de guardados, eliminar el elemento
        const elementoGuardado = document.getElementById('guardado-' + idProyecto);
        if (elementoGuardado) {
          elementoGuardado.remove();
        }
        
        // Si no hay proyectos guardados, mostrar estado vacío
        if (document.querySelectorAll('.proyecto-guardado').length === 0) {
          document.querySelector('.proyectos-guardados').innerHTML = `
            <div class="empty-state">
              <ion-icon name="bookmark-outline"></ion-icon>
              <p>No tienes proyectos guardados.</p>
              <p>Haz clic en el icono de marcador para guardar proyectos interesantes.</p>
            </div>
          `;
        }
      }
    },
    error: function() {
      console.error('Error al guardar/eliminar proyecto');
    }
  });
}

// Función para eliminar proyecto guardado
function eliminarProyectoGuardado(idProyecto) {
  $.ajax({
    url: 'empresa.php',
    type: 'POST',
    data: {
      accion: 'eliminar_proyecto',
      idproyecto: idProyecto
    },
    success: function(response) {
      // Eliminar de la lista de guardados
      const elementoGuardado = document.getElementById('guardado-' + idProyecto);
      if (elementoGuardado) {
        elementoGuardado.remove();
      }
      
      // Actualizar el botón en la lista principal
      const botonGuardar = document.querySelector(`#proyecto-${idProyecto} .btn-guardar`);
      if (botonGuardar) {
        botonGuardar.classList.remove('activo');
        botonGuardar.title = 'Guardar proyecto';
      }
      
      // Si no hay proyectos guardados, mostrar estado vacío
      if (document.querySelectorAll('.proyecto-guardado').length === 0) {
        document.querySelector('.proyectos-guardados').innerHTML = `
          <div class="empty-state">
            <ion-icon name="bookmark-outline"></ion-icon>
            <p>No tienes proyectos guardados.</p>
            <p>Haz clic en el icono de marcador para guardar proyectos interesantes.</p>
          </div>
        `;
      }
    },
    error: function() {
      console.error('Error al eliminar proyecto guardado');
    }
  });
}

// Función para actualizar la lista de proyectos guardados
function actualizarProyectosGuardados() {
  $.ajax({
    url: 'empresa.php',
    type: 'GET',
    data: { actualizar_guardados: true },
    success: function(response) {
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = response;
      const nuevosGuardados = tempDiv.querySelector('.proyectos-guardados');
      
      if (nuevosGuardados) {
        document.querySelector('.proyectos-guardados').innerHTML = nuevosGuardados.innerHTML;
        
        // Reaplicar event listeners a los botones de eliminar
        document.querySelectorAll('.proyecto-guardado .btn-eliminar').forEach(btn => {
          const idProyecto = btn.closest('.proyecto-guardado').id.replace('guardado-', '');
          btn.onclick = function() {
            eliminarProyectoGuardado(idProyecto);
          };
        });
      }
    },
    error: function() {
      console.error('Error al actualizar proyectos guardados');
    }
  });
}

// Ajustar el header en responsive
function ajustarHeader() {
  const header = document.querySelector('header');
  const barraLateral = document.querySelector('.barra-lateral');
  
  if (window.innerWidth <= 768) {
    header.style.marginLeft = '0';
    header.style.paddingRight = '15px';
  } else {
    header.style.marginLeft = '80px';
    header.style.paddingRight = '30px';
  }
}

// Ejecutar al cargar y al redimensionar
document.addEventListener('DOMContentLoaded', ajustarHeader);
window.addEventListener('resize', ajustarHeader);

function rewireGuardarYStars() {
  document.querySelectorAll('.btn-guardar').forEach(btn => {
    const card = btn.closest('[data-id]');
    if (!card) return;
    const id = card.getAttribute('data-id');
    btn.onclick = () => toggleGuardarProyecto(id, btn);
  });

  // Estrellas (delegado por card)
  document.querySelectorAll('#gridProyectos .stars').forEach(stars => {
    stars.onclick = (e) => {
      const star = e.target.closest('.star');
      if (!star) return;
      const value = Number(star.dataset.value);
      const card = stars.closest('[data-id]');
      if (!card) return;
      const id = card.getAttribute('data-id');

      // UI: pintar estrellas
      [...stars.querySelectorAll('.star')].forEach((s, i) => {
        s.classList.toggle('active', i < value);
      });

      // Enviar al backend
      $.post('empresa.php', { accion: 'calificar', idproyecto: id, calificacion: value, comentario: '' })
        .done((res) => {
          try {
            const data = JSON.parse(res);
            if (data && data.ok) {
              const badge = card.querySelector('.badge.avg');
              if (badge) badge.textContent = `⭐ ${data.promedio.toFixed(2)} (${data.total_votos})`;
            }
          } catch {}
        });
    };
  });
}

// Llamar una vez al cargar
document.addEventListener('DOMContentLoaded', rewireGuardarYStars);

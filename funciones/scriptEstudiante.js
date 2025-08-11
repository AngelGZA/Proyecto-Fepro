// Elementos del DOM
const Lobo = document.getElementById("Lobo"); // Cambié 'error' por 'Lobo' que es el nombre correcto
const barraLateral = document.querySelector(".barra-lateral");
const spans = document.querySelectorAll("span");
const main = document.querySelector("main");
const header = document.querySelector("header");

// Función para el toggle de la barra lateral
if (Lobo) { // Añadí validación para que no falle si no existe el elemento
    Lobo.addEventListener("click", () => {
        barraLateral.classList.toggle("mini-barra-lateral");
        main.classList.toggle("min-main");
        spans.forEach((span) => {
            span.classList.toggle("oculto");
        });
    });
}

// Configuración de ScrollReveal
if (typeof ScrollReveal !== 'undefined') {
    ScrollReveal().reveal('header > *', {
        distance: '50px',
        duration: 1000,
        easing: 'ease-in-out',
        origin: 'bottom',
        interval: 200,
    });

    ScrollReveal().reveal('main > *', {
        distance: '50px',
        duration: 1000,
        easing: 'ease-in-out',
        origin: 'bottom',
        interval: 200,
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
}

// Función debounce para mejorar el rendimiento
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
}

// Normalizar texto para búsqueda (quita acentos y convierte a mayúsculas)
function normalizeText(text) {
    return text.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toUpperCase();
}

// Función de búsqueda de empleos
function setupJobSearch() {
    const searchInput = document.getElementById('searchInput');
    
    if (searchInput) {
        const performSearch = debounce(function() {
            const filter = normalizeText(this.value);
            const empleos = document.querySelectorAll('.empleo-card');
            
            empleos.forEach(empleo => {
                if (filter === '') {
                    empleo.style.display = "";
                    return;
                }
                
                const text = normalizeText(empleo.textContent || empleo.innerText);
                empleo.style.display = text.includes(filter) ? "" : "none";
            });
        }, 300);

        searchInput.addEventListener('input', performSearch);
    }
}

// Intersection Observer para animaciones
function setupAnimations() {
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('activo');
            }
        });
    }, {
        threshold: 0.5
    });

    const elemento = document.querySelector('.animacion-ods');
    if (elemento) {
        observer.observe(elemento);
    }
}

// Verificación de login
function checkLoginStatus() {
    fetch('/tecweb/practicas/ProyectoFinal/public/check_login.php')
        .then(res => res.json())
        .then(data => {
            const link = document.getElementById('login-link');
            const icon = document.getElementById('login-icon');
            const text = document.getElementById('login-text');

            if (link && icon && text) {
                if (data.logged) {
                    icon.setAttribute('name', 'person-circle-outline');
                    text.textContent = data.username;
                    link.href = '/public/profile.php';
                } else {
                    icon.setAttribute('name', 'log-in-outline');
                    text.textContent = 'Iniciar sesión';
                    link.href = '/public/login.php';
                }
            }
        })
        .catch(error => console.error('Error checking login status:', error));
}

// Validación de formulario
function setupFormValidation() {
    const form = document.querySelector('.perfil-form');
    const fileInput = document.querySelector('input[type="file"]');
    const emailInput = document.getElementById('email');
    
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    alert('El archivo es demasiado grande. El tamaño máximo permitido es 2MB.');
                    e.target.value = '';
                    return;
                }
                
                if (file.type !== 'application/pdf') {
                    alert('Solo se permiten archivos PDF.');
                    e.target.value = '';
                }
            }
        });
    }
    
    if (form && emailInput) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailInput.value)) {
                alert('Por favor ingresa un correo electrónico válido.');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
}

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    setupAnimations();
    setupJobSearch();
    checkLoginStatus();
    setupFormValidation();
    
    console.log("script.js cargado correctamente");
});

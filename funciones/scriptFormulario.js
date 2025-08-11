const container = document.querySelector(".container");
const btnSignIn = document.getElementById("btn-sign-in");
const btnSignUp = document.getElementById("btn-sign-up");
const error = document.getElementById("Lobo");
const barraLateral = document.querySelector(".barra-lateral");
const spans = document.querySelectorAll("span");
const main = document.querySelector("main");
const header = document.querySelector("header");

Lobo.addEventListener("click", ()=>{
    barraLateral.classList.toggle("mini-barra-lateral");
    main.classList.toggle("min-main");
    spans.forEach((span)=>{
        span.classList.toggle("oculto");
    });
});

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

btnSignIn.addEventListener("click",()=>{
    container.classList.remove("toggle");
});

btnSignUp.addEventListener("click",()=>{
    container.classList.add("toggle");
});

function mostrarCamposAdicionales() {
    const tipoUsuario = document.getElementById("tipo_usuario").value;
    const camposEstudiante = document.getElementById("campos-estudiante");
    const camposEmpresa = document.getElementById("campos-empresa");
    // Ocultar todos primero
    camposEstudiante.style.display = "none";
    camposEmpresa.style.display = "none";
    // Mostrar los correspondientes
    if (tipoUsuario === "estudiante") {
        camposEstudiante.style.display = "block";
    } else if (tipoUsuario === "empresa") {
        camposEmpresa.style.display = "block";
    }
}

// Ejecutar al cargar la página
document.addEventListener("DOMContentLoaded", function() {
    // Mostrar campos de Estudiante por defecto (ya que es la primera opción seleccionada)
    mostrarCamposAdicionales();
});

// Muestra el nombre del archivo seleccionado
document.getElementById('cv-upload').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'Subir CV (PDF)';
    document.getElementById('file-label').textContent = fileName;
    
    // Opcional: Añade un checkmark verde al seleccionar
    const icon = document.querySelector('.file-input ion-icon');
    icon.style.color = '#3AB397'; // Verde de tu paleta
});

//const btn = document.getElementById("btn");
//btn.addEventListener("click", ()=>{
  //  container.classList.toggle("toggle");
//});
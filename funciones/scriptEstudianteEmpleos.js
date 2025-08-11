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

console.log("script.js cargado correctamente");

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

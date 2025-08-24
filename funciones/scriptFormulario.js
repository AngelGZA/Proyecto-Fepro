const container = document.querySelector(".container");
const btnSignIn = document.getElementById("btn-sign-in");
const btnSignUp = document.getElementById("btn-sign-up");
const codeBtn = document.getElementById("Code");
const barraLateral = document.querySelector(".barra-lateral");
const spansBarra = barraLateral ? barraLateral.querySelectorAll("span") : null;
const main = document.querySelector("main");
const header = document.querySelector("header");

if (codeBtn) {
  codeBtn.addEventListener("click", () => {
    barraLateral && barraLateral.classList.toggle("mini-barra-lateral");
    main && main.classList.toggle("min-main");
    if (spansBarra) {
      spansBarra.forEach((span) => span.classList.toggle("oculto"));
    }
  });
}

// === Animaciones con ScrollReveal ===
if (typeof ScrollReveal !== "undefined") {
  ScrollReveal().reveal("header > *", {
    distance: "50px",
    duration: 1000,
    easing: "ease-in-out",
    origin: "bottom",
    interval: 200,
  });

  ScrollReveal().reveal("main > *", {
    distance: "50px",
    duration: 1000,
    easing: "ease-in-out",
    origin: "bottom",
    interval: 200,
  });

  ScrollReveal().reveal(".container", {
    distance: "50px",
    origin: "bottom",
    duration: 800,
    easing: "ease-in-out",
    interval: 200,
    opacity: 0,
  });

  ScrollReveal().reveal(".barra-lateral", {
    distance: "20px",
    origin: "left",
    duration: 800,
    easing: "ease-in-out",
  });
}

if (btnSignIn) {
  btnSignIn.addEventListener("click", () => {
    container && container.classList.remove("toggle");
  });
}
if (btnSignUp) {
  btnSignUp.addEventListener("click", () => {
    container && container.classList.add("toggle");
  });
}

function mostrarCamposAdicionales() {
  const tipoUsuario = document.getElementById("tipo_usuario")?.value;

  const camposEstudiante = document.getElementById("campos-estudiante");
  const camposEmpresa = document.getElementById("campos-empresa");
  const camposDocente = document.getElementById("campos-docente");

  if (camposEstudiante) camposEstudiante.style.display = "none";
  if (camposEmpresa) camposEmpresa.style.display = "none";
  if (camposDocente) camposDocente.style.display = "none";

  if (tipoUsuario === "estudiante" && camposEstudiante) {
    camposEstudiante.style.display = "block";
  } else if (tipoUsuario === "empresa" && camposEmpresa) {
    camposEmpresa.style.display = "block";
  } else if (tipoUsuario === "docente" && camposDocente) {
    camposDocente.style.display = "block";
  }
}

document.addEventListener("DOMContentLoaded", function () {

  mostrarCamposAdicionales();

  const selectTipo = document.getElementById("tipo_usuario");
  if (selectTipo) {
    selectTipo.addEventListener("change", mostrarCamposAdicionales);
  }

  const cvInput = document.getElementById("cv-upload");
  const fileLabel = document.getElementById("file-label");
  const fileIcon = document.querySelector(".file-input ion-icon");

  if (cvInput && fileLabel) {
    cvInput.addEventListener("change", function (e) {
      const fileName = e.target.files?.[0]?.name || "Subir CV (PDF)";
      fileLabel.textContent = fileName;
      if (fileIcon) fileIcon.style.color = "#3AB397";
    });
  }
});

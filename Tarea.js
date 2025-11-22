// JS para cambiar entre pestañas
function openTab(evt, tabName) {
  let i, tabcontent, tablinks;

  // Oculta todo el contenido de las pestañas
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }

  // Quita la clase "active" de todos los botones
  tablinks = document.getElementsByClassName("tablink");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].classList.remove("active");
  }

  // Muestra la pestaña actual y añade la clase "active" al botón
  document.getElementById(tabName).style.display = "block";
  evt.currentTarget.classList.add("active");
}

// Abre la primera pestaña por defecto al cargar la página
document.addEventListener("DOMContentLoaded", function() {
  document.getElementsByClassName("tablink")[0].click();
})
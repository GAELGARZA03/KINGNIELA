// Este archivo está correcto, no se necesita modificar.
function openChat(friend) {
    const chatHeader = document.getElementById('chat-header');
    const chatContent = document.getElementById('chat-content');

    if (friend === 'amigo1') {
      chatHeader.innerHTML = "<h3>Chat con Juan Pérez</h3>";
      chatContent.innerHTML = "<p><b>Juan:</b> Hola, ¿ya hiciste tu quiniela?</p>";
    } else if (friend === 'amigo2') {
      chatHeader.innerHTML = "<h3>Chat con Carlos López</h3>";
      chatContent.innerHTML = "<p><b>Carlos:</b> ¿Listo para el partido de hoy?</p>";
    } else if (friend === 'amigo3') {
      chatHeader.innerHTML = "<h3>Chat con Ana Torres</h3>";
      chatContent.innerHTML = "<p><b>Ana:</b> ¡Vamos a ganar la corona!</p>";
    }
}
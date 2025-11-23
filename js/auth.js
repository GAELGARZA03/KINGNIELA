document.addEventListener('DOMContentLoaded', function() {
    
    // --- LÓGICA DE REGISTRO ---
    const registerForm = document.getElementById('registerForm');

    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Evita que la página se recargue

            // Crear un objeto con los datos del formulario
            const formData = new FormData(registerForm);

            // Enviar los datos a registro.php usando Fetch
            fetch('php/registro.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message); // Mensaje de éxito
                    window.location.href = 'Principal.html'; // Redirigir al login
                } else {
                    alert("Error: " + data.message); // Mensaje de error
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error de conexión.');
            });
        });
    }

    // --- LÓGICA DE LOGIN (Placeholder para cuando lo hagamos) ---
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // Aquí pondremos la lógica de login en el siguiente paso
            alert("Funcionalidad de login pendiente de conectar.");
        });
    }
});
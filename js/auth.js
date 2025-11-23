document.addEventListener('DOMContentLoaded', function() {
    
    // --- LÓGICA DE REGISTRO ---
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
            const formData = new FormData(registerForm);

            fetch('php/registro.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message); 
                    // Opcional: Recargar o cambiar vista para login
                    window.location.reload(); 
                } else {
                    alert("Error: " + data.message); 
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }

    // --- LÓGICA DE LOGIN (ACTUALIZADA) ---
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Obtener valores (ajusta los IDs según tu HTML IniciarSesion.html)
            const email = document.getElementById('loginIdentifier').value;
            const password = document.getElementById('loginPassword').value;

            fetch('php/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, password: password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Guardar usuario en LocalStorage para usarlo en Social.js y Quiniela.js
                    localStorage.setItem('kingniela_user', JSON.stringify(data.user));
                    
                    // Redirigir a la página principal de la app
                    window.location.href = 'Quiniela.html'; 
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("Error de conexión al iniciar sesión");
            });
        });
    }
});
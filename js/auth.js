document.addEventListener('DOMContentLoaded', function() {
    
    // --- REFERENCIAS GLOBALES ---
    const loginEmailInput = document.getElementById('loginIdentifier');
    const rememberCheckbox = document.getElementById('rememberUser');

    // 1. VERIFICAR SI HAY USUARIO RECORDADO AL CARGAR LA PÁGINA
    // Si existe un correo guardado en localStorage, lo ponemos en el input y marcamos el check
    if (loginEmailInput && rememberCheckbox) {
        const savedEmail = localStorage.getItem('saved_email');
        if (savedEmail) {
            loginEmailInput.value = savedEmail;
            rememberCheckbox.checked = true;
        }
    }

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
                    window.location.href = 'Principal.html';
                } else {
                    alert("Error: " + data.message); 
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }

    // --- LÓGICA DE LOGIN ---
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
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
                    // Guardar sesión de usuario actual (datos completos)
                    localStorage.setItem('kingniela_user', JSON.stringify(data.user));
                    
                    // --- LÓGICA RECORDAR USUARIO ---
                    // Si el checkbox está marcado, guardamos el correo permanentemente
                    if (rememberCheckbox && rememberCheckbox.checked) {
                        localStorage.setItem('saved_email', email);
                    } else {
                        // Si no, borramos cualquier rastro previo
                        localStorage.removeItem('saved_email');
                    }
                    
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
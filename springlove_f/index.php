<?php
// index.php
// Incluir el archivo de login para manejar la lógica de autenticación
require_once 'vista/login.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spring Love</title>
    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet" >
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <img src="img/logospringloveSF.png" class="img-fluid mb-2 " alt="SPRING LOVE">
        <p class="text-secondary mb-3 ">Inicia sesión para acceder al sistema.</p>

        <?php
        if (!empty($login_err)) {
            echo '<div class="alert alert-danger mb-4" role="alert">' . $login_err . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="loginForm">
            <div class="mb-3">
                <label for="email" class="form-label d-block text-start fw-bold">Email:</label>
                <input type="email" id="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>"  title="Ingresa aquí tu Email" value="<?php echo $email; ?>" placeholder="tu@email.com" maxlength=35>
                <div class="invalid-feedback error-message"><?php echo $email_err; ?></div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label d-block text-start fw-bold">Contraseña:</label>
                <input type="password" id="password" name="password" autocomplete="off" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="********" maxlength=15>
                <div class="invalid-feedback error-message"><?php echo $password_err; ?></div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                <span class="small text-primary-emphasis mt-4">Si olvidó su contraseña contacte al administrador.</span>
            </div>
            
        </form>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript para validación básica del lado del cliente (PHP es la principal)
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');

            if (loginForm) {
                loginForm.addEventListener('submit', function(event) {
                    let emailInput = document.getElementById('email');
                    let passwordInput = document.getElementById('password');
                    let isValid = true;

                    // Limpiar mensajes de error previos de Bootstrap
                    emailInput.classList.remove('is-invalid');
                    passwordInput.classList.remove('is-invalid');
                    document.querySelector('#email + .invalid-feedback').textContent = '';
                    document.querySelector('#password + .invalid-feedback').textContent = '';

                    if (emailInput.value.trim() === '') {
                        document.querySelector('#email + .invalid-feedback').textContent = 'El email es requerido.';
                        emailInput.classList.add('is-invalid');
                        isValid = false;
                    } else if (!/\S+@\S+\.\S+/.test(emailInput.value)) {
                        document.querySelector('#email + .invalid-feedback').textContent = 'Formato de email inválido.';
                        emailInput.classList.add('is-invalid');
                        isValid = false;
                    }

                    if (passwordInput.value.trim() === '') {
                        document.querySelector('#password + .invalid-feedback').textContent = 'La contraseña es requerida.';
                        passwordInput.classList.add('is-invalid');
                        isValid = false;
                    }

                    if (!isValid) {
                        event.preventDefault(); // Evitar el envío del formulario si hay errores
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php
// Подключение к базе данных
require_once('dbd_config.php');

// Создаем подключение
$conn = new mysqli($servername, $username, $password, $dbname);

// Проверяем подключение
if ($conn->connect_error) {
    die("Ошибка подключения к базе данных: " . $conn->connect_error);
}

$error_message = ""; 
$success_message = ""; 

// Проверка, что запрос пришел через POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Получаем данные из формы
    $user = trim($_POST['username']); 
    $email = $_POST['email']; 
    $pass = $_POST['password']; 
    $confirm_pass = $_POST['confirm_password']; 

    $normalized_email = strtolower($email); 

    // Проверки
    if (empty($user)) { 
        $error_message = "Username is required."; 
    } elseif (strlen($user) > 50) { 
        $error_message = "Username is too long. Maximum 50 characters allowed."; 
    } elseif (!preg_match('/^[a-zA-Z0-9_\x{0400}-\x{04FF}]+$/u', $user)) { 
        $error_message = "Username can only contain letters (Latin and Cyrillic), numbers, and underscores."; 
    } elseif (preg_match('/[^\wа-яА-ЯёЁ]/u', $user)) { 
        $error_message = "Username can only contain letters, numbers, and underscores."; 
    } elseif (!preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i', $email)) { 
        $error_message = "Invalid email format. Please use a valid email address (e.g., example@domain.com)."; 
    } elseif (strlen($email) > 100) { 
        $error_message = "Email is too long. Maximum 100 characters allowed."; 
    } elseif (preg_match('/[^a-zA-Z0-9._@-]/', $email)) { 
        $error_message = "Некорректное имя электронной почты."; 
    } elseif (strlen($pass) > 50) { 
        $error_message = "Password is too long. Maximum 50 characters allowed."; 
    } elseif ($pass !== $confirm_pass) { 
        $error_message = "Passwords do not match."; 
    }

    // Выполнение регистрации только если нет ошибок
    if (empty($error_message)) { 
        // Проверка на существование пользователя с таким email
        $stmt = $conn->prepare("SELECT * FROM Users WHERE LOWER(TRIM(email)) = ? LIMIT 1"); 
        $stmt->bind_param("s", $email); 
        $stmt->execute(); 
        $result = $stmt->get_result(); 

        if ($result->num_rows > 0) { 
            $error_message = "A user with this email already exists!"; 
        } else {
            // Хеширование пароля перед сохранением
            $hashed_password = password_hash($pass, PASSWORD_DEFAULT); 

            // Подготовка SQL-запроса для вставки нового пользователя
            $stmt = $conn->prepare("INSERT INTO Users (username, email, password) VALUES (?, ?, ?)"); 
            $stmt->bind_param("sss", $user, $email, $hashed_password); 

            if ($stmt->execute()) { 
                // Получаем ID новой записи
                $new_user_id = $stmt->insert_id;

                // Назначение роли user
                $stmt_role = $conn->prepare("INSERT INTO userroles (user_id, role_id) SELECT ?, role_id FROM roles WHERE role_name = 'user' LIMIT 1");
                $stmt_role->bind_param("i", $new_user_id);

                if ($stmt_role->execute()) {
                    // Успешная регистрация
                    $success_message = "Registration successful! You can now <a href='login.php'>login</a>.";
                } else {
                    $error_message = "Error assigning role: " . $stmt_role->error;
                }

                $stmt_role->close();
            } else {
                $error_message = "Error: " . $stmt->error;
            }
        }

        // Закрываем подготовленный запрос
        $stmt->close();
    }
}

// Закрываем соединение с базой данных
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
    <link rel="stylesheet" href="style_for_registration.css">
</head>
<body>
    <div class="container">
        <h2>Register</h2>

        <!-- Вывод сообщений об ошибке или успешной регистрации -->
        <?php if (!empty($error_message)): ?>
            <p style="color:red;" id="server-error"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <p style="color:green;"><?php echo $success_message; ?></p>
        <?php endif; ?>

        <!-- Поле для вывода клиентских ошибок -->
        <p style="color:red;" id="client-error"></p>

        <form id="registration-form" action="" method="POST">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required><br><br>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required><br><br>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br><br>

            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required><br><br>

            <input type="submit" value="Register">
        </form>

        <!-- Кнопка возврата на главную страницу -->
        <button class="back-button" onclick="window.location.href='index.php'">Back to Home</button>
    </div>

    <script>
    // Проверка на совпадение паролей и длину полей на клиентской стороне
    const form = document.getElementById('registration-form');
    const clientError = document.getElementById('client-error');

    form.addEventListener('submit', function (e) {
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const email = document.getElementById('email').value;

        const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-z]{2,}$/; 

        // Сбрасываем сообщение об ошибке перед проверкой
        clientError.textContent = "";

        // Проверка длины имени пользователя
        if (username.length > 50) {
            e.preventDefault();
            clientError.textContent = "Username is too long. Maximum 50 characters allowed.";
            return;
        }

        // Проверка корректности email
        if (!emailPattern.test(email)) {
            e.preventDefault();
            clientError.textContent = "Invalid email format.";
            return;
        }

        // Проверка длины email
        if (email.length > 100) {
            e.preventDefault();
            clientError.textContent = "Email is too long. Maximum 100 characters allowed.";
            return;
        }

        // Проверка длины пароля
        if (password.length > 50) {
            e.preventDefault();
            clientError.textContent = "Password is too long. Maximum 50 characters allowed.";
            return;
        }

        // Проверка на совпадение паролей
        if (password !== confirmPassword) {
            e.preventDefault();
            clientError.textContent = "Passwords do not match.";
            return;
        }
    });
    </script>
</body>
</html>

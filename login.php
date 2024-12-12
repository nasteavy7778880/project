<?php
// Подключение к базе данных
require_once('dbd_config.php');

// Создаем подключение
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    // Если произошла ошибка подключения, выводим сообщение об ошибке
    die("Ошибка подключения к базе данных: " . $conn->connect_error);
}

$error_message = ""; // Для хранения сообщений об ошибке

// Проверяем, что запрос пришел через POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Получаем данные из формы
    $email = $_POST['email'];
    $pass = $_POST['password'];

    // Ограничение длины email и пароля на серверной стороне
    if (strlen($email) > 100) {
        $error_message = "Email is too long. Maximum 100 characters allowed.";
    } elseif (strlen($pass) > 50) {
        $error_message = "Password is too long. Maximum 50 characters allowed.";
    } else {
        // Подготавливаем SQL-запрос с защитой от SQL-инъекций
        $stmt = $conn->prepare("SELECT * FROM Users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);  // "s" означает, что параметр - строка
        $stmt->execute();
        
        // Получаем результат
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            // Получаем данные пользователя
            $row = $result->fetch_assoc();

            // Проверка статуса пользователя
            if ($row['status'] == 'blocked') {
                $error_message = "Ваш аккаунт был заблокирован, для получения дополнительной информации свяжитесь с администратором.";
            } else {
                // Проверяем пароль (предполагаем, что пароль захеширован)
                if (password_verify($pass, $row['password'])) {
                    // Сессия для авторизации
                    session_start();
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['id'] = $row['id'];

                    $_SESSION['login_success'] = "Вы удачно вошли в систему!"; // Устанавливаем сообщение об успехе
                    
                    // Получаем роль пользователя
                    $user_id = $row['id']; // предполагаем, что в таблице Users есть поле user_id
                    $role_query = $conn->prepare("SELECT r.role_name FROM roles r 
                                                  JOIN userroles ur ON r.role_id = ur.role_id
                                                  WHERE ur.user_id = ?");
                    $role_query->bind_param("i", $user_id);
                    $role_query->execute();
                    $role_result = $role_query->get_result();

                    if ($role_result->num_rows > 0) {
                        // Если роль найдена, получаем ее
                        $role_row = $role_result->fetch_assoc();
                        $role_name = $role_row['role_name'];

                        // Перенаправление в зависимости от роли
                        if ($role_name == 'admin') {
                            header("Location: admin.php");  // Страница для админа
                        } elseif ($role_name == 'manager') {
                            header("Location: manager.php");  // Страница для менеджера
                        } else {
                            header("Location: user.php");  // Страница для пользователя
                        }
                    } else {
                        // Если роли нет, перенаправляем на страницу обычного пользователя
                        header("Location: user.php");  // Страница для пользователя по умолчанию
                    }
                    exit(); // Завершаем выполнение скрипта после перенаправления
                } else {
                    $error_message = "Invalid password.";
                }
            }
        } else {
            $error_message = "No user found with that email.";
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
    <title>Login</title>
    <link rel="stylesheet" href="style_for_login.css">
</head>
<body>
    <div class="container">
        <h2>Login</h2>
       
        <?php if (!empty($error_message)): ?>
            <p style="color:red;"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <form action="" method="POST">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" maxlength="100" required><br><br>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" maxlength="50" required><br><br>

            <input type="submit" value="Login">
        </form>

        <button class="back-button" onclick="window.location.href='index.php'">Вернуться на главную</button>

        <p class="signup-link">Нет аккаунта? <a href="registration.php">Создать </a></p>
    </div>
</body>
</html>

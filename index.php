<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="media\loader.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная страница</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Добро пожаловать!</h1>
            <h2> для продолжения зарегистрируйтесь или войдите.</h2>
            <button onclick="window.location.href='registration.php'">Регистрация</button>
            <button onclick="window.location.href='login.php'">Авторизация</button>
        </div>

    </div>
    <script>
        // Очистка localStorage при загрузке страницы
        localStorage.removeItem('username');
    </script>
</body>

</html>

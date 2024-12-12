<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Получение ID пользователя из сессии
$user_id = $_SESSION['id'];

// Получение квартир пользователя для выбора
$query = "SELECT id, address FROM apartments WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$apartments = $result->fetch_all(MYSQLI_ASSOC);

// Проверка, была ли отправлена форма
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['logout'])) {
        // Завершение сессии при нажатии кнопки выхода
        session_destroy();
        header("Location: login.php");
        exit;
    }

    $apartment_id = $_POST['apartment_id'];
    $issue_description = $_POST['issue_description'];
    $request_date = date('Y-m-d'); // Текущая дата

    $query = "INSERT INTO service_requests (user_id, apartment_id, issue_description, request_date) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiss", $user_id, $apartment_id, $issue_description, $request_date);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Заявка успешно подана!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        echo "Ошибка: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подача заявки на услугу</title>
    <link rel="stylesheet" href="style_request.css">
</head>
<body>
    <!-- Кнопка выхода расположена слева вверху -->
    
        <button type="button" class="logout-button" onclick="window.location.href = 'user.php';">Выход</button>
  

    <h1>Подача заявки на услугу</h1>
    
    <!-- Сообщение об успешной отправке -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <p><?= $_SESSION['success_message'] ?></p>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Форма для подачи заявки -->
    <form method="POST">
        <label for="apartment_id">Выберите квартиру:</label>
        <select name="apartment_id" id="apartment_id" required>
            <?php foreach ($apartments as $apartment): ?>
                <option value="<?= $apartment['id'] ?>"><?= htmlspecialchars($apartment['address']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="issue_description">Опишите проблему:</label>
        <textarea name="issue_description" id="issue_description" rows="4" placeholder="Опишите проблему..." required></textarea>

        <button type="submit">Подать заявку</button>
    </form>
</body>
</html>

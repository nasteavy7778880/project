<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка авторизации
if (!isset($_SESSION['username'])) { 
    header("Location: login.php"); 
    exit();
}

// Проверяем метод POST на наличие данных для удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];

    // Подготовка и выполнение запроса на удаление
    $stmt = $conn->prepare("DELETE FROM service_bookings WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Бронирование успешно удалено.";
    } else {
        $_SESSION['error'] = "Ошибка при удалении бронирования.";
    }
    $stmt->close();

    // Перенаправляем пользователя обратно на текущую страницу
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Получение ID пользователя из сессии
$user_id = $_SESSION['id'];

// Получение бронирований для текущего пользователя
$query = "SELECT sb.id, s.name AS service_name, a.address AS apartment_address, sb.booking_date, sb.status 
          FROM service_bookings sb 
          JOIN services s ON sb.service_id = s.id 
          JOIN apartments a ON sb.apartment_id = a.id 
          WHERE sb.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Закрытие соединения
$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои Бронирования</title>
    <link rel="stylesheet" href="style8.css">
    
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f0f0f0; /* Однородный светло-серый фон */
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
        }

        .card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 10px;
            justify-content: flex-start;
        }

        .card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            width: 300px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .card h3 {
            margin: 0 0 10px;
            font-size: 18px;
            color: #333;
        }

        .card p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }

        .delete-button {
            margin-top: 10px;
            padding: 5px 10px;
            background-color: #ff0000;
            border: none;
            color: white;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
        }

        .delete-button:hover {
            background-color: #c00000;
        }

        .message {
            text-align: center;
            color: green;
            margin-bottom: 20px;
        }

        .error {
            text-align: center;
            color: red;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
   
<div style="display: flex; align-items: center; justify-content: space-between; margin: 20px 0;">
    <button type="button" class="logout-button" onclick="window.location.href = 'user.php';">Назад</button>
    <h1 style="margin: 0; flex-grow: 1; text-align: center;">Мои забронированные услуги</h1>
</div>

    <!-- Сообщения об успехе и ошибке -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message"><?= htmlspecialchars($_SESSION['message']) ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card-container">
        <?php if (empty($bookings)): ?>
            <p style="font-size: 16px; color: #555; text-align: center;">Нет активных бронирований.</p>
        <?php else: ?>
            <?php foreach ($bookings as $booking): ?>
                <div class="card">
                    <h3>Услуга: <?= htmlspecialchars($booking['service_name']) ?></h3>
                    <p><strong>Квартира:</strong> <?= htmlspecialchars($booking['apartment_address']) ?></p>
                    <p><strong>Дата Бронирования:</strong> <?= htmlspecialchars($booking['booking_date']) ?></p>
                    <p><strong>Статус:</strong> <?= htmlspecialchars($booking['status']) ?></p>

                    <!-- Форма для удаления -->
                    <form method="POST" action="">
                        <input type="hidden" name="delete_id" value="<?= htmlspecialchars($booking['id']) ?>">
                        <button type="submit" class="delete-button">Удалить</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
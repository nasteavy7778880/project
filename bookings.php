<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Получение ID пользователя из сессии
$user_id = $_SESSION['id'];

// Получение бронирований услуг текущего пользователя
$query = "
    SELECT sb.id, sb.booking_date, sb.status, s.name AS service_name, a.address AS apartment_address 
    FROM service_bookings sb
    JOIN services s ON sb.service_id = s.id
    JOIN apartments a ON sb.apartment_id = a.id
    WHERE sb.user_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);

// Обработка статуса бронирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['new_status'];

    // Обновление статуса бронирования
    $update_query = "UPDATE service_bookings SET status = ? WHERE id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sii", $new_status, $booking_id, $user_id);
    $update_stmt->execute();
    
    // Перенаправление обратно на страницу
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Закрытие соединения с базой данных
$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style_for_bills.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управления забронированными услугами</title>
    <style>
        .card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 20px;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            width: 300px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .card h3 {
            margin: 0 0 10px;
        }
        .card p {
            margin: 5px 0;
        }
        .button-group {
            margin-top: 10px;
        }
        .button {
            margin-right: 5px;
            padding: 5px 10px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .button.cancel {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <h1>Ваши бронирования услуг</h1>

    <div class="card-container">
        <?php if (empty($bookings)): ?>
            <p>Нет доступных бронирований.</p>
        <?php else: ?>
            <?php foreach ($bookings as $booking): ?>
                <div class="card">
                    <h3>Бронирование для <?= htmlspecialchars($booking['service_name']) ?></h3>
                    <p><strong>Дата бронирования:</strong> <?= htmlspecialchars($booking['booking_date']) ?></p>
                    <p><strong>Статус:</strong> <?= htmlspecialchars($booking['status']) ?></p>
                    <p><strong>Адрес квартиры:</strong> <?= htmlspecialchars($booking['apartment_address']) ?></p>
                    <div class="button-group">
                        <form action="" method="POST">
                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                            <button type="submit" name="new_status" value="подтверждено" class="button">Подтвердить</button>
                            <button type="submit" name="new_status" value="отменено" class="button cancel">Отменить</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
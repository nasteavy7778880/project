<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Получение ID пользователя из сессии
$user_id = $_SESSION['id'];

// Получение заявок пользователя
$query = "SELECT sr.id, sr.issue_description, sr.request_date, sr.status, a.address 
          FROM service_requests sr 
          JOIN apartments a ON sr.apartment_id = a.id 
          WHERE sr.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);

// Закрытие соединения с базой данных
$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История заявок</title>
    <link rel="stylesheet" href="style_for_user_page.css">
    <link rel="stylesheet" href="style3.css">
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
    </style>
</head>
<body>
    <h1>История заявок</h1>
    <div class="card-container">
        <?php if (empty($requests)): ?>
            <p>Нет доступных заявок.</p>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
                <div class="card">
                    <h3>Заявление №<?= htmlspecialchars($request['id']) ?></h3>
                    <p><strong>Адрес квартиры:</strong> <?= htmlspecialchars($request['address']) ?></p>
                    <p><strong>Описание проблемы:</strong> <?= htmlspecialchars($request['issue_description']) ?></p>
                    <p><strong>Дата подачи:</strong> <?= htmlspecialchars($request['request_date']) ?></p>
                    <p><strong>Статус:</strong> <?= htmlspecialchars($request['status']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
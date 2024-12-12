<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Получение всех счетов с информацией о пользователе и квартире
$query = "
    SELECT b.id AS bill_id, b.billing_date, b.total_amount, 
           m.reading_date, m.value, t.service_name, 
           u.username, u.email, a.address 
    FROM bills b
    JOIN meter_readings m ON b.apartment_id = m.apartment_id
    JOIN tariffs t ON m.service_id = t.id
    JOIN apartments a ON b.apartment_id = a.id
    JOIN users u ON a.user_id = u.id
    ORDER BY b.billing_date DESC"; // Сортировка по дате счета

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$bills = $result->fetch_all(MYSQLI_ASSOC);

// Закрытие соединения с базой данных
$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История счетов</title>
    
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
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пример</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
        }
    </style>
</head>
<body>

<div style="display: flex; align-items: center; justify-content: space-between; margin: 20px 0;">
    <div style="display: flex; align-items: center;">
        <button onclick="window.location.href='analiz.php'" style="padding: 5px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-right: 10px;">
            Аналитика расходов
        </button>
        <button onclick="window.location.href='manager.php'" style="padding: 5px 20px; background-color:  #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
            Выход
        </button>
        <button onclick="window.location.href='reiting.php'" style="padding: 5px 20px; background-color:  #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
            Рейтинг
        </button>
    </div>
    <h1 style="margin: 0; flex-grow: 1; text-align: center;">Сводка по счетам пользователей</h1>
</div>

</body>
</html>
    <div class="card-container">
        <?php if (empty($bills)): ?>
            <p>Нет доступных счетов.</p>
        <?php else: ?>
            <?php foreach ($bills as $bill): ?>
                <div class="card">
                    <h3>Счет за <?= htmlspecialchars($bill['service_name']) ?></h3>
                    <p><strong>Пользователь:</strong> <?= htmlspecialchars($bill['username']) ?> (<?= htmlspecialchars($bill['email']) ?>)</p>
                    <p><strong>Квартира:</strong> <?= htmlspecialchars($bill['address']) ?></p>
                    <p><strong>Дата счета:</strong> <?= htmlspecialchars($bill['billing_date']) ?></p>
                    <p><strong>Общая сумма:</strong> <?= number_format($bill['total_amount'], 2, '.', '') ?> ₽</p>
                    <p><strong>Дата показания:</strong> <?= htmlspecialchars($bill['reading_date']) ?></p>
                    <p><strong>Показание:</strong> <?= number_format($bill['value'], 2, '.', '') ?></p>
                </div>
                
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
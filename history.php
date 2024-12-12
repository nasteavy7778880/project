<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Инициализация переменных поиска
$search_billing_date = '';
$search_total_amount = '';
$search_reading_date = '';
$search_value = '';

// Проверка на отправку формы
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['search'])) {
        $search_billing_date = $_POST['search_billing_date'];
        $search_total_amount = $_POST['search_total_amount'];
        $search_reading_date = $_POST['search_reading_date'];
        $search_value = $_POST['search_value'];

        // Сохранение параметров поиска в сессии
        $_SESSION['search_billing_date'] = $search_billing_date;
        $_SESSION['search_total_amount'] = $search_total_amount;
        $_SESSION['search_reading_date'] = $search_reading_date;
        $_SESSION['search_value'] = $search_value;

        // Перенаправление после обработки формы
        header("Location: " . $_SERVER['PHP_SELF'] . "?search=1");
        exit();
    }

    if (isset($_POST['clear_search'])) {
        unset($_SESSION['search_billing_date']);
        unset($_SESSION['search_total_amount']);
        unset($_SESSION['search_reading_date']);
        unset($_SESSION['search_value']);
        header("Location: " . $_SERVER['PHP_SELF'] . "?cleared=1");
        exit();
    }
}

// Получение параметров поиска из сессии
$search_billing_date = $_SESSION['search_billing_date'] ?? '';
$search_total_amount = $_SESSION['search_total_amount'] ?? '';
$search_reading_date = $_SESSION['search_reading_date'] ?? '';
$search_value = $_SESSION['search_value'] ?? '';

// Получение ID пользователя из сессии
$user_id = $_SESSION['id'];

// Получение квартир пользователя
$query = "SELECT id FROM apartments WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$apartments = $result->fetch_all(MYSQLI_ASSOC);

// Получение счетов для квартир пользователя
$bills = [];
foreach ($apartments as $apartment) {
    $apartment_id = $apartment['id'];

    // Подготовка SQL-запроса с возможностью поиска
    $query = "
        SELECT b.id AS bill_id, b.billing_date, b.total_amount, 
               m.reading_date, m.value, t.service_name 
        FROM bills b
        JOIN meter_readings m ON b.apartment_id = m.apartment_id AND b.billing_date = m.reading_date
        JOIN tariffs t ON m.service_id = t.id
        WHERE b.apartment_id = ?";

    $params = [$apartment_id];

    // Фильтрация по полям
    if (!empty($search_billing_date)) {
        $query .= " AND b.billing_date = ?";
        $params[] = $search_billing_date;
    }

    if (!empty($search_total_amount)) {
        $query .= " AND b.total_amount = ?";
        $params[] = $search_total_amount;
    }

    if (!empty($search_reading_date)) {
        $query .= " AND m.reading_date = ?";
        $params[] = $search_reading_date;
    }

    if (!empty($search_value)) {
        $query .= " AND m.value = ?";
        $params[] = $search_value;
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $bills[] = $row;
    }
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
    <title>История счетов</title>
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
        .navigate-button {
            margin: 20px;
            padding: 10px 20px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <h1>История счетов</h1>
    <button onclick="window.location.href='analitic.php'" class="navigate-button">
        Аналитика расхода
    </button>
    <button onclick="window.location.href='prognos.php'" class="navigate-button">
        Прогноз
    </button>
   
    <!-- Форма поиска -->
    <form method="POST" style="margin-bottom: 10px;">
        <input type="date" name="search_billing_date" placeholder="Дата счета" value="<?= htmlspecialchars($search_billing_date) ?>">
        <input type="text" name="search_total_amount" placeholder="Общая сумма" value="<?= htmlspecialchars($search_total_amount) ?>">
        <input type="date" name="search_reading_date" placeholder="Дата показания" value="<?= htmlspecialchars($search_reading_date) ?>">
        <input type="text" name="search_value" placeholder="Показание" value="<?= htmlspecialchars($search_value) ?>">
        <button type="submit" name="search">Поиск</button>
        <button type="submit" name="clear_search">Сбросить</button>
    </form>

    <div class="card-container">
        <?php if (empty($bills)): ?>
            <p>Нет доступных счетов.</p>
        <?php else: ?>
            <?php foreach ($bills as $bill): ?>
                <div class="card">
                    <h3>Счет за <?= htmlspecialchars($bill['service_name']) ?></h3>
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
<?php
require 'dbd_config.php'; // Подключение к базе данных

// Получение списка ресурсов для выпадающего списка
$resourcesQuery = "SELECT id, service_name FROM tariffs";
$resourcesResult = $conn->query($resourcesQuery);

// Проверка на наличие ресурсов
$resources = [];
if ($resourcesResult->num_rows > 0) {
    while ($row = $resourcesResult->fetch_assoc()) {
        $resources[] = $row;
    }
}

// Обработка выбора ресурса и времени
$selectedResource = isset($_POST['resource']) ? $_POST['resource'] : null;
$timeframe = isset($_POST['timeframe']) ? $_POST['timeframe'] : 'all';

// Получение данных по использованию выбранного ресурса пользователями
$query = "
    SELECT 
        u.username,
        u.email,
        a.address,
        SUM(mr.value) AS total_usage,
        COALESCE(SUM(b.total_amount), 0) AS total_paid,
        rl.limit_value
    FROM 
        users u
    JOIN 
        apartments a ON u.id = a.user_id
    JOIN 
        meter_readings mr ON a.id = mr.apartment_id
    LEFT JOIN 
        bills b ON a.id = b.apartment_id
    LEFT JOIN 
        resource_limits rl ON mr.service_id = rl.service_id
";

if ($selectedResource) {
    $query .= " WHERE mr.service_id = ?";
}

// Добавление условия по времени
if ($timeframe === 'last_30_days') {
    $query .= ($selectedResource ? " AND" : " WHERE") . " mr.reading_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$query .= " GROUP BY u.id, u.email, a.address ORDER BY total_usage DESC";

$stmt = $conn->prepare($query);

if ($selectedResource) {
    $stmt->bind_param("i", $selectedResource);
}

$stmt->execute();
$result = $stmt->get_result();

// Массивы для данных графика и превышающих лимит
$labels = [];
$data = [];
$overLimitUsers = [];

// Проверка результата
if ($result->num_rows > 0) {
    echo "<h1>Рейтинг пользователей по использованию ресурса</h1>";
    echo "<table border='1' cellpadding='10' cellspacing='0' style='width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background-color: #007bff; color: white;'><th>Пользователь</th><th>Email</th><th>Адрес</th><th>Использование</th><th>Сумма платежей</th></tr>";

    while ($row = $result->fetch_assoc()) {
        $username = htmlspecialchars($row['username']);
        $email = htmlspecialchars($row['email']);
        $address = htmlspecialchars($row['address']);
        $totalUsage = htmlspecialchars($row['total_usage']);
        $totalPaid = htmlspecialchars($row['total_paid']);
        $limitValue = $row['limit_value'] ?? 0; // Значение по умолчанию

        // Проверяем, если запрашивается период за последние 30 дней
        if ($timeframe === 'last_30_days') {
            // Если расход за последние 30 дней превышает лимит, выделяем красным
            $usageStyle = (float)$totalUsage > (float)$limitValue ? 'color: red;' : '';
        } else {
            $usageStyle = '';
        }

        echo "<tr style='background-color: #f8f9fa;'>";
        echo "<td>$username</td>";
        echo "<td>$email</td>";
        echo "<td>$address</td>";
        echo "<td style='$usageStyle'>$totalUsage</td>"; // Применение стиля
        echo "<td>$totalPaid</td>";
        echo "</tr>";

        // Если расход превышает лимит, добавляем в массив
        if ((float)$totalUsage > (float)$limitValue) {
            $overLimitUsers[] = ['username' => $username, 'email' => $email];
        }

        // Заполнение данных для графика
        $labels[] = $username;
        $data[] = (int)$totalUsage; // Приводим к целому числу
    }

    echo "</table>";
} else {
    echo "<p>Нет данных для отображения рейтинга.</p>";
}

// Запрос для получения пользователей с расходом выше лимита
$overLimitQuery = "
    SELECT 
        u.username,
        u.email,
        a.address,
        SUM(mr.value) AS total_usage,
        rl.limit_value
    FROM 
        users u
    JOIN 
        apartments a ON u.id = a.user_id
    JOIN 
        meter_readings mr ON a.id = mr.apartment_id
    LEFT JOIN 
        resource_limits rl ON mr.service_id = rl.service_id
    WHERE 
        mr.reading_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";

if ($selectedResource) {
    $overLimitQuery .= " AND mr.service_id = ?";
}

$overLimitQuery .= " GROUP BY u.id, u.email, a.address
                     HAVING total_usage > rl.limit_value";

// Подготовка и выполнение запроса
$stmtOverLimit = $conn->prepare($overLimitQuery);
if ($selectedResource) {
    $stmtOverLimit->bind_param("i", $selectedResource);
}
$stmtOverLimit->execute();
$resultOverLimit = $stmtOverLimit->get_result();

if ($resultOverLimit->num_rows > 0) {
    echo "<h2>Пользователи с расходом выше лимита за последние 30 дней</h2>";
    echo "<table border='1' cellpadding='10' cellspacing='0' style='width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background-color: #dc3545; color: white;'><th>Пользователь</th><th>Email</th><th>Адрес</th><th>Использование</th><th>Лимит</th></tr>";

    while ($row = $resultOverLimit->fetch_assoc()) {
        $username = htmlspecialchars($row['username']);
        $email = htmlspecialchars($row['email']);
        $address = htmlspecialchars($row['address']);
        $totalUsage = htmlspecialchars($row['total_usage']);
        $limitValue = htmlspecialchars($row['limit_value'] ?? 0);

        echo "<tr style='background-color: #f8f9fa;'>";
        echo "<td>$username</td>";
        echo "<td>$email</td>";
        echo "<td>$address</td>";
        echo "<td style='color: red;'>$totalUsage</td>"; // Подсветка превышения
        echo "<td>$limitValue</td>";
        echo "</tr>";
    }

    echo "</table>";
} 

// Закрытие соединения
$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Рейтинг пользователей</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Общие стили */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Стили для тела страницы */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(to bottom, #e0eafc, #cfdef3);
            color: #333;
            line-height: 1.8;
            padding: 20px;
            transition: background-color 0.3s ease;
        }

        /* Заголовок страницы */
        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #555;
            font-size: 2.5rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        /* Форма выбора ресурса и времени */
        form {
            text-align: center;
            margin-bottom: 20px;
        }

        form select {
            padding: 10px;
            border: 2px solid #6c757d;
            border-radius: 30px;
            font-size: 1rem;
            margin-right: 10px;
        }

        form button {
            padding: 12px 20px;
            background: linear-gradient(to right, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s ease, background 0.3s ease;
        }

        form button:hover {
            background: linear-gradient(to right, #0056b3, #00408d);
            transform: translateY(-2px);
        }

        /* Стиль для таблицы */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #007bff;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tr:hover {
            background-color: #e2e6ea;
        }

        /* График */
        canvas {
            max-width: 600px;
            margin: auto;
        }

        /* Стили для таблицы пользователей с превышением лимита */
        .over-limit-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .over-limit-table th {
            background-color: #dc3545;
            color: white;
        }

        .over-limit-table td {
            color: red;
        }
    </style>
</head>
<body>
    <form method="POST" action="">
        <label for="resource">Выберите ресурс:</label>
        <select name="resource" id="resource">
            <option value="">Все ресурсы</option>
            <?php foreach ($resources as $resource): ?>
                <option value="<?= $resource['id'] ?>" <?= ($selectedResource == $resource['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($resource['service_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label for="timeframe">Выберите период:</label>
        <select name="timeframe" id="timeframe">
            <option value="all" <?= ($timeframe == 'all') ? 'selected' : '' ?>>Все время</option>
            <option value="last_30_days" <?= ($timeframe == 'last_30_days') ? 'selected' : '' ?>>Последние 30 дней</option>
        </select>
        
        <button type="submit">Показать</button>
    </form>

    <?php if (!empty($data)): ?>
        <h2>График использования ресурса</h2>
        <canvas id="usageChart"></canvas>
        <script>
            const ctx = document.getElementById('usageChart').getContext('2d');
            const usageChart = new Chart(ctx, {
                type: 'doughnut', // Изменяем тип графика на круговой
                data: {
                    labels: <?= json_encode($labels) ?>,
                    datasets: [{
                        label: 'Использование ресурса',
                        data: <?= json_encode($data) ?>,
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(153, 102, 255, 0.5)',
                            'rgba(255, 159, 64, 0.5)',
                        ],
                        borderWidth: 1,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Использование ресурса пользователями'
                        }
                    }
                }
            });
        </script>
        
        <?php if (!empty($overLimitUsers)): ?>
            <h3>Пользователи, превысившие лимит:</h3>
            <table class="over-limit-table">
                <tr><th>Пользователь</th><th>Email</th></tr>
                <?php foreach ($overLimitUsers as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
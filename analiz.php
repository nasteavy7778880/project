<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Получение выбранного периода, типа графика и ресурса
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : '30';
$userId = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$chartType = isset($_GET['chart_type']) ? $_GET['chart_type'] : 'money'; // money или resource
$resourceType = isset($_GET['resource_type']) ? $_GET['resource_type'] : ''; // Вода, газ, отопление

// Получение списка пользователей с ролью 'user'
$usersQuery = "
    SELECT u.id, u.username 
    FROM users u
    JOIN userroles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.role_id
    WHERE r.role_name = 'user'";
$usersResult = $conn->query($usersQuery);
$users = $usersResult->fetch_all(MYSQLI_ASSOC);

// Получение текущих расходов по каждому пользователю и услуге
$currentExpensesQuery = "
    SELECT 
        u.username, 
        t.service_name,
        SUM(mr.value * t.rate_per_unit) AS current_cost,
        SUM(mr.value) AS current_resource
    FROM 
        users u
    JOIN 
        userroles ur ON u.id = ur.user_id
    JOIN 
        roles r ON ur.role_id = r.role_id
    JOIN 
        apartments a ON u.id = a.user_id
    JOIN 
        meter_readings mr ON a.id = mr.apartment_id
    JOIN 
        tariffs t ON mr.service_id = t.id
    WHERE 
        r.role_name = 'user'";

// Проверка условий
$params = [];
$paramTypes = '';

if ($timeframe === '30') {
    $currentExpensesQuery .= " AND mr.reading_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}
if ($userId) {
    $currentExpensesQuery .= " AND u.id = ?";
    $params[] = $userId;
    $paramTypes .= 'i'; // 'i' для integer
}
if ($resourceType) {
    $currentExpensesQuery .= " AND t.service_name = ?";
    $params[] = $resourceType;
    $paramTypes .= 's'; // 's' для string
}

$currentExpensesQuery .= "
    GROUP BY 
        u.id, t.id
    ORDER BY 
        u.username, t.service_name";

$stmt = $conn->prepare($currentExpensesQuery);

// Проверка наличия параметров и их привязка
if ($params) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$currentResult = $stmt->get_result();
$currentExpenses = $currentResult->fetch_all(MYSQLI_ASSOC);

// Получение прогнозируемых расходов
$predictedExpensesQuery = "
    SELECT 
        u.username, 
        t.service_name,
        AVG(mr.value) AS avg_usage,
        AVG(mr.value) * t.rate_per_unit AS predicted_cost,
        AVG(mr.value) AS predicted_resource
    FROM 
        users u
    JOIN 
        userroles ur ON u.id = ur.user_id
    JOIN 
        roles r ON ur.role_id = r.role_id
    JOIN 
        apartments a ON u.id = a.user_id
    JOIN 
        meter_readings mr ON a.id = mr.apartment_id
    JOIN 
        tariffs t ON mr.service_id = t.id
    WHERE 
        r.role_name = 'user' AND
        mr.reading_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";

// Инициализация переменных
$paramsPredicted = [];
$paramTypesPredicted = ''; // Initialize with an empty string

if ($userId) {
    $predictedExpensesQuery .= " AND u.id = ?";
    $paramsPredicted[] = $userId;
    $paramTypesPredicted .= 'i'; // 'i' для integer
}
if ($resourceType) {
    $predictedExpensesQuery .= " AND t.service_name = ?";
    $paramsPredicted[] = $resourceType;
    $paramTypesPredicted .= 's'; // 's' для string
}

$predictedExpensesQuery .= "
    GROUP BY 
        u.id, t.id
    ORDER BY 
        u.username, t.service_name";

$stmt = $conn->prepare($predictedExpensesQuery);

// Проверка наличия параметров и их привязка
if (!empty($paramsPredicted)) {
    $stmt->bind_param($paramTypesPredicted, ...$paramsPredicted);
}
$stmt->execute();
$predictedResult = $stmt->get_result();
$predictedExpenses = $predictedResult->fetch_all(MYSQLI_ASSOC);

// Закрытие соединения с базой данных
$conn->close();

// Подготовка данных для графика
$labels = [];
$currentData = [];
$predictedData = [];
$currentResourceData = [];
$predictedResourceData = [];
foreach ($currentExpenses as $current) {
    $labels[] = $current['username'] . ' (' . $current['service_name'] . ')';
    $currentData[] = $current['current_cost'];
    $currentResourceData[] = $current['current_resource'];
}

foreach ($predictedExpenses as $prediction) {
    $predictedData[] = $prediction['predicted_cost'];
    $predictedResourceData[] = $prediction['predicted_resource'];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Прогноз расходов пользователей</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        #chart-container {
            max-width: 800px;
            margin: 20px auto;
        }
    </style>
</head>
<body>
<div style="display: flex; align-items: center; justify-content: flex-start; margin: 20px 0;">
    <button onclick="window.location.href='manager.php'" style="padding: 5px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-right: 10px;">
        Назад на главную
    </button>

    <button onclick="window.location.href='generate_report.php'">Сгенерировать отчет</button>

    <h1 style="margin: 0; margin-left: 60px;">Прогноз расходов пользователей на следующий месяц</h1>
</div>
    <div>
        <label for="timeframe">Период:</label>
        <select id="timeframe">
            <option value="30" <?php echo ($timeframe === '30') ? 'selected' : ''; ?>>Последние 30 дней</option>
            <option value="all" <?php echo ($timeframe === 'all') ? 'selected' : ''; ?>>Все время</option>
        </select>

        <label for="userSelect">Пользователь:</label>
        <select id="userSelect">
            <option value="">Выберите пользователя</option>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>" <?php echo ($userId == $user['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user['username']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="resourceSelect">Ресурс:</label>
        <select id="resourceSelect">
            <option value="">Все ресурсы</option>
            <option value="Вода" <?php echo ($resourceType === 'Вода') ? 'selected' : ''; ?>>Вода</option>
            <option value="Газ" <?php echo ($resourceType === 'Газ') ? 'selected' : ''; ?>>Газ</option>
            <option value="Отопление" <?php echo ($resourceType === 'Отопление') ? 'selected' : ''; ?>>Отопление</option>
        </select>

        <label for="chartType">Тип графика:</label>
        <select id="chartType">
            <option value="money" <?php echo ($chartType === 'money') ? 'selected' : ''; ?>>Расходы (руб.)</option>
            <option value="resource" <?php echo ($chartType === 'resource') ? 'selected' : ''; ?>>Расход ресурсов</option>
        </select>
    </div>

    <table>
        <thead>
            <tr>
                <th>Пользователь</th>
                <th>Услуга</th>
                <th>Текущие расходы</th>
                <th>Расход ресурса</th> <!-- New Column -->
                <th>Предсказанные расходы</th>
                <th>Предсказанный расход ресурса</th> <!-- New Column for Predicted Resource -->
            </tr>
        </thead>
        <tbody>
            <?php foreach ($currentExpenses as $current): ?>
                <tr>
                    <td><?php echo htmlspecialchars($current['username']); ?></td>
                    <td><?php echo htmlspecialchars($current['service_name']); ?></td>
                    <td><?php echo htmlspecialchars(number_format($current['current_cost'], 2)); ?> руб.</td>
                    <td><?php echo htmlspecialchars(number_format($current['current_resource'], 2)); ?> единиц.</td>
                    <td><?php 
                        // Найти предсказанные расходы для того же пользователя и услуги
                        $predictedCost = 0;
                        $predictedResource = 0; // Initialize predicted resource
                        foreach ($predictedExpenses as $prediction) {
                            if ($prediction['username'] == $current['username'] && $prediction['service_name'] == $current['service_name']) {
                                $predictedCost = $prediction['predicted_cost'];
                                $predictedResource = $prediction['predicted_resource']; // Get predicted resource
                            }
                        }
                        echo htmlspecialchars(number_format($predictedCost, 2)); ?> руб.</td>
                    <td><?php echo htmlspecialchars(number_format($predictedResource, 2)); ?> единиц.</td> <!-- New Data for Predicted Resource -->
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div id="chart-container">
        <canvas id="expenseChart" width="800" height="400"></canvas>
    </div>

    <script>
        const labels = <?php echo json_encode($labels); ?>;
        const currentData = <?php echo json_encode($currentData); ?>;
        const predictedData = <?php echo json_encode($predictedData); ?>;
        const currentResourceData = <?php echo json_encode($currentResourceData); ?>;
        const predictedResourceData = <?php echo json_encode($predictedResourceData); ?>;

        const ctx = document.getElementById('expenseChart').getContext('2d');
        const expenseChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Текущие расходы',
                        data: currentData,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Предсказанные расходы',
                        data: predictedData,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Сумма (руб.)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        enabled: true,
                    }
                }
            }
        });

        document.getElementById('chartType').addEventListener('change', function() {
            const selectedChartType = this.value;

            if (selectedChartType === 'money') {
                expenseChart.data.datasets[0].data = currentData;
                expenseChart.data.datasets[1].data = predictedData;
                expenseChart.data.datasets[0].label = 'Текущие расходы';
                expenseChart.data.datasets[1].label = 'Предсказанные расходы';
            } else {
                expenseChart.data.datasets[0].data = currentResourceData;
                expenseChart.data.datasets[1].data = predictedResourceData;
                expenseChart.data.datasets[0].label = 'Расход ресурсов';
                expenseChart.data.datasets[1].label = 'Предсказанный расход ресурсов';
            }
            expenseChart.update();
        });

        document.getElementById('timeframe').addEventListener('change', function() {
            const timeframe = this.value;
            const userId = document.getElementById('userSelect').value;
            const chartType = document.getElementById('chartType').value;
            const resourceType = document.getElementById('resourceSelect').value;
            window.location.href = '?timeframe=' + timeframe + '&user_id=' + userId + '&chart_type=' + chartType + '&resource_type=' + resourceType; // Перезагрузка страницы с новыми параметрами
        });

        document.getElementById('userSelect').addEventListener('change', function() {
            const userId = this.value;
            const timeframe = document.getElementById('timeframe').value;
            const chartType = document.getElementById('chartType').value;
            const resourceType = document.getElementById('resourceSelect').value;
            window.location.href = '?timeframe=' + timeframe + '&user_id=' + userId + '&chart_type=' + chartType + '&resource_type=' + resourceType; // Перезагрузка страницы с новыми параметрами
        });

        document.getElementById('resourceSelect').addEventListener('change', function() {
            const resourceType = this.value;
            const timeframe = document.getElementById('timeframe').value;
            const userId = document.getElementById('userSelect').value;
            const chartType = document.getElementById('chartType').value;
            window.location.href = '?timeframe=' + timeframe + '&user_id=' + userId + '&chart_type=' + chartType + '&resource_type=' + resourceType; // Перезагрузка страницы с новыми параметрами
        });

        // Инициализация графика с выбранным типом
        // Убедитесь, что график инициализируется правильно в зависимости от выбранного типа
        document.getElementById('chartType').dispatchEvent(new Event('change'));
    </script>
</body>
</html>
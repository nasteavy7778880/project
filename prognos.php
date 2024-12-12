<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Получение ID текущего пользователя
$currentUserId = $_SESSION['id'];

// Получение выбранного периода, типа графика и ресурса
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : '30';
$chartType = isset($_GET['chart_type']) ? $_GET['chart_type'] : 'money'; // money или resource
$resourceType = isset($_GET['resource_type']) ? $_GET['resource_type'] : ''; // Вода, газ, отопление

// Получение текущих расходов по пользователю
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
        r.role_name = 'user' AND
        u.id = ?"; // Фильтрация по текущему пользователю

// Проверка условий
$params = [$currentUserId];
$paramTypes = 'i'; // 'i' для integer

if ($timeframe === '30') {
    $currentExpensesQuery .= " AND mr.reading_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
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
$stmt->bind_param($paramTypes, ...$params);
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
        u.id = ? AND
        mr.reading_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)"; // Фильтрация по текущему пользователю

$paramsPredicted = [$currentUserId];
$paramTypesPredicted = 'i'; // 'i' для integer

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
$stmt->bind_param($paramTypesPredicted, ...$paramsPredicted);
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

        
/* Кнопки страницы */
button,
.logout-button {
    padding: 12px 20px;
    background: linear-gradient(to right, #007bff, #0056b3);
    color: white;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    font-size: 1rem;
    transition: transform 0.2s ease, background 0.3s ease;
}

button:hover,
.logout-button:hover {
    background: linear-gradient(to right, #0056b3, #00408d);
    transform: translateY(-2px);
}
    </style>
</head>
<body>
<div style="display: flex; align-items: center; justify-content: flex-start; margin: 20px 0;">
<button type="button" class="logout-button" onclick="window.location.href = 'user.php';">Назад</button>
    <h1 style="margin: 0; margin-left: 300px;">Прогноз расходов на следующий месяц</h1>
</div>
    <div>
        <label for="timeframe">Период:</label>
        <select id="timeframe">
            <option value="30" <?php echo ($timeframe === '30') ? 'selected' : ''; ?>>Последние 30 дней</option>
            <option value="all" <?php echo ($timeframe === 'all') ? 'selected' : ''; ?>>Все время</option>
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
            const chartType = document.getElementById('chartType').value;
            const resourceType = document.getElementById('resourceSelect').value;
            window.location.href = '?timeframe=' + timeframe + '&chart_type=' + chartType + '&resource_type=' + resourceType; // Перезагрузка страницы с новыми параметрами
        });

        document.getElementById('resourceSelect').addEventListener('change', function() {
            const resourceType = this.value;
            const timeframe = document.getElementById('timeframe').value;
            const chartType = document.getElementById('chartType').value;
            window.location.href = '?timeframe=' + timeframe + '&chart_type=' + chartType + '&resource_type=' + resourceType; // Перезагрузка страницы с новыми параметрами
        });

        // Инициализация графика с выбранным типом
        document.getElementById('chartType').dispatchEvent(new Event('change'));
    </script>
</body>
</html>
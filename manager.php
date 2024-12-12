<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require_once('dbd_config.php');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current usage data by user and service for the last 30 days
$currentExpensesQuery = "
    SELECT 
        u.username,
        t.service_name,
        SUM(mr.value) AS total_usage,
        rl.limit_value
    FROM 
        users u
    JOIN 
        apartments a ON u.id = a.user_id
    JOIN 
        meter_readings mr ON a.id = mr.apartment_id
    JOIN 
        tariffs t ON mr.service_id = t.id
    LEFT JOIN 
        resource_limits rl ON t.id = rl.service_id
    WHERE 
        mr.reading_date >= NOW() - INTERVAL 30 DAY
    GROUP BY 
        u.username, t.service_name
    ORDER BY 
        t.service_name, u.username";

$currentExpensesResult = $conn->query($currentExpensesQuery);
$currentExpenses = $currentExpensesResult->fetch_all(MYSQLI_ASSOC);

$alerts = [];
$usageByService = [];

// Prepare data for charts and alerts
foreach ($currentExpenses as $expense) {
    $username = htmlspecialchars($expense['username']);
    $serviceName = htmlspecialchars($expense['service_name']);
    $totalUsage = (float)$expense['total_usage'];
    $limitValue = (float)$expense['limit_value'];

    // Group data by service type
    if (!isset($usageByService[$serviceName])) {
        $usageByService[$serviceName] = ['labels' => [], 'data' => []];
    }
    $usageByService[$serviceName]['labels'][] = $username;
    $usageByService[$serviceName]['data'][] = $totalUsage;

    // Check if usage exceeds limit
    if (!empty($limitValue) && $totalUsage > $limitValue) {
        $overLimit = $totalUsage - $limitValue;
        $alerts[] = "$username: превышен лимит для $serviceName (использование: $totalUsage, лимит: $limitValue, превышение: $overLimit)";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления</title>
    <link rel="stylesheet" href="style_for_user.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Уменьшаем отступы сверху и между кнопками */
        .button-group {
            margin-top: 5px; /* Уменьшаем отступ сверху */
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px; /* Уменьшаем расстояние между кнопками */
        }

        .button-group button {
            padding: 8px 16px; /* Уменьшаем размер кнопок */
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .button-group button:hover {
            background-color: #45a049;
        }

        .service-buttons-container {
            margin-top: 30px;
            text-align: center;
        }

        .service-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }

        .service-button {
            margin: 5px;
            padding: 10px 20px;
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .service-button:hover {
            background-color: #0b7dda;
        }

        .usage-chart-container {
            position: relative;
            width: 80%;
            height: 400px;
            margin: 0 auto;
            max-width: 800px;
        }

        .alerts {
            background-color: rgba(255, 255, 255, 0.8);
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            margin: 20px auto;
            width: 100%;
            max-width: 800px;
            box-sizing: border-box;
        }

        .alert-message {
            color: #ff0000;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
       
        <div class="button-group">
            <button onclick="window.location.href='manage.php'">Сводка по счетам пользователей</button>
            <button onclick="window.location.href='tarif.php'">Управление тарифами</button>
            <button onclick="window.location.href='problem_request.php'">Заявки на услуги</button>
            <button onclick="window.location.href='services.php'">Управление услугами</button>
            <button onclick="window.location.href='bookings.php'">Забронированные услуги</button>
            <button onclick="window.location.href='news.php'">Добавление объявлений</button>
            <button onclick="window.location.href='login.php'">Выход</button>
        </div>

        <div class="service-buttons-container">
            <div class="service-buttons">
                <?php foreach ($usageByService as $serviceName => $usage): ?>
                    <button class="service-button" onclick="showChart('<?= htmlspecialchars($serviceName) ?>')"><?= htmlspecialchars($serviceName) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="main-content">
            <?php if (!empty($alerts)): ?>
                <div class="alerts">
                    <?php foreach ($alerts as $alert): ?>
                        <p class="alert-message"><?= $alert ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="usage-chart-container">
                <canvas id="usagePieChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        const usageByService = <?= json_encode($usageByService) ?>;
        const ctx = document.getElementById('usagePieChart').getContext('2d');
        let usagePieChart;

        function showChart(serviceName) {
            const labels = usageByService[serviceName].labels;
            const data = usageByService[serviceName].data;

            if (usagePieChart) {
                usagePieChart.destroy();
            }

            usagePieChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Использование ресурсов (%)',
                        data: data,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)'
                        ],
                        borderColor: 'rgba(255, 255, 255, 1)',
                        borderWidth: 1
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
                            text: 'Использование ресурсов: ' + serviceName
                        }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const firstService = Object.keys(usageByService)[0];
            showChart(firstService);
        });
    </script>
</body>
</html>

<?php
$conn->close();
?> 

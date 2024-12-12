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

$username = htmlspecialchars($_SESSION['username']);
$query = "SELECT title, content FROM news";
$result = $conn->query($query);

$userId = $_SESSION['id'];

// Получаем все доступные ресурсы
$resourcesQuery = "SELECT id, service_name FROM tariffs";
$resourcesResult = $conn->query($resourcesQuery);

$resources = [];
while ($row = $resourcesResult->fetch_assoc()) {
    $resources[] = $row;
}

// Фильтрация по текущему месяцу
$timeFilter = "AND MONTH(mr.reading_date) = MONTH(CURRENT_DATE()) AND YEAR(mr.reading_date) = YEAR(CURRENT_DATE())";

// Получение данных по выбранному ресурсу
$selectedResourceId = isset($_GET['resource_id']) ? $_GET['resource_id'] : null;

// Запрос для получения данных по расходу для выбранного ресурса или всех ресурсов
$usageQuery = "
    SELECT 
        t.service_name,
        SUM(mr.value) AS total_usage,
        rl.limit_value
    FROM 
        meter_readings mr
    JOIN 
        apartments a ON mr.apartment_id = a.id
    JOIN 
        tariffs t ON mr.service_id = t.id
    LEFT JOIN 
        resource_limits rl ON t.id = rl.service_id
    WHERE 
        a.user_id = ? 
        " . ($selectedResourceId ? "AND t.id = ?" : "") . "
        $timeFilter
    GROUP BY 
        t.service_name, rl.limit_value
";

$stmt = $conn->prepare($usageQuery);
if ($selectedResourceId) {
    $stmt->bind_param("ii", $userId, $selectedResourceId);
} else {
    $stmt->bind_param("i", $userId);
}
$stmt->execute();
$usageResult = $stmt->get_result();

$labels = [];
$data = [];
$limits = [];
$alerts = [];

if ($usageResult->num_rows > 0) {
    while ($row = $usageResult->fetch_assoc()) {
        $serviceName = htmlspecialchars($row['service_name']);
        $totalUsage = (float)$row['total_usage'];
        $limitValue = (float)$row['limit_value'];

        $labels[] = $serviceName;
        $data[] = $totalUsage;
        $limits[] = $limitValue;

        // Уведомление о превышении лимита за текущий месяц
        if (!empty($limitValue) && $totalUsage > $limitValue) {
            $alerts[] = "$serviceName: превышен лимит (использование: $totalUsage, лимит: $limitValue)";
        }
    }
} else {
    $alerts[] = "Нет данных для выбранного ресурса и периода.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель пользователя</title>
    <link rel="stylesheet" href="style_for_user.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .alert-message {
            color: #ff0000;
            font-size: 12px;
        }
        .alerts {
            background-color: rgba(255, 255, 255, 0.8);
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            margin-top: 10px;
            margin-bottom: 90px;
            width: 55%;
            margin-left: auto;
            margin-right: auto;
        }
        .usage-chart-container {
            position: relative;
            margin-bottom: 60px;
        }
        .filter-container {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <img src="image.png" alt="Описание изображения" class="panel-image" />
    <div class="button-group">
        <button onclick="window.location.href='flat.php'">Редактировать данные о недвижимости</button>
        <button onclick="window.location.href='add_readings.php'">Оплатить счета</button>
        <button onclick="window.location.href='history.php'">Счета</button>
        <button onclick="window.location.href='problem.php'">Подача заявки на услугу</button>
        <button onclick="window.location.href='history2.php'">История поданных заявок</button>
        <button onclick="window.location.href='serv.php'">Услуги ЖКХ</button>
        <button onclick="window.location.href='bookings_serv.php'">Забронированные Услуги ЖКХ</button>
        <button onclick="window.location.href='login.php'">Выход</button>
    </div>
</div>

<div class="main-content">
    <div class="news-feed">
        <h3>Лента новостей</h3>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<div class='news-item'>";
                echo "<h4>" . htmlspecialchars($row['title']) . "</h4>";
                echo "<p>" . nl2br(htmlspecialchars($row['content'])) . "</p>";
                echo "</div>";
            }
        } else {
            echo "<p>Новостей пока нет.</p>";
        }
        ?>
    </div>

    <div class="usage-chart-container">
        <div class="filter-container">
            <form method="get">
                <label>
                    <select name="resource_id" onchange="this.form.submit()">
                        <option value="" <?= !$selectedResourceId ? 'selected' : '' ?>>Все ресурсы</option>
                        <?php foreach ($resources as $resource): ?>
                            <option value="<?= $resource['id'] ?>" <?= $resource['id'] == $selectedResourceId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($resource['service_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        </div>

        <canvas id="usageChart"></canvas>
    </div>

    <?php if (!empty($alerts)): ?>
        <div class="alerts">
            <?php foreach ($alerts as $alert): ?>
                <p class="alert-message"><?= $alert ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
    const ctx = document.getElementById('usageChart').getContext('2d');
    const data = <?= json_encode($data) ?>;
    const limits = <?= json_encode($limits) ?>;
    const labels = <?= json_encode($labels) ?>;

    const barColors = data.map((value, index) => {
        return value > limits[index] ? 'rgba(255, 99, 132, 0.5)' : 'rgba(75, 192, 192, 0.5)';
    });

    const dataset = data.map((value, index) => {
        return {
            label: labels[index] + (value === 0 ? ' (нет использования)' : ''),
            data: [value],
            backgroundColor: barColors[index],
            borderWidth: 1,
            borderColor: '#fff'
        };
    });

    const usageChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: dataset
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Использование ресурса'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Использование (ед.)'
                    }
                }
            }
        }
    });
</script>

</body>
</html>

<?php
$conn->close();
?>

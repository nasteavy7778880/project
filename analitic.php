<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Получение ID пользователя из сессии
$user_id = $_SESSION['id'];

// Функция для получения сумм счетов
function getServiceTotals($conn, $user_id, $timeframe) {
    $whereClause = '';
    if ($timeframe === '30') {
        $whereClause = "WHERE b.billing_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    }

    $query = "
        SELECT t.service_name, SUM(b.total_amount) AS total_amount
        FROM bills b
        JOIN meter_readings m ON b.apartment_id = m.apartment_id
        JOIN tariffs t ON m.service_id = t.id
        $whereClause
        AND b.apartment_id IN (SELECT id FROM apartments WHERE user_id = ?)
        GROUP BY t.service_name";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $service_totals = [];

    while ($row = $result->fetch_assoc()) {
        $service_totals[] = $row;
    }
    
    $stmt->close();
    return $service_totals;
}

// Получение данных по умолчанию (все время)
$service_totals = getServiceTotals($conn, $user_id, 'all');

// Подготовка данных для графика
$labels = [];
$data = [];

foreach ($service_totals as $service) {
    $labels[] = htmlspecialchars($service['service_name']); // Название услуги
    $data[] = (float)$service['total_amount']; // Сумма по услуге
}

// Закрытие соединения с базой данных
$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История счетов</title>
    <link rel="stylesheet" href="style_for_user_page.css">
    <link rel="stylesheet" href="style3.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        #servicesChart {
            max-width: 400px; /* Уменьшение ширины диаграммы */
            margin: auto; /* Центрирование диаграммы */
        }
        .controls {
            text-align: center;
            margin: 20px 0;
        }

        /* Кнопки страницы */
        .styled-button {
            padding: 12px 20px;
            background: linear-gradient(to right, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s ease, background 0.3s ease;
        }

        .styled-button:hover {
            background: linear-gradient(to right, #0056b3, #00408d);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <h1>История счетов</h1>

    <div class="controls">
        <button id="last30DaysBtn" class="styled-button">Последние 30 дней</button>
        <button id="allTimeBtn" class="styled-button">Все время</button>
    </div>

    <p id="timeframeText" style="text-align: center; font-size: 1.2rem; margin-top: 20px;"></p>

    <canvas id="servicesChart" width="400" height="200"></canvas>
    <div class="card-container" id="serviceCards">
        <?php if (empty($service_totals)): ?>
            <p>Нет доступных счетов.</p>
        <?php else: ?>
            <?php foreach ($service_totals as $service): ?>
                <div class="card">
                    <h3>Счет за <?= htmlspecialchars($service['service_name']) ?></h3>
                    <p><strong>Общая сумма:</strong> <?= number_format($service['total_amount'], 2, '.', '') ?> ₽</p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        const ctx = document.getElementById('servicesChart').getContext('2d');
        let servicesChart = null;

        const renderChart = (data, labels) => {
            if (servicesChart) {
                servicesChart.destroy(); // Удаление предыдущего графика
            }
            servicesChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Оплаченные суммы по услугам',
                        data: data,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                        ],
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
                            text: 'Оплаченные суммы по услугам'
                        }
                    }
                }
            });
        };

        const updateData = (timeframe) => {
            fetch(`get_service_totals.php?timeframe=${timeframe}`)
                .then(response => response.json())
                .then(data => {
                    const labels = data.map(service => service.service_name);
                    const amounts = data.map(service => service.total_amount);
                    renderChart(amounts, labels);

                    const serviceCardsContainer = document.getElementById('serviceCards');
                    serviceCardsContainer.innerHTML = ''; // Очистка предыдущих карточек
                    data.forEach(service => {
                        const card = document.createElement('div');
                        card.className = 'card';
                        card.innerHTML = `
                            <h3>Счет за ${service.service_name}</h3>
                            <p><strong>Общая сумма:</strong> ${parseFloat(service.total_amount).toFixed(2)} ₽</p>
                        `;
                        serviceCardsContainer.appendChild(card);
                    });

                    // Обновление текста в зависимости от выбранного временного периода
                    const timeframeText = document.getElementById('timeframeText');
                    if (timeframe === '30') {
                        timeframeText.textContent = 'Данные за последние 30 дней';
                    } else {
                        timeframeText.textContent = 'Данные за все время';
                    }
                });
        };

        // Начальная отрисовка графика
        renderChart(<?= json_encode($data) ?>, <?= json_encode($labels) ?>);

        document.getElementById('last30DaysBtn').addEventListener('click', function() {
            updateData('30'); // Получение данных за последние 30 дней
        });

        document.getElementById('allTimeBtn').addEventListener('click', function() {
            updateData('all'); // Получение данных за все время
        });
    </script>
</body>
</html>
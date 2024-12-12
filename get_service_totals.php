<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Получение ID пользователя из сессии
$user_id = $_SESSION['id'];
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'all';

// Получение сумм счетов для каждой услуги
$service_totals = getServiceTotals($conn, $user_id, $timeframe);

// Форматирование данных для ответа
$response = [];
foreach ($service_totals as $service) {
    $response[] = [
        'service_name' => htmlspecialchars($service['service_name']),
        'total_amount' => (float)$service['total_amount']
    ];
}

// Возвращение данных в формате JSON
header('Content-Type: application/json');
echo json_encode($response);

// Функция для получения данных
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
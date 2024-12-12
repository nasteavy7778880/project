<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Получение ID пользователя из сессии
$user_id = $_SESSION['id'];

// Получение квартир пользователя
$query = "SELECT id, address FROM apartments WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$apartments = $result->fetch_all(MYSQLI_ASSOC);

// Получение тарифов
$query = "SELECT id, service_name, rate_per_unit, unit_of_measurement FROM tariffs";
$tariffs = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

$message = ""; // Переменная для сообщений

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $apartment_id = $_POST['apartment_id'];
    $service_id = $_POST['service_id'];
    $reading_date = $_POST['reading_date'];
    $value = $_POST['value'];

    // Проверка на существование показаний с той же датой для данной квартиры и услуги
    $query = "SELECT id FROM meter_readings WHERE apartment_id = ? AND service_id = ? AND reading_date = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $apartment_id, $service_id, $reading_date);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $message = "Показания за эту дату уже добавлены.";
    } else {
        // Получение тарифа
        $query = "SELECT rate_per_unit FROM tariffs WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $stmt->bind_result($rate);
        $stmt->fetch();
        $stmt->close();

        // Расчет общей суммы
        $total_amount = $value * $rate;

        // Вставка показаний счетчика
        $query = "INSERT INTO meter_readings (apartment_id, service_id, reading_date, value) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iisd", $apartment_id, $service_id, $reading_date, $value);

        if ($stmt->execute()) {
            // Вставка информации о счете
            $query = "INSERT INTO bills (apartment_id, billing_date, total_amount) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isd", $apartment_id, $reading_date, $total_amount);

            if ($stmt->execute()) {
                $message = "Показания успешно добавлены, счет сгенерирован!";
            } else {
                $message = "Ошибка при добавлении счета: " . $stmt->error;
            }
        } else {
            $message = "Ошибка при добавлении показаний: " . $stmt->error;
        }
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style_for_add_bills.css">
    <title>Ввод показаний счетчика</title>
    <style>
        .message-box {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 10px;
            margin: 10px 0;
            color: #333;
            font-size: 14px;
            max-width: 500px;
        }
    </style>
</head>
<body>
    <h1>Ввод показаний счетчика</h1>

    <?php if (!empty($message)): ?>
        <div class="message-box">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label for="apartment_id">Выберите квартиру:</label>
        <select name="apartment_id" id="apartment_id" required>
            <?php foreach ($apartments as $apartment): ?>
                <option value="<?= $apartment['id'] ?>"><?= htmlspecialchars($apartment['address']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="service_id">Выберите услугу:</label>
        <select name="service_id" id="service_id" required>
            <?php foreach ($tariffs as $tariff): ?>
                <option value="<?= $tariff['id'] ?>"><?= htmlspecialchars($tariff['service_name']) ?> (<?= htmlspecialchars($tariff['unit_of_measurement']) ?>)</option>
            <?php endforeach; ?>
        </select>

        <label for="reading_date">Дата показания:</label>
        <input type="date" name="reading_date" id="reading_date" required>

        <label for="value">Показание счетчика:</label>
        <input type="number" name="value" id="value" required>

        <button type="submit">Отправить</button>
    </form>
</body>
</html>
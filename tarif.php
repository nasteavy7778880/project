<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Инициализация переменных для сообщений
$error_message = '';
$success_message = '';

// Обработка добавления нового тарифа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_tariff'])) {
    $service_name = $_POST['service_name'];
    $rate_per_unit = $_POST['rate_per_unit'];
    $unit_of_measurement = $_POST['unit_of_measurement'];

    $query = "INSERT INTO tariffs (service_name, rate_per_unit, unit_of_measurement) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->bind_param("sds", $service_name, $rate_per_unit, $unit_of_measurement);
        if ($stmt->execute()) {
            $success_message = "Тариф успешно добавлен.";
        } else {
            $error_message = "Ошибка при добавлении тарифа: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "Ошибка подготовки запроса.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Обработка редактирования тарифа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_tariff'])) {
    $tariff_id = $_POST['tariff_id'];
    $new_rate_per_unit = $_POST['new_rate_per_unit'];

    $query = "UPDATE tariffs SET rate_per_unit = ? WHERE id = ?";
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->bind_param("di", $new_rate_per_unit, $tariff_id);
        if ($stmt->execute()) {
            $success_message = "Тариф успешно обновлен.";
        } else {
            $error_message = "Ошибка при обновлении тарифа: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "Ошибка подготовки запроса.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Обработка удаления тарифа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_tariff'])) {
    $tariff_id = $_POST['tariff_id'];

    $query = "DELETE FROM tariffs WHERE id = ?";
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->bind_param("i", $tariff_id);
        if ($stmt->execute()) {
            $success_message = "Тариф успешно удален.";
        } else {
            $error_message = "Ошибка при удалении тарифа: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "Ошибка подготовки запроса.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Получение всех тарифов для отображения
$query = "SELECT * FROM tariffs";
$result = $conn->query($query);
$tariffs = $result->fetch_all(MYSQLI_ASSOC);

// Закрытие соединения с базой данных
$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление тарифами</title>
    <link rel="stylesheet" href="style4.css">
</head>
<body>
    <h1>Управление тарифами</h1>

    <?php if ($error_message): ?>
        <div style="color: red;"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div style="color: green;"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <h2></h2>
    <form method="POST">
        <label for="service_name">Название услуги:</label>
        <input type="text" name="service_name" id="service_name" required>
        
        <label for="rate_per_unit">Тариф за единицу:</label>
        <input type="number" step="0.01" name="rate_per_unit" id="rate_per_unit" required>
        
        <label for="unit_of_measurement">Единица измерения:</label>
        <input type="text" name="unit_of_measurement" id="unit_of_measurement" required>
        
        <button type="submit" name="add_tariff">Добавить тариф</button>
    </form>

    <h2>Существующие тарифы</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Название услуги</th>
                <th>Тариф за единицу</th>
                <th>Единица измерения</th>
                <th>Редактирование</th>
                <th>Удаление</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tariffs as $tariff): ?>
                <tr>
                    <td><?= htmlspecialchars($tariff['service_name']) ?></td>
                    <td><?= htmlspecialchars($tariff['rate_per_unit']) ?></td>
                    <td><?= htmlspecialchars($tariff['unit_of_measurement']) ?></td>
                    
                    <!-- Форма для редактирования -->
                    <td>
                        <form method="POST">
                            <input type="hidden" name="tariff_id" value="<?= $tariff['id'] ?>">
                            <input type="number" step="0.01" name="new_rate_per_unit" required>
                            <button type="submit" name="edit_tariff">Редактировать</button>
                        </form>
                    </td>
                    
                    <!-- Форма для удаления -->
                    <td>
                        <form method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить тариф?');">
                            <input type="hidden" name="tariff_id" value="<?= $tariff['id'] ?>">
                            <button type="submit" name="delete_tariff">Удалить</button>
                        </form>
                    </td>
                    
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>

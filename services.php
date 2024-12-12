<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка авторизации
if (!isset($_SESSION['username'])) { 
    header("Location: login.php"); 
    exit(); 
}

// Получение ID менеджера из сессии
$manager_id = $_SESSION['id'];

// Обработка добавления услуги
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_service'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $type = $_POST['type'];
    $cost = $_POST['cost'] ?: NULL; // Если стоимость не указана, будет NULL

    $query = "INSERT INTO services (name, description, manager_id, type, cost) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssisi", $name, $description, $manager_id, $type, $cost);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Услуга успешно добавлена!";
    } else {
        echo "Ошибка при добавлении услуги: " . $stmt->error;
    }

    $stmt->close();

    // Перенаправление после успешного выполнения
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Обработка удаления услуги
if (isset($_GET['delete'])) {
    $service_id = $_GET['delete'];

    $query = "DELETE FROM services WHERE id = ? AND manager_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $service_id, $manager_id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Услуга успешно удалена!";
    } else {
        echo "Ошибка при удалении услуги: " . $stmt->error;
    }

    $stmt->close();

    // Перенаправление после удаления
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Получение услуг текущего менеджера
$query = "SELECT * FROM services WHERE manager_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$result = $stmt->get_result();
$services = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Закрытие соединения с базой данных
$conn->close();
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление услугами</title>
    <link rel="stylesheet" href="style_add_services.css">

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
    </style>
</head>
<body>
    <h1>Управление услугами</h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <p style="color: green;"><?= htmlspecialchars($_SESSION['success_message']) ?></p>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Форма для добавления услуги -->
    <form method="POST" style="margin-bottom: 20px;">
        <input type="text" name="name" placeholder="Название услуги" required>
        <textarea name="description" placeholder="Описание услуги" required></textarea>
        <select name="type">
            <option value="бесплатная">Бесплатная</option>
            <option value="платная">Платная</option>
        </select>
        <input type="number" name="cost" placeholder="Стоимость" step="0.01">
        <button type="submit" name="add_service">Добавить услугу</button>
    </form>

    <h2>Ваши услуги</h2>
    <div class="card-container">
        <?php if (empty($services)): ?>
            <p>У вас нет добавленных услуг.</p>
        <?php else: ?>
            <?php foreach ($services as $service): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($service['name']) ?></h3>
                    <p><strong>Описание:</strong> <?= htmlspecialchars($service['description']) ?></p>
                    <p><strong>Тип:</strong> <?= htmlspecialchars($service['type']) ?></p>
                    <p><strong>Стоимость:</strong> <?= htmlspecialchars($service['cost'] ?? 'Бесплатно') ?></p>
                    <a href="?delete=<?= $service['id'] ?>" 
                       onclick="return confirm('Вы уверены, что хотите удалить эту услугу?');">
                       Удалить услугу
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <button onclick="window.location.href='manager.php'">Назад на главную менеджера</button>
</body>
</html>

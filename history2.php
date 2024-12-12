<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка авторизации
if (!isset($_SESSION['username'])) { 
    header("Location: login.php"); 
    exit(); 
}

// Инициализация переменных поиска
$search_query = '';
$search_status = '';
$search_date = '';

// Проверка на отправку формы
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['search'])) {
        $search_query = $_POST['search_query'];
        $search_status = $_POST['search_status'];
        $search_date = $_POST['search_date'];

        // Сохранение параметров поиска в сессии
        $_SESSION['search_query'] = $search_query;
        $_SESSION['search_status'] = $search_status;
        $_SESSION['search_date'] = $search_date;

        // Перенаправление после обработки формы
        header("Location: " . $_SERVER['PHP_SELF'] . "?search=1");
        exit();
    }

    if (isset($_POST['update_status'])) {
        $request_id = $_POST['request_id'];
        $new_status = $_POST['status'];

        // Сопоставление статусов
        $status_mapping = [
            'pending' => 'в ожидании',
            'approved' => 'одобрено',
            'rejected' => 'отклонено',
        ];

        $new_status = $status_mapping[$new_status] ?? $new_status;

        // Обновление статуса заявки
        $query = "UPDATE service_requests SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $new_status, $request_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Статус заявки успешно обновлен!";
        } else {
            $_SESSION['error_message'] = "Ошибка при обновлении статуса: " . $stmt->error;
        }

        // Перенаправление после обработки
        header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
        exit();
    }

    if (isset($_POST['clear_search'])) {
        unset($_SESSION['search_query']);
        unset($_SESSION['search_status']);
        unset($_SESSION['search_date']);
        header("Location: " . $_SERVER['PHP_SELF'] . "?cleared=1");
        exit();
    }
}

// Получение параметров поиска из сессии
$search_query = $_SESSION['search_query'] ?? '';
$search_status = $_SESSION['search_status'] ?? '';
$search_date = $_SESSION['search_date'] ?? '';

// Подготовка SQL-запроса с возможностью поиска
$query = "SELECT sr.id, sr.issue_description, sr.request_date, sr.status, a.address 
          FROM service_requests sr 
          JOIN apartments a ON sr.apartment_id = a.id 
          WHERE (sr.issue_description LIKE ? OR a.address LIKE ?)";

$like_query = '%' . $search_query . '%';
$stmt_params = [$like_query, $like_query];

if (!empty($search_status)) {
    $query .= " AND sr.status = ?";
    $stmt_params[] = $search_status;
}

if (!empty($search_date)) {
    $query .= " AND sr.request_date = ?";
    $stmt_params[] = $search_date;
}

$stmt = $conn->prepare($query);
$stmt->bind_param(str_repeat('s', count($stmt_params)), ...$stmt_params);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);

// Закрытие соединения с базой данных
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style_request.css">
    <title>Заявки на услуги</title>
    <link rel="stylesheet" href="style10.css">
   
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
        .back-button {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 10px 15px;
            background-color: #007BFF;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            font-size: 14px;
        }
        .back-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    
    <h1>Заявки на услуги</h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <p style="color: green;"><?= htmlspecialchars($_SESSION['success_message']) ?></p>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <p style="color: red;"><?= htmlspecialchars($_SESSION['error_message']) ?></p>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Форма поиска -->
    <form method="POST" style="margin-bottom: 20px;">
        <input type="text" name="search_query" placeholder="Поиск по описанию или адресу" value="<?= htmlspecialchars($search_query) ?>">
        
        <select name="search_status">
            <option value="">Все статусы</option>
            <option value="в ожидании" <?= $search_status == 'в ожидании' ? 'selected' : '' ?>>Ожидает</option>
            <option value="одобрено" <?= $search_status == 'одобрено' ? 'selected' : '' ?>>Одобрена</option>
            <option value="отклонено" <?= $search_status == 'отклонено' ? 'selected' : '' ?>>Отклонена</option>
        </select>

        <input type="date" name="search_date" value="<?= htmlspecialchars($search_date) ?>">

        <button type="submit" name="search">Поиск</button>
        <button type="submit" name="clear_search">Сбросить фильтры</button>
    </form>

    <div class="card-container">
        <?php if (empty($requests)): ?>
            <p>Нет доступных заявок.</p>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
                <div class="card">
                    <h3>Заявка #<?= htmlspecialchars($request['id']) ?></h3>
                    <p><strong>Квартира:</strong> <?= htmlspecialchars($request['address']) ?></p>
                    <p><strong>Описание проблемы:</strong> <?= htmlspecialchars($request['issue_description']) ?></p>
                    <p><strong>Дата подачи:</strong> <?= htmlspecialchars($request['request_date']) ?></p>
                    <p><strong>Статус:</strong> <?= htmlspecialchars($request['status']) ?></p>
                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="request_id" value="<?= htmlspecialchars($request['id']) ?>">
                        <select name="status" required>
                            <option value="pending" <?= $request['status'] == 'в ожидании' ? 'selected' : '' ?>>Ожидает</option>
                            <option value="approved" <?= $request['status'] == 'одобрено' ? 'selected' : '' ?>>Одобрена</option>
                            <option value="rejected" <?= $request['status'] == 'отклонено' ? 'selected' : '' ?>>Отклонена</option>
                        </select>
                        <button type="submit" name="update_status">Обновить статус</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <button onclick="window.location.href='manager.php'">Назад на главную менеджера</button>
</body>
</html>

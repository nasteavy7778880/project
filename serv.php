<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка авторизации
if (!isset($_SESSION['id'])) { 
    header("Location: login.php"); 
    exit(); 
}

// Инициализация переменных поиска
$search_query = '';

// Проверка на отправку формы
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['search'])) {
        $search_query = $_POST['search_query'];

        // Сохранение параметров поиска в сессии
        $_SESSION['search_query'] = $search_query;

        // Перенаправление после обработки формы
        header("Location: " . $_SERVER['PHP_SELF'] . "?search=1");
        exit();
    }

    // Обработка бронирования услуги
    if (isset($_POST['book_service'])) {
        $service_id = $_POST['service_id'];
        $apartment_id = $_POST['apartment_id']; // Get selected apartment ID

        $booking_query = "INSERT INTO service_bookings (user_id, service_id, apartment_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($booking_query);
        $stmt->bind_param("iii", $_SESSION['id'], $service_id, $apartment_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Услуга успешно забронирована!";
        } else {
            echo "Ошибка при бронировании услуги: " . $stmt->error;
        }

        $stmt->close();
    }
}

// Получение параметров поиска из сессии
$search_query = $_SESSION['search_query'] ?? '';

// Получение услуг из базы данных
$query = "SELECT * FROM services WHERE name LIKE ? OR description LIKE ? OR type LIKE ?";
$like_query = '%' . $search_query . '%';
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $like_query, $like_query, $like_query);
$stmt->execute();
$result = $stmt->get_result();
$services = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Получение квартир пользователя
$apartments_query = "SELECT id, address FROM apartments WHERE user_id = ?";
$stmt = $conn->prepare($apartments_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$apartments_result = $stmt->get_result();
$apartments = $apartments_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Закрытие соединения с базой данных
$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Услуги</title>
    
    <link rel="stylesheet" href="style6.css">
    
<style>

/* Стиль для контейнера карточек */
.card-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between; /* Равномерное распределение карточек */
    gap: 20px; /* Отступы между карточками */
    padding: 10px 20px;
}

/* Стиль для карточек */
.card {
    border: none;
    border-radius: 12px;
    padding: 20px;
    width: 280px;
    background: #ffffff;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

/* Эффект наведения на карточку */
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
}

/* Заголовок карточки */
.card h3 {
    font-size: 1.4rem;
    color: #007bff; /* Яркий синий цвет для контраста */
    margin-bottom: 10px;
    text-align: left; /* Выровнять текст по левому краю */
    font-weight: bold; /* Сделать текст жирным */
}

/* Описание карточки */
.card p {
    font-size: 1rem;
    color: #333; /* Более яркий цвет */
    margin-bottom: 12px;
    text-align: left; /* Выровнять текст по левому краю */
    font-weight: bold;
    line-height: 1.6; /* Улучшить читаемость текста */
}

/* Сыплющийся список */
.suggestions {
    font-size: 1rem;
    color: #555;
    margin-top: 10px;
    padding-left: 10px;
    text-align: left;
    font-weight: bold;
}

.suggestions ul {
    list-style-type: disc;
    padding-left: 20px; /* Отступ слева */
}

.suggestions li {
    margin-bottom: 6px;
    line-height: 1.4; /* Удобное расстояние между пунктами */
}

/* Контейнер для выпадающего списка */
.dropdown-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}
/* Стили для select (выпадающего списка) */
.apartment-select {
    padding: 8px 10px;
    border: 1px solid #6c757d;
    border-radius: 5px;
    font-size: 1rem;
    color: #333;
    transition: all 0.3s ease;
    text-align: left;
}

.apartment-select:hover {
    border-color: #495057;
}

/* Адаптивные медиа-запросы */
@media (max-width: 768px) {
    .card {
        width: 45%;
    }

    .card-container {
        gap: 15px;
    }
}

@media (max-width: 480px) {
    .card {
        width: 100%;
    }

    .card-container {
        gap: 10px;
    }
}

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
<div style="display: flex; align-items: center; justify-content: space-between; margin: 20px 0;">
    <button type="button" class="logout-button" onclick="window.location.href = 'user.php';">Назад</button>
    <h1 style="margin: 0; flex-grow: 1; text-align: center;">Доступные услуги</h1>
</div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <p style="color: green;"><?= htmlspecialchars($_SESSION['success_message']) ?></p>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Форма поиска услуг -->
    <form method="POST" style="margin-bottom: 20px;">
        <input type="text" name="search_query" placeholder="Поиск услуги" value="<?= htmlspecialchars($search_query) ?>">
        <button type="submit" name="search">Поиск</button>
    </form>

    <div class="card-container">
        <?php if (empty($services)): ?>
            <p>Нет доступных услуг.</p>
        <?php else: ?>
            <?php foreach ($services as $service): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($service['name']) ?></h3>
                    <p><strong>Описание:</strong> <?= htmlspecialchars($service['description']) ?></p>
                    <p><strong>Тип:</strong> <?= htmlspecialchars($service['type']) ?></p>
                    <p><strong>Стоимость:</strong> <?= htmlspecialchars($service['cost'] ? number_format($service['cost'], 2, '.', '') . ' ₽' : 'Бесплатно') ?></p>
                    <form method="POST">
                        <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                        <label for="apartment_id">Выберите квартиру:</label>
                        <select name="apartment_id" required>
                            <option value="">-- Выберите квартиру --</option>
                            <?php foreach ($apartments as $apartment): ?>
                                <option value="<?= $apartment['id'] ?>"><?= htmlspecialchars($apartment['address']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="book_service">Забронировать услугу</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</body>
</html>
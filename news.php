<?php
session_start();
require 'dbd_config.php'; // Подключение к базе данных

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['id'])) {
    die('Пожалуйста, войдите в систему.');
}

// Подключение к базе данных
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

// Обработка добавления новости
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_news'])) {
    $userId = $_SESSION['id']; // ID пользователя из сессии
    $title = $_POST['title'];
    $content = $_POST['content'];

    $insertQuery = "INSERT INTO news (user_id, title, content) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("iss", $userId, $title, $content);
    $stmt->execute();
}

// Обработка удаления новости
if (isset($_GET['delete'])) {
    $newsId = $_GET['delete'];
    $deleteQuery = "DELETE FROM news WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $newsId);
    $stmt->execute();
}

// Обработка редактирования новости
if (isset($_GET['edit'])) {
    $newsId = $_GET['edit'];
    $query = "SELECT title, content FROM news WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $newsId);
    $stmt->execute();
    $result = $stmt->get_result();
    $newsItem = $result->fetch_assoc();
}

// Обработка обновления новости
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_news'])) {
    $newsId = $_POST['news_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];

    $updateQuery = "UPDATE news SET title = ?, content = ? WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ssi", $title, $content, $newsId);
    $stmt->execute();
}

// Получение новостей
$query = "SELECT id, title, content FROM news ORDER BY created_at DESC";
$result = $conn->query($query);
$newsItems = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $newsItems[] = $row;
    }
}

// Закрытие соединения с базой данных
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style_for_bills.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление новостями</title>
    <link rel="stylesheet" href="style_news_management.css">

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
        .navigate-button, .form-toggle-button, .back-button {
            margin: 20px;
            padding: 10px 20px;
            font-size: 16px;
        }
        .form-container {
            display: none; /* Скрываем форму по умолчанию */
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
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
    <script>
        function toggleForm() {
            const formContainer = document.getElementById('formContainer');
            formContainer.style.display = formContainer.style.display === 'none' ? 'block' : 'none';
        }

        function editNews(id) {
            const formContainer = document.getElementById('formContainer');
            formContainer.style.display = 'block';

            // Загрузка данных новости в форму
            document.getElementById('news_id').value = id;
            document.getElementById('news_title').value = document.getElementById('title_' + id).value;
            document.getElementById('news_content').value = document.getElementById('content_' + id).value;
        }
    </script>
</head>
<body>
<div class="header">
        <h1>Управление новостями</h1>
        <a class="back-button" href="manager.php">Назад</a> <!-- Укажите нужный URL -->
    </div>

    <button class="form-toggle-button" onclick="toggleForm()">Добавить новость</button>

    <div id="formContainer" class="form-container">
        <h2><?= isset($newsItem) ? 'Редактировать новость' : 'Добавить новость' ?></h2>
        <form method="POST">
            <input type="hidden" id="news_id" name="news_id" value="">
            <input type="text" id="news_title" name="title" placeholder="Заголовок" required>
            <textarea id="news_content" name="content" placeholder="Содержание" required></textarea>
            <button type="submit" name="<?= isset($newsItem) ? 'update_news' : 'add_news' ?>">
                <?= isset($newsItem) ? 'Обновить' : 'Добавить' ?>
            </button>
            <button type="button" onclick="toggleForm()">Отменить</button>
        </form>
    </div>

    <div class="card-container">
        <?php if (empty($newsItems)): ?>
            <p>Нет доступных новостей.</p>
        <?php else: ?>
            <?php foreach ($newsItems as $news): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($news['title']) ?></h3>
                    <p><?= htmlspecialchars($news['content']) ?></p>
                    <input type="hidden" id="title_<?= $news['id'] ?>" value="<?= htmlspecialchars($news['title']) ?>">
                    <input type="hidden" id="content_<?= $news['id'] ?>" value="<?= htmlspecialchars($news['content']) ?>">
                    <button onclick="editNews(<?= $news['id'] ?>)">Редактировать</button>
                    <a href="?delete=<?= $news['id'] ?>" onclick="return confirm('Вы уверены, что хотите удалить эту новость?');">Удалить</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
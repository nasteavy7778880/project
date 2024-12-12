<?php
session_start();

// Проверка, если пользователь не авторизован
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Подключение к базе данных
require 'dbd_config.php'; // Подключаем файл с настройками БД

// Получаем username из сессии
$username = htmlspecialchars($_SESSION['username']);

// Получаем user_id пользователя
$sql_user = "SELECT id FROM users WHERE username = '$username'";
$result_user = $conn->query($sql_user);

if ($result_user->num_rows > 0) {
    $user = $result_user->fetch_assoc();
    $userId = $user['id']; // Получаем ID пользователя
} else {
    die("Пользователь не найден");
}

// Получение всех квартир пользователя
function getApartments($userId, $conn) {
    $stmt = $conn->prepare("SELECT * FROM apartments WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Добавление новой квартиры
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $address = $_POST['address'];
    $area = $_POST['area'];
    $rooms = $_POST['rooms'];

    // Валидация на сервере (макс. 6 знаков до запятой, 2 знака после запятой)
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $area)) {
        die("Площадь должна быть в формате: до 6 знаков до запятой и 2 знака после запятой.");
    }

    $stmt = $conn->prepare("INSERT INTO apartments (user_id, address, area, rooms) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isdi", $userId, $address, $area, $rooms);
    $stmt->execute();

    header('Location: flat.php');
    exit;
}

// Редактирование квартиры
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['id'];
    $address = $_POST['address'];
    $area = $_POST['area'];
    $rooms = $_POST['rooms'];

    // Валидация на сервере (макс. 6 знаков до запятой, 2 знака после запятой)
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $area)) {
        die("Площадь должна быть в формате: до 6 знаков до запятой и 2 знака после запятой.");
    }

    $stmt = $conn->prepare("UPDATE apartments SET address = ?, area = ?, rooms = ? WHERE id = ?");
    $stmt->bind_param("sdii", $address, $area, $rooms, $id);
    $stmt->execute();

    header('Location: flat.php');
    exit;
}

// Удаление квартиры
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'];

    $stmt = $conn->prepare("DELETE FROM apartments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header('Location: flat.php');
    exit;
}

// Получение всех квартир для текущего пользователя
$apartments = getApartments($userId, $conn);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои квартиры</title>
    <link rel="stylesheet" href="style_for_flat.css">
    <style>
        /* Скрыть форму редактирования по умолчанию */
        .edit-form {
            display: none;
        }
    </style>
</head>
<body>


    <h1>Мои квартиры</h1>
    <button type="button" class="logout-button" onclick="window.location.href = 'user.php';">Выход</button>
    <!-- Список квартир -->
    <table border="1">
        <thead>
            <tr>
                <th>Адрес</th>
                <th>Площадь</th>
                <th>Комнаты</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($apartments as $apartment): ?>
                <tr>
                    <td><?= htmlspecialchars($apartment['address']) ?></td>
                    <td><?= htmlspecialchars($apartment['area']) ?> м²</td>
                    <td><?= htmlspecialchars($apartment['rooms']) ?></td>
                    <td>
                        <!-- Кнопка для показа формы редактирования -->
                        <button type="button" onclick="toggleEditForm(<?= $apartment['id'] ?>)">Редактировать</button>

                        <!-- Форма для редактирования -->
                        <form action="flat.php" method="POST" class="edit-form" id="edit-form-<?= $apartment['id'] ?>">
                            <input type="hidden" name="id" value="<?= $apartment['id'] ?>">
                            <input type="hidden" name="action" value="edit">
                            <label for="address">Адрес:</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($apartment['address']) ?>" required>
                            <br>
                            <label for="area">Площадь (м²):</label>
                            <input type="number" name="area" id="area-<?= $apartment['id'] ?>" value="<?= htmlspecialchars($apartment['area']) ?>" min="0" step="0.01" required onchange="validateArea(<?= $apartment['id'] ?>)">
                            <br>
                            <label for="rooms">Количество комнат:</label>
                            <input type="number" name="rooms" value="<?= htmlspecialchars($apartment['rooms']) ?>" min="1" required>
                            <br>
                            <button type="submit">Сохранить</button>
                        </form>
                        
                        <!-- Форма для удаления -->
                    

                            <button type="submit">Удалить</button>
                     
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Форма для добавления -->
    <h2>Добавить новую квартиру</h2>
    <form action="flat.php" method="POST">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="user_id" value="<?= $userId ?>">
        <label for="address">Адрес:</label>
        <input type="text" name="address" id="address" required>
        <br>
        <label for="area">Площадь (м²):</label>
        <input type="number" name="area" id="area" step="0.01" min="0" required onchange="validateArea()">
        <br>
        <label for="rooms">Количество комнат:</label>
        <input type="number" name="rooms" id="rooms" min="1" required>
        <br>
        <button type="submit">Добавить</button>
    </form>

    <script>
        // Функция для показа/скрытия формы редактирования
        function toggleEditForm(apartmentId) {
            var form = document.getElementById('edit-form-' + apartmentId);
            if (form.style.display === "none" || form.style.display === "") {
                form.style.display = "block";
            } else {
                form.style.display = "none";
            }
        }

        // Функция для валидации поля "Площадь" (не отрицательное значение, максимум 6 знаков до запятой, 2 знака после запятой)
        function validateArea(apartmentId = null) {
            var areaInput = apartmentId ? document.getElementById('area-' + apartmentId) : document.getElementById('area');
            var value = areaInput.value;

            // Ограничение до 6 знаков до запятой и 2 знаков после запятой
            var parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            if (parts.length === 2 && parts[1].length > 2) {
                value = parts[0] + '.' + parts[1].substring(0, 2);
            }

            // Устанавливаем отфильтрованное значение обратно в поле
            areaInput.value = value;
        }
    </script>
</body>
</html>

<?php
// Закрытие соединения с базой данных
$conn->close();
?>

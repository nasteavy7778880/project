<?php
// Подключение к базе данных
require_once('dbd_config.php');

// Создаем подключение
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Ошибка подключения к базе данных: " . $conn->connect_error);
}

/// Обработка назначения роли
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_role'])) {
    $user_id = $_POST['user_id'];
    $role_id = $_POST['role_id'];

    // Проверяем, есть ли у пользователя уже роль
    $stmt_check = $conn->prepare("SELECT 1 FROM userroles WHERE user_id = ?");
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        // Если роль существует, обновляем её
        $stmt = $conn->prepare("UPDATE userroles SET role_id = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $role_id, $user_id);
    } else {
        // Если роли нет, добавляем её
        $stmt = $conn->prepare("INSERT INTO userroles (user_id, role_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $role_id);
    }

    // Выполняем запрос и устанавливаем сообщение
    if ($stmt->execute()) {
        $message = "";
    } else {
        $message = "Ошибка при назначении роли.";
    }

    $stmt->close();
    $stmt_check->close();
}

// Обработка блокировки пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['block_user'])) {
    $user_id = $_POST['user_id'];

    // Устанавливаем статус пользователя на 'blocked'
    $stmt = $conn->prepare("UPDATE Users SET status = 'blocked' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $message = "";
    } else {
        $message = "Ошибка при блокировке пользователя.";
    }
    $stmt->close();
}

// Обработка разблокировки пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unblock_user'])) {
    $user_id = $_POST['user_id'];

    // Устанавливаем статус пользователя на 'active'
    $stmt = $conn->prepare("UPDATE Users SET status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $message = "";
    } else {
        $message = "Ошибка при разблокировке пользователя.";
    }
    $stmt->close();
}

// Получаем список пользователей и их роли, исключая тех, у кого роль "Admin"
$sql = "SELECT u.id, u.username, u.email, IFNULL(r.role_name, 'No role assigned') as role_name, u.status
        FROM Users u
        LEFT JOIN userroles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.role_id
        WHERE r.role_name != 'Admin' OR r.role_name IS NULL";
$result = $conn->query($sql);

// Получаем список всех ролей, исключая "Admin"
$roles_result = $conn->query("SELECT * FROM roles WHERE role_name IN ('Manager', 'User')");

// Закрываем соединение с базой данных
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style_for_admin.css">
</head>
<body>
    <div class="container">
        <h2>Панель управления администратора</h2>

        <?php if (isset($message)): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <table>
            <tr>
                <th>Имя пользователя</th>
                <th>Email</th>
                <th>Статус</th>
                <th>Роль</th>
                <th>Назначить роль</th>
                <th>Блокировка/Разблокировка</th>
            </tr>

            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td>
                        <?php 
                        echo $row['status'] === 'blocked' ? 'Заблокирован' : 'Активен'; 
                        ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($row['role_name']); ?>
                    </td>
                    <td>
                        <form action="" method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                            <select name="role_id">
                                <?php while ($role = $roles_result->fetch_assoc()): ?>
                                    <option value="<?php echo $role['role_id']; ?>" <?php echo ($role['role_name'] == $row['role_name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" name="assign_role">Назначить</button>
                        </form>
                    </td>
                    <td>
                        <?php if ($row['status'] === 'blocked'): ?>
                            <form action="" method="POST">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="unblock_user">Разблокировать</button>
                            </form>
                        <?php else: ?>
                            <form action="" method="POST">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="block_user">Заблокировать</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php $roles_result->data_seek(0); ?>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>

<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require_once('dbd_config.php');
require_once 'vendor/autoload.php'; // Подключаем автозагрузчик PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userId = $_SESSION['id'];

// Фильтр для текущего месяца
$currentMonth = date('Y-m-01'); // Начало текущего месяца
$usageQuery = "
    SELECT 
        u.username,
        u.email,
        t.service_name,
        SUM(mr.value) AS total_usage,
        SUM(b.total_amount) AS total_amount
    FROM 
        meter_readings mr
    JOIN 
        apartments a ON mr.apartment_id = a.id
    JOIN 
        users u ON a.user_id = u.id
    JOIN 
        tariffs t ON mr.service_id = t.id
    LEFT JOIN 
        bills b ON a.id = b.apartment_id
    WHERE 
        mr.reading_date >= ? 
    GROUP BY 
        u.username, u.email, t.service_name
";

$stmt = $conn->prepare($usageQuery);
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$usageResult = $stmt->get_result();

// Инициализация PhpSpreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Заголовок
$sheet->setCellValue('A1', "Отчет об расходе ресурсов за " . date('Y-m-d H:i:s'));
$sheet->mergeCells('A1:F1'); // Объединяем ячейки для заголовка
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

// Данные о потреблении
$sheet->setCellValue('A2', "Данные о потреблении ресурсов за текущий месяц:");

if ($usageResult->num_rows > 0) {
    // Заголовки таблицы
    $tableHeaders = ['Имя пользователя', 'Email', 'Услуга', 'Общее использование (ед.)', 'Общая сумма (руб.)'];
    $sheet->fromArray($tableHeaders, null, 'A4'); // Заголовки таблицы

    $row = 5; // Начинаем с 5-й строки для данных
    while ($data = $usageResult->fetch_assoc()) {
        $username = htmlspecialchars($data['username']);
        $email = htmlspecialchars($data['email']);
        $serviceName = htmlspecialchars($data['service_name']);
        $totalUsage = (float)$data['total_usage'];
        $totalAmount = (float)$data['total_amount'];

        // Заполняем данные в таблице
        $sheet->setCellValue("A$row", $username);
        $sheet->setCellValue("B$row", $email);
        $sheet->setCellValue("C$row", $serviceName);
        $sheet->setCellValue("D$row", $totalUsage);
        $sheet->setCellValue("E$row", $totalAmount);

        $row++; // Инкрементируем строку для следующей записи данных
    }
} else {
    $sheet->setCellValue('A4', "Нет данных для текущего месяца.");
}

// Создание директории, если она не существует
$directory = "C:/xampp/htdocs/cursach/reports/";
if (!is_dir($directory)) {
    mkdir($directory, 0777, true); // Создаем директорию с правами на запись
}

// Сохранение файла отчета
$fileName = "Отчет_" . date('Y_m_d_H_i_s') . ".xlsx";
$filePath = $directory . $fileName;  // Путь с файлом отчета
$writer = new Xlsx($spreadsheet);
$writer->save($filePath);

// Отправка файла на скачивание
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);

$conn->close();
?>

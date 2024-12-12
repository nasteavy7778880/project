<?php
require 'vendor/autoload.php'; // подключите автозагрузчик Composer для PHPWord

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Если была нажата кнопка для скачивания отчета
if (isset($_GET['download']) && $_GET['download'] == 'word') {
    // Создаем новый документ Word
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();

    // Заголовок
    $section->addTitle("Прогноз расходов пользователей", 1);

    // Добавляем фильтрацию, которую пользователь выбрал
    $section->addText("Период: " . ($timeframe === '30' ? 'Последние 30 дней' : 'Все время'));
    $section->addText("Пользователь: " . ($userId ? htmlspecialchars($userId) : 'Все пользователи'));
    $section->addText("Ресурс: " . ($resourceType ? htmlspecialchars($resourceType) : 'Все ресурсы'));
    $section->addText("Тип графика: " . ($chartType === 'money' ? 'Расходы (руб.)' : 'Расход ресурсов'));

    // Таблица с данными
    $table = $section->addTable();
    $table->addRow();
    $table->addCell(2000)->addText("Пользователь");
    $table->addCell(2000)->addText("Услуга");
    $table->addCell(2000)->addText("Текущие расходы (руб.)");
    $table->addCell(2000)->addText("Расход ресурса");
    $table->addCell(2000)->addText("Предсказанные расходы");
    $table->addCell(2000)->addText("Предсказанный расход ресурса");

    // Добавляем строки с данными о пользователях
    foreach ($currentExpenses as $current) {
        $predictedCost = 0;
        $predictedResource = 0;
        foreach ($predictedExpenses as $prediction) {
            if ($prediction['username'] == $current['username'] && $prediction['service_name'] == $current['service_name']) {
                $predictedCost = $prediction['predicted_cost'];
                $predictedResource = $prediction['predicted_resource'];
            }
        }

        $table->addRow();
        $table->addCell(2000)->addText($current['username']);
        $table->addCell(2000)->addText($current['service_name']);
        $table->addCell(2000)->addText(number_format($current['current_cost'], 2));
        $table->addCell(2000)->addText(number_format($current['current_resource'], 2));
        $table->addCell(2000)->addText(number_format($predictedCost, 2));
        $table->addCell(2000)->addText(number_format($predictedResource, 2));
    }

    // Сохраняем файл
    $filename = "report_" . time() . ".docx";
    $filePath = "/path/to/your/directory/$filename"; // Укажите путь, куда сохранить файл
    $phpWord->save($filePath, 'Word2007');

    // Отправляем файл пользователю
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($filePath);
    exit();
}
?>

<!-- Добавьте кнопку для скачивания отчета -->
<form method="get">
    <input type="hidden" name="download" value="word">
    <button type="submit" style="padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 5px;">
        Скачать отчет в Word
    </button>
</form>

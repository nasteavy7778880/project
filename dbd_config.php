<?php
$servername = "localhost";  
$username = "root";         
$password = "17112003";            
$dbname = "db";    


$error_message = ""; 

try {
   
    $conn = new mysqli($servername, $username, $password, $dbname);

   
    if ($conn->connect_error) {
        throw new Exception("Ошибка подключения к базе данных: " . $conn->connect_error);
    }
} catch (Exception $e) {
   
    $error_message = $e->getMessage();
    die("Ошибка: " . $error_message); 
}
?>

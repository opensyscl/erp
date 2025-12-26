<?php
// seed_caja.php - Genera datos de prueba aleatorios
include_once 'config.php';

// Elimina registros anteriores (opcional, descomenta si quieres limpiar)
// $pdo->exec("TRUNCATE TABLE cash_register");

$start = strtotime("-30 days");
$today = strtotime("today");

for ($d = $start; $d <= $today; $d += 86400) {
    $date = date("Y-m-d", $d);

    // Montos aleatorios simulados
    $opening_cash = rand(100, 500);
    $pos1 = rand(200, 2000);
    $pos2 = rand(100, 1500);
    $closing_cash = $opening_cash + rand(-50, 50) + rand(300, 1000);
    $cash_in_day = $closing_cash - $opening_cash;
    $notes = "Registro de prueba";

    $stmt = $pdo->prepare("INSERT INTO cash_register 
        (date, pos1, pos2, opening_cash, closing_cash, cash_in_day, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
          pos1=VALUES(pos1), pos2=VALUES(pos2), 
          opening_cash=VALUES(opening_cash),
          closing_cash=VALUES(closing_cash), 
          cash_in_day=VALUES(cash_in_day), 
          notes=VALUES(notes)");
    
    $stmt->execute([$date, $pos1, $pos2, $opening_cash, $closing_cash, $cash_in_day, $notes]);
}

echo "✅ Datos aleatorios insertados correctamente en cash_register.";

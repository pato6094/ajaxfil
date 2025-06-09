<?php
include 'connessione.php';

header('Content-Type: application/json');

try {
    // Get statistics
    $statistiche = ['Confermata' => 0, 'In attesa' => 0, 'Cancellata' => 0];
    
    // Check if stato column exists
    $stato_exists = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'stato'")->num_rows > 0;
    
    if ($stato_exists) {
        $totali = $conn->query("SELECT stato, COUNT(*) as totale FROM prenotazioni GROUP BY stato");
        if ($totali) {
            while ($row = $totali->fetch_assoc()) {
                $statistiche[$row['stato']] = $row['totale'];
            }
        }
    } else {
        // If no stato column, just count total bookings
        $total_result = $conn->query("SELECT COUNT(*) as totale FROM prenotazioni");
        if ($total_result) {
            $total_row = $total_result->fetch_assoc();
            $statistiche['In attesa'] = $total_row['totale'];
        }
    }
    
    // Calculate total revenue
    $totale_ricavi = 0;
    $servizi_exists = $conn->query("SHOW TABLES LIKE 'servizi'")->num_rows > 0;
    $escludi_exists = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'escludi_ricavi'")->num_rows > 0;
    
    if ($servizi_exists && $stato_exists) {
        $where_condition = "p.stato = 'Confermata'";
        if ($escludi_exists) {
            $where_condition .= " AND (p.escludi_ricavi = 0 OR p.escludi_ricavi IS NULL)";
        }
        
        $entrate = $conn->query("SELECT SUM(s.prezzo) as totale FROM prenotazioni p JOIN servizi s ON p.servizio = s.nome WHERE $where_condition");
        if ($entrate) {
            $entrate_row = $entrate->fetch_assoc();
            $totale_ricavi = $entrate_row['totale'] ?? 0;
        }
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $statistiche,
        'totalRevenue' => $totale_ricavi
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
<?php
include 'connessione.php';

header('Content-Type: application/json');

try {
    $query = $conn->query("SELECT MAX(id) as lastId FROM prenotazioni");
    
    if ($query) {
        $row = $query->fetch_assoc();
        $lastId = $row['lastId'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'lastId' => intval($lastId)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Query failed'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
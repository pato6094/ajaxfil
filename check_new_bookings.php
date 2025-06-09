<?php
include 'connessione.php';

header('Content-Type: application/json');

$lastId = isset($_GET['lastId']) ? intval($_GET['lastId']) : 0;

try {
    // Check if there are new bookings with ID greater than lastId
    $query = $conn->prepare("SELECT MAX(id) as newLastId, COUNT(*) as newCount FROM prenotazioni WHERE id > ?");
    $query->bind_param("i", $lastId);
    $query->execute();
    $result = $query->get_result();
    
    if ($result) {
        $row = $result->fetch_assoc();
        $newLastId = $row['newLastId'] ?? $lastId;
        $newCount = $row['newCount'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'hasNewBookings' => $newCount > 0,
            'newLastId' => intval($newLastId),
            'newCount' => intval($newCount)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'hasNewBookings' => false,
            'error' => 'Query failed'
        ]);
    }
    
    $query->close();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'hasNewBookings' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
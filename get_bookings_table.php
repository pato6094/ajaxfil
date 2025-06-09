<?php
include 'connessione.php';

// Get recent bookings with operator info
$prenotazioni_query = "SELECT p.*, CONCAT(o.nome, ' ', o.cognome) as operatore_nome FROM prenotazioni p LEFT JOIN operatori o ON p.operatore_id = o.id ORDER BY ";

// Check if we have data_prenotazione column
$data_col_exists = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'data_prenotazione'")->num_rows > 0;
if ($data_col_exists) {
    $prenotazioni_query .= "p.data_prenotazione DESC, ";
}
$prenotazioni_query .= "p.id DESC LIMIT 10";

$prenotazioni = $conn->query($prenotazioni_query);

// Check if stato column exists
$stato_exists = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'stato'")->num_rows > 0;

if ($prenotazioni && $prenotazioni->num_rows > 0) {
    $i = 1;
    while ($row = $prenotazioni->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $i++ . '</td>';
        echo '<td>' . htmlspecialchars($row['nome'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($row['telefono'] ?? 'N/A') . '</td>';
        
        if ($data_col_exists) {
            echo '<td>';
            if (isset($row['data_prenotazione']) && $row['data_prenotazione']) {
                $data = date_create($row['data_prenotazione']);
                echo $data ? date_format($data, 'd/m/Y') : 'N/A';
            } else {
                echo 'N/A';
            }
            echo '</td>';
        }
        
        echo '<td>' . (isset($row['orario']) ? date('H:i', strtotime($row['orario'])) : 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($row['servizio'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($row['operatore_nome'] ?? 'Non assegnato') . '</td>';
        
        if ($stato_exists) {
            $stato = $row['stato'] ?? 'In attesa';
            $statusClass = '';
            if ($stato === 'Confermata') $statusClass = 'confermata';
            elseif ($stato === 'In attesa') $statusClass = 'in-attesa';
            elseif ($stato === 'Cancellata') $statusClass = 'cancellata';
            
            echo '<td><span class="status ' . $statusClass . '">' . htmlspecialchars($stato) . '</span></td>';
            echo '<td>';
            echo '<a href="?action=confirm&id=' . $row['id'] . '" class="action-btn confirm" onclick="return confirm(\'Confermare questa prenotazione?\')"><i class="fas fa-check"></i>Conferma</a>';
            echo '<a href="?action=cancel&id=' . $row['id'] . '" class="action-btn cancel" onclick="return confirm(\'Cancellare questa prenotazione?\')"><i class="fas fa-times"></i>Cancella</a>';
            echo '</td>';
        }
        
        echo '</tr>';
    }
} else {
    $colspan = $stato_exists ? 9 : 7;
    echo '<tr><td colspan="' . $colspan . '" style="text-align: center; color: #a0a0a0;">Nessuna prenotazione trovata</td></tr>';
}

$conn->close();
?>
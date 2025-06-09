<?php
include 'connessione.php';

// Create cancellation tokens table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS cancellation_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    FOREIGN KEY (booking_id) REFERENCES prenotazioni(id) ON DELETE CASCADE
)");

// Handle cancellation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Find bookings for this email that are not cancelled
        $stmt = $conn->prepare("SELECT id, nome, servizio, data_prenotazione, orario, stato FROM prenotazioni WHERE email = ? AND (stato IS NULL OR stato != 'Cancellata') ORDER BY data_prenotazione DESC, orario DESC");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $bookings = [];
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
            $stmt->close();
        } else {
            $error_message = "Nessuna prenotazione attiva trovata per questo indirizzo email.";
            $stmt->close();
        }
    } else {
        $error_message = "Inserisci un indirizzo email valido.";
    }
}

// Handle booking cancellation
if (isset($_GET['token']) && isset($_GET['booking_id'])) {
    $token = $_GET['token'];
    $booking_id = intval($_GET['booking_id']);
    
    // Verify token
    $stmt = $conn->prepare("SELECT ct.*, p.nome, p.servizio, p.data_prenotazione, p.orario 
                           FROM cancellation_tokens ct 
                           JOIN prenotazioni p ON ct.booking_id = p.id 
                           WHERE ct.token = ? AND ct.booking_id = ? AND ct.used = 0 AND ct.expires_at > NOW()");
    $stmt->bind_param("si", $token, $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $token_data = $result->fetch_assoc();
        
        // Cancel the booking
        $update_stmt = $conn->prepare("UPDATE prenotazioni SET stato = 'Cancellata' WHERE id = ?");
        $update_stmt->bind_param("i", $booking_id);
        
        if ($update_stmt->execute()) {
            // Mark token as used
            $mark_used = $conn->prepare("UPDATE cancellation_tokens SET used = 1 WHERE token = ?");
            $mark_used->bind_param("s", $token);
            $mark_used->execute();
            $mark_used->close();
            
            $cancellation_success = true;
            $cancelled_booking = $token_data;
        } else {
            $cancellation_error = "Errore durante la cancellazione della prenotazione.";
        }
        $update_stmt->close();
    } else {
        $cancellation_error = "Token non valido o scaduto.";
    }
    $stmt->close();
}

// Generate cancellation token
if (isset($_POST['cancel_booking_id'])) {
    $booking_id = intval($_POST['cancel_booking_id']);
    $email = $_POST['booking_email'];
    
    // Generate unique token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Insert token
    $stmt = $conn->prepare("INSERT INTO cancellation_tokens (booking_id, token, email, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $booking_id, $token, $email, $expires_at);
    
    if ($stmt->execute()) {
        $cancellation_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/cancel_booking.php?token=" . $token . "&booking_id=" . $booking_id;
        $link_generated = true;
    } else {
        $error_message = "Errore durante la generazione del link di cancellazione.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancella Prenotazione - Old School Barber</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            color: #ffffff;
            padding: 1rem;
        }

        .container {
            max-width: 600px;
            margin: 2rem auto;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .logo i {
            font-size: 2.5rem;
            color: #d4af37;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #d4af37 0%, #ffd700 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #a0a0a0;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #e0e0e0;
            font-weight: 500;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0a0a0;
            font-size: 1rem;
        }

        input[type="email"] {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #d4af37;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        input[type="email"]::placeholder {
            color: #a0a0a0;
        }

        .btn {
            width: 100%;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #d4af37 0%, #ffd700 100%);
            color: #1a1a2e;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.3);
        }

        .bookings-list {
            margin: 2rem 0;
        }

        .booking-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .booking-card:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .booking-info {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: center;
        }

        .booking-details h4 {
            color: #d4af37;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .booking-details p {
            color: #a0a0a0;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .booking-details .status {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status.confermata {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .status.in-attesa {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .cancel-btn {
            padding: 0.5rem 1rem;
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cancel-btn:hover {
            background: rgba(239, 68, 68, 0.3);
            transform: translateY(-1px);
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .message.success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        .message.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .message.info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #60a5fa;
        }

        .back-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .back-link a {
            color: #a0a0a0;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-link a:hover {
            color: #d4af37;
        }

        .cancellation-link {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.9rem;
            color: #d4af37;
        }

        @media (max-width: 768px) {
            .container {
                margin: 1rem auto;
                padding: 2rem 1.5rem;
            }

            .logo {
                flex-direction: column;
                gap: 0.5rem;
            }

            .booking-info {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .cancel-btn {
                width: 100%;
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-cut"></i>
                <h1>Old School Barber</h1>
            </div>
            <h2 class="title">Cancella Prenotazione</h2>
            <p class="subtitle">Inserisci la tua email per visualizzare le prenotazioni attive</p>
        </div>

        <?php if (isset($cancellation_success)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <strong>Prenotazione cancellata con successo!</strong><br>
                La tua prenotazione per <?php echo htmlspecialchars($cancelled_booking['servizio']); ?> 
                del <?php echo date('d/m/Y', strtotime($cancelled_booking['data_prenotazione'])); ?> 
                alle <?php echo date('H:i', strtotime($cancelled_booking['orario'])); ?> è stata cancellata.
            </div>
            
            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Torna alla home
                </a>
            </div>

        <?php elseif (isset($cancellation_error)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $cancellation_error; ?>
            </div>
            
            <div class="back-link">
                <a href="cancel_booking.php">
                    <i class="fas fa-arrow-left"></i>
                    Torna indietro
                </a>
            </div>

        <?php elseif (isset($link_generated)): ?>
            <div class="message info">
                <i class="fas fa-info-circle"></i>
                <strong>Link di cancellazione generato!</strong><br>
                Copia il link sottostante e aprilo per cancellare la prenotazione. Il link è valido per 24 ore.
            </div>
            
            <div class="cancellation-link">
                <?php echo $cancellation_link; ?>
            </div>
            
            <button class="btn btn-secondary" onclick="copyToClipboard('<?php echo $cancellation_link; ?>')">
                <i class="fas fa-copy"></i>
                Copia Link
            </button>
            
            <div class="back-link">
                <a href="cancel_booking.php">
                    <i class="fas fa-arrow-left"></i>
                    Cerca altre prenotazioni
                </a>
            </div>

        <?php elseif (isset($bookings)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                Trovate <?php echo count($bookings); ?> prenotazione/i attive per questo indirizzo email.
            </div>

            <div class="bookings-list">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card">
                        <div class="booking-info">
                            <div class="booking-details">
                                <h4><?php echo htmlspecialchars($booking['servizio']); ?></h4>
                                <p><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($booking['data_prenotazione'])); ?></p>
                                <p><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($booking['orario'])); ?></p>
                                <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['nome']); ?></p>
                                <span class="status <?php echo strtolower(str_replace(' ', '-', $booking['stato'] ?? 'in-attesa')); ?>">
                                    <?php echo $booking['stato'] ?? 'In attesa'; ?>
                                </span>
                            </div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="cancel_booking_id" value="<?php echo $booking['id']; ?>">
                                <input type="hidden" name="booking_email" value="<?php echo htmlspecialchars($email); ?>">
                                <button type="submit" class="cancel-btn" onclick="return confirm('Sei sicuro di voler generare il link di cancellazione per questa prenotazione?')">
                                    <i class="fas fa-times"></i>
                                    Genera Link Cancellazione
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="back-link">
                <a href="cancel_booking.php">
                    <i class="fas fa-search"></i>
                    Cerca con un'altra email
                </a>
            </div>

        <?php else: ?>
            <?php if (isset($error_message)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Indirizzo Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="la-tua-email@esempio.com" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Cerca Prenotazioni
                </button>
            </form>

            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Torna alla home
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Link copiato negli appunti!');
            }, function(err) {
                console.error('Errore nel copiare il link: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Link copiato negli appunti!');
            });
        }
    </script>
</body>
</html>
```
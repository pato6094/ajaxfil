<?php
date_default_timezone_set('Europe/Rome');
include 'connessione.php';

// Check if time limits table exists, if not create it
$conn->query("CREATE TABLE IF NOT EXISTS limiti_orari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    giorno_settimana ENUM('lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica') NOT NULL,
    orario TIME NOT NULL,
    limite_persone INT NOT NULL DEFAULT 1,
    attivo TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_slot (giorno_settimana, orario)
)");

// Check if specific date limits table exists, if not create it
$conn->query("CREATE TABLE IF NOT EXISTS limiti_date_specifiche (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_specifica DATE NOT NULL,
    orario TIME NOT NULL,
    limite_persone INT NOT NULL DEFAULT 1,
    attivo TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_date_slot (data_specifica, orario)
)");

// Create operators table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS operatori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cognome VARCHAR(255) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(255),
    specialita TEXT,
    attivo TINYINT(1) DEFAULT 1,
    data_inserimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Add operatore_id column to prenotazioni table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'operatore_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE prenotazioni ADD COLUMN operatore_id INT, ADD FOREIGN KEY (operatore_id) REFERENCES operatori(id)");
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
    <title>Old School Barber - Prenota il tuo taglio</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Light mode colors */
            --bg-primary: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            --bg-secondary: rgba(255, 255, 255, 0.05);
            --bg-tertiary: rgba(255, 255, 255, 0.08);
            --text-primary: #ff4444; /* Fire red for form text */
            --text-secondary: #a0a0a0;
            --text-accent: #ffd700;
            --border-primary: rgba(255, 255, 255, 0.1);
            --border-secondary: rgba(255, 255, 255, 0.15);
            --accent-primary: #d4af37;
            --accent-secondary: #ffd700;
            --success-color: #4ade80;
            --error-color: #f87171;
            --warning-color: #fbbf24;
            --shadow-primary: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --shadow-hover: 0 35px 70px -12px rgba(0, 0, 0, 0.35);
        }

        /* Dark mode colors - enhanced for better contrast */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-primary: linear-gradient(135deg, #000000 0%, #0a0a0f 50%, #111111 100%);
                --bg-secondary: rgba(255, 255, 255, 0.03);
                --bg-tertiary: rgba(255, 255, 255, 0.06);
                --text-primary: #ff4444; /* Fire red for form text */
                --text-secondary: #9ca3af;
                --text-accent: #fbbf24;
                --border-primary: rgba(255, 255, 255, 0.08);
                --border-secondary: rgba(255, 255, 255, 0.12);
                --accent-primary: #f59e0b;
                --accent-secondary: #fbbf24;
                --success-color: #10b981;
                --error-color: #ef4444;
                --warning-color: #f59e0b;
                --shadow-primary: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
                --shadow-hover: 0 35px 70px -12px rgba(0, 0, 0, 0.5);
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            min-height: 100vh;
            color: var(--text-accent);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Background Animation */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.1;
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Header */
        .header {
            text-align: center;
            padding: 2rem 1rem;
            margin-bottom: 2rem;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .logo i {
            font-size: 3rem;
            color: var(--accent-primary);
            filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.3));
        }

        .logo h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 30px rgba(212, 175, 55, 0.5);
        }

        .tagline {
            font-size: 1.1rem;
            color: var(--text-secondary);
            font-weight: 300;
            margin-top: 0.5rem;
        }

        /* Main Container */
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 200px);
            padding: 1rem;
        }

        .form-container {
            background: var(--bg-secondary);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid var(--border-primary);
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 480px;
            box-shadow: var(--shadow-primary), 0 0 0 1px var(--border-primary);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent-primary), transparent);
            opacity: 0.5;
        }

        .form-container:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover), 0 0 0 1px var(--border-secondary);
        }

        .form-title {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .form-title h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-title p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 400;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1rem;
            z-index: 2;
        }

        input, select {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-secondary);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 400;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        /* Mobile calendar fix */
        input[type="date"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            position: relative;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2214%22%20height%3D%2214%22%20viewBox%3D%220%200%2014%2014%22%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%3E%3Cpath%20d%3D%22M1%201h12v12H1V1zm2%202v8h8V3H3zm1%201h1v1H4V4zm2%200h1v1H6V4zm2%200h1v1H8V4zm-4%202h1v1H4V6zm2%200h1v1H6V6zm2%200h1v1H8V6zm2%200h1v1h-1V6zM4%208h1v1H4V8zm2%200h1v1H6V8zm2%200h1v1H8V8zm2%200h1v1h-1V8z%22%20fill%3D%22%23ff4444%22/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 14px 14px;
            cursor: pointer;
        }

        /* iOS specific fixes */
        input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            position: absolute;
            right: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        /* Android specific fixes */
        input[type="date"]::-webkit-inner-spin-button,
        input[type="date"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input::placeholder {
            color: var(--text-secondary);
            font-weight: 400;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--accent-primary);
            background: var(--bg-secondary);
            box-shadow: 
                0 0 0 3px rgba(212, 175, 55, 0.1),
                0 8px 25px -8px rgba(212, 175, 55, 0.2);
            transform: translateY(-1px);
        }

        select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2214%22%20height%3D%2210%22%20viewBox%3D%220%200%2014%2010%22%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%3E%3Cpath%20d%3D%22M1%200l6%206%206-6%22%20stroke%3D%22%23d4af37%22%20stroke-width%3D%222%22%20fill%3D%22none%22%20fill-rule%3D%22evenodd%22/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 14px 10px;
            cursor: pointer;
        }

        select option {
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 0.5rem;
        }

        /* Dark mode specific input styling */
        @media (prefers-color-scheme: dark) {
            input, select {
                background: rgba(255, 255, 255, 0.04);
                border-color: rgba(255, 255, 255, 0.1);
                color: #ff4444;
            }

            input:focus, select:focus {
                background: rgba(255, 255, 255, 0.08);
                border-color: var(--accent-primary);
            }

            input::placeholder {
                color: #6b7280;
            }

            select option {
                background: #111111;
                color: #ff4444;
            }
        }

        /* Availability indicator */
        .availability-indicator {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            z-index: 3;
            max-width: 80px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .availability-indicator.available {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success-color);
        }

        .availability-indicator.unavailable {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error-color);
        }

        .availability-indicator.checking {
            background: rgba(251, 191, 36, 0.2);
            color: var(--warning-color);
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1.2rem 2rem;
            background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
            border: none;
            border-radius: 12px;
            color: #1a1a2e;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 1rem;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px -5px rgba(212, 175, 55, 0.4);
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Admin Link */
        .admin-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-primary);
        }

        .admin-link a {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-link a:hover {
            color: var(--accent-secondary);
            transform: translateX(5px);
        }

        /* Cancel Link */
        .cancel-link {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-primary);
        }

        .cancel-link a {
            color: var(--error-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cancel-link a:hover {
            color: #ef4444;
            transform: translateX(5px);
        }

        /* Services Preview */
        .services-preview {
            margin-top: 3rem;
            text-align: center;
            padding: 0 1rem;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .service-card {
            background: var(--bg-secondary);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-primary);
            transition: all 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-5px);
            background: var(--bg-tertiary);
            border-color: rgba(212, 175, 55, 0.3);
        }

        .service-card i {
            font-size: 2rem;
            color: var(--accent-primary);
            margin-bottom: 1rem;
        }

        .service-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .service-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Loading Animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(212, 175, 55, 0.3);
            border-top: 3px solid var(--accent-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .services-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.2rem;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 1.5rem 1rem;
            }

            .logo h1 {
                font-size: 2rem;
            }

            .logo i {
                font-size: 2.5rem;
            }

            .form-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
                border-radius: 20px;
                max-width: 100%;
            }

            .form-title h2 {
                font-size: 1.5rem;
            }

            input, select {
                padding: 0.9rem 0.9rem 0.9rem 2.8rem;
                font-size: 0.95rem;
            }

            /* Mobile date input specific styling */
            input[type="date"] {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 0.9rem 2.8rem 0.9rem 2.8rem;
            }

            .submit-btn {
                padding: 1rem 1.5rem;
                font-size: 1rem;
            }

            .services-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .availability-indicator {
                position: static;
                display: block;
                margin-top: 0.5rem;
                font-size: 0.75rem;
                text-align: center;
                max-width: none;
                padding: 0.3rem 0.6rem;
            }
            
            select {
                padding-right: 2.5rem;
            }
        }

        @media (max-width: 480px) {
            .logo {
                flex-direction: column;
                gap: 0.5rem;
            }

            .form-container {
                padding: 1.5rem 1rem;
                margin: 0.5rem;
            }

            .tagline {
                font-size: 1rem;
            }

            .logo h1 {
                font-size: 1.8rem;
            }

            .logo i {
                font-size: 2rem;
            }

            .form-title h2 {
                font-size: 1.3rem;
            }

            .form-title p {
                font-size: 0.9rem;
            }

            input, select {
                padding: 0.8rem 0.8rem 0.8rem 2.5rem;
                font-size: 0.9rem;
            }

            /* Mobile date input specific styling */
            input[type="date"] {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 0.8rem 2.5rem 0.8rem 2.5rem;
            }

            .submit-btn {
                padding: 0.9rem 1.2rem;
                font-size: 0.95rem;
            }

            .services-grid {
                margin-top: 1.5rem;
            }

            .service-card {
                padding: 1.2rem;
            }

            .service-card i {
                font-size: 1.8rem;
            }

            .service-card h3 {
                font-size: 1rem;
            }

            .service-card p {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 360px) {
            .form-container {
                padding: 1.2rem 0.8rem;
            }

            .header {
                padding: 1rem 0.5rem;
            }

            .logo h1 {
                font-size: 1.6rem;
            }

            .form-title h2 {
                font-size: 1.2rem;
            }

            input, select {
                padding: 0.7rem 0.7rem 0.7rem 2.2rem;
                font-size: 0.85rem;
            }

            /* Mobile date input specific styling */
            input[type="date"] {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 0.7rem 2.2rem 0.7rem 2.2rem;
            }

            .submit-btn {
                padding: 0.8rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Landscape orientation adjustments for mobile */
        @media (max-height: 600px) and (orientation: landscape) {
            .header {
                padding: 1rem;
            }

            .container {
                min-height: auto;
                padding: 0.5rem;
            }

            .form-container {
                padding: 1.5rem;
            }

            .services-preview {
                margin-top: 2rem;
            }
        }

        /* High DPI displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .logo i {
                filter: drop-shadow(0 0 5px rgba(212, 175, 55, 0.3));
            }
        }

        /* Accessibility improvements */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }

            .bg-animation::before {
                animation: none;
            }
        }

        /* Enhanced dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background: var(--bg-primary);
            }

            .form-container {
                background: rgba(255, 255, 255, 0.02);
                border-color: rgba(255, 255, 255, 0.05);
            }

            .service-card {
                background: rgba(255, 255, 255, 0.02);
                border-color: rgba(255, 255, 255, 0.05);
            }

            .service-card:hover {
                background: rgba(255, 255, 255, 0.04);
            }
        }

        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            input, select, .submit-btn {
                min-height: 44px;
            }

            .admin-link a {
                min-height: 44px;
                display: inline-flex;
                align-items: center;
            }
        }

        /* Improved zoom handling for mobile */
        @media screen and (max-width: 768px) {
            html {
                -webkit-text-size-adjust: 100%;
                -ms-text-size-adjust: 100%;
            }

            body {
                -webkit-overflow-scrolling: touch;
            }

            .form-container {
                min-width: 0;
                width: calc(100% - 2rem);
                max-width: none;
            }

            input, select {
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }

        /* Better contrast for dark mode */
        @media (prefers-color-scheme: dark) {
            .form-group label {
                color: #ff4444;
            }

            .form-title h2 {
                color: #ff4444;
            }

            .service-card h3 {
                color: #ff4444;
            }

            .tagline {
                color: #9ca3af;
            }

            .form-title p {
                color: #9ca3af;
            }
        }
    </style>
</head>
<body>
    <div class="bg-animation"></div>
    
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <header class="header">
        <div class="logo">
            <i class="fas fa-cut"></i>
            <h1>Old School Barber</h1>
        </div>
        <p class="tagline">Tradizione, stile e passione dal 1985</p>
    </header>

    <div class="container">
        <div class="form-container">
            <div class="form-title">
                <h2>Prenota il tuo taglio</h2>
                <p>Scegli il servizio perfetto per te</p>
            </div>

            <form method="POST" action="prenota.php" id="bookingForm">
                <div class="form-group">
                    <label for="nome">Nome completo</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="nome" name="nome" placeholder="Inserisci il tuo nome" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="la-tua-email@esempio.com">
                    </div>
                </div>

                <div class="form-group">
                    <label for="telefono">Telefono</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="telefono" name="telefono" placeholder="+39 123 456 7890">
                    </div>
                </div>

                <div class="form-group">
                    <label for="servizio">Servizio</label>
                    <div class="input-wrapper">
                        <i class="fas fa-scissors"></i>
                        <select id="servizio" name="servizio" required>
                            <option value="" disabled selected>Seleziona il servizio</option>
                            <?php
                            $query = $conn->query("SELECT nome, prezzo FROM servizi");
                            while ($row = $query->fetch_assoc()) {
                                echo "<option value='{$row['nome']}'>{$row['nome']} - €{$row['prezzo']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="operatore">Operatore (opzionale)</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user-tie"></i>
                        <select id="operatore" name="operatore_id">
                            <option value="">Nessuna preferenza</option>
                            <?php
                            $operators_query = $conn->query("SELECT id, nome, cognome FROM operatori WHERE attivo = 1 ORDER BY nome, cognome");
                            while ($operator = $operators_query->fetch_assoc()) {
                                echo "<option value='{$operator['id']}'>{$operator['nome']} {$operator['cognome']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="data_prenotazione">Data</label>
                    <div class="input-wrapper">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" id="data_prenotazione" name="data_prenotazione" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="orario">Orario</label>
                    <div class="input-wrapper">
                        <i class="fas fa-clock"></i>
                        <select id="orario" name="orario" required>
                            <option value="" disabled selected>Seleziona l'orario</option>
                        </select>
                        <div class="availability-indicator" id="availabilityIndicator" style="display: none;"></div>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-calendar-check"></i>
                    Prenota ora
                </button>
            </form>

            <div class="admin-link">
                <a href="login.php">
                    <i class="fas fa-user-shield"></i>
                    Area Amministratore
                </a>
            </div>

            <div class="cancel-link">
                <a href="cancel_booking.php">
                    <i class="fas fa-times-circle"></i>
                    Cancella una prenotazione
                </a>
            </div>
        </div>
    </div>

    <div class="services-preview">
        <div class="services-grid">
            <div class="service-card">
                <i class="fas fa-cut"></i>
                <h3>Taglio Classico</h3>
                <p>Il nostro taglio tradizionale con forbici e rasoio</p>
            </div>
            <div class="service-card">
                <i class="fas fa-user-tie"></i>
                <h3>Taglio & Barba</h3>
                <p>Servizio completo per un look impeccabile</p>
            </div>
            <div class="service-card">
                <i class="fas fa-spa"></i>
                <h3>Trattamenti</h3>
                <p>Cura e benessere per i tuoi capelli</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dateInput = document.getElementById('data_prenotazione');
            const timeSelect = document.getElementById('orario');
            const form = document.getElementById('bookingForm');
            const loading = document.getElementById('loading');
            const submitBtn = document.getElementById('submitBtn');
            const availabilityIndicator = document.getElementById('availabilityIndicator');

            // Set minimum date to today
            const today = new Date();
            const day = String(today.getDate()).padStart(2, '0');
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const year = today.getFullYear();
            const todayLocal = `${year}-${month}-${day}`;

            dateInput.setAttribute("min", todayLocal);
            dateInput.value = todayLocal;

            // Block Mondays and Sundays
            dateInput.addEventListener('input', () => {
                const selected = new Date(dateInput.value);
                const dayOfWeek = selected.getDay();
                
                if (dayOfWeek === 1) { // Monday
                    alert('Siamo chiusi il lunedì. Scegli un altro giorno.');
                    dateInput.value = "";
                    timeSelect.innerHTML = '<option value="" disabled selected>Seleziona l\'orario</option>';
                    hideAvailabilityIndicator();
                } else if (dayOfWeek === 0) { // Sunday
                    alert('Siamo chiusi la domenica. Scegli un altro giorno.');
                    dateInput.value = "";
                    timeSelect.innerHTML = '<option value="" disabled selected>Seleziona l\'orario</option>';
                    hideAvailabilityIndicator();
                } else {
                    updateTimeSlots();
                }
            });

            // Check availability when time is selected
            timeSelect.addEventListener('change', () => {
                if (dateInput.value && timeSelect.value) {
                    checkAvailability(dateInput.value, timeSelect.value);
                }
            });

            function hideAvailabilityIndicator() {
                availabilityIndicator.style.display = 'none';
                submitBtn.disabled = false;
            }

            function showAvailabilityIndicator(status, message) {
                availabilityIndicator.style.display = 'block';
                availabilityIndicator.className = `availability-indicator ${status}`;
                availabilityIndicator.textContent = message;
                
                if (status === 'unavailable') {
                    submitBtn.disabled = true;
                } else {
                    submitBtn.disabled = false;
                }
            }

            function checkAvailability(date, time) {
                showAvailabilityIndicator('checking', 'Controllo...');
                
                fetch(`check_availability.php?date=${date}&time=${time}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            showAvailabilityIndicator('available', data.message);
                        } else {
                            showAvailabilityIndicator('unavailable', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error checking availability:', error);
                        showAvailabilityIndicator('unavailable', 'Errore controllo');
                    });
            }

            // Function to update time slots based on selected date
            function updateTimeSlots() {
                const selectedDate = new Date(dateInput.value);
                const dayOfWeek = selectedDate.getDay();
                
                // Clear existing options
                timeSelect.innerHTML = '<option value="" disabled selected>Seleziona l\'orario</option>';
                hideAvailabilityIndicator();
                
                // Generate time slots from 9:00 to 18:30
                for (let hour = 9; hour < 19; hour++) {
                    for (let min of [0, 30]) {
                        let h = hour.toString().padStart(2, '0');
                        let m = min.toString().padStart(2, '0');
                        let timeValue = `${h}:${m}`;
                        
                        let option = document.createElement('option');
                        option.value = timeValue;
                        option.textContent = timeValue;
                        timeSelect.appendChild(option);
                    }
                }
            }

            // Initial time slots generation
            updateTimeSlots();

            // Form submission with loading
            form.addEventListener('submit', (e) => {
                if (submitBtn.disabled) {
                    e.preventDefault();
                    alert('Seleziona un orario disponibile prima di procedere.');
                    return;
                }
                loading.style.display = 'flex';
            });

            // Add smooth animations
            const inputs = document.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('focus', () => {
                    input.parentElement.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', () => {
                    input.parentElement.parentElement.style.transform = 'translateY(0)';
                });
            });

            // Touch device optimizations
            if ('ontouchstart' in window) {
                document.body.classList.add('touch-device');
                
                // Improve touch targets
                const touchElements = document.querySelectorAll('input, select, button, a');
                touchElements.forEach(element => {
                    element.style.minHeight = '44px';
                });
            }

            // Viewport height fix for mobile browsers
            function setViewportHeight() {
                const vh = window.innerHeight * 0.01;
                document.documentElement.style.setProperty('--vh', `${vh}px`);
            }

            setViewportHeight();
            window.addEventListener('resize', setViewportHeight);
            window.addEventListener('orientationchange', () => {
                setTimeout(setViewportHeight, 100);
            });

            // Prevent zoom on input focus for iOS
            if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                const viewport = document.querySelector('meta[name=viewport]');
                viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
            }
        });
    </script>
</body>
</html>
```
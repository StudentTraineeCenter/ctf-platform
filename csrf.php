<?php
/**
 * CSRF Protection Helper
 * 
 * Poskytuje funkce pro ochranu proti Cross-Site Request Forgery útokům.
 * Token je uložen v session a validován u všech POST requestů.
 * 
 * Funkce:
 * - generateCsrfToken() - Vygeneruje nový token a uloží do session
 * - getCsrfToken() - Získá aktuální token (vytvoří pokud neexistuje)
 * - validateCsrfToken() - Validuje token z requestu (timing-attack safe s hash_equals)
 * - regenerateCsrfToken() - Obnoví token (např. po přihlášení)
 */

function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? generateCsrfToken();
}

function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

function regenerateCsrfToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

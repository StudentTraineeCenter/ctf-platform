<?php
/**
 * XSS Protection & Input Validation Helper
 * 
 * Poskytuje funkce pro sanitizaci vstupů a ochranu proti XSS útokům.
 * 
 * Sanitizační funkce:
 * - cleanHtml() - Escapuje HTML speciální znaky pro bezpečný výstup
 * - cleanJs() - Sanitizace pro JavaScript kontext
 * - cleanUrl() - URL encoding
 * - sanitizeDescription() - Whitelist HTML tagů pro challenge popisy (povoleny pouze bezpečné tagy)
 * - sanitizeInput() - Základní očištění vstupu (trim, stripslashes)
 * 
 * Validační funkce:
 * - validateUsername() - Regex validace: a-z, A-Z, 0-9, _, délka 3-20 znaků
 * - validateEmail() - Email formát validace
 * - validateFlagFormat() - FLAG{...} formát s alfanumerickými znaky
 * 
 * Automatická ochrana:
 * - Odstranění event handlerů (onclick, onerror, atd.)
 * - Blokování javascript: protokolu v odkazech
 */

function cleanHtml($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function cleanJs($str) {
    return json_encode($str, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function cleanUrl($str) {
    return urlencode($str);
}

function sanitizeDescription($html) {
    $allowedTags = '<p><br><strong><b><em><i><code><pre><a><h3><h4><ul><ol><li><div><span><hr>';
    $html = strip_tags($html, $allowedTags);
    
    // Dodatečné ošetření - odstranit event handlery (onclick, onerror, atd.)
    $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    
    // Odstranit javascript: protokol z odkazů
    $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $html);
    
    return $html;
}

function validateUsername($username) {
    return (bool) preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    return $input;
}

function validateFlagFormat($flag) {
    return (bool) preg_match('/^FLAG\{[a-zA-Z0-9_]+\}$/', $flag);
}

function safeErrorMessage($message) {
    return cleanHtml($message);
}

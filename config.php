<?php
/**
 * Application Configuration
 * 
 * Centrální konfigurační soubor platformy obsahující:
 * 
 * Databázové připojení:
 * - DB_HOST, DB_NAME, DB_USER, DB_PASS - MySQL přihlašovací údaje
 * 
 * Aplikační nastavení:
 * - APP_NAME - Název aplikace
 * - APP_VERSION - Verze platformy
 * 
 * Session konfigurace:
 * - SESSION_NAME - Název session cookie
 * - SESSION_LIFETIME - Doba platnosti session (7 dní)
 * 
 * Bezpečnostní parametry:
 * - PASSWORD_MIN_LENGTH - Minimální délka hesla (6 znaků)
 * - FLAG_PREFIX, FLAG_SUFFIX - Formát flagů (FLAG{...})
 * 
 * Debug režim:
 * - DEBUG_MODE - Při true zobrazuje detailní chyby, při false skrývá (DŮLEŽITÉ: nastavit false v produkci!)
 * 
 * DŮLEŽITÉ: Před nasazením do produkce změňte DB heslo a nastavte DEBUG_MODE na false!
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'ctf_platform');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Shadow Protocol CTF');
define('APP_VERSION', '1.0.0');

define('SESSION_NAME', 'ctf_session');
define('SESSION_LIFETIME', 3600 * 24 * 7);

define('PASSWORD_MIN_LENGTH', 6);

define('FLAG_PREFIX', 'CTF{');
define('FLAG_SUFFIX', '}');

define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

date_default_timezone_set('Europe/Prague');

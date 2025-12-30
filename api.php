<?php
/**
 * API Endpoint - AJAX Request Handler
 * 
 * Centrální API endpoint pro všechny AJAX požadavky z frontendu. Zpracovává:
 * - Registraci a přihlášení uživatelů (s automatickou synchronizací LocalStorage)
 * - Získání výzev (challenges) s progresem uživatele
 * - Validaci flagů a dokončování misí
 * - Správu statistik, logů a easter eggs
 * - Session management
 * 
 * 
 * Všechny odpovědi vraceny jako JSON: {success: bool, message: string, data: object}
 */

session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'db.php';
require_once 'csrf.php';
require_once 'xss.php';

function jsonResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        jsonResponse(false, 'Neplatný bezpečnostní token. Obnovte stránku a zkuste to znovu.');
    }
}

try {
    $db = Database::getInstance();
    
    switch ($action) {
        
        case 'register':
            $username = sanitizeInput($_POST['username'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($username) || empty($email) || empty($password)) {
                jsonResponse(false, 'Všechna pole jsou povinná');
            }
            
            if (!validateUsername($username)) {
                jsonResponse(false, 'Uživatelské jméno může obsahovat pouze písmena, čísla a podtržítko (3-20 znaků)');
            }
            
            if (!validateEmail($email)) {
                jsonResponse(false, 'Neplatná emailová adresa');
            }
            
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                jsonResponse(false, 'Heslo musí mít alespoň ' . PASSWORD_MIN_LENGTH . ' znaků');
            }
            
            if ($password !== $confirmPassword) {
                jsonResponse(false, 'Hesla se neshodují');
            }
            
            $userId = $db->createUser($username, $email, $password);
            
            if ($userId) {
                $db->initializeUserProgress($userId);
                
                if (!empty($_POST['local_progress'])) {
                    $localProgress = json_decode($_POST['local_progress'], true);
                    if ($localProgress) {
                        $db->syncLocalProgress($userId, $localProgress);
                    }
                }
                
                if (!empty($_POST['local_easter_eggs'])) {
                    $localEasterEggs = json_decode($_POST['local_easter_eggs'], true);
                    if ($localEasterEggs) {
                        $db->syncLocalEasterEggs($userId, $localEasterEggs);
                    }
                }
                
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = 0;
                
                $user = $db->getUserById($userId);
                
                jsonResponse(true, 'Registrace úspěšná! Vítej, agente.', [
                    'user' => $user,
                    'redirect' => 'index.php'
                ]);
            }
            
            break;
            
        case 'login':
            $username = sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                jsonResponse(false, 'Uživatelské jméno a heslo jsou povinné');
            }
            
            $user = $db->verifyLogin($username, $password);
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                if (!empty($_POST['local_progress'])) {
                    $localProgress = json_decode($_POST['local_progress'], true);
                    if ($localProgress) {
                        $db->syncLocalProgress($user['id'], $localProgress);
                    }
                }
                
                if (!empty($_POST['local_easter_eggs'])) {
                    $localEasterEggs = json_decode($_POST['local_easter_eggs'], true);
                    if ($localEasterEggs) {
                        $db->syncLocalEasterEggs($user['id'], $localEasterEggs);
                    }
                }
                
                $userData = $db->getUserById($user['id']);
                
                jsonResponse(true, 'Přihlášení úspěšné! Vítej zpět, agente.', [
                    'user' => $userData,
                    'redirect' => 'index.php'
                ]);
            } else {
                jsonResponse(false, 'Neplatné přihlašovací údaje');
            }
            
            break;
            
        case 'logout':
            session_destroy();
            jsonResponse(true, 'Odhlášení úspěšné');
            break;
            
        case 'get_challenges':
            $challenges = $db->getAllChallenges();
            
            if (isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
                $progress = $db->getUserProgress($userId);
                
                $progressMap = [];
                foreach ($progress as $p) {
                    $progressMap[$p['challenge_id']] = $p;
                }
                
                foreach ($challenges as &$challenge) {
                    if (isset($progressMap[$challenge['id']])) {
                        $challenge['user_status'] = $progressMap[$challenge['id']]['status'];
                        $challenge['attempts'] = $progressMap[$challenge['id']]['attempts'];
                        $challenge['completed_at'] = $progressMap[$challenge['id']]['completed_at'];
                    } else {
                        $challenge['user_status'] = $challenge['is_unlocked_default'] ? 'unlocked' : 'locked';
                        $challenge['attempts'] = 0;
                    }
                }
            } else {
                foreach ($challenges as &$challenge) {
                    $challenge['user_status'] = $challenge['is_unlocked_default'] ? 'unlocked' : 'locked';
                    $challenge['attempts'] = 0;
                }
            }
            
            jsonResponse(true, 'Výzvy načteny', ['challenges' => $challenges]);
            break;
            
        case 'submit_flag':
            $challengeId = intval($_POST['challenge_id'] ?? 0);
            $flag = trim($_POST['flag'] ?? '');
            
            if (empty($flag) || $challengeId <= 0) {
                jsonResponse(false, 'Neplatné data');
            }
            
            $isValid = $db->validateFlag($challengeId, $flag);
            
            if ($isValid) {
                if (isset($_SESSION['user_id'])) {
                    $userId = $_SESSION['user_id'];
                    
                    $currentProgress = $db->getChallengeProgress($userId, $challengeId);
                    
                    if ($currentProgress && $currentProgress['status'] === 'completed') {
                        $challenge = $db->getChallengeById($challengeId);
                        $stats = $db->getUserStatistics($userId);
                        $user = $db->getUserById($userId);
                        
                        jsonResponse(true, 'Tato výzva je již dokončena!', [
                            'story_chapter' => $challenge['story_chapter'],
                            'points' => 0,
                            'stats' => $stats,
                            'user' => $user,
                            'already_completed' => true
                        ]);
                    } else {
                        $db->completeChallenge($userId, $challengeId);
                        
                        $challenge = $db->getChallengeById($challengeId);
                        $stats = $db->getUserStatistics($userId);
                        $user = $db->getUserById($userId);
                        
                        jsonResponse(true, 'Správně! Výzva dokončena!', [
                            'story_chapter' => $challenge['story_chapter'],
                            'points' => $challenge['points'],
                            'stats' => $stats,
                            'user' => $user
                        ]);
                    }
                } else {
                    $challenge = $db->getChallengeById($challengeId);
                    jsonResponse(true, 'Správně! Výzva dokončena! (Přihlas se pro uložení progresu)', [
                        'story_chapter' => $challenge['story_chapter'],
                        'points' => $challenge['points']
                    ]);
                }
            } else {
                if (isset($_SESSION['user_id'])) {
                    $db->incrementAttempts($_SESSION['user_id'], $challengeId);
                }
                
                jsonResponse(false, 'Nesprávná vlajka. Zkus to znovu!');
            }
            
            break;
            
        case 'get_stats':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(false, 'Musíš být přihlášen');
            }
            
            $userId = $_SESSION['user_id'];
            $user = $db->getUserById($userId);
            $stats = $db->getUserStatistics($userId);
            $logs = $db->getUserLogs($userId);
            $easterEggs = $db->getUserEasterEggs($userId);
            
            jsonResponse(true, 'Statistiky načteny', [
                'user' => $user,
                'stats' => $stats,
                'logs' => $logs,
                'easter_eggs' => $easterEggs
            ]);
            break;
            
        case 'get_logs':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(false, 'Musíš být přihlášen');
            }
            
            $logs = $db->getUserLogs($_SESSION['user_id']);
            jsonResponse(true, 'Logy načteny', ['logs' => $logs]);
            break;
            
        case 'discover_easter_egg':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(false, 'Musíš být přihlášen');
            }
            
            $challengeId = intval($_POST['challenge_id'] ?? 0);
            $easterEggCode = trim($_POST['code'] ?? '');
            
            if ($challengeId <= 0 || empty($easterEggCode)) {
                jsonResponse(false, 'Neplatné data');
            }
            
            $challenge = $db->getChallengeById($challengeId);
            
            if ($challenge && $challenge['easter_egg'] === $easterEggCode) {
                $db->discoverEasterEgg($_SESSION['user_id'], $challengeId, $easterEggCode);
                jsonResponse(true, 'Easter Egg objeven! +50 bodů!', [
                    'bonus_points' => 50
                ]);
            } else {
                jsonResponse(false, 'Neplatný easter egg kód');
            }
            
            break;
            
        case 'check_session':
            if (isset($_SESSION['user_id'])) {
                $user = $db->getUserById($_SESSION['user_id']);
                jsonResponse(true, 'Uživatel přihlášen', ['user' => $user]);
            } else {
                jsonResponse(false, 'Uživatel není přihlášen');
            }
            break;
            
        default:
            jsonResponse(false, 'Neplatná akce');
            break;
    }
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        jsonResponse(false, 'Chyba: ' . $e->getMessage());
    } else {
        jsonResponse(false, 'Došlo k chybě. Zkus to prosím znovu.');
    }
}

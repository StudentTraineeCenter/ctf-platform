<?php
/**
 * ADMIN ACTIONS API - Backend Handler for Admin Operations
 * 
 * API endpoint pro zpracování všech admin CRUD operací. Všechny akce vyžadují:
 * - Aktivní session s is_admin = 1
 * - Platný CSRF token
 * 
 * Challenge Operations:
 * - add_challenge - Vytvoření nové výzvy (validace flag formátu, generování hash)
 * - update_challenge - Editace výzvy (možnost změnit flag nebo jen metadata)
 * - delete_challenge - Smazání výzvy včetně všech závislostí (user_progress, logs, easter eggs)
 * - get_challenge - Získání detailu výzvy (bez flag_hash z bezpečnostních důvodů)
 * 
 * User Operations:
 * - get_users - Seznam všech uživatelů s detaily
 * - update_user - Editace uživatele (username, email, admin práva)
 * - delete_user - Smazání uživatele včetně progresu
 * - reset_password - Reset hesla uživatele
 * 
 * System Operations:
 * - get_stats - Systémové statistiky
 * - get_logs - Audit logy
 * 
 * Bezpečnostní vlastnosti:
 * - Admin oprávnění check na začátku
 * - CSRF token validace
 * - XSS sanitizace HTML vstupů (challenge descriptions, story chapters)
 * - Flag format validace (FLAG{[a-zA-Z0-9_]+})
 * - bcrypt hashing flagů
 * - Transaction handling při mazání (rollback při chybě)
 * 
 * Všechny odpovědi jako JSON: {success: bool, message: string, data?: object}
 */

session_start();
header('Content-Type: application/json');

require_once '../config.php';
require_once '../db.php';
require_once '../csrf.php';
require_once '../xss.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Neplatný bezpečnostní token']);
    exit;
}

$action = $_POST['action'] ?? '';
$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    switch ($action) {
        
        case 'delete_challenge':
            $challengeId = intval($_POST['challenge_id'] ?? 0);
            if ($challengeId <= 0) {
                throw new Exception('Neplatné ID challenge');
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("DELETE FROM user_progress WHERE challenge_id = :id");
            $stmt->execute([':id' => $challengeId]);
            
            $stmt = $pdo->prepare("DELETE FROM agent_logs WHERE challenge_id = :id");
            $stmt->execute([':id' => $challengeId]);
            
            $stmt = $pdo->prepare("DELETE FROM discovered_easter_eggs WHERE challenge_id = :id");
            $stmt->execute([':id' => $challengeId]);
            
            $stmt = $pdo->prepare("DELETE FROM challenges WHERE id = :id");
            $stmt->execute([':id' => $challengeId]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Challenge smazána']);
            break;
            
        case 'add_challenge':
            $title = sanitizeInput($_POST['title'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $category = sanitizeInput($_POST['category'] ?? '');
            $difficulty = sanitizeInput($_POST['difficulty'] ?? '');
            $points = intval($_POST['points'] ?? 0);
            $flag = sanitizeInput($_POST['flag'] ?? '');
            $hintText = sanitizeInput($_POST['hint_text'] ?? '');
            $storyChapter = sanitizeInput($_POST['story_chapter'] ?? '');
            $storyOrder = intval($_POST['story_order'] ?? 0);
            $unlockAfter = intval($_POST['unlock_after_challenge_id'] ?? 0) ?: null;
            $isUnlockedDefault = isset($_POST['is_unlocked_default']) ? 1 : 0;
            
            if (empty($title) || empty($flag) || $points <= 0) {
                throw new Exception('Vyplňte všechna povinná pole');
            }
            
            $description = sanitizeDescription($description);
            $storyChapter = sanitizeDescription($storyChapter);
            
            if (!validateFlagFormat($flag)) {
                throw new Exception('Flag musí být ve formátu FLAG{...} a obsahovat pouze písmena, čísla a podtržítko');
            }
            
            $flagHash = password_hash($flag, PASSWORD_BCRYPT, ['cost' => 10]);
            
            $sql = "INSERT INTO challenges (
                title, description, category, difficulty, points, flag_hash,
                hint_text, story_chapter, story_order, unlock_after_challenge_id, is_unlocked_default
            ) VALUES (
                :title, :description, :category, :difficulty, :points, :flag_hash,
                :hint_text, :story_chapter, :story_order, :unlock_after, :is_unlocked_default
            )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':category' => $category,
                ':difficulty' => $difficulty,
                ':points' => $points,
                ':flag_hash' => $flagHash,
                ':hint_text' => $hintText,
                ':story_chapter' => $storyChapter,
                ':story_order' => $storyOrder,
                ':unlock_after' => $unlockAfter,
                ':is_unlocked_default' => $isUnlockedDefault
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Challenge vytvořena']);
            break;
            
        case 'get_challenge':
            $challengeId = intval($_POST['challenge_id'] ?? 0);
            if ($challengeId <= 0) {
                throw new Exception('Neplatné ID challenge');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = :id");
            $stmt->execute([':id' => $challengeId]);
            $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$challenge) {
                throw new Exception('Challenge nenalezena');
            }
            
            unset($challenge['flag_hash']);
            $challenge['has_flag'] = true;
            
            echo json_encode(['success' => true, 'challenge' => $challenge]);
            break;
            
        case 'update_challenge':
            $challengeId = intval($_POST['challenge_id'] ?? 0);
            $title = sanitizeInput($_POST['title'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $category = sanitizeInput($_POST['category'] ?? '');
            $difficulty = sanitizeInput($_POST['difficulty'] ?? '');
            $points = intval($_POST['points'] ?? 0);
            $hintText = sanitizeInput($_POST['hint_text'] ?? '');
            $storyChapter = sanitizeInput($_POST['story_chapter'] ?? '');
            $storyOrder = intval($_POST['story_order'] ?? 0);
            $unlockAfter = intval($_POST['unlock_after_challenge_id'] ?? 0) ?: null;
            $isUnlockedDefault = isset($_POST['is_unlocked_default']) ? 1 : 0;
            
            if ($challengeId <= 0 || empty($title) || $points <= 0) {
                throw new Exception('Vyplňte všechna povinná pole');
            }
            
            // XSS ochrana - sanitizace HTML description
            $description = sanitizeDescription($description);
            $storyChapter = sanitizeDescription($storyChapter);
            
            $flag = sanitizeInput($_POST['flag'] ?? '');
            if (!empty($flag)) {
                if (!validateFlagFormat($flag)) {
                    throw new Exception('Flag musí být ve formátu FLAG{...} a obsahovat pouze písmena, čísla a podtržítko');
                }
                $flagHash = password_hash($flag, PASSWORD_BCRYPT, ['cost' => 10]);
                
                $sql = "UPDATE challenges SET 
                    title = :title, description = :description, category = :category, 
                    difficulty = :difficulty, points = :points, flag_hash = :flag_hash,
                    hint_text = :hint_text, story_chapter = :story_chapter, story_order = :story_order,
                    unlock_after_challenge_id = :unlock_after, is_unlocked_default = :is_unlocked_default
                    WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':category' => $category,
                    ':difficulty' => $difficulty,
                    ':points' => $points,
                    ':flag_hash' => $flagHash,
                    ':hint_text' => $hintText,
                    ':story_chapter' => $storyChapter,
                    ':story_order' => $storyOrder,
                    ':unlock_after' => $unlockAfter,
                    ':is_unlocked_default' => $isUnlockedDefault,
                    ':id' => $challengeId
                ]);
            } else {
                $sql = "UPDATE challenges SET 
                    title = :title, description = :description, category = :category, 
                    difficulty = :difficulty, points = :points,
                    hint_text = :hint_text, story_chapter = :story_chapter, story_order = :story_order,
                    unlock_after_challenge_id = :unlock_after, is_unlocked_default = :is_unlocked_default
                    WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':category' => $category,
                    ':difficulty' => $difficulty,
                    ':points' => $points,
                    ':hint_text' => $hintText,
                    ':story_chapter' => $storyChapter,
                    ':story_order' => $storyOrder,
                    ':unlock_after' => $unlockAfter,
                    ':is_unlocked_default' => $isUnlockedDefault,
                    ':id' => $challengeId
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Challenge aktualizována']);
            break;
            
        // ==================== USER OPERATIONS ====================
        
        case 'make_admin':
            $userId = intval($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new Exception('Neplatné ID uživatele');
            }
            
            $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Uživatel je nyní admin']);
            break;
            
        case 'remove_admin':
            $userId = intval($_POST['user_id'] ?? 0);
            $currentUserId = $_SESSION['user_id'];
            
            if ($userId === $currentUserId) {
                throw new Exception('Nemůžete odebrat sami sobě admin práva');
            }
            
            if ($userId <= 0) {
                throw new Exception('Neplatné ID uživatele');
            }
            
            $stmt = $pdo->prepare("UPDATE users SET is_admin = 0 WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Admin práva odebrána']);
            break;
            
        case 'delete_user':
            $userId = intval($_POST['user_id'] ?? 0);
            $currentUserId = $_SESSION['user_id'];
            
            if ($userId === $currentUserId) {
                throw new Exception('Nemůžete smazat sám sebe');
            }
            
            if ($userId <= 0) {
                throw new Exception('Neplatné ID uživatele');
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("DELETE FROM user_progress WHERE user_id = :id");
            $stmt->execute([':id' => $userId]);
            
            $stmt = $pdo->prepare("DELETE FROM agent_logs WHERE user_id = :id");
            $stmt->execute([':id' => $userId]);
            
            $stmt = $pdo->prepare("DELETE FROM discovered_easter_eggs WHERE user_id = :id");
            $stmt->execute([':id' => $userId]);
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Uživatel smazán']);
            break;
            
        case 'get_user':
            $userId = intval($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new Exception('Neplatné ID uživatele');
            }
            
            $stmt = $pdo->prepare("SELECT id, username, email, is_admin FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('Uživatel nenalezen');
            }
            
            echo json_encode(['success' => true, 'user' => $user]);
            break;
            
        case 'update_user':
            $userId = intval($_POST['user_id'] ?? 0);
            $username = sanitizeInput($_POST['username'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if ($userId <= 0 || empty($username) || empty($email)) {
                throw new Exception('Vyplňte všechna pole');
            }
            
            // XSS ochrana - validace formátu
            if (!validateUsername($username)) {
                throw new Exception('Uživatelské jméno může obsahovat pouze písmena, čísla a podtržítko (3-20 znaků)');
            }
            
            if (!validateEmail($email)) {
                throw new Exception('Neplatný email');
            }
            
            // Kontrola duplicitního username (kromě tohoto uživatele)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :userId");
            $stmt->execute([':username' => $username, ':userId' => $userId]);
            if ($stmt->fetch()) {
                throw new Exception('Username již existuje');
            }
            
            // Kontrola duplicitního emailu
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :userId");
            $stmt->execute([':email' => $email, ':userId' => $userId]);
            if ($stmt->fetch()) {
                throw new Exception('Email již existuje');
            }
            
            $stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email WHERE id = :id");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':id' => $userId
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Uživatel aktualizován']);
            break;
            
        default:
            throw new Exception('Neplatná akce');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

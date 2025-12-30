<?php
/**
 * Database Access Layer - PDO Wrapper
 * 
 * 
 * Hlavní funkcionality:
 * - User management (create, verify login, update progress, statistics)
 * - Challenge operations (get all, validate flag, update)
 * - User progress tracking (initialize, complete, unlock, sync with LocalStorage)
 * - Agent logs (story progression)
 * - Easter eggs discovery
 * - Transaction handling s ochranou před nested transactions
 * 
 */

require_once 'config.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Database Connection Error: " . $e->getMessage());
            } else {
                die("Database connection failed. Please contact administrator.");
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __clone() {}
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function createUser($username, $email, $password) {
        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            
            $sql = "INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $passwordHash
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                throw new Exception("Error creating user: " . $e->getMessage());
            }
            throw new Exception("Registration failed. Username or email might already exist.");
        }
    }
    
    public function getUserByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = :username LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        return $stmt->fetch();
    }
    
    public function getUserById($userId) {
        $sql = "SELECT id, username, email, total_progress, current_level, total_score, agent_rank, is_admin, created_at, last_login 
                FROM users WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch();
    }
    
    public function verifyLogin($username, $password) {
        $user = $this->getUserByUsername($username);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $sql = "UPDATE users SET last_login = NOW() WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $user['id']]);
            
            return $user;
        }
        
        return false;
    }
    
    public function updateUserProgress($userId, $totalProgress, $currentLevel, $totalScore, $agentRank = null) {
        $sql = "UPDATE users SET 
                total_progress = :progress, 
                current_level = :level, 
                total_score = :score" . 
                ($agentRank ? ", agent_rank = :rank" : "") . 
                " WHERE id = :id";
        
        $params = [
            ':progress' => $totalProgress,
            ':level' => $currentLevel,
            ':score' => $totalScore,
            ':id' => $userId
        ];
        
        if ($agentRank) {
            $params[':rank'] = $agentRank;
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function getAllChallenges() {
        $sql = "SELECT * FROM challenges ORDER BY story_order ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    public function getChallengeById($challengeId) {
        $sql = "SELECT * FROM challenges WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $challengeId]);
        return $stmt->fetch();
    }
    
    public function validateFlag($challengeId, $submittedFlag) {
        $challenge = $this->getChallengeById($challengeId);
        
        if (!$challenge) {
            return false;
        }
        
        return password_verify($submittedFlag, $challenge['flag_hash']);
    }
    
    public function getUserProgress($userId) {
        $sql = "SELECT up.*, c.title, c.category, c.difficulty, c.points, c.story_order, c.story_chapter
                FROM user_progress up
                JOIN challenges c ON up.challenge_id = c.id
                WHERE up.user_id = :userId
                ORDER BY c.story_order ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':userId' => $userId]);
        return $stmt->fetchAll();
    }
    
    public function initializeUserProgress($userId, $skipIfExists = false) {
        $challenges = $this->getAllChallenges();
        
        foreach ($challenges as $challenge) {
            $status = $challenge['is_unlocked_default'] ? 'unlocked' : 'locked';
            
            if ($skipIfExists) {
                $sql = "INSERT IGNORE INTO user_progress (user_id, challenge_id, status) 
                    VALUES (:userId, :challengeId, :status_ins)";
            } else {

                $sql = "INSERT INTO user_progress (user_id, challenge_id, status) 
                    VALUES (:userId, :challengeId, :status_ins)
                    ON DUPLICATE KEY UPDATE status = IF(status = 'completed', 'completed', status)";
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':challengeId' => $challenge['id'],
                ':status_ins' => $status
            ]);
        }
        
        return true;
    }
    
    public function updateChallengeStatus($userId, $challengeId, $status) {
        if ($status !== 'completed') {
            $sql = "INSERT INTO user_progress (user_id, challenge_id, status, updated_at) 
                    VALUES (:userId, :challengeId, :status_val, NOW())
                    ON DUPLICATE KEY UPDATE 
                        status = IF(status = 'completed', 'completed', :status_upd), 
                        updated_at = NOW()";
        } else {
            $sql = "INSERT INTO user_progress (user_id, challenge_id, status, updated_at) 
                    VALUES (:userId, :challengeId, :status_val, NOW())
                    ON DUPLICATE KEY UPDATE status = :status_upd, updated_at = NOW()";
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':userId' => $userId,
            ':challengeId' => $challengeId,
            ':status_val' => $status,
            ':status_upd' => $status
        ]);
    }
    
    public function getChallengeProgress($userId, $challengeId) {
        $sql = "SELECT * FROM user_progress 
                WHERE user_id = :userId AND challenge_id = :challengeId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':userId' => $userId, ':challengeId' => $challengeId]);
        return $stmt->fetch();
    }
    
    public function completeChallenge($userId, $challengeId) {
        try {
            $ownTransaction = false;
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $ownTransaction = true;
            }
            
            $sql = "INSERT INTO user_progress (user_id, challenge_id, status, completed_at) 
                    VALUES (:userId, :challengeId, 'completed', NOW())
                    ON DUPLICATE KEY UPDATE 
                        status = 'completed', 
                        completed_at = NOW()";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':userId' => $userId, ':challengeId' => $challengeId]);
            
            $challenge = $this->getChallengeById($challengeId);
            
            $sql = "SELECT id FROM challenges WHERE unlock_after_challenge_id = :currentId LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':currentId' => $challengeId]);
            $nextChallenge = $stmt->fetch();
            if ($nextChallenge) {
                $this->updateChallengeStatus($userId, $nextChallenge['id'], 'unlocked');
            }
            
            if (!empty($challenge['story_chapter'])) {
                $this->addAgentLog($userId, $challengeId, $challenge['story_chapter']);
            }
            
            $sql = "UPDATE users 
                    SET total_score = total_score + :points,
                        total_progress = ROUND((SELECT COUNT(*) * 100.0 / (SELECT COUNT(*) FROM challenges) FROM user_progress WHERE user_id = :userId_count AND status = 'completed'), 2),
                        current_level = :storyOrder
                    WHERE id = :userId";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':points' => $challenge['points'],
                ':userId' => $userId,
                ':userId_count' => $userId,
                ':storyOrder' => $challenge['story_order']
            ]);
            
            if ($ownTransaction) {
                $this->pdo->commit();
            }
            return true;
            
        } catch (Exception $e) {
            if (isset($ownTransaction) && $ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (DEBUG_MODE) {
                throw new Exception("Error completing challenge: " . $e->getMessage());
            }
            return false;
        }
    }
    
    public function incrementAttempts($userId, $challengeId) {
        $sql = "UPDATE user_progress 
                SET attempts = attempts + 1 
                WHERE user_id = :userId AND challenge_id = :challengeId";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':userId' => $userId, ':challengeId' => $challengeId]);
    }
    
    public function addAgentLog($userId, $challengeId, $logEntry) {
        $sql = "INSERT INTO agent_logs (user_id, challenge_id, log_entry) 
                VALUES (:userId, :challengeId, :logEntry)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':userId' => $userId,
            ':challengeId' => $challengeId,
            ':logEntry' => $logEntry
        ]);
    }
    
    public function getUserLogs($userId) {
        $sql = "SELECT al.*, c.title as challenge_title, c.story_order
                FROM agent_logs al
                JOIN challenges c ON al.challenge_id = c.id
                WHERE al.user_id = :userId
                ORDER BY c.story_order ASC, al.log_timestamp ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':userId' => $userId]);
        return $stmt->fetchAll();
    }
    
    public function discoverEasterEgg($userId, $challengeId, $easterEggCode) {
        $sql = "INSERT INTO discovered_easter_eggs (user_id, challenge_id, easter_egg_code) 
                VALUES (:userId, :challengeId, :code)
                ON DUPLICATE KEY UPDATE discovered_at = NOW()";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':userId' => $userId,
            ':challengeId' => $challengeId,
            ':code' => $easterEggCode
        ]);
    }
    
    public function getUserEasterEggs($userId) {
        $sql = "SELECT dee.*, c.title as challenge_title 
                FROM discovered_easter_eggs dee
                JOIN challenges c ON dee.challenge_id = c.id
                WHERE dee.user_id = :userId
                ORDER BY dee.discovered_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':userId' => $userId]);
        return $stmt->fetchAll();
    }
    
    public function getUserStatistics($userId) {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM user_progress WHERE user_id = :uid1 AND status = 'completed') as completed_challenges,
                    (SELECT COUNT(*) FROM user_progress WHERE user_id = :uid2 AND status = 'unlocked') as unlocked_challenges,
                    (SELECT COUNT(*) FROM challenges) as total_challenges,
                    (SELECT SUM(c.points) FROM user_progress up JOIN challenges c ON up.challenge_id = c.id WHERE up.user_id = :uid3 AND up.status = 'completed') as total_points,
                    (SELECT COUNT(*) FROM discovered_easter_eggs WHERE user_id = :uid4) as easter_eggs_found,
                    (SELECT COUNT(*) FROM agent_logs WHERE user_id = :uid5) as log_entries";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uid1' => $userId,
            ':uid2' => $userId,
            ':uid3' => $userId,
            ':uid4' => $userId,
            ':uid5' => $userId,
        ]);
        return $stmt->fetch();
    }
    
    public function getTotalChallengesCount() {
        $sql = "SELECT COUNT(*) FROM challenges";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchColumn();
    }
    
    public function syncLocalProgress($userId, $localProgressData) {
        try {
            $ownTransaction = false;
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $ownTransaction = true;
            }
            
            if (empty($localProgressData)) {
                if ($ownTransaction) {
                    $this->pdo->commit();
                }
                return true;
            }
            
            $sql = "SELECT challenge_id, status FROM user_progress WHERE user_id = :userId";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            $dbProgress = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            foreach ($localProgressData as $challengeId => $data) {
                $localStatus = null;
                
                if (isset($data['completed']) && $data['completed'] === true) {
                    $localStatus = 'completed';
                } elseif (isset($data['unlocked']) && $data['unlocked'] === true) {
                    $localStatus = 'unlocked';
                } else {
                    $localStatus = 'locked';
                }
                
                $dbStatus = $dbProgress[$challengeId] ?? 'locked';
                
                $statusPriority = ['locked' => 1, 'unlocked' => 2, 'completed' => 3];
                $localPriority = $statusPriority[$localStatus] ?? 1;
                $dbPriority = $statusPriority[$dbStatus] ?? 1;
                
                if ($localPriority > $dbPriority) {
                    if ($localStatus === 'completed') {
                        $this->completeChallenge($userId, $challengeId);
                    } else {
                        $this->updateChallengeStatus($userId, $challengeId, $localStatus);
                    }
                }
            }
            
            if ($ownTransaction) {
                $this->pdo->commit();
            }
            return true;
            
        } catch (Exception $e) {
            if (isset($ownTransaction) && $ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (DEBUG_MODE) {
                throw new Exception("Error syncing progress: " . $e->getMessage());
            }
            return false;
        }
    }
    
    public function syncLocalEasterEggs($userId, $localEasterEggsData) {
        if (empty($localEasterEggsData) || !is_array($localEasterEggsData)) {
            return false;
        }
        
        try {
            $ownTransaction = !$this->pdo->inTransaction();
            if ($ownTransaction) {
                $this->pdo->beginTransaction();
            }
            
            foreach ($localEasterEggsData as $key => $data) {
                if (isset($data['challengeId']) && isset($data['code'])) {
                    $challengeId = intval($data['challengeId']);
                    $code = trim($data['code']);
                    
                    $challenge = $this->getChallengeById($challengeId);
                    
                    if ($challenge && $challenge['easter_egg'] === $code) {
                        $this->discoverEasterEgg($userId, $challengeId, $code);
                    }
                }
            }
            
            if ($ownTransaction) {
                $this->pdo->commit();
            }
            return true;
            
        } catch (Exception $e) {
            if (isset($ownTransaction) && $ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (DEBUG_MODE) {
                throw new Exception("Error syncing easter eggs: " . $e->getMessage());
            }
            return false;
        }
    }
}

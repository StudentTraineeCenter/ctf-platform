<?php
/**
 * ADMIN PANEL - Shadow Protocol Management Dashboard
 * 
 * Administrátorské rozhraní pro kompletní správu CTF platformy.
 * Přístup pouze pro uživatele s is_admin = 1.
 * 
 * Funkcionality:
 * 
 * 1. Přehledové statistiky:
 *    - Počet uživatelů (celkový, aktivní za 7 dní)
 *    - Počet výzev (celkový, dokončený)
 *    - Průměrné skóre a progress
 *    - Top 10 uživatelů podle bodů
 *    - Nejoblíbenější a nejobtížnější výzvy
 *    - Poslední registrace a aktivity
 * 
 * 2. User Management:
 *    - Zobrazení všech uživatelů s detaily
 *    - Editace uživatelských dat
 *    - Smazání uživatelů
 *    - Udělení/odebrání admin práv
 *    - Reset hesla
 * 
 * 3. Challenge Management (CRUD):
 *    - Přidání nových výzev (s flag hashem)
 *    - Editace existujících výzev
 *    - Smazání výzev (včetně závislostí)
 *    - Nastavení unlock conditions
 *    - Story chapter management
 */

session_start();

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self';");

require_once '../config.php';
require_once '../db.php';
require_once '../csrf.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header('Location: ../index.php');
    exit;
}

$csrfToken = generateCsrfToken();

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

$stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM challenges");
$stmt->execute();
$totalChallenges = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM user_progress WHERE status = :status");
$stmt->execute([':status' => 'completed']);
$completedChallenges = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->prepare("SELECT AVG(total_score) as avg_score FROM users");
$stmt->execute();
$avgScore = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_score'], 2);

$stmt = $db->prepare("SELECT AVG(total_progress) as avg_progress FROM users");
$stmt->execute();
$avgProgress = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_progress'], 2);

$stmt = $db->prepare("
    SELECT username, total_score, total_progress, agent_rank, last_login, is_admin
    FROM users 
    ORDER BY total_score DESC 
    LIMIT 10
");
$stmt->execute();
$topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
    SELECT c.title, c.category, c.difficulty, c.points, COUNT(up.id) as completions
    FROM challenges c
    LEFT JOIN user_progress up ON c.id = up.challenge_id AND up.status = :status
    GROUP BY c.id
    ORDER BY completions DESC
    LIMIT 10
");
$stmt->execute([':status' => 'completed']);
$popularChallenges = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
    SELECT c.title, c.category, c.difficulty, c.points, COUNT(up.id) as completions,
           SUM(up.attempts) as total_attempts
    FROM challenges c
    LEFT JOIN user_progress up ON c.id = up.challenge_id AND up.status = :status
    GROUP BY c.id
    ORDER BY completions ASC, total_attempts DESC
    LIMIT 10
");
$stmt->execute([':status' => 'completed']);
$hardestChallenges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Poslední registrace
$stmt = $db->prepare("
    SELECT id, username, email, created_at, is_admin
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiky jednotlivých challenges
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.title,
        c.category,
        c.difficulty,
        c.points,
        COUNT(DISTINCT up.id) as total_completions,
        CASE 
            WHEN COUNT(DISTINCT up.id) > 0 THEN GREATEST(1, ROUND(AVG(up.attempts)))
            ELSE 0
        END as avg_attempts
    FROM challenges c
    LEFT JOIN user_progress up ON c.id = up.challenge_id AND up.status = :status
    GROUP BY c.id
    ORDER BY c.id ASC
");
$stmt->execute([':status' => 'completed']);
$challengeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiky podle kategorie
$stmt = $db->prepare("
    SELECT 
        c.category,
        COUNT(DISTINCT c.id) as total_challenges,
        COUNT(DISTINCT up.id) as total_completions,
        CASE 
            WHEN COUNT(DISTINCT up.id) > 0 THEN GREATEST(1, ROUND(AVG(up.attempts)))
            ELSE 0
        END as avg_attempts
    FROM challenges c
    LEFT JOIN user_progress up ON c.id = up.challenge_id AND up.status = :status
    GROUP BY c.category
    ORDER BY c.category
");
$stmt->execute([':status' => 'completed']);
$categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiky podle obtížnosti
$stmt = $db->prepare("
    SELECT 
        c.difficulty,
        COUNT(DISTINCT c.id) as total_challenges,
        COUNT(DISTINCT up.id) as total_completions,
        ROUND(AVG(up.attempts)) as avg_attempts
    FROM challenges c
    LEFT JOIN user_progress up ON c.id = up.challenge_id AND up.status = :status
    GROUP BY c.difficulty
    ORDER BY FIELD(c.difficulty, 'easy', 'medium', 'hard', 'expert')
");
$stmt->execute([':status' => 'completed']);
$difficultyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, rgba(255, 0, 85, 0.2), rgba(0, 255, 255, 0.2));
            border: 2px solid var(--color-neon-red);
        }
        
        .admin-badge {
            background: var(--color-neon-red);
            color: var(--color-bg-primary);
            padding: 0.25rem 0.75rem;
            border-radius: var(--border-radius);
            font-weight: 900;
            text-transform: uppercase;
            font-size: 0.75rem;
            animation: pulse-glow 2s ease-in-out infinite;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-card {
            background: var(--color-bg-card);
            border: var(--border-width) solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            transition: all var(--transition-fast);
        }
        
        .stat-card:hover {
            border-color: var(--color-text-accent);
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.2);
            transform: translateY(-3px);
        }
        
        .stat-card-icon {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-sm);
            color: var(--color-neon-cyan);
        }
        
        .stat-card-value {
            font-family: var(--font-heading);
            font-size: 2rem;
            font-weight: 900;
            color: var(--color-text-accent);
            text-shadow: 0 0 10px var(--color-neon-cyan);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-card-label {
            font-size: 0.875rem;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--spacing-md);
        }
        
        .data-table th {
            background: var(--color-bg-tertiary);
            padding: var(--spacing-md);
            text-align: left;
            font-family: var(--font-heading);
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--color-text-accent);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
        }
        
        .data-table td {
            padding: var(--spacing-md);
            border-bottom: var(--border-width) solid var(--border-color);
            color: var(--color-text-secondary);
            font-size: 0.875rem;
        }
        
        .data-table tr:hover {
            background: rgba(0, 255, 255, 0.05);
        }
        
        .badge-rank {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-weight: 700;
            background: rgba(0, 255, 136, 0.2);
            color: var(--color-neon-green);
            border: 1px solid var(--color-neon-green);
        }
        
        .section-divider {
            margin: var(--spacing-2xl) 0;
            border: none;
            border-top: var(--border-width) solid var(--border-color);
        }
        
        .two-column {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }
        
        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Background Effects -->
    <div class="background-grid"></div>
    <div class="scanline"></div>
    
    <div class="container">
        
        <!-- Admin Header -->
        <header class="header admin-header">
            <div class="logo">
                <span class="logo-icon">◈</span>
                <span class="logo-text">ADMIN DASHBOARD</span>
                <span class="admin-badge">⚠ ADMINISTRATOR</span>
            </div>
            
            <nav class="nav" style="flex: 1; justify-content: center;">
                <button class="nav-btn active" data-section="statistics" onclick="switchSection('statistics')">
                    <span>📊</span> STATISTIKY
                </button>
                <button class="nav-btn" data-section="new-challenge" onclick="switchSection('new-challenge')">
                    <span>➕</span> NOVÁ CHALLENGE
                </button>
            </nav>
            
            <div class="user-controls">
                <div class="user-info">
                    <span class="agent-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="../index.php" class="btn-logout">← Zpět na platformu</a>
                </div>
            </div>
        </header>
        
        <main class="main-content">
            
            <!-- SEKCE 1: STATISTIKY -->
            <div class="admin-section active" id="section-statistics">
            
            <!-- Hlavní statistiky -->
            <section>
                <h1 style="font-family: var(--font-heading); color: var(--color-text-accent); margin-bottom: var(--spacing-xl); text-align: center; font-size: 2rem;">
                    📊 CELKOVÝ PŘEHLED PLATFORMY
                </h1>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-icon">👥</div>
                        <div class="stat-card-value"><?php echo $totalUsers; ?></div>
                        <div class="stat-card-label">Celkem uživatelů</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon">✓</div>
                        <div class="stat-card-value"><?php echo $activeUsers; ?></div>
                        <div class="stat-card-label">Aktivní (7 dní)</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon">🎯</div>
                        <div class="stat-card-value"><?php echo $totalChallenges; ?></div>
                        <div class="stat-card-label">Celkem challenges</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon">🏆</div>
                        <div class="stat-card-value"><?php echo $completedChallenges; ?></div>
                        <div class="stat-card-label">Dokončené challenges</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon">📈</div>
                        <div class="stat-card-value"><?php echo $avgScore; ?></div>
                        <div class="stat-card-label">Průměrné skóre</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon">💯</div>
                        <div class="stat-card-value"><?php echo $avgProgress; ?>%</div>
                        <div class="stat-card-label">Průměrný progress</div>
                    </div>
                </div>
            </section>
            
            <hr class="section-divider">
            
            <!-- Top uživatelé -->
            <div class="card" style="margin-bottom: var(--spacing-xl);">
                <div class="card-header">
                    <h2>🏆 TOP 10 UŽIVATELŮ</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Skóre</th>
                            <th>Progress</th>
                            <th>Rank</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($topUsers as $user): ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td>
                                <?php echo htmlspecialchars($user['username']); ?>
                                <?php if ($user['is_admin'] == 1): ?>
                                    <span class="admin-badge" style="margin-left: 8px; font-size: 0.65rem; padding: 2px 6px;">⚠ ADMIN</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--color-neon-cyan); font-weight: 700;"><?php echo $user['total_score']; ?></td>
                            <td><?php echo $user['total_progress']; ?>%</td>
                            <td><span class="badge-rank"><?php echo htmlspecialchars($user['agent_rank']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Statistiky jednotlivých challenges -->
            <div class="card" style="margin-bottom: var(--spacing-xl);">
                <div class="card-header">
                    <h2>🎯 STATISTIKY CHALLENGES</h2>
                </div>
                <div class="card-body">
                    <input type="text" id="searchChallenges" placeholder="🔍 Vyhledat challenge..." onkeyup="filterChallenges()" style="width: 100%; padding: 12px; margin-bottom: 20px; background: var(--color-bg-tertiary); border: 1px solid var(--border-color); border-radius: 4px; color: var(--color-text-primary);">
                    
                    <table class="data-table" id="challengesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Název</th>
                                <th>Kategorie</th>
                                <th>Obtížnost</th>
                                <th>Body</th>
                                <th>Dokončení</th>
                                <th>Ø Pokusů</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($challengeStats as $stat): ?>
                            <tr>
                                <td><?php echo $stat['id']; ?></td>
                                <td style="font-weight: 700;"><?php echo htmlspecialchars($stat['title']); ?></td>
                                <td><span class="badge-category"><?php echo htmlspecialchars($stat['category']); ?></span></td>
                                <td><span class="badge-difficulty <?php echo strtolower($stat['difficulty']); ?>"><?php echo strtoupper($stat['difficulty']); ?></span></td>
                                <td style="color: var(--color-neon-cyan);"><?php echo $stat['points']; ?></td>
                                <td style="color: var(--color-neon-green); font-weight: 700;"><?php echo $stat['total_completions']; ?></td>
                                <td><?php echo $stat['avg_attempts'] > 0 ? $stat['avg_attempts'] : '-'; ?></td>
                                <td>
                                    <button class="btn-action" onclick="editChallenge(<?php echo $stat['id']; ?>)" title="Upravit">✏️</button>
                                    <button class="btn-action btn-delete" onclick="deleteChallenge(<?php echo $stat['id']; ?>)" title="Smazat">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Statistiky podle kategorie -->
            <div class="card" style="margin-bottom: var(--spacing-xl);">
                <div class="card-header">
                    <h2>📂 STATISTIKY PODLE KATEGORIE</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kategorie</th>
                            <th>Počet Challenges</th>
                            <th>Dokončení</th>
                            <th>Ø Pokusů</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryStats as $stat): ?>
                        <tr>
                            <td style="font-weight: 700;"><?php echo htmlspecialchars($stat['category']); ?></td>
                            <td><?php echo $stat['total_challenges']; ?></td>
                            <td style="color: var(--color-neon-green); font-weight: 700;"><?php echo $stat['total_completions']; ?></td>
                            <td><?php echo $stat['avg_attempts'] > 0 ? $stat['avg_attempts'] : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Statistiky podle obtížnosti -->
            <div class="card" style="margin-bottom: var(--spacing-xl);">
                <div class="card-header">
                    <h2>⚡ STATISTIKY PODLE OBTÍŽNOSTI</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Obtížnost</th>
                            <th>Počet Challenges</th>
                            <th>Dokončení</th>
                            <th>Průměrný počet pokusů</th>
                            <th>Success Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($difficultyStats as $stat): 
                            $successRate = $stat['total_challenges'] > 0 ? round(($stat['total_completions'] / ($stat['total_challenges'] * max($totalUsers, 1))) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <span class="badge-difficulty <?php echo strtolower($stat['difficulty']); ?>">
                                    <?php echo strtoupper($stat['difficulty']); ?>
                                </span>
                            </td>
                            <td><?php echo $stat['total_challenges']; ?></td>
                            <td style="color: var(--color-neon-cyan); font-weight: 700;"><?php echo $stat['total_completions']; ?></td>
                            <td><?php echo $stat['avg_attempts'] ? $stat['avg_attempts'] : '0'; ?></td>
                            <td><?php echo $successRate; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <hr class="section-divider">
            
            <!-- Oblíbené a nejobtížnější challenges -->
            <div class="two-column">
                
                <!-- Nejoblíbenější challenges -->
                <div class="card">
                    <div class="card-header">
                        <h2>⭐ NEJOBLÍBENĚJŠÍ CHALLENGES</h2>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Název</th>
                                <th>Kategorie</th>
                                <th>Dokončení</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popularChallenges as $challenge): ?>
                            <tr>
                                <td style="font-weight: 700;"><?php echo htmlspecialchars($challenge['title']); ?></td>
                                <td><span class="badge-category"><?php echo htmlspecialchars($challenge['category']); ?></span></td>
                                <td style="color: var(--color-neon-green); font-weight: 700;"><?php echo $challenge['completions']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Nejobtížnější challenges -->
                <div class="card">
                    <div class="card-header">
                        <h2>🔥 NEJOBTÍŽNĚJŠÍ CHALLENGES</h2>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Název</th>
                                <th>Obtížnost</th>
                                <th>Dokončení</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hardestChallenges as $challenge): ?>
                            <tr>
                                <td style="font-weight: 700;"><?php echo htmlspecialchars($challenge['title']); ?></td>
                                <td><span class="badge-difficulty <?php echo strtolower($challenge['difficulty']); ?>"><?php echo strtoupper($challenge['difficulty']); ?></span></td>
                                <td style="color: var(--color-neon-red); font-weight: 700;"><?php echo $challenge['completions']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            </div>
            
            <hr class="section-divider">
            
            <!-- Poslední registrace -->
            <div class="card">
                <div class="card-header">
                    <h2>👤 POSLEDNÍ REGISTRACE & SPRÁVA UŽIVATELŮ</h2>
                </div>
                <div class="card-body">
                    <input type="text" id="searchUsers" placeholder="🔍 Vyhledat uživatele..." onkeyup="filterUsers()" style="width: 100%; padding: 12px; margin-bottom: 20px; background: var(--color-bg-tertiary); border: 1px solid var(--border-color); border-radius: 4px; color: var(--color-text-primary);">
                    
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Datum registrace</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td style="font-weight: 700;">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['is_admin'] == 1): ?>
                                        <span class="admin-badge" style="margin-left: 8px; font-size: 0.65rem; padding: 2px 6px;">⚠ ADMIN</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td style="color: var(--color-text-muted);"><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['is_admin'] == 0): ?>
                                        <button class="btn-action" onclick="makeAdmin(<?php echo $user['id']; ?>)" title="Udělit admina">⬆️</button>
                                    <?php elseif ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn-action" onclick="removeAdmin(<?php echo $user['id']; ?>)" title="Odebrat admina">⬇️</button>
                                    <?php endif; ?>
                                    <button class="btn-action" onclick="editUser(<?php echo $user['id']; ?>)" title="Upravit">✏️</button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn-action btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Smazat">🗑️</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            </div> <!-- Konec section-statistics -->
            
            <!-- SEKCE 2: NOVÁ CHALLENGE -->
            <div class="admin-section" id="section-new-challenge">
                <h1 style="font-family: var(--font-heading); color: var(--color-text-accent); margin-bottom: var(--spacing-xl); text-align: center; font-size: 2rem;">
                    ➕ PŘIDAT NOVOU CHALLENGE
                </h1>
                
                <div class="card">
                    <div class="card-header">
                        <h2>📝 FORMULÁŘ NOVÉ CHALLENGE</h2>
                    </div>
                    <div class="card-body">
                        <form id="newChallengeForm" onsubmit="addChallenge(event)" style="max-width: 800px; margin: 0 auto;">
                            
                            <div class="form-group">
                                <label for="title">Název *</label>
                                <input type="text" id="title" name="title" required placeholder="např. Cookie Monster">
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Popis (HTML) *</label>
                                <textarea id="description" name="description" rows="6" required placeholder="HTML popis challenge..."></textarea>
                                <small style="color: var(--color-text-muted);">Podporovány HTML tagy - viz tutorial níže</small>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="category">Kategorie *</label>
                                    <select id="category" name="category" required>
                                        <option value="">-- Vyberte --</option>
                                        <option value="Web Basics">Web Basics</option>
                                        <option value="Cryptography">Cryptography</option>
                                        <option value="Forensics">Forensics</option>
                                        <option value="Reverse Engineering">Reverse Engineering</option>
                                        <option value="Network">Network</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="difficulty">Obtížnost *</label>
                                    <select id="difficulty" name="difficulty" required>
                                        <option value="">-- Vyberte --</option>
                                        <option value="easy">Easy</option>
                                        <option value="medium">Medium</option>
                                        <option value="hard">Hard</option>
                                        <option value="expert">Expert</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="points">Body *</label>
                                    <input type="number" id="points" name="points" required min="1" placeholder="např. 25">
                                </div>
                                
                                <div class="form-group">
                                    <label for="story_order">Story Order *</label>
                                    <input type="number" id="story_order" name="story_order" required min="1" placeholder="např. 4">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="flag">Flag *</label>
                                <input type="text" id="flag" name="flag" required placeholder="FLAG{tvuj_flag_zde}">
                                <small style="color: var(--color-text-muted);">Formát: FLAG{...} - bude automaticky zahashován</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="hint_text">Hint</label>
                                <textarea id="hint_text" name="hint_text" rows="2" placeholder="Nápověda pro hráče (nepovinné)"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="story_chapter">Story Chapter</label>
                                <textarea id="story_chapter" name="story_chapter" rows="3" placeholder="Story text který se zobrazí po dokončení (nepovinné)"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="unlock_after_challenge_id">Odemknout po challenge #</label>
                                <input type="number" id="unlock_after_challenge_id" name="unlock_after_challenge_id" min="0" placeholder="ID předchozí challenge (0 = nezávislé)">
                                <small style="color: var(--color-text-muted);">0 nebo prázdné = nezávislé na jiných challenges</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="is_unlocked_default" name="is_unlocked_default">
                                    <span>Odemčeno od začátku</span>
                                </label>
                                <small style="color: var(--color-text-muted); display: block; margin-top: 8px;">Zaškrtni, pokud má být challenge dostupná ihned</small>
                            </div>
                            
                            <button type="submit" class="btn-submit">✅ Vytvořit Challenge</button>
                        </form>
                    </div>
                </div>
                
                <!-- HTML Tutorial -->
                <div class="card" style="margin-top: var(--spacing-xl);">
                    <div class="card-header">
                        <h2>📖 HTML TAGY - TUTORIAL</h2>
                    </div>
                    <div class="card-body">
                        <div style="background: var(--color-bg-tertiary); padding: var(--spacing-lg); border-radius: var(--border-radius); font-family: monospace; font-size: 0.9rem;">
                            <h3 style="color: var(--color-neon-cyan); margin-bottom: var(--spacing-md);">Podporované HTML tagy:</h3>
                            
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;p&gt;</code> - Odstavec textu</p>
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;strong&gt;</code> nebo <code>&lt;b&gt;</code> - Tučný text</p>
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;em&gt;</code> nebo <code>&lt;i&gt;</code> - Kurzíva</p>
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;code&gt;</code> - Inline kód</p>
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;pre&gt;</code> - Předformátovaný text (zachová mezery)</p>
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;a href="url"&gt;</code> - Odkaz</p>
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;br&gt;</code> - Zalomení řádku</p>
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;ul&gt; &lt;li&gt;</code> - Nečíslovaný seznam</p>
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;ol&gt; &lt;li&gt;</code> - Číslovaný seznam</p>
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;h3&gt; až &lt;h6&gt;</code> - Nadpisy</p>
                            <p style="margin-bottom: var(--spacing-sm);"><code>&lt;hr&gt;</code> - Horizontální čára</p>
                            
                            <h3 style="color: var(--color-neon-cyan); margin: var(--spacing-lg) 0 var(--spacing-md);">Příklad:</h3>
                            <pre style="background: var(--color-bg-primary); padding: var(--spacing-md); border-radius: var(--border-radius); overflow-x: auto;">
&lt;p&gt;Tvým úkolem je najít &lt;strong&gt;skrytý flag&lt;/strong&gt; v cookies prohlížeče.&lt;/p&gt;

&lt;p&gt;Hint: Použij &lt;code&gt;document.cookie&lt;/code&gt; v konzoli.&lt;/p&gt;

&lt;p&gt;Kroky:&lt;/p&gt;
&lt;ol&gt;
  &lt;li&gt;Otevři DevTools (F12)&lt;/li&gt;
  &lt;li&gt;Přejdi na záložku Application&lt;/li&gt;
  &lt;li&gt;Najdi Cookies&lt;/li&gt;
&lt;/ol&gt;</pre>
                        </div>
                    </div>
                </div>
                
            </div> <!-- Konec section-new-challenge -->
            
        </main>
        
        <footer class="footer">
            <p>&copy; 2025 SHADOW PROTOCOL - Admin Dashboard</p>
        </footer>
        
    </div>
    
    <!-- Modal pro editaci uživatele -->
    <div id="editUserModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close" onclick="closeEditUserModal()">&times;</span>
            <h2>✏️ Upravit Uživatele</h2>
            <form id="editUserForm" onsubmit="updateUser(event)">
                <input type="hidden" id="edit_user_id" name="user_id">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <button type="submit" class="btn-submit">💾 Uložit</button>
            </form>
        </div>
    </div>
    
    <!-- Modal pro editaci challenge -->
    <div id="editChallengeModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px;">
            <span class="modal-close" onclick="closeEditChallengeModal()">&times;</span>
            <h2>✏️ Upravit Challenge</h2>
            <form id="editChallengeForm" onsubmit="updateChallenge(event)">
                <input type="hidden" id="edit_challenge_id" name="challenge_id">
                
                <div class="form-group">
                    <label>Název *</label>
                    <input type="text" id="edit_title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label>Popis (HTML) *</label>
                    <textarea id="edit_description" name="description" rows="6" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategorie *</label>
                        <select id="edit_category" name="category" required>
                            <option value="Web Basics">Web Basics</option>
                            <option value="Cryptography">Cryptography</option>
                            <option value="Forensics">Forensics</option>
                            <option value="Reverse Engineering">Reverse Engineering</option>
                            <option value="Network">Network</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Obtížnost *</label>
                        <select id="edit_difficulty" name="difficulty" required>
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Body *</label>
                        <input type="number" id="edit_points" name="points" required min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Story Order *</label>
                        <input type="number" id="edit_story_order" name="story_order" required min="1">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Nový Flag (ponech prázdné pro zachování stávajícího)</label>
                    <input type="text" id="edit_flag" name="flag" placeholder="FLAG{...}">
                    <small style="color: var(--color-text-muted);">Vyplň pouze pokud chceš změnit flag</small>
                </div>
                
                <div class="form-group">
                    <label>Hint</label>
                    <textarea id="edit_hint" name="hint_text" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Story Chapter</label>
                    <textarea id="edit_story_chapter" name="story_chapter" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Odemknout po challenge #</label>
                    <input type="number" id="edit_unlock_after_challenge_id" name="unlock_after_challenge_id" min="0">
                </div>
                
                <div class="form-group">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" id="edit_is_unlocked_default" name="is_unlocked_default">
                        <span>Odemčeno od začátku</span>
                    </label>
                </div>
                
                <button type="submit" class="btn-submit">💾 Uložit Změny</button>
            </form>
        </div>
    </div>
    
    <style>
        .admin-section { display: none; }
        .admin-section.active { display: block; }
        
        .btn-action {
            background: var(--color-bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--color-text-primary);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin: 0 2px;
            transition: all 0.2s;
        }
        
        .btn-action:hover {
            background: var(--color-neon-cyan);
            color: var(--color-bg-primary);
            transform: scale(1.1);
        }
        
        .btn-delete:hover {
            background: var(--color-neon-red) !important;
            border-color: var(--color-neon-red) !important;
        }
        
        .form-group {
            margin-bottom: var(--spacing-lg);
        }
        
        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--color-text-accent);
            font-weight: 700;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: var(--spacing-md);
            background: var(--color-bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--color-text-primary);
            font-family: inherit;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--color-neon-cyan);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-lg);
        }
        
        .btn-submit {
            width: 100%;
            padding: var(--spacing-md) var(--spacing-xl);
            background: linear-gradient(135deg, var(--color-neon-cyan), var(--color-neon-green));
            border: none;
            border-radius: var(--border-radius);
            color: var(--color-bg-primary);
            font-weight: 900;
            font-size: 1.1rem;
            cursor: pointer;
            margin-top: var(--spacing-lg);
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 255, 255, 0.4);
        }
        
        .modal {
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--color-bg-card);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--spacing-2xl);
            max-width: 500px;
            width: 90%;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 2rem;
            cursor: pointer;
            color: var(--color-text-secondary);
        }
        
        .modal-close:hover {
            color: var(--color-neon-red);
        }
        
        /* Checkbox styling */
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--color-bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .checkbox-wrapper:hover {
            border-color: var(--color-neon-cyan);
            background: rgba(0, 255, 255, 0.05);
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--color-neon-cyan);
            flex-shrink: 0;
        }
        
        .checkbox-wrapper span {
            color: var(--color-text-primary);
            font-weight: 500;
        }
    </style>
    
    <script>
        // Přepínání sekcí
        function switchSection(sectionName) {
            // Skrýt všechny sekce
            document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
            // Zobrazit vybranou
            document.getElementById('section-' + sectionName).classList.add('active');
            
            // Aktivní tlačítko
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-section="${sectionName}"]`).classList.add('active');
        }
        
        // Vyhledávání v tabulkách
        function filterChallenges() {
            const input = document.getElementById('searchChallenges');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('challengesTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        }
        
        function filterUsers() {
            const input = document.getElementById('searchUsers');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        }
        
        // Challenge operations
        function editChallenge(id) {
            fetch('admin_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_challenge&challenge_id=${id}&csrf_token=${CSRF_TOKEN}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const c = data.challenge;
                    document.getElementById('edit_challenge_id').value = c.id;
                    document.getElementById('edit_title').value = c.title;
                    document.getElementById('edit_description').value = c.description;
                    document.getElementById('edit_category').value = c.category;
                    document.getElementById('edit_difficulty').value = c.difficulty;
                    document.getElementById('edit_points').value = c.points;
                    document.getElementById('edit_story_order').value = c.story_order;
                    document.getElementById('edit_hint').value = c.hint_text || '';
                    document.getElementById('edit_story_chapter').value = c.story_chapter || '';
                    document.getElementById('edit_unlock_after_challenge_id').value = c.unlock_after_challenge_id || '';
                    document.getElementById('edit_is_unlocked_default').checked = c.is_unlocked_default == 1;
                    document.getElementById('edit_flag').value = ''; // Vždy prázdné
                    document.getElementById('editChallengeModal').style.display = 'flex';
                }
            });
        }
        
        function closeEditChallengeModal() {
            document.getElementById('editChallengeModal').style.display = 'none';
        }
        
        function updateChallenge(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'update_challenge');
            formData.append('csrf_token', CSRF_TOKEN);
            
            fetch('admin_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    closeEditChallengeModal();
                    location.reload();
                }
            });
        }
        
        function deleteChallenge(id) {
            if (!confirm('Opravdu smazat tuto challenge? Tato akce je nevratná!')) return;
            
            fetch('admin_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete_challenge&challenge_id=${id}&csrf_token=${CSRF_TOKEN}`
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function addChallenge(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'add_challenge');
            formData.append('csrf_token', CSRF_TOKEN);
            
            fetch('admin_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    form.reset();
                    setTimeout(() => location.reload(), 500);
                }
            });
        }
        
        // User operations
        function makeAdmin(id) {
            if (!confirm('Udělit tomuto uživateli admin práva?')) return;
            
            fetch('admin_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=make_admin&user_id=${id}&csrf_token=${CSRF_TOKEN}`
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function removeAdmin(id) {
            if (!confirm('Odebrat admin práva tomuto uživateli?')) return;
            
            fetch('admin_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=remove_admin&user_id=${id}&csrf_token=${CSRF_TOKEN}`
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function deleteUser(id) {
            if (!confirm('Opravdu smazat tohoto uživatele? Tato akce je nevratná!')) return;
            
            fetch('admin_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete_user&user_id=${id}&csrf_token=${CSRF_TOKEN}`
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function editUser(id) {
            fetch('admin_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_user&user_id=${id}&csrf_token=${CSRF_TOKEN}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_user_id').value = data.user.id;
                    document.getElementById('edit_username').value = data.user.username;
                    document.getElementById('edit_email').value = data.user.email;
                    document.getElementById('editUserModal').style.display = 'flex';
                }
            });
        }
        
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }
        
        function updateUser(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'update_user');
            formData.append('csrf_token', CSRF_TOKEN);
            
            fetch('admin_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    closeEditUserModal();
                    location.reload();
                }
            });
        }
    </script>
    <script>
        // CSRF token pro všechny AJAX requesty
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
    </script>
</body>
</html>

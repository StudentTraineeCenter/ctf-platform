<?php
/**
 * SHADOW PROTOCOL - Main Application Entry Point
 * 
 * Hlavní vstupní bod platformy. Generuje kompletní HTML strukturu SPA (Single Page Application)
 * s třemi hlavními sekcemi: Dashboard, Mise, Agent Log. Inicializuje session, generuje CSRF token,
 * nastavuje bezpečnostní hlavičky a připravuje prostředí pro JavaScript aplikaci.
 * 
 * Funkce:
 * - Ověření admin oprávnění (zobrazení admin tlačítka)
 * - Generování CSRF tokenu pro všechny POST requesty
 * - Nastavení XSS a CSP bezpečnostních hlaviček
 * - HTML struktura pro autentizační a challenge modály
 * - Předání PHP proměnných do JavaScriptu (TOTAL_MISSIONS, CSRF_TOKEN)
 */

session_start();

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';");

require_once 'config.php';
require_once 'db.php';
require_once 'csrf.php';

$db = Database::getInstance();
$totalMissions = $db->getTotalChallengesCount();
$csrfToken = generateCsrfToken();
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 1;

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Kybernetická Výcviková Platforma</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
</head>
<body>
    <div class="background-grid"></div>
    <div class="scanline"></div>
    
    <div class="container">
        <header class="header">
            <div class="logo">
                <span class="logo-icon">◈</span>
                <span class="logo-text">SHADOW PROTOCOL</span>
                <span class="version">v<?php echo APP_VERSION; ?></span>
            </div>
            
            <nav class="nav" id="mainNav">
                <button class="nav-btn" data-section="dashboard" id="btnDashboard">
                    <span>⬢</span> DASHBOARD
                </button>
                <button class="nav-btn" data-section="missions" id="btnMissions">
                    <span>▣</span> MISE
                </button>
                <button class="nav-btn" data-section="logs" id="btnLogs">
                    <span>◫</span> AGENT LOG
                </button>
                <a href="admin/index.php" class="nav-btn admin-btn hidden" id="btnAdmin" style="background: linear-gradient(135deg, #DC143C, #8B0000); border-color: #DC143C;">
                    <span>⚙</span> ADMIN
                </a>
            </nav>
            
            <div class="user-controls" id="userControls">
                <div class="user-info hidden" id="userInfo">
                    <span class="agent-rank" id="agentRank">RECRUIT</span>
                    <span class="agent-name" id="agentName">AGENT_###</span>
                    <button class="btn-logout" id="btnLogout">⊗ LOGOUT</button>
                </div>
                <button class="btn-auth" id="btnShowAuth">⊕ LOGIN / REGISTER</button>
            </div>
        </header>
        
        <main class="main-content">
            <section class="section active" id="sectionDashboard">
                <div class="dashboard">
                    <div class="card agent-card">
                        <div class="card-header">
                            <h2>◈ AGENT STATUS</h2>
                            <div class="status-indicator">
                                <span class="pulse"></span>
                                <span>ACTIVE</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="stat-grid">
                                <div class="stat-item">
                                    <span class="stat-label">AGENT ID</span>
                                    <span class="stat-value" id="statAgentId">GUEST_###</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">RANK</span>
                                    <span class="stat-value" id="statRank">RECRUIT</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">TOTAL SCORE</span>
                                    <span class="stat-value" id="statScore">0</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">CURRENT LEVEL</span>
                                    <span class="stat-value" id="statLevel">1</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">COMPLETED</span>
                                    <span class="stat-value" id="statCompleted">0 / <?php echo $totalMissions; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">PROGRESS</span>
                                    <span class="stat-value" id="statProgress">0%</span>
                                </div>
                            </div>
                            
                            <div class="progress-bar-container">
                                <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card log-preview-card">
                        <div class="card-header">
                            <h2>◫ LATEST TRANSMISSION</h2>
                        </div>
                        <div class="card-body">
                            <div class="log-entry" id="latestLog">
                                <p>Systém iniciuje spojení s NEXUS AI...</p>
                                <p><em>Čeká se na první misi.</em></p>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </section>
            
            <section class="section" id="sectionMissions">
                <div class="section-header">
                    <h1>▣ AKTIVNÍ MISE</h1>
                    <p class="section-subtitle">Postupně odemykej výzvy a dokončuj mise pro získání přístupu k dalším úrovním</p>
                </div>
                
                <div class="challenges-grid" id="challengesGrid">
                    <div class="loading">Načítání misí...</div>
                </div>
            </section>
            
            <section class="section" id="sectionLogs">
                <div class="section-header">
                    <h1>◫ AGENT LOG</h1>
                    <p class="section-subtitle">Záznamy o tvých operacích a odhalených informacích</p>
                </div>
                
                <div class="logs-container" id="logsContainer">
                    <div class="log-entry">
                        <p><strong>SYSTÉM INICIALIZOVÁN</strong></p>
                        <p>Vítej v programu SHADOW PROTOCOL. Tvým úkolem je prokázat své dovednosti v oblasti kyberbezpečnosti.</p>
                        <p>Každá dokončená mise odhalí další část příběhu.</p>
                    </div>
                </div>
            </section>
            
        </main>
        
        <footer class="footer">
            <p>&copy; 2025 SHADOW PROTOCOL</p>
        </footer>
        
    </div>
    
    <div class="modal hidden" id="authModal">
        <div class="modal-content">
            <button class="modal-close" id="btnCloseAuth">×</button>
            
            <div class="modal-header">
                <h2>◈ AUTENTIZACE</h2>
                <p>Přihlásit se pro uložení progresu</p>
            </div>
            
            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="login">PŘIHLÁŠENÍ</button>
                <button class="auth-tab" data-tab="register">REGISTRACE</button>
            </div>
            
            <form id="loginForm" class="auth-form active">
                <div class="form-group">
                    <label>USERNAME</label>
                    <input type="text" name="username" required placeholder="agent_id">
                </div>
                <div class="form-group">
                    <label>PASSWORD</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <div class="form-message" id="loginMessage"></div>
                <button type="submit" class="btn-submit">⊕ PŘIHLÁSIT SE</button>
            </form>
            
            <form id="registerForm" class="auth-form">
                <div class="form-group">
                    <label>USERNAME</label>
                    <input type="text" name="username" required placeholder="agent_id" minlength="3">
                </div>
                <div class="form-group">
                    <label>EMAIL</label>
                    <input type="email" name="email" required placeholder="agent@shadow.net">
                </div>
                <div class="form-group">
                    <label>PASSWORD</label>
                    <input type="password" name="password" required placeholder="••••••••" minlength="6">
                </div>
                <div class="form-group">
                    <label>CONFIRM PASSWORD</label>
                    <input type="password" name="confirm_password" required placeholder="••••••••">
                </div>
                <div class="form-message" id="registerMessage"></div>
                <button type="submit" class="btn-submit">⊕ REGISTROVAT</button>
            </form>
        </div>
    </div>
    
    <div class="modal hidden" id="challengeModal">
        <div class="modal-content modal-large">
            <button class="modal-close" id="btnCloseChallenge">×</button>
            
            <div class="challenge-detail" id="challengeDetail">
            </div>
        </div>
    </div>
    
    <div class="modal hidden" id="successModal">
        <div class="modal-content modal-success">
            <div class="success-animation">
                <div class="success-icon">✓</div>
            </div>
            <h2 id="successTitle">MISE DOKONČENA!</h2>
            <div id="successMessage" class="success-message"></div>
            <div id="successStory" class="story-chapter"></div>
            <button class="btn-submit" id="btnCloseSuccess">POKRAČOVAT</button>
        </div>
    </div>
    
    <script>
        const TOTAL_MISSIONS = <?php echo $totalMissions; ?>;
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
    </script>
    <script src="scripts.js"></script>
</body>
</html>

/**
 * Frontend Application Logic
 * 
 * Vanilla JavaScript SPA (Single Page Application) řídící celou frontend logiku platformy.
 * Implementuje offline-first přístup s LocalStorage a automatickou synchronizací po registraci.
 * 
 * Hlavní komponenty:
 * 
 * 1. AppState - Globální stav aplikace (user, challenges, logs, isAuthenticated)
 * 
 * 2. LocalStorage Management - Offline režim
 *    - Ukládání progresu do LocalStorage pro nepřihlášené uživatele
 *    - Guest ID persistence přes cookies + localStorage
 *    - Auto-synchronizace s DB při registraci/přihlášení
 * 
 * 3. API Communication - AJAX komunikace se serverem
 *    - Automatické přidávání CSRF tokenu ke všem POST requestům
 *    - JSON response handling
 *    - Actions: register, login, logout, getChallenges, submitFlag, getStats, getLogs
 * 
 * 4. UI Management
 *    - Přepínání sekcí (dashboard, missions, logs)
 *    - Modal systém (auth, challenge detail, success)
 *    - Dynamic content rendering (challenges, logs, stats)
 * 
 * 5. Challenge System
 *    - Progresivní odemykání výzev
 *    - Flag validace a submity
 *    - LocalStorage + DB synchronizace stavů (completed > unlocked > locked)
 * 
 * 6. Authentication
 *    - Login/Register formuláře s validací
 *    - Session management
 *    - Automatický logout s page reload (nový CSRF token)
 * 
 */

const AppState = {
    user: null,
    challenges: [],
    logs: [],
    isAuthenticated: false,
    currentSection: 'dashboard'
};

const Cookies = {
    set(name, value, days = 365) {
        const d = new Date();
        d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
        const expires = 'expires=' + d.toUTCString();
        document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; ${expires}; path=/`;
    },
    get(name) {
        const key = encodeURIComponent(name) + '=';
        const ca = document.cookie.split(';');
        for (let c of ca) {
            while (c.charAt(0) === ' ') c = c.substring(1);
            if (c.indexOf(key) === 0) return decodeURIComponent(c.substring(key.length, c.length));
        }
        return null;
    },
    remove(name) {
        document.cookie = `${encodeURIComponent(name)}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
    }
};

function getGuestId() {
    let id = Cookies.get('ctf_guest_id');
    if (!id) {
        try {
            id = localStorage.getItem('ctf_guest_id');
        } catch (e) {}
    }
    // 3) Generate if missing, store in both cookie and localStorage
    if (!id) {
        const num = Math.floor(Math.random() * 1000);
        id = String(num).padStart(3, '0');
        Cookies.set('ctf_guest_id', id, 365);
        try {
            localStorage.setItem('ctf_guest_id', id);
        } catch (e) {}
    }
    return `GUEST_${id}`;
}

const LocalStorage = {
    KEYS: {
        PROGRESS: 'ctf_progress',
        COMPLETED: 'ctf_completed',
        UNLOCKED: 'ctf_unlocked',
        STATS: 'ctf_stats',
        EASTER_EGGS: 'ctf_easter_eggs'
    },
    
    getProgress() {
        try {
            const data = localStorage.getItem(this.KEYS.PROGRESS);
            return data ? JSON.parse(data) : {};
        } catch (e) {
            console.error('Error reading localStorage:', e);
            return {};
        }
    },
    
    saveProgress(challengeId, data) {
        try {
            const progress = this.getProgress();
            progress[challengeId] = {
                ...progress[challengeId],
                ...data,
                timestamp: Date.now()
            };
            localStorage.setItem(this.KEYS.PROGRESS, JSON.stringify(progress));
        } catch (e) {
            console.error('Error saving to localStorage:', e);
        }
    },
    
    markCompleted(challengeId, points = 0) {
        this.saveProgress(challengeId, {
            completed: true,
            completedAt: new Date().toISOString(),
            points: points
        });
    },
    
    unlockChallenge(challengeId) {
        this.saveProgress(challengeId, {
            unlocked: true,
            unlockedAt: new Date().toISOString()
        });
    },
    
    getStats() {
        const progress = this.getProgress();
        let totalScore = 0;
        let completed = 0;
        
        Object.values(progress).forEach(p => {
            if (p.completed) {
                completed++;
                totalScore += p.points || 0;
            }
        });
        
        return { completed, totalScore };
    },
    
    getEasterEggs() {
        try {
            const data = localStorage.getItem(this.KEYS.EASTER_EGGS);
            return data ? JSON.parse(data) : {};
        } catch (e) {
            console.error('Error reading easter eggs:', e);
            return {};
        }
    },
    
    saveEasterEgg(challengeId, code) {
        try {
            const easterEggs = this.getEasterEggs();
            const key = `${challengeId}_${code}`;
            if (!easterEggs[key]) {
                easterEggs[key] = {
                    challengeId,
                    code,
                    discoveredAt: new Date().toISOString()
                };
                localStorage.setItem(this.KEYS.EASTER_EGGS, JSON.stringify(easterEggs));
                return true;
            }
            return false;
        } catch (e) {
            console.error('Error saving easter egg:', e);
            return false;
        }
    },
    
    clear() {
        Object.values(this.KEYS).forEach(key => {
            localStorage.removeItem(key);
        });
    }
};

const API = {
    BASE_URL: 'api.php',
    
    async post(action, data = {}) {
        try {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('csrf_token', CSRF_TOKEN);
            
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });
            
            const response = await fetch(this.BASE_URL, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            return result;
            
        } catch (error) {
            console.error('API Error:', error);
            return { success: false, message: 'Chyba komunikace se serverem' };
        }
    },
    
    async register(username, email, password, confirmPassword) {
        const localProgress = JSON.stringify(LocalStorage.getProgress());
        const localEasterEggs = JSON.stringify(LocalStorage.getEasterEggs());
        return this.post('register', {
            username,
            email,
            password,
            confirm_password: confirmPassword,
            local_progress: localProgress,
            local_easter_eggs: localEasterEggs
        });
    },
    
    async login(username, password) {
        const localProgress = JSON.stringify(LocalStorage.getProgress());
        const localEasterEggs = JSON.stringify(LocalStorage.getEasterEggs());
        return this.post('login', {
            username,
            password,
            local_progress: localProgress,
            local_easter_eggs: localEasterEggs
        });
    },
    
    async logout() {
        return this.post('logout');
    },
    
    async getChallenges() {
        return this.post('get_challenges');
    },
    
    async submitFlag(challengeId, flag) {
        return this.post('submit_flag', {
            challenge_id: challengeId,
            flag: flag
        });
    },
    
    async getStats() {
        return this.post('get_stats');
    },
    
    async getLogs() {
        return this.post('get_logs');
    },
    
    async discoverEasterEgg(challengeId, code) {
        return this.post('discover_easter_egg', {
            challenge_id: challengeId,
            code: code
        });
    },
    
    async checkSession() {
        return this.post('check_session');
    }
};

const UI = {
    showSection(sectionName) {
        document.querySelectorAll('.section').forEach(section => {
            section.classList.remove('active');
        });
        
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const section = document.getElementById(`section${sectionName.charAt(0).toUpperCase() + sectionName.slice(1)}`);
        if (section) {
            section.classList.add('active');
        }
        
        const btn = document.querySelector(`[data-section="${sectionName}"]`);
        if (btn) {
            btn.classList.add('active');
        }
        
        AppState.currentSection = sectionName;
        
        if (sectionName === 'missions') {
            loadChallenges();
        } else if (sectionName === 'logs') {
            loadLogs();
        } else if (sectionName === 'dashboard') {
            updateDashboard();
        }
    },
    
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
        }
    },
    
    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
        }
    },
    
    showFormMessage(formId, message, isError = true) {
        const messageEl = document.getElementById(`${formId}Message`);
        if (messageEl) {
            messageEl.textContent = message;
            messageEl.classList.remove('error', 'success');
            messageEl.classList.add(isError ? 'error' : 'success');
            messageEl.style.display = 'block';
        }
    },
    
    updateUserInterface() {
        const userInfo = document.getElementById('userInfo');
        const btnShowAuth = document.getElementById('btnShowAuth');
        const btnAdmin = document.getElementById('btnAdmin');
        
        if (AppState.isAuthenticated && AppState.user) {
            userInfo.classList.remove('hidden');
            btnShowAuth.classList.add('hidden');
            
            document.getElementById('agentName').textContent = AppState.user.username;
            document.getElementById('agentRank').textContent = AppState.user.agent_rank || 'RECRUIT';
            
            // Zobrazit/skrýt admin tlačítko podle is_admin
            if (AppState.user.is_admin == 1) {
                btnAdmin.classList.remove('hidden');
            } else {
                btnAdmin.classList.add('hidden');
            }
        } else {
            userInfo.classList.add('hidden');
            btnShowAuth.classList.remove('hidden');
            btnAdmin.classList.add('hidden'); // Skrýt admin tlačítko pro nepřihlášené
        }
    },
    
    // Aktualizace statistik na dashboardu
    updateStats(stats) {
        if (!stats) return;
        
        const user = AppState.user;
        const localStats = LocalStorage.getStats();
        
        // Pro přihlášené uživatele používat serverová data
        if (AppState.isAuthenticated && user) {
            document.getElementById('statAgentId').textContent = user.username;
            document.getElementById('statRank').textContent = user.agent_rank || 'RECRUIT';
            document.getElementById('statScore').textContent = user.total_score || 0;
            document.getElementById('statLevel').textContent = user.current_level || 1;
            document.getElementById('statCompleted').textContent = `${stats.completed_challenges || 0} / ${stats.total_challenges || (typeof TOTAL_MISSIONS !== 'undefined' ? TOTAL_MISSIONS : 35)}`;
            
            const progress = stats.total_challenges > 0 
                ? Math.round((stats.completed_challenges / stats.total_challenges) * 100) 
                : 0;
            document.getElementById('statProgress').textContent = `${progress}%`;
            document.getElementById('progressBar').style.width = `${progress}%`;
        } else {
            // Pro nepřihlášené - lokální data
            const totalMissions = typeof TOTAL_MISSIONS !== 'undefined' ? TOTAL_MISSIONS : 35;
            document.getElementById('statAgentId').textContent = getGuestId();
            document.getElementById('statRank').textContent = 'RECRUIT';
            document.getElementById('statScore').textContent = localStats.totalScore;
            document.getElementById('statLevel').textContent = localStats.completed + 1;
            document.getElementById('statCompleted').textContent = `${localStats.completed} / ${totalMissions}`;
            
            const progress = Math.round((localStats.completed / totalMissions) * 100);
            document.getElementById('statProgress').textContent = `${progress}%`;
            document.getElementById('progressBar').style.width = `${progress}%`;
        }
    }
};

async function loadChallenges() {
    const container = document.getElementById('challengesGrid');
    container.innerHTML = '<div class="loading">Načítání misí...</div>';
    
    const response = await API.getChallenges();
    
    if (response.success) {
        AppState.challenges = response.data.challenges;
        renderChallenges(response.data.challenges);
    } else {
        container.innerHTML = '<div class="loading">Chyba při načítání misí</div>';
    }
}

function renderChallenges(challenges) {
    const container = document.getElementById('challengesGrid');
    
    if (!challenges || challenges.length === 0) {
        container.innerHTML = '<div class="loading">Žádné mise k dispozici</div>';
        return;
    }
    
    const localProgress = LocalStorage.getProgress();
    
    container.innerHTML = challenges.map(challenge => {
        let status = challenge.user_status || 'locked';
        
        if (!AppState.isAuthenticated) {
            if (localProgress[challenge.id]?.completed) {
                status = 'completed';
            } else if (challenge.is_unlocked_default || localProgress[challenge.id]?.unlocked) {
                status = 'unlocked';
            }
        }
        
        const statusIcon = '';

    const statusText = (status === 'completed') ? 'COMPLETED' : 'IN PROGRESS';
        
        return `
            <div class="challenge-card ${status}" data-challenge-id="${challenge.id}" data-status="${status}">
                <div class="challenge-header">
                    <div>
                        <h3 class="challenge-title">${challenge.title}</h3>
                        <div class="challenge-meta">
                            <span class="challenge-badge badge-category">${challenge.category}</span>
                            <span class="challenge-badge badge-difficulty ${challenge.difficulty}">${challenge.difficulty}</span>
                            <span class="challenge-badge badge-points">+${challenge.points} PTS</span>
                        </div>
                    </div>
                    <div class="challenge-order">LVL ${challenge.story_order}</div>
                </div>
                <div class="challenge-status ${status}">
                    <span class="status-icon"></span>
                    <span>${statusText}</span>
                </div>
            </div>
        `;
    }).join('');
    
    document.querySelectorAll('.challenge-card').forEach(card => {
        card.addEventListener('click', () => {
            const challengeId = parseInt(card.dataset.challengeId);
            const status = card.dataset.status;
            
            if (status !== 'locked') {
                showChallengeDetail(challengeId);
            }
        });
    });
}

function showChallengeDetail(challengeId) {
    const challenge = AppState.challenges.find(c => c.id === challengeId);
    if (!challenge) return;
    
    const detailContainer = document.getElementById('challengeDetail');
    
    detailContainer.innerHTML = `
        <div class="challenge-meta">
            <span class="challenge-badge badge-category">${challenge.category}</span>
            <span class="challenge-badge badge-difficulty ${challenge.difficulty}">${challenge.difficulty}</span>
            <span class="challenge-badge badge-points">+${challenge.points} PTS</span>
        </div>
        
        <h2 style="font-family: 'Orbitron', sans-serif; color: var(--color-text-accent); margin: 1rem 0;">
            ${challenge.title}
        </h2>
        
        <div style="color: var(--color-text-secondary); line-height: 1.8;">
            ${challenge.description}
        </div>
        
        ${challenge.hint_text ? `
            <div style="background: var(--color-bg-tertiary); border-left: 4px solid var(--color-neon-yellow); padding: 1rem; margin: 1.5rem 0; border-radius: 4px;">
                <strong style="color: var(--color-neon-yellow);">💡 HINT:</strong><br>
                ${challenge.hint_text}
            </div>
        ` : ''}
        
        ${challenge.tutorial_content ? `
            <details style="margin: 1.5rem 0;">
                <summary style="cursor: pointer; color: var(--color-neon-blue); font-weight: bold;">
                    📚 TUTORIAL
                </summary>
                <div style="margin-top: 1rem; padding: 1rem; background: var(--color-bg-tertiary); border-radius: 4px;">
                    ${challenge.tutorial_content}
                </div>
            </details>
        ` : ''}
        
        <div class="flag-submit">
            <h3 style="font-family: 'Orbitron', sans-serif; color: var(--color-text-accent); margin-bottom: 1rem;">
                SUBMIT FLAG / EASTER EGG
            </h3>
            <p style="color: var(--color-text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">
                Zadej flag nebo skrytý easter egg kód (🥚 +50 bonusových bodů)
            </p>
            <div class="flag-input-group">
                <input type="text" id="flagInput" class="flag-input" placeholder="FLAG{...} nebo EASTER_EGG_CODE" autocomplete="off">
                <button class="btn-submit-flag" id="btnSubmitFlag">SUBMIT</button>
            </div>
            <div id="flagMessage" style="margin-top: 1rem; display: none;"></div>
        </div>
    `;
    
    document.getElementById('btnSubmitFlag').addEventListener('click', () => {
        submitFlag(challengeId);
    });
    
    document.getElementById('flagInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            submitFlag(challengeId);
        }
    });
    
    UI.showModal('challengeModal');
}

async function submitFlag(challengeId) {
    const flagInput = document.getElementById('flagInput');
    const flag = flagInput.value.trim();
    const messageEl = document.getElementById('flagMessage');
    
    if (!flag) {
        messageEl.textContent = 'Zadej vlajku!';
        messageEl.style.color = 'var(--color-error)';
        messageEl.style.display = 'block';
        return;
    }
    
    messageEl.textContent = 'Ověřování...';
    messageEl.style.color = 'var(--color-text-secondary)';
    messageEl.style.display = 'block';
    
    const response = await API.submitFlag(challengeId, flag);
    
    if (response.success) {
        if (!AppState.isAuthenticated) {
            LocalStorage.markCompleted(challengeId, response.data.points);
            
            const challenge = AppState.challenges.find(c => c.id === challengeId);
            if (challenge) {
                const nextChallenge = AppState.challenges.find(c => 
                    c.story_order === challenge.story_order + 1
                );
                if (nextChallenge) {
                    LocalStorage.unlockChallenge(nextChallenge.id);
                }
            }
        }
        
        if (response.data.user) {
            AppState.user = response.data.user;
        }
        
        UI.hideModal('challengeModal');
        
        showSuccessModal(response.data);
        
        loadChallenges();
        updateDashboard();
        
    } else {
        messageEl.textContent = 'Testování easter egg...';
        messageEl.style.color = 'var(--color-text-secondary)';
        
        if (AppState.isAuthenticated) {
            const easterEggResponse = await API.discoverEasterEgg(challengeId, flag);
            
            if (easterEggResponse.success) {
                messageEl.textContent = '🥚 Easter Egg objeven! +50 bonusových bodů!';
                messageEl.style.color = '#ffd700';
                flagInput.value = '';
                
                setTimeout(() => {
                    loadChallenges();
                    updateDashboard();
                    messageEl.style.display = 'none';
                }, 2000);
            } else {
                messageEl.textContent = 'Nesprávná vlajka ani easter egg!';
                messageEl.style.color = 'var(--color-error)';
                flagInput.value = '';
                flagInput.focus();
            }
        } else {
            const challenge = AppState.challenges.find(c => c.id === challengeId);
            if (challenge && challenge.easter_egg === flag) {
                const saved = LocalStorage.saveEasterEgg(challengeId, flag);
                if (saved) {
                    messageEl.textContent = '🥚 Easter Egg objeven! +50 bodů! (Přihlaš se pro trvalé uložení)';
                    messageEl.style.color = '#ffd700';
                    flagInput.value = '';
                    
                    setTimeout(() => {
                        messageEl.style.display = 'none';
                    }, 3000);
                } else {
                    messageEl.textContent = 'Tento easter egg už jsi našel!';
                    messageEl.style.color = 'var(--color-warning)';
                    flagInput.value = '';
                }
            } else {
                messageEl.textContent = 'Nesprávná vlajka ani easter egg!';
                messageEl.style.color = 'var(--color-error)';
                flagInput.value = '';
                flagInput.focus();
            }
        }
    }
}

function showSuccessModal(data) {
    document.getElementById('successTitle').textContent = 'MISE DOKONČENA!';
    document.getElementById('successMessage').textContent = `Získal jsi +${data.points} bodů!`;
    
    if (data.story_chapter) {
        document.getElementById('successStory').innerHTML = data.story_chapter;
    } else {
        document.getElementById('successStory').innerHTML = '';
    }
    
    UI.showModal('successModal');
}

async function updateDashboard() {
    if (AppState.isAuthenticated) {
        const response = await API.getStats();
        if (response.success) {
            UI.updateStats(response.data.stats);
            updateLatestLog(response.data.logs);
        }
    } else {
        const stats = {
            completed_challenges: LocalStorage.getStats().completed,
            total_challenges: 35
        };
        UI.updateStats(stats);
        updateLatestLogLocal();
    }
}

function updateLatestLogLocal() {
    const container = document.getElementById('latestLog');
    const localProgress = LocalStorage.getProgress();
    
    let latestChallenge = null;
    let latestTimestamp = 0;
    
    AppState.challenges.forEach(challenge => {
        const progress = localProgress[challenge.id];
        if (progress && progress.completed && progress.timestamp > latestTimestamp) {
            latestTimestamp = progress.timestamp;
            latestChallenge = challenge;
        }
    });
    
    if (latestChallenge && latestChallenge.story_chapter) {
        container.innerHTML = `
            <p><strong>${latestChallenge.title}</strong></p>
            ${latestChallenge.story_chapter}
            <p class="log-timestamp">${new Date(latestTimestamp).toLocaleString('cs-CZ')}</p>
        `;
    } else {
        container.innerHTML = `
            <p>Systém iniciuje spojení s NEXUS AI...</p>
            <p><em>Čeká se na první misi.</em></p>
        `;
    }
}

function updateLatestLog(logs) {
    const container = document.getElementById('latestLog');
    
    if (logs && logs.length > 0) {
        const latest = logs[0];
        container.innerHTML = `
            <p><strong>${latest.challenge_title}</strong></p>
            ${latest.log_entry}
            <p class="log-timestamp">${new Date(latest.log_timestamp).toLocaleString('cs-CZ')}</p>
        `;
    }
}

async function loadLogs() {
    const container = document.getElementById('logsContainer');
    
    if (!AppState.isAuthenticated) {
        const localProgress = LocalStorage.getProgress();
        const completedLogs = [];
        
        AppState.challenges.forEach(challenge => {
            const progress = localProgress[challenge.id];
            if (progress && progress.completed && challenge.story_chapter) {
                completedLogs.push({
                    title: challenge.title,
                    story: challenge.story_chapter,
                    timestamp: progress.timestamp,
                    order: challenge.story_order
                });
            }
        });
        

        completedLogs.sort((a, b) => a.order - b.order);
        
        if (completedLogs.length === 0) {
            container.innerHTML = `
                <div class="log-entry">
                    <p><strong>SYSTÉM INICIALIZOVÁN</strong></p>
                    <p>Vítej v programu SHADOW PROTOCOL. Tvým úkolem je prokázat své dovednosti v oblasti kyberbezpečnosti.</p>
                    <p>Každá dokončená mise odhalí další část příběhu.</p>
                </div>
            `;
        } else {
            container.innerHTML = completedLogs.map(log => `
                <div class="log-entry">
                    <p><strong>${log.title}</strong></p>
                    ${log.story}
                    <p class="log-timestamp">${new Date(log.timestamp).toLocaleString('cs-CZ')}</p>
                </div>
            `).join('');
        }
        return;
    }
    
    const response = await API.getLogs();
    
    if (response.success && response.data.logs) {
        const logs = response.data.logs;
        
        if (logs.length === 0) {
            container.innerHTML = `
                <div class="log-entry">
                    <p><strong>SYSTÉM INICIALIZOVÁN</strong></p>
                    <p>Zatím žádné záznamy. Dokonči první misi pro odemčení prvního logu.</p>
                </div>
            `;
        } else {
            container.innerHTML = logs.map(log => `
                <div class="log-entry">
                    <p><strong>${log.challenge_title}</strong></p>
                    ${log.log_entry}
                    <p class="log-timestamp">${new Date(log.log_timestamp).toLocaleString('cs-CZ')}</p>
                </div>
            `).join('');
        }
    }
}

function setupAuthForms() {
    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.dataset.tab;
            
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
            
            tab.classList.add('active');
            document.getElementById(`${targetTab}Form`).classList.add('active');
        });
    });
    
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const username = formData.get('username');
        const password = formData.get('password');
        
        const response = await API.login(username, password);
        
        if (response.success) {
            AppState.user = response.data.user;
            AppState.isAuthenticated = true;
            
            UI.updateUserInterface();
            UI.hideModal('authModal');
            
            LocalStorage.clear();
            
            await loadChallenges();
            await updateDashboard();
            
        } else {
            UI.showFormMessage('login', response.message, true);
        }
    });
    
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const username = formData.get('username');
        const email = formData.get('email');
        const password = formData.get('password');
        const confirmPassword = formData.get('confirm_password');
        
        const response = await API.register(username, email, password, confirmPassword);
        
        if (response.success) {
            AppState.user = response.data.user;
            AppState.isAuthenticated = true;
            
            UI.updateUserInterface();
            UI.hideModal('authModal');
            
            LocalStorage.clear();
            
            await loadChallenges();
            await updateDashboard();
            
        } else {
            UI.showFormMessage('register', response.message, true);
        }
    });
}

function setupEventListeners() {
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const section = btn.dataset.section;
            UI.showSection(section);
        });
    });
    
    document.getElementById('btnShowAuth').addEventListener('click', () => {
        UI.showModal('authModal');
    });
    
    document.getElementById('btnCloseAuth').addEventListener('click', () => {
        UI.hideModal('authModal');
    });
    
    document.getElementById('btnCloseChallenge').addEventListener('click', () => {
        UI.hideModal('challengeModal');
    });
    
    document.getElementById('btnCloseSuccess').addEventListener('click', () => {
        UI.hideModal('successModal');
    });
    
    document.getElementById('btnLogout').addEventListener('click', async () => {
        await API.logout();
        
        AppState.user = null;
        AppState.isAuthenticated = false;
        AppState.challenges = [];
        AppState.logs = [];
        

        window.location.href = 'index.php';
    });
    
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    });
}

async function init() {
    console.log('🚀 Shadow Protocol CTF Platform - Initializing...');
    
    console.log('Setting ch1_4_admin cookie...');
    const existingCookie = Cookies.get('ch1_4_admin');
    console.log('Existing ch1_4_admin cookie:', existingCookie);
    
    if (!existingCookie) {
        Cookies.set('ch1_4_admin', 'false', 365);
        console.log('Cookie ch1_4_admin set to: false');
    } else {
        console.log('Cookie ch1_4_admin already exists with value:', existingCookie);
    }
    
    const verifyCheck = Cookies.get('ch1_4_admin');
    console.log('Verification check - ch1_4_admin:', verifyCheck);
    console.log('All cookies:', document.cookie);
    
    setupEventListeners();
    setupAuthForms();
    
    const sessionCheck = await API.checkSession();
    if (sessionCheck.success) {
        AppState.user = sessionCheck.data.user;
        AppState.isAuthenticated = true;
    }
    
    UI.updateUserInterface();
    
    await loadChallenges();
    await updateDashboard();
    
    UI.showSection('dashboard');
    
    console.log('✅ Platform initialized successfully');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

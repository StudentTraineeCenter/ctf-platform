-- =============================================
-- CTF Platform Database Schema
-- =============================================

-- Odstranění existující databáze (pokud existuje)
DROP DATABASE IF EXISTS ctf_platform;

-- Vytvoření databáze
CREATE DATABASE IF NOT EXISTS ctf_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ctf_platform;

-- =============================================
-- Tabulka uživatelů
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    total_progress INT DEFAULT 0,
    current_level INT DEFAULT 1,
    total_score INT DEFAULT 0,
    agent_rank VARCHAR(50) DEFAULT 'Recruit',
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Tabulka výzev/úkolů
-- =============================================
CREATE TABLE IF NOT EXISTS challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard', 'expert') DEFAULT 'easy',
    points INT DEFAULT 100,
    flag_hash VARCHAR(255) NOT NULL,
    story_chapter TEXT,
    story_order INT NOT NULL,
    is_unlocked_default BOOLEAN DEFAULT FALSE,
    unlock_after_challenge_id INT NULL,
    hint_text TEXT NULL,
    tutorial_content TEXT NULL,
    easter_egg TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_story_order (story_order),
    INDEX idx_category (category),
    FOREIGN KEY (unlock_after_challenge_id) REFERENCES challenges(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Tabulka pokroku uživatelů
-- =============================================
CREATE TABLE IF NOT EXISTS user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    status ENUM('locked', 'unlocked', 'in_progress', 'completed') DEFAULT 'locked',
    attempts INT DEFAULT 0,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_challenge (user_id, challenge_id),
    INDEX idx_user_id (user_id),
    INDEX idx_challenge_id (challenge_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Tabulka Agent Logu (příběhové záznamy)
-- =============================================
CREATE TABLE IF NOT EXISTS agent_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    log_entry TEXT NOT NULL,
    log_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Tabulka Easter Eggs (objevené)
-- =============================================
CREATE TABLE IF NOT EXISTS discovered_easter_eggs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    easter_egg_code VARCHAR(50) NOT NULL,
    discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_easter_egg (user_id, challenge_id, easter_egg_code),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Vložení výzev podle příběhu SHADOW PROTOCOL
-- =============================================

-- Dočasně vypnout foreign key checks (kvůli unlock_after_challenge_id závislosti)
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- KAPITOLA 1: PRVNÍ KROKY (Web Exploitation - Basics)
-- =============================================================================

-- Challenge 1: Vítej v Matrix (Tutorial)
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Vítej v Matrixu',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Vítej v programu SHADOW PROTOCOL! Tvůj první úkol je jednoduchý - naučit se, jak fungují CTF výzvy."</p></div><p>Na stránce níže je tlačítko <strong>"ZÍSKAT FLAG"</strong>. Klikni na něj a zkopíruj zobrazený flag do pole pro odevzdání.</p><p class="hint-text">💡 Toto je tutorial - flag je textový řetězec ve formátu: <code>FLAG{nějaký_text}</code></p><div class="challenge-content"><button onclick="alert(\'FLAG{vitej_v_shadow_protocol_2025}\')" class="btn-primary">🎯 ZÍSKAT FLAG</button></div><p class="info">Vždy zkopíruj flag přesně tak, jak je napsaný, včetně složených závorek a velkých písmen!</p>',
    'Web',
    'easy',
    10,
    '$2y$10$zijTptUqpXxn9GkSMr2CDe8zCX7LlJOiaTgkJOk9DKal2WS2TZtKS',
    '<div class="log-entry"><h4>📋 Agent Log #001</h4><p><strong>SHADOW PROTOCOL ACTIVATED</strong></p><p>Vítej, budoucí cyber-agentu! Korporace NEXUS TECH byla hacknutá a ty jsi byl vybrán do elitního školicího programu. Tvůj průvodce jsem já - <strong>Agent Byte</strong>, AI asistent. Do levelu 8 tě budu učit základy a poté tě pošlu do akce na hackery.</p><p>První mise dokončena. Naučil ses základní formát flagů. Pokračujme...</p></div>',
    1,
    TRUE,
    NULL,
    'Flag je viditelný přímo na stránce.',
    '<h3>📚 Co je to CTF?</h3><p>Capture The Flag (CTF) je soutěž v kybernetické bezpečnosti. Tvým úkolem je najít "vlajky" (flags) - tajné řetězce ukryté v úkolech.</p><p><strong>Formát flagu:</strong> <code>FLAG{text}</code></p><p>Flags mohou být ukryté v kódu, souborech, šifrované, nebo získané exploitací zranitelností.</p><p><strong>Jak začít:</strong> Stačí kliknout na tlačítko "ZÍSKAT FLAG" a zkopírovat zobrazený flag do pole pro odevzdání.</p>'
);

-- Challenge 2: View Source
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'View Source',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Hackeři často schovávají informace v kódu stránky. Musíš se naučit číst HTML zdrojový kód."</p></div><p>Na této stránce je schovaný flag. Je viditelný pouze v HTML kódu. Najdi ho!</p><div class="challenge-content"><p>Tady je nějaký viditelný text...</p><p>Ale flag je ukrytý někde v HTML kódu této stránky! 🔍</p><p style="color: #00D1FF;">HINT: Zmáčkni F12 nebo Ctrl+U</p><!-- Gratuluju! Našel jsi flag: FLAG{html_zdroj_je_tvuj_pritel} --></div>',
    'Web',
    'easy',
    15,
    '$2y$10$E50FDX3PH4fzhpKdv.mhdOX/RzTUiYnBKu9SmeQfx/lzglwBEXHCu',
    '<div class="log-entry"><h4>📋 Agent Log #002</h4><p>Výborně! Objevil jsi skrytý flag v HTML komentáři.</p><p><strong>Agent Byte:</strong> "HTML komentáře jsou viditelné v zdrojovém kódu, ale ne na stránce. Vývojáři tam často zanechávají citlivé informace - jména, hesla, API klíče..."</p><p>První lekce: <em>Nikdy nevěř tomu, co je schované "pouze" na klientovi.</em></p></div>',
    2,
    FALSE,
    1,
    'Podívej se na HTML zdrojový kód stránky. Hledej komentáře.',
    '<h3>📚 HTML Zdrojový kód</h3><p>Každá webová stránka je postavená z HTML kódu. Prohlížeč tento kód interpretuje a zobrazí jako stránku.</p><p><strong>Zobrazení zdrojového kódu:</strong></p><ul><li>Windows: <code>Ctrl + U</code> nebo <code>F12</code></li><li>Mac: <code>Cmd + Option + U</code></li><li>Pravé tlačítko → "Zobrazit zdrojový kód"</li><li>F12 → záložka "Elements"</li></ul><p><strong>HTML komentáře:</strong> <code>&lt;!-- text --&gt;</code> jsou viditelné ve zdrojáku, ale ne na stránce.</p><p><strong>Jak na to:</strong> Zmáčkni F12 nebo Ctrl+U, pak hledej HTML komentář ve formátu <code>&lt;!-- ... --&gt;</code></p>'
);

-- Challenge 3: Robot Hunters
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Robot Hunters',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Každá webová stránka má soubor robots.txt, který říká vyhledávačům, které části webu mají ignorovat."</p></div><p>Hackeři často schovávají zajímavé cesty v robots.txt, protože ji běžní uživatelé nečtou!</p><div class="challenge-content"><p>Najdi <code>robots.txt</code> soubor této výzvy a prozkoumej ho.</p><p class="hint-text">💡 robots.txt je vždy v root adresáři: <code>http://example.com/robots.txt</code></p></div>',
    'Web',
    'easy',
    20,
    '$2y$10$gaQOx3t3mhgYnPTIqZTb3OK4ZTLGJJT6RhOun4hXbmT.CCiHMF7QW',
    '<div class="log-entry"><h4>📋 Agent Log #003</h4><p>Skvělá práce! Našel jsi skrytý flag přes robots.txt soubor.</p><p><strong>Agent Byte:</strong> "robots.txt je určený pro roboty (vyhledávače), ale každý ho může číst. Je to častý zdroj information disclosure - odhalení citlivých adresářů jako /admin, /backup, /config..."</p><p><em>Bezpečnost skrze utajení (security through obscurity) není bezpečnost.</em></p></div>',
    3,
    FALSE,
    2,
    'Prozkoumej soubor robots.txt a zkontroluj zakázané cesty.',
    '<h3>📚 robots.txt</h3><p>Soubor robots.txt říká vyhledávačům (Google, Bing), které části webu mají ignorovat.</p><p><strong>Příklad:</strong></p><pre>User-agent: *\nDisallow: /admin\nDisallow: /private</pre><p>Tento soubor je veřejně přístupný a může odhalit zajímavé adresáře!</p><p><strong>V penetračním testování:</strong> robots.txt je prvním místem, kam se podíváme.</p><p><strong>Jak na to:</strong> Přidej <code>/robots.txt</code> za URL této výzvy a prozkoumej Disallow cesty - jedna z nich vede k flagu.</p>'
);

-- Challenge 4: Cookie Monster
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Cookie Monster',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Cookies ukládají data v prohlížeči uživatele. Ale pozor - uživatel je může měnit!"</p></div><p>Tato stránka používá cookie pro kontrolu, zda jsi admin. Zkus to obejít!</p><div class="challenge-content"><p><a href="challenges/4_cookie_monster.html" target="_blank" style="display:inline-block;background:#00D1FF;color:#000;padding:15px 30px;text-decoration:none;border-radius:5px;font-weight:bold;margin:20px 0;">🚀 OTEVŘÍT CHALLENGE</a></p><p style="margin-top:20px;">Challenge se otevře v novém okně. Najdi cookie <code>ch1_4_admin</code> a změň její hodnotu!</p></div><p class="hint-text">💡 Otevři Developer Tools (F12) → Application/Storage → Cookies</p>',
    'Web',
    'easy',
    25,
    '$2y$10$KiIATBy5H07idY2wci1lvucztuCCIOM9CWr3J5pwKFcp9LA75NWg2',
    '<div class="log-entry"><h4>📋 Agent Log #004</h4><p>Perfektní! Manipuloval jsi s cookies a získal admin přístup.</p><p><strong>Agent Byte:</strong> "Cookies jsou uložené na straně klienta, takže jim nelze věřit! Nikdy nesmíš ukládat citlivé rozhodnutí (jako \'je admin\') do cookies."</p><p><strong>Správně:</strong> Ověřuj vše na serveru. Cookies jen jako session ID.</p></div>',
    4,
    FALSE,
    3,
    'Najdi cookie s názvem "ch1_4_admin" a změň její hodnotu.',
    '<h3>📚 HTTP Cookies</h3><p>Cookies jsou malé soubory ukládané v prohlížeči. Servery je používají pro:</p><ul><li>Session management (přihlášení)</li><li>Personalizace (nastavení)</li><li>Tracking (analytics)</li></ul><p><strong>Zobrazení cookies:</strong> F12 → Application → Cookies (Chrome) nebo F12 → Storage → Cookies (Firefox)</p><p><strong>Bezpečnostní riziko:</strong> Uživatel může cookies upravovat! Proto nikdy neukládej citlivá rozhodnutí přímo v cookies.</p><p><strong>Jak na to:</strong> Otevři Developer Tools (F12), přejdi do Application/Storage → Cookies, najdi cookie "ch1_4_admin" s hodnotou "false" a změň ji na "true". Flag se zobrazí automaticky.</p>'
);

-- Challenge 5: Inspect Element
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Inspect Element',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "HTML v prohlížeči je jako plastelína - můžeš ho měnit jak chceš!"</p></div><p>Formulář má tlačítko Submit, které je <code>disabled</code> (neaktivní). Aktivuj ho pomocí Inspect Element!</p><div class="challenge-content"><form id="disabledForm" onsubmit="event.preventDefault(); alert(\'FLAG{html_je_jen_navrh}\'); return false;"><input type="text" value="admin" required><input type="password" value="secretpass123" required><button type="submit" disabled style="opacity: 0.5; cursor: not-allowed;">🔒 SUBMIT (disabled)</button></form><p class="hint-text">💡 Pravé tlačítko na button → Inspect Element → Odstraň atribut "disabled"</p></div>',
    'Web',
    'easy',
    30,
    '$2y$10$z2DNP5mz0svqfuFubqm.ceTPPwaTO1ADnv.KmKToin0fvVPAT7Rc.',
    '<div class="log-entry"><h4>📋 Agent Log #005</h4><p>Výtečně! Upravil jsi DOM a aktivoval disabled tlačítko.</p><p><strong>Agent Byte:</strong> "Inspect Element umožňuje měnit cokoliv na stránce - texty, tlačítka, formuláře... Vše je to jen HTML+CSS+JS v tvém prohlížeči."</p><p><strong>Lekce:</strong> Client-side validace (jako disabled button) není bezpečnostní opatření. Je to jen UX. Vše musí být ověřené na serveru!</p></div>',
    5,
    FALSE,
    4,
    'Použij Inspect Element k úpravě HTML tlačítka.',
    '<h3>📚 Inspect Element</h3><p>Developer Tools v prohlížeči umožňují:</p><ul><li>Zobrazit a upravovat HTML/CSS</li><li>Debugovat JavaScript</li><li>Monitorovat network requests</li><li>Manipulovat s cookies</li></ul><p><strong>Otevření:</strong> F12 nebo Pravé tlačítko → Inspect</p><p><strong>Důležité:</strong> Vše co upravíš je jen lokální - zmizí po refreshi stránky. Ale ukazuje to, že klientovi nelze věřit!</p><p><strong>Jak na to:</strong> Pravé tlačítko na tlačítko → Inspect Element. V HTML najdi <code>&lt;button ... disabled&gt;</code> a odstraň atribut "disabled".</p>'
);

-- Challenge 6: Hidden Input
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Hidden Input',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Formuláře často obsahují skrytá pole (hidden inputs). Prozkoumej je!"</p></div><p>Máš credentials: <code>guest / guest123</code>, ale po přihlášení ti řekne, že nejsi admin.</p><div class="challenge-content"><form id="hiddenForm" onsubmit="event.preventDefault(); const role = document.querySelector(\'input[name=role]\').value; if(role === \'admin\') { alert(\'FLAG{hidden_neznamena_bezpecne}\'); } else { alert(\'Přihlášen jako GUEST. Nemáš admin práva!\'); } return false;"><input type="text" name="username" value="guest" readonly><input type="password" name="password" value="guest123" readonly><input type="hidden" name="role" value="guest"><button type="submit">🔐 PŘIHLÁSIT SE</button></form><p class="hint-text">💡 Inspect Element na formuláři. Jsou tam nějaká <code>type="hidden"</code> pole?</p></div>',
    'Web',
    'medium',
    35,
    '$2y$10$POkNxSs6KadaW6I1/ehUoOQZ91tZbe.MDLAIYq.j.FY50WsUl5wia',
    '<div class="log-entry"><h4>📋 Agent Log #006</h4><p>Skvělé! Odhalil jsi hidden input a změnil svou roli na admin.</p><p><strong>Agent Byte:</strong> "Hidden inputs jsou stále součástí formuláře - jen je nevidíš. Ale uživatel je může najít a změnit pomocí Inspect Element!"</p><p><strong>Realita:</strong> Spousta starších webů ukládá důležitá data (role, ceny, user_id) do hidden inputs. To je obrovská bezpečnostní chyba!</p></div>',
    6,
    FALSE,
    5,
    'Prozkoumej formulář pomocí Inspect Element. Je tam skryté pole s tvou rolí.',
    '<h3>📚 Hidden Form Fields</h3><p>HTML formuláře mohou obsahovat skrytá pole:</p><pre>&lt;input type="hidden" name="role" value="user"&gt;</pre><p>Tato pole nejsou viditelná, ale:</p><ul><li>Jsou součástí formuláře</li><li>Odesílají se při submitu</li><li>Uživatel je může zobrazit a upravit</li></ul><p><strong>Bezpečnostní zásada:</strong> Nikdy neukládej důležitá rozhodnutí (role, ceny, oprávnění) do hidden inputů!</p><p><strong>Jak na to:</strong> Použij Inspect Element na formulář. Najdi <code>&lt;input type="hidden" name="role" value="guest"&gt;</code> a změň value="guest" na value="admin". Pak klikni Submit.</p>'
);

-- Challenge 7: JavaScript Secrets
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'JavaScript Secrets',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "JavaScript kód běží v prohlížeči uživatele. Vše je viditelné!"</p></div><p>Tato stránka má "super bezpečné" heslo ověřované v JavaScriptu. Najdi ho!</p><div class="challenge-content"><p><a href="challenges/7_javascript_secrets.html" target="_blank" style="display:inline-block;background:#00D1FF;color:#000;padding:15px 30px;text-decoration:none;border-radius:5px;font-weight:bold;margin:20px 0;">🚀 OTEVŘÍT CHALLENGE</a></p><p style="margin-top:20px;">Challenge se otevře v novém okně. Najdi heslo v JavaScript kódu!</p></div><p class="hint-text">💡 View Source (Ctrl+U) a hledej JavaScript kód nebo .js soubory</p>',
    'Web',
    'medium',
    40,
    '$2y$10$kgw4AlvmVaQ.2kxaceufFuRlaXwOv9IYyn0Ew2UO.nwJk58Luv4pi',
    '<div class="log-entry"><h4>📋 Agent Log #007</h4><p>Výborně! Našel jsi heslo ukryté přímo v JavaScript kódu.</p><p><strong>Agent Byte:</strong> "JavaScript běží na straně klienta - v prohlížeči uživatele. To znamená, že VEŠKERÝ kód je viditelný a může být čten, upraven nebo obejit."</p><p><strong>Nikdy neukládej:</strong> Hesla, API klíče, tajné algoritmy do JavaScriptu!</p><p><strong>Správně:</strong> Veškeré ověřování musí probíhat na serveru.</p></div>',
    7,
    FALSE,
    6,
    'Heslo je ukryté v JavaScript kódu stránky.',
    '<h3>📚 Client-side JavaScript</h3><p>JavaScript v prohlížeči je:</p><ul><li>Viditelný (View Source)</li><li>Upravitelný (DevTools Console)</li><li>Obejitelný (lze zakázat JS)</li></ul><p><strong>Časté chyby:</strong></p><ul><li>Hesla v JS kódu</li><li>API klíče v JS</li><li>Ověřování pouze na klientovi</li></ul><p><strong>Zlaté pravidlo:</strong> "Nikdy nevěř klientovi!" Vše důležité musí být ověřené na serveru.</p><p><strong>Jak na to:</strong> Zobraz zdrojový kód (Ctrl+U nebo View Source). Hledej JavaScript funkci checkPassword() nebo podobnou - obsahuje heslo přímo v kódu!</p>'
);

-- Challenge 8: POST Master (BOSS)
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content, easter_egg) VALUES
(
    'POST Master',
    '<div class="challenge-intro boss"><h3>🏆 BOSS KAPITOLY 1</h3><p><strong>Agent Byte:</strong> "Gratulace! Toto je finální test první kapitoly. Prokážeš znalost HTTP metod?"</p></div><p>Hackeři používají různé HTTP metody - GET, POST, PUT, DELETE... Tato stránka odpovídá pouze na správnou metodu se správnými parametry.</p><div class="challenge-content"><p><a href="challenges/8_post_master.html" target="_blank" style="display:inline-block;background:#00D1FF;color:#000;padding:15px 30px;text-decoration:none;border-radius:5px;font-weight:bold;margin:20px 0;">🚀 OTEVŘÍT CHALLENGE</a></p><p style="margin-top:20px;">Challenge se otevře v novém okně. Použij POST request s parametry!</p></div><p class="hint-text">💡 Můžeš použít formulář na stránce, cURL, nebo Postman</p>',
    'Web',
    'medium',
    50,
    '$2y$10$XO0EzXg92nbQ1LemSr.EZOiwtvp6QaFPZSui7mRv0ivVSeS4Jw8.q',
    '<div class="log-entry boss-complete"><h3>🏆 KAPITOLA 1 DOKONČENA!</h3><p><strong>Agent Byte:</strong> "Výborná práce! Zvládl jsi všech 8 úkolů první kapitoly."</p><p><strong>Naučil ses:</strong></p><ul><li>✓ Základy HTML a developer tools</li><li>✓ Manipulaci s cookies</li><li>✓ Inspect Element</li><li>✓ Hidden inputs a formuláře</li><li>✓ Client-side JavaScript</li><li>✓ HTTP metody (GET vs POST)</li></ul><p><em>"Webová bezpečnost začíná pochopením, že klientovi nelze věřit. Vše důležité musí být ověřeno na serveru."</em></p><hr><p>🎖️ <strong>Achievement odemčen:</strong> Web Warrior - Level 1</p><p>📚 <strong>Další kapitola:</strong> Tajné zprávy (Cryptography)</p></div>',
    8,
    FALSE,
    7,
    'Použij POST request se správnými parametry: action a chapter.',
    '<h3>📚 HTTP Metody</h3><p>HTTP protokol má několik metod (verb):</p><ul><li><strong>GET</strong> - Získání dat (parametry v URL)</li><li><strong>POST</strong> - Odeslání dat (parametry v těle requestu)</li><li><strong>PUT</strong> - Update dat</li><li><strong>DELETE</strong> - Smazání dat</li></ul><p><strong>Rozdíl GET vs POST:</strong></p><table><tr><th>GET</th><th>POST</th></tr><tr><td>Viditelné v URL</td><td>Skryté v těle</td></tr><tr><td>Lze bookmarknout</td><td>Nelze bookmarknout</td></tr><tr><td>Omezená délka</td><td>Neomezená délka</td></tr></table><p><strong>Nástroje:</strong> cURL, Postman, Burp Suite, browser DevTools</p><p><strong>Jak na to:</strong> Klikni na tlačítko a použij připravený formulář na stránce (parametry: action=unlock a chapter=1), nebo vytvoř vlastní POST request pomocí cURL/Postman.</p>',
    'CHAPTER_1_MASTER'
);

-- =============================================================================
-- KAPITOLA 2: TAJNÉ ZPRÁVY (Cryptography)
-- =============================================================================

-- Challenge 9: Caesar's Legacy
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Caesar\'s Legacy',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Výborně! Kapitola 1 je za námi. Teď jsme našli zašifrované zprávy mezi hackery. Musíš se naučit dešifrovat jejich komunikaci."</p></div><p>Hackeři použili jednu z nejstarších šifer na světě - <strong>Caesarovu šifru</strong>. Každé písmeno je posunuto o N pozic v abecedě.</p><div class="challenge-content"><p><strong>Zašifrovaná zpráva:</strong></p><code class="code-block">SYNT{pnrfne_fuvsg_guerr}</code><p class="hint-text">💡 Zkus všechny možné posuny (ROT1 až ROT25) a najdi čitelný text!</p></div>',
    'Crypto',
    'easy',
    15,
    '$2y$10$2whWvR9gfq1.McCwsy.YhOBWcBwswUcj0A8CKWcFVUce6GITmT7AG',
    '<div class="log-entry"><h4>📋 Agent Log #009</h4><p>První kryptografický úkol splněn! Rozluštil jsi Caesarovu šifru.</p><p><strong>Agent Byte:</strong> "Caesar cipher je substituce - každé písmeno nahrazuješ jiným. Je to velmi slabá šifra, protože má jen 25 možných klíčů. Moderní šifry mají klíče dlouhé stovky bitů!"</p><p><strong>ROT13</strong> je speciální případ Caesaru s posunem o 13. Používá se i dnes pro "skrytí" spoilerů.</p></div>',
    9,
    FALSE,
    8,
    'Zkus všechny možné posuny v abecedě (ROT1-ROT25).',
    '<h3>📚 Caesarova šifra</h3><p>Jedna z nejstarších šifer (Julius Caesar, 100 př.n.l.). Princip:</p><pre>Plaintext:  ABCDEFG...\nCiphertext: DEFGHIJ... (posun o 3)</pre><p><strong>ROT13:</strong> Speciální případ s posunem o 13 pozic. Je to vlastní inverze!</p><p><strong>Lámání:</strong> Jen 25 možných klíčů → zkusíš všechny (brute-force)</p><p><strong>Online nástroje:</strong> dcode.fr/caesar-cipher, rot13.com</p><p><strong>Jak na to:</strong> Použij online nástroj (dcode.fr/caesar-cipher) nebo zkus ručně všechny ROT varianty (1-25). Tento challenge používá ROT13.</p>'
);

-- Challenge 10: Base Encoding
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Base Encoding',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Našli jsme podivný text v logách. Vypadá jako náhodné znaky, ale není to šifra - je to encoding!"</p></div><p><strong>Base64</strong> je způsob kódování binárních dat do ASCII textu. Používá 64 znaků: A-Z, a-z, 0-9, +, /</p><div class="challenge-content"><p><strong>Kódovaný text:</strong></p><code class="code-block">RkxBR3tiYXNlNjRfbmVuaV9zaWZyb3Zhbml9</code><p class="hint-text">💡 Base64 poznáš podle znaků A-Z, a-z, 0-9, +, / a často končí na =</p></div>',
    'Crypto',
    'easy',
    20,
    '$2y$10$8fvFoyQD3piw5oWNpOiLzOhLYKoWY3SBmJnHI1B/yrHpBCjQeROtu',
    '<div class="log-entry"><h4>📋 Agent Log #010</h4><p>Dekódování Base64 úspěšné!</p><p><strong>Agent Byte:</strong> "Důležité: Base64 není šifrování! Je to jen encoding - převod dat do jiného formátu. Lze jednoduše dekódovat bez hesla."</p><p><strong>Rozdíl:</strong></p><ul><li><strong>Encoding</strong> (Base64, Hex): Převod formátu, reverzibilní bez klíče</li><li><strong>Encryption</strong> (AES, RSA): Šifrování, vyžaduje klíč k dešifrování</li></ul></div>',
    10,
    FALSE,
    9,
    'Použij Base64 dekodér k převodu textu zpět na čitelnou formu.',
    '<h3>📚 Base64 Encoding</h3><p>Base64 převádí binární data (obrázky, soubory) na ASCII text.</p><p><strong>Použití:</strong></p><ul><li>Email attachments</li><li>Vložení obrázků do HTML/CSS</li><li>API tokeny</li></ul><p><strong>Dekódování:</strong></p><pre># Online:\nbase64decode.org\n\n# Linux/Mac:\necho "RkxBR..." | base64 -d\n\n# Python:\nimport base64\nbase64.b64decode(b"RkxBR...")</pre><p><strong>DŮLEŽITÉ:</strong> Base64 není bezpečnostní ochrana!</p><p><strong>Jak na to:</strong> Zkopíruj text a vlož ho do online Base64 dekodéru (base64decode.org) nebo použij příkaz v terminálu/Pythonu.</p>'
);

-- Challenge 11: Hexadecimal Hunt
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Hexadecimal Hunt',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Další zpráva, tentokrát v hexadecimálním formátu."</p></div><p><strong>Hexadecimální</strong> (hex) je číselná soustava o základu 16. Používá znaky 0-9 a A-F. Každé písmeno je reprezentováno dvěma hex znaky (00-FF).</p><div class="challenge-content"><p><strong>Hex zpráva:</strong></p><code class="code-block">464c41477b6865785f6a655f6a656e6f6d5f7a616b6c61646e695f73797374656d7d</code><p class="hint-text">💡 Každé 2 hex znaky = 1 ASCII znak. 46 4c = "FL"</p></div>',
    'Crypto',
    'easy',
    25,
    '$2y$10$mlbCy4t0rg0Rbl0CMYOLRuVTK4W4Iv45QUqU0fMTuBZk90dpx2q52',
    '<div class="log-entry"><h4>📋 Agent Log #011</h4><p>Hex to ASCII konverze úspěšná!</p><p><strong>Agent Byte:</strong> "Hexadecimální zápis je běžný v programování a forensics. Používá se pro zobrazení binárních dat v čitelné formě."</p><p><strong>ASCII tabulka:</strong> Každý znak má číslo (A=65, B=66...). Hex je jen jiný způsob zápisu těchto čísel.</p><p>65 (decimal) = 41 (hex) = \'A\'</p></div>',
    11,
    FALSE,
    10,
    'Převeď hexadecimální znaky na ASCII text.',
    '<h3>📚 Hexadecimální systém</h3><p>Hex je základ 16:</p><pre>Dec: 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15\nHex: 0 1 2 3 4 5 6 7 8 9 A  B  C  D  E  F</pre><p><strong>Hex to ASCII:</strong></p><pre># Python\nbytes.fromhex("464c41").decode()\n# Output: "FLA"\n\n# Linux\necho "464c41" | xxd -r -p\n\n# Online\nrapidtables.com/convert/number/hex-to-ascii.html</pre><p><strong>Použití:</strong> Memory dumps, packet captures, binary files</p><p><strong>Jak na to:</strong> Každé 2 hex znaky = 1 ASCII znak. Použij online konvertor nebo Python: <code>bytes.fromhex("464c...").decode()</code></p>'
);

-- Challenge 12: XOR Mystery
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'XOR Mystery',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "XOR (exclusive OR) je základní kryptografická operace. Jednoduchá, ale mocná!"</p></div><p>Zpráva byla zašifrována pomocí <strong>single-byte XOR</strong> - každý byte je XORován se stejným klíčem (jeden znak).</p><div class="challenge-content"><p><strong>Zašifrovaná data (hex):</strong></p><code class="code-block">6c666b6d51524558755943444d464f7548535e4f755d4f4b4157</code><p><strong>Tvůj úkol:</strong> Najdi správný XOR klíč (0-255) a dešifruj zprávu!</p><p class="hint-text">💡 Hledej klíč, který vrátí čitelný text začínající "FLAG{"</p></div>',
    'Crypto',
    'medium',
    35,
    '$2y$10$vQPddz6rlrWhGAVzfuY9h.2vWcyVxTjksD6yTskgHq/YvL6Uy0Rpu',
    '<div class="log-entry"><h4>📋 Agent Log #012</h4><p>XOR single-byte crack úspěšný!</p><p><strong>Agent Byte:</strong> "XOR má zajímavou vlastnost: A ⊕ B = C, pak C ⊕ B = A. To znamená, že stejná operace šifruje i dešifruje!"</p><p><strong>Single-byte XOR je slabý:</strong> Pouze 256 možných klíčů → lze brute-forcovat za sekundu.</p><p><strong>Silnější:</strong> Multi-byte XOR s dlouhým klíčem (One-Time Pad je teoreticky nezlomitelný)</p></div>',
    12,
    FALSE,
    11,
    'Single-byte XOR má jen 256 možných klíčů - zkus je všechny.',
    '<h3>📚 XOR Šifra</h3><p>XOR (⊕) je binární operace:</p><pre>0 ⊕ 0 = 0\n0 ⊕ 1 = 1\n1 ⊕ 0 = 1\n1 ⊕ 1 = 0</pre><p><strong>Šifrování:</strong> plaintext ⊕ key = ciphertext</p><p><strong>Dešifrování:</strong> ciphertext ⊕ key = plaintext</p><p><strong>Single-byte XOR:</strong> Každý byte zprávy XORován se stejným jedním bytem.</p><p><strong>Útok:</strong> Brute-force všech 256 možných klíčů</p><p><strong>Jak na to v Pythonu:</strong></p><pre>encrypted = bytes.fromhex("6c666b6d5152...")\nfor key in range(256):\n    decrypted = bytes([b ^ key for b in encrypted])\n    if b"FLAG" in decrypted:\n        print(f"Key: {key}, Text: {decrypted}")</pre>'
);

-- Challenge 13: Substitution Cipher
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Substitution Cipher',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Tohle bude náročnější! Substituční šifra - každé písmeno je nahrazeno jiným podle tajné tabulky."</p></div><p>Na rozdíl od Caesara, kde je posun fixní, zde je substituce náhodná. Ale lze ji prolomit!</p><div class="challenge-content"><p><strong>Zašifrovaný text:</strong></p><code class="code-block">SYNT{FHOFGVGHGVBA_PELCGB_GNXRF_SERDHRAPL}</code><p><strong>Hint:</strong> V angličtině je nejčastější písmeno E, pak T, A, O, I, N...</p><p class="hint-text">💡 Pattern: SYNT se opakuje na začátku - pravděpodobně "FLAG"!</p></div>',
    'Crypto',
    'medium',
    40,
    '$2y$10$Le7C4OGbckGFKpxtB3PkEekErNMLoU0rCYTd3W6p6AsmnJdqXvU/C',
    '<div class="log-entry"><h4>📋 Agent Log #013</h4><p>Substituční šifra prolomena pomocí frekvenční analýzy!</p><p><strong>Agent Byte:</strong> "Substituční šifry byly považovány za nezlomitelné... dokud arabští matematici v 9. století neobjevili frekvenční analýzu."</p><p><strong>Klíč k prolomení:</strong> Jazyky mají vzorce (patterns). Některá písmena jsou častější než jiná. Některé dvojice písmen (bigrams) jsou běžnější.</p><p>Moderní šifry (AES) kombinují substituci, permutaci a mnoho kol transformací.</p></div>',
    13,
    FALSE,
    12,
    'Rozpoznej pattern: SYNT se opakuje - pravděpodobně "FLAG".',
    '<h3>📚 Substituční šifra</h3><p>Monoalphabetic substitution: Každé písmeno → jiné písmeno podle klíče</p><p><strong>Příklad klíče:</strong></p><pre>Plain:  ABCDEFGHIJKLMNOPQRSTUVWXYZ\nCipher: ZEBRASCDFGHIJKLMNOPQTUVWXY</pre><p><strong>Počet možných klíčů:</strong> 26! ≈ 4×10²⁶ (obrovské!)</p><p><strong>Ale:</strong> Lze prolomit frekvenční analýzou</p><p><strong>Frekvence v angličtině:</strong></p><pre>E: 12.7%\nT: 9.1%\nA: 8.2%\nO: 7.5%\n...</pre><p><strong>Nástroje:</strong> dcode.fr/monoalphabetic-substitution, quipqiup.com</p><p><strong>Jak na to:</strong> SYNT se opakuje na začátku → pravděpodobně "FLAG". Použij pattern matching nebo online nástroj.</p>'
);

-- Challenge 14: RSA Baby
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'RSA Baby',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Teď to bude těžké! RSA - moderní asymetrická šifra používaná všude na internetu."</p></div><p>Našli jsme RSA šifrovanou zprávu. Hackeři ale udělali chybu - použili velmi malé prvočísla!</p><div class="challenge-content"><p><strong>Public key:</strong></p><code style="word-break: break-all; display: block; padding: 10px; background: rgba(0,0,0,0.3); border-radius: 5px;">n = 5231568074501831989853697029976539503599758662241810022594690918345794436136753818631545475698197241278389401455795863546933427221261846420561881295091804654899<br>e = 65537</code><p><strong>Ciphertext:</strong></p><code style="word-break: break-all; display: block; padding: 10px; background: rgba(0,0,0,0.3); border-radius: 5px;">2006771695171939679641324518792951025572665368191351196933800659893516063592675154971823446265917655005321046387173273584670698814291897404269450149182303620407</code><p><strong>Tvůj úkol:</strong> Faktorizuj n na p×q, vypočítej private key d, dešifruj!</p><p class="hint-text">💡 n je malé → lze faktorizovat na factordb.com</p></div>',
    'Crypto',
    'hard',
    50,
    '$2y$10$fg9kZfD83/9p9C12TBEI1uGycJBRBx5uaq4Zz7m3525C21XMloque',
    '<div class="log-entry"><h4>📋 Agent Log #014</h4><p>RSA crack úspěšný! Faktorizoval jsi malé n.</p><p><strong>Agent Byte:</strong> "RSA je založené na problému faktorizace - rozložit velké číslo na prvočísla je těžké. Ale jen pokud je n dostatečně velké!"</p><p><strong>Bezpečné RSA:</strong> n má 2048-4096 bitů (600-1200 číslic). Faktorizace takového čísla by trvala miliony let.</p><p><strong>Kvantové počítače:</strong> Shor\'s algorithm by mohl faktorizovat rychle → RSA by bylo zlomené!</p></div>',
    14,
    FALSE,
    13,
    'Slabé n lze faktorizovat. Zkus factordb.com.',
    '<h3>📚 RSA Kryptografie</h3><p>RSA je asymetrická šifra (public/private key)</p><p><strong>Generování klíčů:</strong></p><pre>1. Vyber 2 velká prvočísla p, q\n2. n = p × q\n3. φ(n) = (p-1) × (q-1)\n4. Vyber e (obvykle 65537)\n5. Vypočítej d = e⁻¹ mod φ(n)\n\nPublic key: (n, e)\nPrivate key: (n, d)</pre><p><strong>Šifrování:</strong> c = m^e mod n</p><p><strong>Dešifrování:</strong> m = c^d mod n</p><p><strong>Bezpečnost:</strong> Založeno na obtížnosti faktorizace n</p><p><strong>Jak na to:</strong> 1) Faktorizuj n na factordb.com → získáš p a q. 2) Python: <code>phi=(p-1)*(q-1); d=pow(e,-1,phi); m=pow(c,d,n)</code>. 3) Převeď m na text.</p>'
);

-- Challenge 15: Vigenere Quest
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Vigenere Quest',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Vigenere cipher - 400 let byla považována za nezlomitelnou!"</p></div><p><strong>Vigenere</strong> je polyalfabetická šifra. Používá klíčové slovo pro šifrování. Každé písmeno klíče určuje posun (A=0, B=1, C=2...).</p><div class="challenge-content"><p><strong>Ciphertext:</strong></p><code class="code-block">FPVK{MGXVXVIV_BGJOCP_DBQHCBT}</code><p><strong>Hint:</strong> Klíč má 4 písmena a souvisí s počítači. Začíná na "B"...</p><p class="hint-text">💡 Možné klíče: BYTE, BITS, BINARY...</p></div>',
    'Crypto',
    'hard',
    45,
    '$2y$10$A.1hmke7b2eJ5NkBDRTEJeaWPhzdDk80JL71ZDL6pkEx6xXG85ZC2',
    '<div class="log-entry"><h4>📋 Agent Log #015</h4><p>Vigenere dešifrování úspěšné! Klíč byl BYTE.</p><p><strong>Agent Byte:</strong> "Vigenere byla zlomená až v 19. století pomocí Kasiski examination a frekvenční analýzy. Důvod: klíč se opakuje!"</p><p><strong>Pokud je klíč stejně dlouhý jako zpráva a nikdy se neopakuje → One-Time Pad → teoreticky nezlomitelné!</strong></p></div>',
    15,
    FALSE,
    14,
    'Klíč má 4 písmena, začíná na "B" a souvisí s počítači.',
    '<h3>📚 Vigenere Cipher</h3><p>Polyalfabetická substituce s klíčovým slovem</p><p><strong>Příklad:</strong></p><pre>Plaintext:  ATTACKATDAWN\nKey:        LEMONLEMONLE\nCiphertext: LXFOPVEFRNHR</pre><p>Každé písmeno klíče = Caesar shift:</p><ul><li>L = 11 → A+11=L</li><li>E = 4 → T+4=X</li></ul><p><strong>Lámání:</strong></p><ol><li>Kasiski examination (najdi opakující se sekvence)</li><li>Urči délku klíče</li><li>Frekvenční analýza pro každou pozici</li></ol><p><strong>Nástroje:</strong> dcode.fr/vigenere-cipher</p><p><strong>Jak na to:</strong> Zkus klíče související s počítači: BYTE, BITS, BOOT, BIOS... Použij online nástroj (dcode.fr) pro rychlé testování.</p>'
);

-- Challenge 16: Hash Collision (BOSS)
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content, easter_egg) VALUES
(
    'Hash Collision',
    '<div class="challenge-intro boss"><h3>🏆 BOSS KAPITOLY 2</h3><p><strong>Agent Byte:</strong> "Finální test kryptografie! Hash funkce - jednosměrné šifry."</p></div><p><strong>Hash</strong> je jednosměrná funkce: data → fixed-size output. Nelze vrátit zpět (bez brute-force).</p><div class="challenge-content"><p>Hackeři použili <strong>MD5</strong> - starý, prolomitelný hash.</p><p><strong>MD5 Hash:</strong></p><code class="code-block">5f4dcc3b5aa765d61d8327deb882cf99</code><p><strong>Tvůj úkol:</strong> Najdi původní zprávu (heslo).</p><p class="hint-text">💡 Hint: Je to velmi běžné anglické slovo...</p><p><strong>Po cracknutí:</strong> Flag je <code>FLAG{původní_slovo}</code></p></div>',
    'Crypto',
    'hard',
    60,
    '$2y$10$BBjoKJXNZjUpyevO0B/cBuTL8ELwpB7n7nUacP1MzR2e4dFtrHJ9q',
    '<div class="log-entry boss-complete"><h3>🏆 KAPITOLA 2 DOKONČENA!</h3><p><strong>Agent Byte:</strong> "Skvělá práce! Prošel jsi všemi kryptografickými výzvami."</p><p><strong>Naučil ses:</strong></p><ul><li>✓ Klasické šifry (Caesar, Substitution, Vigenere)</li><li>✓ Encoding (Base64, Hex)</li><li>✓ XOR operace</li><li>✓ Moderní kryptografie (RSA basics)</li><li>✓ Hash funkce a cracking</li></ul><p><em>"Kryptografie je základ digitální bezpečnosti. Od HTTPS přes Bitcoin až po WhatsApp - všechno používá šifrování."</em></p><hr><p>🎖️ <strong>Achievement odemčen:</strong> Crypto Master</p><p>📚 <strong>Další kapitola:</strong> Ztracené stopy (Forensics)</p></div>',
    16,
    FALSE,
    15,
    'MD5 hash lze prolomit pomocí online databází nebo brute-force.',
    '<h3>📚 Hash Funkce</h3><p>Hash = jednosměrná funkce: libovolný input → fixed-size output</p><p><strong>Vlastnosti:</strong></p><ul><li>Deterministické (stejný input = stejný hash)</li><li>Jednosměrné (nelze vrátit zpět)</li><li>Collision resistant (těžké najít 2 vstupy se stejným hashem)</li></ul><p><strong>Použití:</strong> Ukládání hesel, integrita dat, Bitcoin mining</p><p><strong>Běžné hash funkce:</strong></p><ul><li>MD5 (128-bit) - PROLOMENÝ, nepoužívat!</li><li>SHA-1 (160-bit) - DEPRECATED</li><li>SHA-256 (256-bit) - ✓ Bezpečný</li><li>bcrypt - ✓ Pro hesla</li></ul><p><strong>Rainbow tables:</strong> Předpočítané hashe běžných hesel</p><p><strong>Jak na to:</strong> Zkus online cracking: crackstation.net, md5decrypt.net. Nebo offline: hashcat, john. Tento hash je velmi známý.</p>',
    'CHAPTER_2_CRYPTOMASTER'
);

-- =============================================================================
-- KAPITOLA 3: ZTRACENÉ STOPY (Forensics)
-- =============================================================================

-- Challenge 17: Hidden in Plain Sight
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Hidden in Plain Sight',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Skvělá práce s kryptografií! Teď jsme našli soubory zanechané hackery. Čeká tě forensics - analýza důkazů."</p></div><p>První úkol: Obrázek <code>evidence.png</code> vypadá normálně, ale obsahuje skrytou zprávu!</p><div class="challenge-content"><p><strong>Download:</strong> <a href="challenges/17_evidence.png" download>17_evidence.png</a></p><p><strong>Tvůj úkol:</strong> Najdi skrytý text v souboru!</p><p class="hint-text">💡 Ne všechno v souboru je viditelné okem...</p></div>',
    'Forensics',
    'easy',
    20,
    '$2y$10$LSwYqmWfxWuROkyO4hPAueDcfjYP9fh2gDwlmEeQgP7a7Skc8pga6',
    '<div class="log-entry"><h4>📋 Agent Log #017</h4><p>První forensics úkol splněn! Našel jsi skrytý text pomocí strings.</p><p><strong>Agent Byte:</strong> "Soubory obsahují víc než jen to, co vidíš. Obrázky, PDF, dokumenty - všechny mají interní strukturu a metadata."</p><p><strong>Strings command:</strong> Extrahuje všechny čitelné texty z binárních souborů. Super nástroj pro rychlý forensics průzkum!</p></div>',
    17,
    FALSE,
    16,
    'Soubory obsahují víc než jen viditelný obsah. Zkus extrahovat text.',
    '<h3>📚 Strings Command</h3><p>Strings extrahuje ASCII/Unicode text z binárních souborů</p><p><strong>Použití:</strong></p><pre># Linux/Mac\nstrings file.png\nstrings file.png | grep FLAG\n\n# Windows PowerShell\nSelect-String -Path file.png -Pattern "FLAG"\n\n# Nebo online nástroje</pre><p><strong>Použití v CTF:</strong></p><ul><li>Rychlý průzkum neznámých souborů</li><li>Hledání skrytých zpráv</li><li>Analýza malware</li><li>Memory dumps</li></ul><p><strong>Jak na to:</strong> Linux: <code>strings challenges/17_evidence.png | grep FLAG</code>. Windows: Otevři v Notepad++ nebo použij online strings viewer.</p>'
);

-- Challenge 18: EXIF Explorer
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'EXIF Explorer',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Fotografie obsahují víc než jen pixely - metadata!"</p></div><p>Hackeři uploadovali fotku <code>vacation.jpg</code>. V EXIF datech může být užitečná informace.</p><div class="challenge-content"><p><strong>Download:</strong> <a href="challenges/18_vacation.jpg" download>18_vacation.jpg</a></p><p><strong>EXIF obsahuje:</strong> GPS souřadnice, čas, fotoaparát, software, komentáře...</p><p class="hint-text">💡 Možná někdo přidal komentář do metadat?</p></div>',
    'Forensics',
    'easy',
    25,
    '$2y$10$un3zpqsPTdd2YMF/seFeSeRs2Parz89aTOJiRQ2x7gFagJkm/NoUG',
    '<div class="log-entry"><h4>📋 Agent Log #018</h4><p>EXIF metadata analyzována!</p><p><strong>Agent Byte:</strong> "EXIF data jsou zlatý důl pro forensics. GPS koordináty mohou odhalit lokaci, timestamp může poskytnout alibi, použitý software může identifikovat útočníka."</p><p><strong>Privacy warning:</strong> Než uploadneš fotku na internet, zkontroluj EXIF! Může obsahovat tvou domácí adresu (GPS).</p></div>',
    18,
    FALSE,
    17,
    'Prozkoumej EXIF metadata fotografie. Hledej komentáře.',
    '<h3>📚 EXIF Metadata</h3><p>EXIF (Exchangeable Image File Format) - metadata v obrázcích</p><p><strong>Co obsahuje:</strong></p><ul><li>Datum a čas</li><li>GPS koordináty (!)</li><li>Model fotoaparátu</li><li>Nastavení (ISO, ohnisko, clona...)</li><li>Software použitý k úpravě</li><li>Autor, copyright</li><li>User comments</li></ul><p><strong>Nástroje:</strong></p><pre># Exiftool (nejlepší)\nexiftool image.jpg\n\n# Online\nmetadata2go.com\njimpl.com</pre><p><strong>Odstranění EXIF:</strong> exiftool -all= image.jpg</p><p><strong>Jak na to:</strong> Online: metadata2go.com nebo jimpl.com. Linux: <code>exiftool challenges/18_vacation.jpg</code>. Windows: Pravé tlačítko → Vlastnosti → Podrobnosti.</p>'
);

-- Challenge 19: Steganography 101
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'Steganography 101',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Teď to bude zajímavé! Steganografie - umění skrývání."</p></div><p><strong>Steganografie</strong> = skrývání informací uvnitř jiných informací. Na rozdíl od kryptografie (šifrování), steganografie skrývá samotnou EXISTENCI zprávy.</p><div class="challenge-content"><p><strong>Download:</strong> <a href="challenges/19_landscape.png" download>19_landscape.png</a></p><p>Tento obrázek vypadá normálně, ale data jsou ukrytá v <strong>LSB</strong> (Least Significant Bits).</p><p class="hint-text">💡 Nástroje: stegsolve, zsteg, nebo online steganography decoder</p></div>',
    'Forensics',
    'medium',
    35,
    '$2y$10$pJWK5TpQEKRVND37L8WmCuvI2Gq1q0RrfnUoWQ8MuN0q3hjNY18hK',
    '<div class="log-entry"><h4>📋 Agent Log #019</h4><p>Steganografie prolomena! Data extrahována z LSB.</p><p><strong>Agent Byte:</strong> "Steganografie byla používána už ve starověku - neviditelný inkoust, tetování pod vlasy otroků... Dnes? LSB, spektrální analýza audio souborů, skrývání dat v IP packet timingu..."</p><p><strong>LSB steganography:</strong> Každý pixel má 3 barvy (RGB), každá 8 bitů. Nejméně významný bit (LSB) lze změnit bez viditelného rozdílu.</p></div>',
    19,
    FALSE,
    18,
    'Data jsou ukrytá v LSB (Least Significant Bits). Použij steganography nástroj.',
    '<h3>📚 Steganografie</h3><p>Skrývání dat uvnitř jiných dat</p><p><strong>LSB (Least Significant Bit):</strong></p><pre>Pixel RGB: (11010110, 10110101, 11001100)\nLSB:        ^^^^^^6   ^^^^^^5   ^^^^^^4\n\nZměna LSB prakticky neviditelná!</pre><p><strong>Nástroje:</strong></p><ul><li><strong>stegsolve</strong> - GUI pro analýzu obrázků</li><li><strong>zsteg</strong> - auto-detection (gem install zsteg)</li><li><strong>steghide</strong> - embedding/extracting</li></ul><p><strong>Použití:</strong></p><pre>zsteg image.png\nsteghide extract -sf image.jpg</pre><p><strong>Jak na to:</strong> Stegsolve (Java): File Formats → Data Extract → Red 0, Green 0, Blue 0. Online: stylesuxx.github.io/steganography/</p>'
);

-- Challenge 20: ZIP Cracker
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content) VALUES
(
    'ZIP Cracker',
    '<div class="challenge-intro"><p><strong>Agent Byte:</strong> "Našli jsme ZIP archiv hackerů, ale je chráněný heslem!"</p></div><p>Soubor <code>secret_files.zip</code> obsahuje důležité informace. Musíš prolomit heslo.</p><div class="challenge-content"><p><strong>Download:</strong> <a href="challenges/20_secret_files.zip" download>20_secret_files.zip</a></p><p><strong>Hint:</strong> Heslo je <strong>4-místné číslo</strong></p><p class="hint-text">💡 Brute-force nebo slovníkový útok!</p></div>',
    'Forensics',
    'medium',
    40,
    '$2y$10$wDx39BUexeMhlZ4h6OWFHeIucLxNI4kVKtBIUbv4wSmEJpnsoBuOC',
    '<div class="log-entry"><h4>📋 Agent Log #020</h4><p>ZIP heslo prolomena!</p><p><strong>Agent Byte:</strong> "ZIP encryption (ZipCrypto) je notoricky slabý. Existuje known-plaintext attack - pokud znáš část obsahu, můžeš prolomit heslo velmi rychle."</p><p><strong>Lepší:</strong> 7-Zip s AES-256, RAR5, nebo nejlépe GPG encryption celého souboru.</p></div>',
    20,
    FALSE,
    19,
    'Heslo je 4-místné číslo. Použij brute-force útok.',
    '<h3>📚 ZIP Password Cracking</h3><p>ZIP soubory mají slabé šifrování</p><p><strong>Nástroje:</strong></p><pre># fcrackzip (fastest)\nfcrackzip -b -c "1" -l 4-4 file.zip\n# -b = brute force\n# -c "1" = pouze číslice\n# -l 4-4 = délka 4\n\n# John the Ripper\nzip2john file.zip > hash.txt\njohn --wordlist=rockyou.txt hash.txt\n\n# Hashcat\nhashcat -m 13600 hash.txt wordlist.txt</pre><p><strong>ZipCrypto slabiny:</strong> Known-plaintext attack možný</p><p><strong>Jak na to:</strong> Linux: <code>fcrackzip -b -c "1" -l 4-4 file.zip</code>. Windows: John the Ripper (<code>zip2john file.zip > hash.txt; john hash.txt</code>).</p>'
);
-- Znovu zapnout foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- KONEC SEEDING - VŠECHNY CHALLENGES PŘIDÁNY
-- =============================================================================
-- 
--  CELKOVÁ STRUKTURA:
-- ---------------------
--  KAPITOLA 1: PRVNÍ KONTAKT (Web Basics) - 8 challenges (10-50 bodu)
--  KAPITOLA 2: TAJNÉ ZPRÁVY (Cryptography) - 8 challenges (15-60 bodu)
--  KAPITOLA 3: ZTRACENÉ STOPY (Forensics) - 4 challenges (20-55 bodu)
--
--  CELKEM: 20 challenges
--
-- =============================================================================

-- =============================================
-- Vytvoření demo účtu (heslo: demo123)
-- =============================================
INSERT INTO users (username, email, password_hash, agent_rank, is_admin) VALUES
('demo', 'demo@shadowprotocol.cz', '$2y$10$kjchMRsOfmnBEtIoAeOQn.UOg/Dz47z7InyHIoQgNTKbgUMlmGCBi', 'Recruit', 0);

-- =============================================
-- Admin účet (username: admin, heslo: admin123)
-- =============================================
INSERT INTO users (username, email, password_hash, agent_rank, is_admin) VALUES
('admin', 'admin@shadowprotocol.cz', '$2y$10$n9ZiyWsIMhnH5..sCYUz2eREukplTSqhD.057UDKwerBQ8zyHtW5C', 'Administrator', 1);

-- =============================================
-- Konec schématu
-- =============================================
# 🎮 Shadow Protocol CTF Platform

Česká vzdělávací platforma pro CTF (Capture The Flag) soutěže v oblasti kyberbezpečnosti s herním rozhraním, příběhem a progresivním odemykáním výzev.

## 🌟 Hlavní Funkce

- **Příběhový Režim** - Lineární progrese s "AI" průvodcem "Agent Byte"
- **Offline Mode** - Funguje bez přihlášení s LocalStorage
- **Auto-synchronizace** - Po registraci se lokální progress sloučí s DB
- **Admin Panel** - Kompletní CRUD pro challenges a uživatele
- **Dark Cyberpunk UI** - Futuristický temný design s neonovými akcenty
- **20 Seed Challenges** - Kapitoly: Web Basics, Cryptography, Forensics
- **Easter Eggs** - Skryté bonusy v BOSS výzvách
- **Bezpečnost** - CSRF protection, XSS sanitizace, bcrypt hashing

## 🛠️ Tech Stack

| Oblast | Technologie |
|--------|-------------|
| Backend | PHP 7.4+, PDO, Sessions |
| Frontend | Vanilla JS (SPA), CSS3 |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Server | Apache 2.4+ (mod_rewrite) |
| Security | bcrypt, CSRF tokens, XSS filters, CSP headers |

## 📋 Požadavky

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Apache 2.4+ (mod_rewrite)

### Instalace

```bash
# 1. Klonuj projekt
git clone https://github.com/StudentTraineeCenter/ctf-platform.git
cd ctf-platform

# 2. Import databáze
mysql -u root -p < db_schema.sql

# 3. Konfigurace
nano config.php  # Uprav DB credentials
```

**Spuštění:** `http://localhost/ctf-platform`

### První Přihlášení

**Demo účet:**
- Username: `demo`
- Password: `demo123`

**Admin:**
- Username: `admin`
- Password: `admin123`
- Panel: http://localhost/ctf-platform/admin/

⚠️ **po zavedení nového účtu jako admina v admin panelu smaž ůvodní admin účet!**

### Hraní Jako Host
Můžeš hrát bez registrace! Progress se uloží do LocalStorage a po registraci se automaticky synchronizuje.

### První Challenge

1. Otevři platformu
2. Klikni na "MISSIONS"
3. První challenge je odemčená: **"Vítej v Matrix"**
4. Flag: `FLAG{welcome_to_shadow_protocol}`
5. Po submitu se odemkne další výzva

## 📚 Dokumentace

- **[DOCUMENTATION.md](DOCUMENTATION.md)** - Kompletní technická dokumentace (API, databáze, bezpečnost)
- **[FLAGYTEST.md](FLAGYTEST.md)** - Testovací flagy

## 🔐 Admin Panel

**URL:** `http://localhost/ctf-platform/admin/`

### Funkce:
- 📊 Statistiky (uživatelé, challenges, success rate)
- 👥 Top 10 hráčů žebříček
- ✏️ CRUD pro challenges (create, edit, delete)
- 👤 Správa uživatelů (make/remove admin, delete)
- 🔍 Search/filter funkcionalita
- 📈 Grafy podle kategorie a obtížnosti

### Vytvoření Admin Účtu:

   - vytvoř noví účet a z účtu admin/admin123 mu dej práva admina
   - poté se vrať na tvůj účet a smaž admin

## 🎮 Přidání Vlastní Challenge

### Přes Admin Panel:
1. Přihlaš se jako admin
2. Klikni "ADMIN PANEL"
3. Sekce "PŘIDAT NOVOU CHALLENGE"
4. Vyplň formulář:
   - Title, Description (HTML podporováno)
   - Category, Difficulty, Points
   - Flag (ve formátu `FLAG{...}`)
   - Story order (pořadí v příběhu)

### Přes SQL:
```sql
INSERT INTO challenges (title, description, category, difficulty, points, flag_hash, story_chapter, story_order, is_unlocked_default, unlock_after_challenge_id, hint_text, tutorial_content, easter_egg) 
VALUES (
    'Název Challenge', 
    '<p>Popis výzvy v HTML</p>', 
    'Web', 
    'medium', 
    100,
    '$2y$10$...bcrypt_hash...',  -- použij generate_hash.php
    '<div class="log-entry"><h4>Agent Log</h4><p>Story text...</p></div>',
    21,  -- pořadí v příběhu
    FALSE,  -- FALSE = musí být odemčená
    20,  -- ID předchozí výzvy (NULL pro první)
    'Nápověda pro uživatele...',
    '<h3>Tutorial Content</h3><p>Jak na to...</p>',
    'SECRET_EASTER_EGG_CODE'  -- NULL pokud nemá easter egg
);
```

### Pro Produkci:
```php
// config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ctf_platform');
define('DB_USER', 'ctf_user');  // Vytvoř dedikovaného uživatele!
define('DB_PASS', 'silne_heslo_xyz123!@#');  // Silné heslo!
define('DEBUG_MODE', false);  // VYPNI DEBUG!
define('PASSWORD_MIN_LENGTH', 8);
```
### Další bezpečnost:
- Nastav `chmod 600 config.php` (Linux)
- Aktivuj HTTPS v `.htaccess` nebo odeber soubory README.md, FLAGYTEST.md, DOCUMENTATION.md, db_schema.sql

## 🐛 Troubleshooting

### Database connection failed
- Zkontroluj MySQL server (běží?)
- Ověř credentials v `config.php`
- Zkontroluj že databáze `ctf_platform` existuje

### Challenges se nenačítají
- F12 → Console (zkontroluj chyby)
- Ověř že `api.php` je dostupný
- Zkontroluj CSRF token (F5 refresh)

### Admin panel odmítá přístup
```sql
-- Zkontroluj is_admin flag
SELECT username, is_admin FROM users WHERE username = 'tvuj_user';

-- Nastav admin práva
UPDATE users SET is_admin = 1 WHERE username = 'tvuj_user';
```

## 📁 Struktura Projektu

```
ctf-platform/
├── index.php              # Main SPA
├── api.php                # AJAX API endpoint
├── config.php             # Configuration
├── db.php                 # Database class (Singleton)
├── csrf.php               # CSRF protection
├── xss.php                # XSS sanitization
├── scripts.js             # Frontend logic
├── styles.css             # Dark theme CSS
├── ADMIN_INSTALACE.md
├── FLAGYTEST.md
├── db_schema.sql      # Database + 20 seed challenges
├── admin/
│   ├── index.php          # Admin dashboard
│   ├── admin_actions.php  # Admin API
│   └── admin_migration.sql
├── challenges/            # Challenge HTML files

```

## 🎨 Customizace

### Změna Barev:
```css
/* styles.css */
:root {
    --color-neon-cyan: #00D1FF;    /* Hlavní accent */
    --color-neon-green: #00ff88;   /* Success */
    --color-neon-red: #ff0055;     /* Error */
}
```

## 📝 Licence

MIT License - volně použitelné pro vzdělávací účely

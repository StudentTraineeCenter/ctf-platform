# 📚 SHADOW PROTOCOL - Projektová Dokumentace

## 📁 Obsah

1. [Přehled Projektu](#přehled-projektu)
2. [Technická Architektura](#technická-architektura)
3. [Databáze](#databáze)
4. [API](#api)
5. [Admin Setup](#admin-setup)
6. [Bezpečnost](#bezpečnost)
7. [Příběh a Challenges](#příběh-a-challenges)

---

## Přehled Projektu

**Název:** Shadow Protocol CTF Platform  
**Verze:** 1.0.0  
**Typ:** Vzdělávací CTF platforma  
**Jazyk:** Čeština

### Cílová Skupina
Studenti SŠ/VŠ (15-25 let) začínající s CTF a kybernetickou bezpečností.

### Hlavní Funkce
- Příběhově řízená progrese (AI průvodce "Agent Byte")
- Offline mode s LocalStorage
- Auto-synchronizace progress po registraci
- 20 seed challenges (3 kapitoly)
- Admin panel s CRUD a statistikami
- Dark cyberpunk UI

---

## Technická Architektura

### Tech Stack

| Vrstva   | Technologie                |
|----------|----------------------------|
| Frontend | Vanilla JS (SPA), CSS3     |
| Backend  | PHP 7.4+, PDO              |
| Database | MySQL 5.7+ / MariaDB 10.3+ |


### Klíčové Soubory

| Soubor | Účel |
|--------|------|
| `index.php` | Main SPA, HTML struktura, 3 sekce |
| `api.php` | AJAX endpoint, 9 actions |
| `db.php` | Database class (Singleton pattern) |
| `config.php` | Konfigurace (DB, session, security) |
| `csrf.php` | CSRF token generování a validace |
| `xss.php` | XSS sanitizace a input validace |
| `scripts.js` | Frontend logika, LocalStorage, AJAX |
| `styles.css` | Dark theme, cyberpunk UI |
| `admin/index.php` | Admin dashboard |
| `admin/admin_actions.php` | Admin CRUD API |

---

## Databáze

### Schéma (5 tabulek)

#### 1. `users`
```sql
id, username, email, password_hash, total_score, total_progress, 
agent_rank, last_login, created_at, is_admin
```

#### 2. `challenges`
```sql
id, title, description (HTML), category, difficulty, points, 
flag_hash (bcrypt), hint_text, story_chapter, story_order, 
unlock_after_challenge_id, is_unlocked_default, created_at
```

#### 3. `user_progress`
```sql
id, user_id, challenge_id, status (locked/unlocked/in_progress/completed),
attempts, completed_at, created_at
```

#### 4. `agent_logs`
```sql
id, user_id, challenge_id, log_entry (HTML), created_at
```

#### 5. `discovered_easter_eggs`
```sql
id, user_id, challenge_id, easter_egg_code, discovered_at
```

### Relace
- `users` 1:N `user_progress` N:1 `challenges`
- `users` 1:N `agent_logs` N:1 `challenges`
- `users` 1:N `discovered_easter_eggs` N:1 `challenges`

### Status Flow
```
locked → unlocked → completed
```

## API

### Endpoint
`POST api.php`

### Response Format
```json
{
  "success": true|false,
  "message": "Text zprávy",
  "data": { ... }
}
```

### Actions

| Action | Parametry | Popis |
|--------|-----------|-------|
| `register` | username, email, password, confirm_password, local_progress | Registrace + sync |
| `login` | username, password, local_progress | Přihlášení + sync |
| `logout` | - | Odhlášení |
| `get_challenges` | - | Načtení všech challenges s user_status |
| `submit_flag` | challenge_id, flag | Validace flagu, unlock dalších |
| `get_stats` | - | User statistiky (score, progress, rank) |
| `get_logs` | - | Agent logs (story entries) |
| `discover_easter_egg` | challenge_id, easter_egg_code | Odeslání easter egg |
| `check_session` | - | Ověření platné session |

---

## Admin Setup

### Přístup
**URL:** `http://localhost/ctf-platform/admin/`

### Vytvoření Admin Účtu

   - vytvoř noví účet a z účtu admin/admin123 mu dej práva admina
   - poté se vrať na tvůj účet a smaž admin

## Příběh a Challenges

### Hlavní Příběh

Korporace NEXUS TECH byla hacknutá. Ty jsi vybrán do programu **SHADOW PROTOCOL** - elitní výcvik budoucích cyber-agentů. Tvůj průvodce je AI asistent **Agent Byte**.

### Struktura Kapitol

#### KAPITOLA 1: První Kontakt (Web Basics) - 8 challenges
```
1. Vítej v Matrix (10b) - Úvod do formátu flagů
2. View Source (15b) - HTML komentáře
3. Robot Hunters (20b) - robots.txt
4. Cookie Monster (25b) - HTTP cookies
5. Inspect Element (30b) - DevTools
6. Hidden Input (35b) - Hidden form fields
7. JavaScript Secrets (40b) - JS source code
8. POST Master (50b) - HTTP POST 🏆 BOSS
```

#### KAPITOLA 2: Tajné Zprávy (Cryptography) - 8 challenges
```
9. Caesar's Legacy (15b) - ROT13/Caesar cipher
10. Base Encoding (20b) - Base64
11. Hash Detective (25b) - MD5 lookup
12-16. ... další crypto challenges
```

#### KAPITOLA 3: Ztracené Stopy (Forensics) - 4 challenges
```
17-20. PCAP analýza, steganografie, metadata
```

### Flag Formát
```
FLAG{lowercase_with_underscores}
```

**Pro testovací flagy viz:** `FLAGYTEST.md`

---

## Quick Reference

### Konfigurace (config.php)
```php
DB_HOST = 'localhost'
DB_NAME = 'ctf_platform'
DB_USER = 'root'
DB_PASS = ''
DEBUG_MODE = false
PASSWORD_MIN_LENGTH = 6
SESSION_NAME = 'ctf_session'
SESSION_LIFETIME = 604800  // 7 dní
```

### CSS Proměnné (styles.css)
```css
--color-bg-primary: #0b0f15
--color-bg-secondary: #0f1720
--color-neon-cyan: #00D1FF
--color-neon-green: #00ff88
--color-neon-red: #ff0055
```

### Důležité Cesty
```
Root: http://localhost/ctf-platform/
Admin: http://localhost/ctf-platform/admin/
API: http://localhost/ctf-platform/api.php
```
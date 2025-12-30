# FLAGY - TESTOVACÍ PŘÍRUČKA PRO SPRÁVCE

> ⚠️ **DŮLEŽITÉ:** Tento soubor obsahuje všechny správné flagy. Nesmí být veřejně přístupný!
> Umísti mimo webroot nebo chraň .htaccess

## Kapitola 1: První Kontakt (Web Basics)

| # | Název | Flag | Body |
|---|-------|------|------|
| 1 | Vítej v Matrix | `FLAG{vitej_v_shadow_protocol_2025}` | 10 |
| 2 | View Source | `FLAG{html_zdroj_je_tvuj_pritel}` | 15 |
| 3 | Robot Hunters | `FLAG{robots_txt_neni_bezpecnost}` | 20 |
| 4 | Cookie Monster | `FLAG{cookies_jsou_na_klientovi}` | 25 |
| 5 | Inspect Element | `FLAG{html_je_jen_navrh}` | 30 |
| 6 | Hidden Input | `FLAG{hidden_neznamena_bezpecne}` | 35 |
| 7 | JavaScript Secrets | `FLAG{javascript_neni_bezpecne_uloziste}` | 40 |
| 8 | POST Master (BOSS) | `FLAG{http_post_metoda_zvladnuta}` | 50 |

## Kapitola 2: Tajné Zprávy (Cryptography)

| # | Název | Flag | Body |
|---|-------|------|------|
| 9 | Caesar's Legacy | `FLAG{caesar_shift_three}` (ROT13) | 15 |
| 10 | Base Encoding | `FLAG{base64_neni_sifrovani}` | 20 |
| 11 | Hexadecimal Hunt | `FLAG{hex_je_jenom_zakladni_system}` | 25 |
| 12 | XOR Mystery | `FLAG{xor_single_byte_weak}` | 35 |
| 13 | Substitution Cipher | `FLAG{substitution_crypto_takes_frequency}` | 40 |
| 14 | RSA Baby | `FLAG{rsa_male_prvocisla_bad}` | 50 |
| 15 | Vigenere Quest | `FLAG{vigenere_crypto_cracked}`  | 45 |
| 16 | Hash Collision (BOSS) | `FLAG{password}` (MD5 z "password") | 60 |

## Kapitola 3: Ztracené Stopy (Forensics)

| # | Název | Flag | Body |
|---|-------|------|------|
| 17 | Hidden in Plain Sight | `FLAG{strings_command_je_mocny}` | 20 |
| 18 | EXIF Explorer | `FLAG{metadata_prozradi_vse}` | 25 |
| 19 | Steganography 101 | `FLAG{steganografie_je_umeni_schovavani}` | 35 |
| 20 | ZIP Cracker | `FLAG{hesla_na_zipy_jsou_slaba}` (password: 2025) | 40 |


## Easter Eggs (Achievements)

Může být i easter egg

- `CHAPTER_1_MASTER` - POST Master

---

## Testovací Postup

1. **Import databáze:**
   ```bash
   mysql -u root < db_schema.sql
   ```

2. **Login jako demo_agent:**
   - Username: `demo`
   - Password: `admin123`
   - Username: `admin`
   - Password: `admin123`

3. **Testuj challenges postupně:**
   - Začni s 1 (Vítej v Matrix)
   - Ověř unlock mechanismus
   - Testuj hint system
   - Kontroluj story progression
   
---
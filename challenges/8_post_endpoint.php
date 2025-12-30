<?php
/**
 * Challenge 8: POST Master - Endpoint
 * Zpracovává POST requesty a vrací flag
 */

header('Content-Type: text/html; charset=utf-8');

// Kontrola, zda je požadavek POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error 405</title>
        <style>
            body {
                background: #0f0f1e;
                color: #e0e0e0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                padding: 40px;
                text-align: center;
            }
            .error-box {
                background: rgba(255, 71, 87, 0.1);
                border: 2px solid #ff4757;
                padding: 30px;
                border-radius: 10px;
                max-width: 600px;
                margin: 50px auto;
            }
            h1 { color: #ff4757; }
            code {
                background: rgba(255, 71, 87, 0.2);
                padding: 5px 10px;
                border-radius: 3px;
                color: #ff4757;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>❌ 405 Method Not Allowed</h1>
            <p>Tento endpoint akceptuje pouze <code>POST</code> požadavky!</p>
            <p>Tvá metoda: <code><?php echo htmlspecialchars($_SERVER['REQUEST_METHOD']); ?></code></p>
            <p><strong>Hint:</strong> Použij HTML formulář s <code>method="POST"</code> nebo cURL s <code>-X POST</code></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Kontrola parametrů
$action = isset($_POST['action']) ? $_POST['action'] : '';
$chapter = isset($_POST['chapter']) ? $_POST['chapter'] : '';

// Validace parametrů
if ($action !== 'unlock') {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Nesprávný parametr</title>
        <style>
            body {
                background: #0f0f1e;
                color: #e0e0e0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                padding: 40px;
                text-align: center;
            }
            .error-box {
                background: rgba(255, 193, 7, 0.1);
                border: 2px solid #ffc107;
                padding: 30px;
                border-radius: 10px;
                max-width: 600px;
                margin: 50px auto;
            }
            h1 { color: #ffc107; }
            code {
                background: rgba(255, 193, 7, 0.2);
                padding: 5px 10px;
                border-radius: 3px;
                color: #ffc107;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Nesprávný parametr</h1>
            <p>Parametr <code>action</code> musí být <code>unlock</code></p>
            <p>Tvá hodnota: <code><?php echo htmlspecialchars($action); ?></code></p>
            <p><strong>Hint:</strong> <code>action=unlock</code></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($chapter !== '1') {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Nesprávný parametr</title>
        <style>
            body {
                background: #0f0f1e;
                color: #e0e0e0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                padding: 40px;
                text-align: center;
            }
            .error-box {
                background: rgba(255, 193, 7, 0.1);
                border: 2px solid #ffc107;
                padding: 30px;
                border-radius: 10px;
                max-width: 600px;
                margin: 50px auto;
            }
            h1 { color: #ffc107; }
            code {
                background: rgba(255, 193, 7, 0.2);
                padding: 5px 10px;
                border-radius: 3px;
                color: #ffc107;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Nesprávný parametr</h1>
            <p>Parametr <code>chapter</code> musí být <code>1</code></p>
            <p>Tvá hodnota: <code><?php echo htmlspecialchars($chapter); ?></code></p>
            <p><strong>Hint:</strong> <code>chapter=1</code></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ÚSPĚCH! Správné parametry
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏆 ÚSPĚCH!</title>
    <style>
        body {
            background: #0f0f1e;
            color: #e0e0e0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 40px;
            text-align: center;
        }
        .success-box {
            background: rgba(46, 213, 115, 0.1);
            border: 3px solid #2ed573;
            padding: 40px;
            border-radius: 10px;
            max-width: 700px;
            margin: 50px auto;
        }
        h1 {
            color: #2ed573;
            font-size: 36px;
            margin-bottom: 20px;
        }
        .flag-display {
            font-size: 28px;
            font-weight: bold;
            color: #2ed573;
            background: rgba(46, 213, 115, 0.1);
            padding: 25px;
            border-radius: 8px;
            margin: 30px 0;
            border: 2px solid #2ed573;
            font-family: monospace;
        }
        .btn-copy {
            background: #2ed573;
            color: #000;
            border: none;
            padding: 15px 40px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
            margin-top: 15px;
        }
        .btn-copy:hover {
            background: #26de81;
        }
        .copy-notification {
            color: #2ed573;
            font-size: 14px;
            margin-top: 10px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .copy-notification.show {
            opacity: 1;
        }
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .info-box {
            background: rgba(0, 209, 255, 0.1);
            border: 2px solid #00D1FF;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            text-align: left;
        }
        code {
            background: rgba(0, 209, 255, 0.2);
            padding: 3px 8px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #00D1FF;
        }
    </style>
</head>
<body>
    <div class="success-box">
        <div class="success-icon">🏆</div>
        <h1>BOSS KAPITOLY 1 DOKONČEN!</h1>
        <p style="font-size: 20px;">Výborná práce! Úspěšně jsi odeslal POST request se správnými parametry.</p>
        
        <div class="flag-display">
            FLAG{http_post_metoda_zvladnuta}
        </div>
        
        <button class="btn-copy" onclick="copyFlag()">📋 ZKOPÍROVAT FLAG</button>
        <div class="copy-notification" id="copyNotification">✓ Flag zkopírován!</div>
        
        <div class="info-box">
            <h3 style="color: #00D1FF; margin-top: 0;">📚 Co jsi se naučil:</h3>
            <ul style="text-align: left;">
                <li>HTTP metody: <code>GET</code>, <code>POST</code>, <code>PUT</code>, <code>DELETE</code></li>
                <li>POST požadavky přenášejí data v těle requestu (ne v URL)</li>
                <li>Formuláře s <code>method="POST"</code></li>
                <li>Nástroje: cURL, Postman, Burp Suite, browser DevTools</li>
            </ul>
            
            <p><strong>Gratulace!</strong> Dokončil jsi celou první kapitolu. Pokračuj na další výzvy! 🚀</p>
        </div>
    </div>

    <script>
        function copyFlag() {
            const flag = "FLAG{http_post_metoda_zvladnuta}";
            navigator.clipboard.writeText(flag).then(() => {
                const notification = document.getElementById("copyNotification");
                notification.classList.add("show");
                setTimeout(() => {
                    notification.classList.remove("show");
                }, 2000);
            }).catch(err => {
                console.error('Chyba při kopírování:', err);
                alert('Flag: ' + flag);
            });
        }
    </script>
</body>
</html>

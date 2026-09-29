<?php
ob_start();

// Config dosyasını dahil et (session, DB bağlantısı ve $db nesnesi burada kuruluyor)
require_once __DIR__ . '/../config.php';

// Zaten giriş yapmışsa yönlendir
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

// Giriş işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            $admin = $db->fetchOne(
                "SELECT * FROM `admin_kullanicilar` WHERE `kullanici_adi` = ? AND `aktif` = 1",
                [$username]
            );

            if ($admin && password_verify($password, $admin['sifre_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['ad_soyad'];
                $_SESSION['admin_level'] = $admin['yetki_seviyesi'] ?? 'Admin';

                $db->query("UPDATE `admin_kullanicilar` SET `son_giris` = NOW() WHERE `admin_id` = ?", [$admin['admin_id']]);

                ob_end_clean();
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Kullanıcı adı veya şifre hatalı!';
            }
        } catch (Exception $e) {
            error_log('Admin login hatası: ' . $e->getMessage());
            $error = 'Bir hata oluştu, lütfen tekrar deneyin.';
        }
    } else {
        $error = 'Lütfen tüm alanları doldurun!';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Girişi - NK Teknoloji</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #141E30 0%, #35577D 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 48px;
            margin-bottom: 10px;
        }

        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #141E30;
            box-shadow: 0 0 0 3px rgba(20, 30, 48, 0.1);
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            user-select: none;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #141E30 0%, #35577D 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(20, 30, 48, 0.3);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #141E30;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
        }

        .info-box h3 {
            color: #141E30;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .info-box p {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
        }

        .info-box code {
            background: #fff;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">🔐</div>
            <h1>NK Teknoloji</h1>
            <p class="subtitle">Yönetim Paneli Girişi</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Şifre</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <span class="toggle-password" onclick="togglePassword()">👁️</span>
                </div>
            </div>

            <button type="submit" class="btn-login" id="submitBtn">Giriş Yap</button>
        </form>


    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = '👁️';
            }
        }

        let isSubmitting = false;
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Giriş yapılıyor...';
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>
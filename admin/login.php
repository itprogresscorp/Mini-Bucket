<?php
/*
 * Copyright (C) 2026 Mamontov Roman Igorevich
 *
 * This file is part of "Mini-Bucket - NAS Control Panel".
 *
 * Mini-Bucket - NAS Control Panel is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version, with the plugin exception (see LICENSE file).
 * Commercial use requires purchasing a separate commercial license from the copyright holder.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * https://mini-bucket.ru/
 */

require_once 'config.php';
require_once 'lang/loader.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	global $lang4403, $lang4404;
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            $log = $db->prepare("INSERT INTO system_logs (action, user) VALUES ('login', :user)");
            $log->bindValue(':user', $username, SQLITE3_TEXT);
            $log->execute();
            
            header('Location: index.php');
            exit;
        } else {
            $error = $lang4403;
        }
    } else {
        $error = $lang4404;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Login — Mini-B</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="css/loader.css">
    <link rel="shortcut icon" href="css/icon.ico" type="image/x-icon">
	<script>
	window.lang = {
		<?php
		for ($i = 1; $i <= 100; $i++) {
			$var_name = "lang$i";
			if (isset($$var_name)) {
				echo "'$i': '" . addslashes($$var_name) . "',\n";
			}
		}
		?>
	};
	function __(num) {
		return window.lang[num] || 'lang'+num;
	}
	console.log('Language loaded');
	</script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'SF Pro Display', 'Helvetica Neue', sans-serif;
            min-height: 100vh;
            background: #f5f5f7;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .gradient-bg {
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 20% 30%, rgba(88, 86, 214, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(0, 122, 255, 0.06) 0%, transparent 50%),
                        radial-gradient(circle at 40% 80%, rgba(175, 82, 222, 0.05) 0%, transparent 60%),
                        linear-gradient(135deg, #f5f5f7 0%, #e8e8ed 100%);
            z-index: -2;
            animation: slowDrift 24s ease-in-out infinite;
        }

        @keyframes slowDrift {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(1%, 1%) rotate(1deg); }
            66% { transform: translate(-0.5%, 0.5%) rotate(-0.5deg); }
        }

        .light-orb {
            position: fixed;
            width: 60vmax;
            height: 60vmax;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 122, 255, 0.12) 0%, transparent 70%);
            top: -20%;
            right: -10%;
            z-index: -1;
            filter: blur(80px);
            animation: orbMove 18s infinite alternate;
        }

        .light-orb-2 {
            position: fixed;
            width: 50vmax;
            height: 50vmax;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(88, 86, 214, 0.1) 0%, transparent 70%);
            bottom: -15%;
            left: -5%;
            z-index: -1;
            filter: blur(90px);
            animation: orbMove2 22s infinite alternate;
        }

        @keyframes orbMove {
            0% { transform: translate(0, 0) scale(1); opacity: 0.5; }
            100% { transform: translate(5%, 8%) scale(1.1); opacity: 0.8; }
        }

        @keyframes orbMove2 {
            0% { transform: translate(0, 0) scale(1); opacity: 0.4; }
            100% { transform: translate(-6%, -5%) scale(1.2); opacity: 0.7; }
        }

        .login-container {
            width: 100%;
            max-width: 460px;
            padding: 20px;
            z-index: 10;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(20px) saturate(180%);
            border-radius: 28px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04), 0 8px 24px rgba(0, 0, 0, 0.02), 0 0 0 0.5px rgba(0, 0, 0, 0.03);
            padding: 44px 38px;
            transition: all 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            border: 0.5px solid rgba(255, 255, 255, 0.6);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.8);
            box-shadow: 0 28px 48px rgba(0, 0, 0, 0.08), 0 0 0 0.5px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }

        .logo {
            text-align: center;
            margin-bottom: 36px;
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #ffffff, #ffffff);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 8px 20px rgba(0, 122, 255, 0.2);
        }

        .logo-icon i {
            font-size: 32px;
            color: white;
        }

        .logo h1 {
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #1c1c1e, #3a3a3e);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 4px;
        }

        .logo p {
            font-size: 14px;
            color: #8e8e93;
            font-weight: 400;
        }

        .input-group-custom {
            margin-bottom: 20px;
        }

        .input-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1e;
            margin-bottom: 8px;
            letter-spacing: -0.2px;
        }

        .input-field {
            width: 100%;
            padding: 14px 18px;
            font-size: 16px;
            font-weight: 400;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 14px;
            transition: all 0.2s ease;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', sans-serif;
            color: #1c1c1e;
        }

        .input-field:focus {
            outline: none;
            border-color: #007AFF;
            background: white;
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
        }

        .input-field::placeholder {
            color: #c6c6c8;
            font-weight: 400;
        }

        .btn-apple {
            width: 100%;
            padding: 14px 20px;
            background: #007AFF;
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 17px;
            font-weight: 590;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', sans-serif;
            transition: all 0.2s ease;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
        }

        .btn-apple:hover {
            background: #005bbf;
            transform: scale(0.98);
        }

        .btn-apple:active {
            transform: scale(0.97);
        }

        .alert-modern {
            background: rgba(255, 59, 48, 0.08);
            border: 1px solid rgba(255, 59, 48, 0.2);
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 14px;
            color: #ff3b30;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(8px);
        }

        .alert-modern i {
            font-size: 16px;
        }

        .footer-links {
            text-align: center;
            margin-top: 28px;
            font-size: 13px;
            color: #8e8e93;
        }

        .footer-links a {
            color: #007AFF;
            text-decoration: none;
            font-weight: 500;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        .divider {
            margin: 24px 0 0;
            height: 1px;
            background: rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 520px) {
            .glass-card {
                padding: 32px 24px;
            }
            .logo-icon {
                width: 56px;
                height: 56px;
            }
            .logo-icon i {
                font-size: 28px;
            }
            .logo h1 {
                font-size: 24px;
            }
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .glass-card {
            animation: cardAppear 0.5s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
        }
    </style>
</head>
<body>

<div class="gradient-bg"></div>
<div class="light-orb"></div>
<div class="light-orb-2"></div>

<div class="login-container">
    <div class="glass-card">
        <div class="logo">
            <div class="logo-icon">
			<img src="css/MINIB_LOGO.png" width="64px" height="64px"></img>
                <!--<i class="fas fa-bucket"></i>-->
            </div>
            <h1>Mini-B</h1>
            <p>NAS Control Panel</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-modern">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group-custom">
                <label class="input-label"><?php echo $lang4405; ?></label>
                <input type="text" name="username" class="input-field" placeholder="<?php echo $lang4406; ?>" autofocus required>
            </div>
            <div class="input-group-custom">
                <label class="input-label"><?php echo $lang4407; ?></label>
                <input type="password" name="password" class="input-field" placeholder="<?php echo $lang4408; ?>" required>
            </div>
            <button type="submit" class="btn-apple">
                <i class="fas fa-arrow-right"></i>
                <span><?php echo $lang4409; ?></span>
            </button>
        </form>

        <div class="divider"></div>
        <div class="footer-links">
            <!--<a href="#"><i class="far fa-question-circle"></i> Forgot password?</a>
            <span class="mx-2">•</span>
            <a href="#"><i class="fas fa-shield-alt"></i> Security</a>-->
        </div>
    </div>
</div>

<script src="js/loader.js"></script>
</body>
</html>
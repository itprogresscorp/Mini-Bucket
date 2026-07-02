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
 require_once '../lang/loader.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
	<link rel="stylesheet" href="../lib/bootstrap-icons-1.11.0/bootstrap-icons.css">
	<link rel="shortcut icon" href="../css/icon.ico" type="image/x-icon">
    <title>SSL Certificate Acceptance</title>
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
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Helvetica Neue", system-ui, sans-serif;
            background: #f2f2f7;   /* iOS light gray */
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .card {
            background: #ffffff;
            border-radius: 44px;          /* large, soft */
            max-width: 400px;
            width: 100%;
            padding: 40px 32px 36px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.04), 0 8px 20px rgba(0, 0, 0, 0.02);
            transition: box-shadow 0.2s ease;
            text-align: center;
        }

        .icon {
            font-size: 72px;
            line-height: 1.1;
            margin-bottom: 16px;
            display: block;
            font-weight: 300; /* light lock */
            letter-spacing: -0.5px;
        }

        h1 {
            font-size: 28px;
            font-weight: 620;
            letter-spacing: -0.3px;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .subhead {
            font-size: 16px;
            font-weight: 400;
            color: #3a3a3c;
            line-height: 1.5;
            margin-bottom: 28px;
            padding: 0 4px;
        }

        .step-box {
            background: #f8f8fc;
            border-radius: 20px;
            padding: 22px 20px 18px;
            margin-bottom: 28px;
            text-align: left;
            border: 0.5px solid rgba(60, 60, 67, 0.06);
        }

        .step-box h3 {
            font-size: 15px;
            font-weight: 600;
            color: #1c1c1e;
            letter-spacing: -0.2px;
            margin-bottom: 12px;
            padding-left: 4px;
        }

        .step-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .step-list li {
            font-size: 15px;
            font-weight: 400;
            color: #2c2c2e;
            padding: 8px 0 8px 28px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="%2334c759" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>') left center no-repeat;
            background-size: 18px;
            border-bottom: 0.5px solid rgba(60, 60, 67, 0.06);
        }

        .step-list li:last-child {
            border-bottom: none;
        }

        .step-list li:first-child {
            padding-top: 2px;
        }

        .btn-primary {
            background: #34c759;           /* iOS green */
            border: none;
            border-radius: 14px;
            padding: 16px 20px;
            width: 100%;
            font-size: 17px;
            font-weight: 600;
            color: white;
            letter-spacing: -0.2px;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.08s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(52, 199, 89, 0.2);
            margin-bottom: 18px;
        }

        .btn-primary:hover {
            background: #2db84e;
        }

        .btn-primary:active {
            background: #28a745;
            transform: scale(0.98);
        }

        .footnote {
            font-size: 13px;
            font-weight: 400;
            color: #8e8e93;
            letter-spacing: -0.1px;
            margin-top: 4px;
        }

        .divider {
            height: 1px;
            background: transparent;
            margin: 8px 0 16px;
        }

        .btn-primary:focus-visible {
            outline: 2px solid #34c759;
            outline-offset: 2px;
        }

        @media (max-width: 440px) {
            .card {
                padding: 32px 20px 28px;
                border-radius: 36px;
            }
            h1 {
                font-size: 26px;
            }
            .icon {
                font-size: 64px;
            }
            .step-box {
                padding: 18px 16px 14px;
            }
        }
    </style>
</head>
<body>
    <div class="card" role="main" aria-labelledby="title">
        <span class="icon" aria-hidden="true"><i class="bi bi-unlock"></i></span>

        <h1 id="title"><?php echo $lang4503; ?></h1>
        <p class="subhead">
            <?php echo $lang4504; ?><br>
            <?php echo $lang4505; ?>
        </p>

        <div class="step-box">
            <h3>✓ <?php echo $lang4506; ?></h3>
            <ul class="step-list">
                <li><?php echo $lang4507; ?></li>
                <li><?php echo $lang4508; ?></li>
                <li><?php echo $lang4509; ?></li>
            </ul>
        </div>

        <button class="btn-primary" id="closeBtn" type="button">
            <span>✕</span> <?php echo $lang4510; ?>
        </button>

        <p class="footnote">
            <?php echo $lang4511; ?>
        </p>
    </div>

    <script>
        (function() {
            'use strict';

            const closeButton = document.getElementById('closeBtn');

            if (window.opener && !window.opener.closed) {
                try {
                    window.opener.postMessage('certificate_accepted', '*');
                } catch (_) {
                }
            }

            function closeTab() {
                window.close();
                setTimeout(() => {
                    if (!window.closed) {
                        try {
                            window.location.replace('about:blank');
                        } catch (_) {
                        }
                    }
                }, 80);
            }

            closeButton.addEventListener('click', closeTab);
        })();
    </script>
</body>
</html>
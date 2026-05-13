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
 * https://mini-b.itp-corp.ru/
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SSL Certificate Acceptance</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            margin: 20px;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h2 {
            color: #333;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .steps {
            text-align: left;
            background: #f5f5f7;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        .steps ol {
            margin: 0;
            padding-left: 20px;
        }
        .steps li {
            margin: 10px 0;
            color: #555;
        }
        button {
            background: #34c759;
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
        }
        button:hover {
            background: #28a745;
        }
        .success {
            color: #34c759;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔓</div>
        <h2>SSL Certificate Accepted</h2>
        <p>
            You have successfully accepted the self-signed certificate.<br>
            You can now close this tab and return to the dashboard.
        </p>
        <div class="steps">
            <h3 style="margin-top: 0;">What happened?</h3>
            <ol>
                <li>✓ You accepted the self-signed certificate in your browser</li>
                <li>✓ The connection to the API server is now trusted</li>
                <li>✓ The dashboard can now fetch data securely</li>
            </ol>
        </div>
        <button onclick="window.close()">
            ✓ Close This Tab & Return to Dashboard
        </button>
        <p style="margin-top: 20px; font-size: 12px; color: #999;">
            If the tab doesn't close automatically, you can close it manually.
        </p>
    </div>
    
    <script>
        if (window.opener) {
            window.opener.postMessage('certificate_accepted', '*');
        }
    </script>
</body>
</html>
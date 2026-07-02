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
 
require_once 'lang/loader.php';
?>
<div id="popupBlockedModal" class="modal" style="display: none;">
    <div class="modal-overlay">
        <div class="modal-content">
            <!-- Заголовок -->
            <div class="modal-header">
                <div class="modal-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#FF9500" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <h2 class="modal-title"><?php echo $lang4512; ?></h2>
                <button class="modal-close" onclick="closePopupBlockedModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <!-- Тело -->
            <div class="modal-body">
                <div class="message">
                    <p class="message-primary"><?php echo $lang4513; ?></p>
                    <p class="message-secondary"><?php echo $lang4514; ?></p>
                </div>

                <div class="steps">
                    <div class="step-item">
                        <span class="step-number">1</span>
                        <div>
                            <span class="step-title"><?php echo $lang4515; ?></span>
                            <span class="step-desc"><?php echo $lang4516; ?></span>
                        </div>
                    </div>
                    <div class="step-item">
                        <span class="step-number">2</span>
                        <div>
                            <span class="step-title"><?php echo $lang4517; ?></span>
                            <span class="step-desc"><?php echo $lang4518; ?></span>
                        </div>
                    </div>
                    <div class="step-item">
                        <span class="step-number">3</span>
                        <div>
                            <span class="step-title"><?php echo $lang4519; ?></span>
                            <span class="step-desc"><?php echo $lang4520; ?></span>
                        </div>
                    </div>
                    <div class="step-item">
                        <span class="step-number">4</span>
                        <div>
                            <span class="step-title"><?php echo $lang4521; ?></span>
                            <span class="step-desc"><?php echo $lang4522; ?></span>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="manual-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#007AFF" stroke-width="2">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                    </svg>
                    <div>
                        <span class="manual-link-title"><?php echo $lang4523; ?></span>
                        <a href="#" target="_blank" id="manualCertLink" class="manual-link-url"><?php echo $lang4524; ?></a>
                    </div>
                </div>

                <div class="auto-reload">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#34C759" stroke-width="2">
                        <path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9"/>
                    </svg>
                    <div>
                        <span class="auto-reload-title"><?php echo $lang4525; ?></span>
                        <span class="auto-reload-desc"><?php echo $lang4526; ?></span>
                    </div>
                </div>
            </div>

            <!-- Футер с кнопками -->
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closePopupBlockedModal()">
                    <?php echo $lang4527; ?>
                </button>
                <button class="btn btn-primary" onclick="retryOpenCertWindow()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                        <path d="M23 4v6h-6"/>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                    </svg>
                    <?php echo $lang4528; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    position: relative;
    width: 100%;
    max-width: 400px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(30px);
    -webkit-backdrop-filter: blur(30px);
    border-radius: 20px;
    box-shadow: 
        0 20px 60px rgba(0, 0, 0, 0.3),
        0 0 0 0.5px rgba(0, 0, 0, 0.05) inset;
    overflow: hidden;
    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    align-items: center;
    padding: 20px 24px 16px;
    border-bottom: 0.5px solid rgba(60, 60, 67, 0.08);
    position: relative;
}

.modal-icon {
    flex-shrink: 0;
    margin-right: 12px;
    display: flex;
    align-items: center;
}

.modal-title {
    flex: 1;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Helvetica Neue', sans-serif;
    font-size: 17px;
    font-weight: 600;
    color: #000000;
    margin: 0;
    letter-spacing: -0.2px;
}

.modal-close {
    width: 30px;
    height: 30px;
    border: none;
    background: rgba(60, 60, 67, 0.08);
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
    flex-shrink: 0;
    color: #8E8E93;
    padding: 0;
}

.modal-close:hover {
    background: rgba(60, 60, 67, 0.15);
}

.modal-close svg {
    width: 18px;
    height: 18px;
}

.modal-body {
    padding: 24px;
}

.message {
    margin-bottom: 24px;
    text-align: center;
}

.message-primary {
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Helvetica Neue', sans-serif;
    font-size: 20px;
    font-weight: 600;
    color: #000000;
    margin: 0 0 6px 0;
    letter-spacing: -0.3px;
}

.message-secondary {
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', sans-serif;
    font-size: 15px;
    color: #8E8E93;
    margin: 0;
    line-height: 1.4;
}

.steps {
    margin: 0 0 24px 0;
}

.step-item {
    display: flex;
    align-items: flex-start;
    padding: 10px 0;
    gap: 14px;
}

.step-item:not(:last-child) {
    border-bottom: 0.5px solid rgba(60, 60, 67, 0.06);
}

.step-number {
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    background: #007AFF;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', sans-serif;
    font-size: 13px;
    font-weight: 600;
    margin-top: 1px;
}

.step-item > div {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.step-title {
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', sans-serif;
    font-size: 15px;
    font-weight: 500;
    color: #000000;
    letter-spacing: -0.2px;
}

.step-desc {
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', sans-serif;
    font-size: 13px;
    color: #8E8E93;
    line-height: 1.3;
}

.divider {
    height: 0.5px;
    background: rgba(60, 60, 67, 0.08);
    margin: 0 0 16px 0;
}

.manual-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: rgba(0, 122, 255, 0.06);
    border-radius: 12px;
    margin-bottom: 12px;
}

.manual-link > svg {
    flex-shrink: 0;
}

.manual-link > div {
    flex: 1;
    min-width: 0;
}

.manual-link-title {
    display: block;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #000000;
    margin-bottom: 2px;
}

.manual-link-url {
    display: block;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', sans-serif;
    font-size: 13px;
    color: #007AFF;
    text-decoration: none;
    word-break: break-all;
    transition: opacity 0.2s ease;
}

.manual-link-url:hover {
    opacity: 0.7;
    text-decoration: underline;
}

.auto-reload {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: rgba(52, 199, 89, 0.06);
    border-radius: 12px;
}

.auto-reload > svg {
    flex-shrink: 0;
}

.auto-reload > div {
    flex: 1;
}

.auto-reload-title {
    display: block;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #000000;
    margin-bottom: 2px;
}

.auto-reload-desc {
    display: block;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', sans-serif;
    font-size: 13px;
    color: #8E8E93;
}

.modal-footer {
    display: flex;
    gap: 8px;
    padding: 16px 24px 20px;
    border-top: 0.5px solid rgba(60, 60, 67, 0.08);
    background: rgba(242, 242, 247, 0.3);
}

.btn {
    flex: 1;
    padding: 14px 20px;
    border: none;
    border-radius: 14px;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', sans-serif;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    letter-spacing: -0.2px;
}

.btn-secondary {
    background: rgba(60, 60, 67, 0.08);
    color: #007AFF;
}

.btn-secondary:hover {
    background: rgba(60, 60, 67, 0.15);
}

.btn-secondary:active {
    transform: scale(0.97);
}

.btn-primary {
    background: #007AFF;
    color: white;
    box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
}

.btn-primary:hover {
    background: #0055CC;
    box-shadow: 0 4px 16px rgba(0, 122, 255, 0.4);
}

.btn-primary:active {
    transform: scale(0.97);
    box-shadow: 0 2px 8px rgba(0, 122, 255, 0.2);
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        transform: translateY(20px) scale(0.95);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

.modal-content::-webkit-scrollbar {
    width: 4px;
}

.modal-content::-webkit-scrollbar-track {
    background: transparent;
}

.modal-content::-webkit-scrollbar-thumb {
    background: rgba(60, 60, 67, 0.2);
    border-radius: 2px;
}

@media (max-width: 480px) {
    .modal-content {
        max-width: 100%;
        border-radius: 16px;
        margin: 10px;
    }
    
    .modal-header {
        padding: 16px 20px 12px;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .modal-footer {
        padding: 12px 20px 16px;
        flex-direction: column;
    }
    
    .btn {
        padding: 16px;
        font-size: 17px;
    }
}

@media (prefers-color-scheme: dark) {
    .modal-content {
        background: rgba(28, 28, 30, 0.95);
    }
    
    .modal-title,
    .message-primary,
    .step-title,
    .manual-link-title,
    .auto-reload-title {
        color: #FFFFFF;
    }
    
    .modal-close {
        background: rgba(255, 255, 255, 0.08);
        color: #AEAEB2;
    }
    
    .modal-close:hover {
        background: rgba(255, 255, 255, 0.15);
    }
    
    .message-secondary,
    .step-desc,
    .auto-reload-desc {
        color: #98989E;
    }
    
    .manual-link {
        background: rgba(0, 122, 255, 0.12);
    }
    
    .auto-reload {
        background: rgba(52, 199, 89, 0.12);
    }
    
    .btn-secondary {
        background: rgba(255, 255, 255, 0.08);
        color: #007AFF;
    }
    
    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.15);
    }
    
    .modal-footer {
        background: rgba(28, 28, 30, 0.5);
        border-top-color: rgba(255, 255, 255, 0.08);
    }
}
<style>
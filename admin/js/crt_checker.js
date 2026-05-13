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
 
let certWindowOpened = false;
let certWindowCheckInterval = null;
let certCheckInterval = null;
let lastCheckTime = 0;

async function checkApiAvailability() {
    const currentHostId = window.apiConfig?.isLocalhost ? 1 : null;
    
    if (window.apiConfig?.isLocalhost === true) {
        console.log('Localhost mode, certificate check disabled');
        return;
    }
    
    let apiBaseUrl = window.apiConfig?.apiBaseUrl;
    if (!apiBaseUrl) {
        console.log('No apiBaseUrl in config');
        return;
    }
    
    const now = Date.now();
    if (now - lastCheckTime < 10000) return;
    lastCheckTime = now;
    
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);
        
        const response = await fetch(apiBaseUrl + 'crt_checker.php', {
            method: 'GET',
            headers: {
                'X-API-Key': window.apiConfig?.apiKey || ''
            },
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (response.ok) {
            if (certWindowOpened) {
                console.log('API is now reachable, closing cert window');
                refreshAfterCertAccept();
            }
            return;
        }
        
        throw new Error(`HTTP ${response.status}`);
        
    } catch (err) {
        console.log('API not reachable:', err.message);
        
        if (err.message === 'Failed to fetch' || err.name === 'AbortError') {
            if (!certWindowOpened && (!window.certWindow || window.certWindow.closed)) {
                console.log('Connection error, showing certificate window...');
                showCertWindow();
            }
        }
    }
}

function showCertWindow() {
    if (window.certWindow && !window.certWindow.closed) {
        window.certWindow.focus();
        console.log('Cert window already open, focusing');
        return;
    }
    
    if (certWindowOpened) {
        console.log('Cert window already attempted recently');
        return;
    }
    
    let apiBaseUrl = window.apiConfig?.apiBaseUrl;
    if (!apiBaseUrl) return;
    
    let baseUrl = apiBaseUrl.replace(/\/?$/, '');
    
    if (baseUrl === '' || baseUrl === '/' || baseUrl === window.location.origin) {
        console.log('Localhost, skipping cert window');
        return;
    }
    
    console.log('Opening cert window at:', baseUrl + '/crt_accept.php');
    
    const popupUrl = baseUrl + '/crt_accept.php';
    
    certWindowOpened = true;
    window.certWindow = window.open(popupUrl, '_blank');
    
    if (!window.certWindow || window.certWindow.closed || typeof window.certWindow.closed === 'undefined') {
        console.log('Popup blocked!');
        showPopupBlockedModal(popupUrl);
        certWindowOpened = false;
        return;
    }
    
    if (certWindowCheckInterval) clearInterval(certWindowCheckInterval);
    certWindowCheckInterval = setInterval(() => {
        if (window.certWindow && window.certWindow.closed) {
            console.log('Cert window was closed by user');
            clearInterval(certWindowCheckInterval);
            certWindowCheckInterval = null;
            certWindowOpened = false;
            window.certWindow = null;
        }
    }, 1000);
    
    setTimeout(() => {
        if (certWindowOpened && (!window.certWindow || window.certWindow.closed)) {
            certWindowOpened = false;
            window.certWindow = null;
            if (certWindowCheckInterval) {
                clearInterval(certWindowCheckInterval);
                certWindowCheckInterval = null;
            }
        }
    }, 30000);
}

function showPopupBlockedModal(url) {
    let modal = document.getElementById('popupBlockedModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'popupBlockedModal';
        modal.className = 'license-modal';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div class="license-modal-content" style="max-width: 500px;">
                <div class="license-modal-header">
                    <div class="license-icon">
                        <i class="fas fa-ban" style="color: #ff9500;"></i>
                    </div>
                    <h2>Popup Blocked</h2>
                    <button class="license-close-btn" onclick="closePopupBlockedModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="license-modal-body">
                    <div class="license-summary">
                        <h3><i class="fas fa-exclamation-triangle" style="color: #ff9500;"></i> Browser blocked the popup window</h3>
                        <p>To accept the SSL certificate, please:</p>
                        <ol style="text-align: left; margin: 20px;">
                            <li>Click the browser's address bar</li>
                            <li>Look for a popup blocked icon (usually in the address bar)</li>
                            <li>Allow popups for this site</li>
                            <li>Click the button below to try again</li>
                        </ol>
                        <div class="summary-item">
                            <i class="fas fa-link"></i>
                            <div>
                                <strong>Or open manually:</strong>
                                <p><a href="${url}" target="_blank" id="manualCertLink">${url}</a></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="license-modal-footer">
                    <button class="btn-license-close" onclick="closePopupBlockedModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn-license-close" onclick="retryOpenCertWindow()" style="background: #007aff;">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    } else {
        const manualLink = modal.querySelector('#manualCertLink');
        if (manualLink) manualLink.href = url;
    }
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closePopupBlockedModal() {
    const modal = document.getElementById('popupBlockedModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function retryOpenCertWindow() {
    closePopupBlockedModal();
    certWindowOpened = false;
    showCertWindow();
}

function refreshAfterCertAccept() {
    console.log('Certificate accepted, reloading page...');
    certWindowOpened = false;
    if (window.certWindow) {
        window.certWindow.close();
        window.certWindow = null;
    }
    if (certWindowCheckInterval) {
        clearInterval(certWindowCheckInterval);
        certWindowCheckInterval = null;
    }
    window.location.reload();
}

window.addEventListener('message', function(event) {
    if (event.data === 'certificate_accepted') {
        refreshAfterCertAccept();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        checkApiAvailability();
        
        if (certCheckInterval) clearInterval(certCheckInterval);
        certCheckInterval = setInterval(checkApiAvailability, 10000);
    }, 1000);
});
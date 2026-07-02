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

let certWindowOpened = false;
let certWindowCheckInterval = null;
let certCheckInterval = null;
let lastCheckTime = 0;
let consecutiveErrors = 0;
const MAX_CONSECUTIVE_ERRORS = 3;

// Функция проверки доступности API (улучшенная)
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
            // API работает нормально
            consecutiveErrors = 0;
            if (certWindowOpened) {
                console.log('API is now reachable, closing cert window');
                refreshAfterCertAccept();
            }
            return;
        }
        
        // Получили ответ, но не 2xx
        console.log(`API returned error status: ${response.status}`);
        
        // Проверяем различные статусы ошибок
        if (response.status === 503) {
            console.log('Service unavailable (503) - possible backend/PHP issue');
            handleConnectionError('Service unavailable (503) - server configuration issue');
        } else if (response.status === 500) {
            console.log('Internal server error (500) - PHP error likely');
            handleConnectionError('Internal server error (500) - check server logs');
        } else if (response.status === 403 || response.status === 401) {
            console.log('Authentication error - API key issue');
            // Не открываем окно сертификата для ошибок авторизации
        } else {
            console.log(`Unhandled HTTP error: ${response.status}`);
            handleConnectionError(`HTTP error ${response.status}`);
        }
        
        throw new Error(`HTTP ${response.status}`);
        
    } catch (err) {
        console.log('API not reachable:', err.message);
        
        // Обрабатываем все типы ошибок соединения и сервера
        if (err.message === 'Failed to fetch' || 
            err.name === 'AbortError' ||
            err.message.includes('503') ||
            err.message.includes('500') ||
            err.message.includes('502') ||
            err.message.includes('504') ||
            err.message === 'Load failed' ||
            err.message.includes('NetworkError')) {
            
            handleConnectionError(err.message);
        }
    }
}

// Отдельная функция для обработки ошибок соединения
function handleConnectionError(errorMessage) {
    consecutiveErrors++;
    console.log(`Connection error (${consecutiveErrors}/${MAX_CONSECUTIVE_ERRORS}): ${errorMessage}`);
    
    // Открываем окно только после нескольких ошибок подряд (избегаем ложных срабатываний)
    if (consecutiveErrors >= MAX_CONSECUTIVE_ERRORS) {
        if (!certWindowOpened && (!window.certWindow || window.certWindow.closed)) {
            console.log('Multiple connection errors detected, showing certificate window...');
            showCertWindow();
        }
    } else if (!certWindowOpened && (!window.certWindow || window.certWindow.closed)) {
        // При первой ошибке тоже пробуем открыть, но с небольшой задержкой
        setTimeout(() => {
            if (!certWindowOpened && (!window.certWindow || window.certWindow.closed)) {
                console.log('Initial connection error, showing certificate window...');
                showCertWindow();
            }
        }, 2000);
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
    
    // Пробуем разные возможные пути к файлу принятия сертификата
    const possiblePaths = [
        baseUrl + '/crt_accept.php',
        baseUrl + '/api/crt_accept.php',
        baseUrl + '/certs/accept.php',
        baseUrl + '/ssl/accept.php'
    ];
    
    let popupUrl = possiblePaths[0];
    console.log('Opening cert window at:', popupUrl);
    
    // Сохраняем информацию о том, что окно открыто
    certWindowOpened = true;
    
    try {
        window.certWindow = window.open(popupUrl, '_blank', 'width=800,height=600,menubar=yes,toolbar=yes,location=yes');
        
        if (!window.certWindow || window.certWindow.closed || typeof window.certWindow.closed === 'undefined') {
            console.log('Popup blocked!');
            showPopupBlockedModal(popupUrl);
            certWindowOpened = false;
            return;
        }
        
        // Устанавливаем проверку закрытия окна
        if (certWindowCheckInterval) clearInterval(certWindowCheckInterval);
        certWindowCheckInterval = setInterval(() => {
            if (window.certWindow && window.certWindow.closed) {
                console.log('Cert window was closed by user');
                clearInterval(certWindowCheckInterval);
                certWindowCheckInterval = null;
                certWindowOpened = false;
                window.certWindow = null;
                consecutiveErrors = 0; // Сбрасываем счетчик при закрытии окна
            }
        }, 500);
        
        // Автоматическое закрытие через 5 минут (если пользователь забыл)
        setTimeout(() => {
            if (certWindowOpened && window.certWindow && !window.certWindow.closed) {
                console.log('Auto-closing cert window after 5 minutes');
                window.certWindow.close();
            }
        }, 300000);
        
    } catch (err) {
        console.error('Error opening cert window:', err);
        certWindowOpened = false;
        showPopupBlockedModal(popupUrl);
    }
}

function loadPopupBlockedModal(callback) {
    if (document.getElementById('popupBlockedModal')) {
        if (typeof callback === 'function') callback();
        return;
    }

    fetch('popup-blocked-modal.php')
        .then(response => response.text())
        .then(html => {
            document.body.insertAdjacentHTML('beforeend', html);
            if (typeof callback === 'function') callback();
        })
        .catch(error => {
            console.error('Error loading modal:', error);
            //createFallbackModal();
            if (typeof callback === 'function') callback();
        });
}

function showPopupBlockedModal(url) {
    loadPopupBlockedModal(function() {
        const modal = document.getElementById('popupBlockedModal');
        if (!modal) {
            console.error('Modal not found');
            return;
        }

        const manualLink = modal.querySelector('#manualCertLink');
        if (manualLink) {
            manualLink.href = url;
            manualLink.textContent = url;
        }

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    });
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
    setTimeout(() => showCertWindow(), 100);
}

function refreshAfterCertAccept() {
    console.log('Certificate accepted or API reachable, reloading page...');
    certWindowOpened = false;
    consecutiveErrors = 0;
    
    if (window.certWindow && !window.certWindow.closed) {
        window.certWindow.close();
        window.certWindow = null;
    }
    
    if (certWindowCheckInterval) {
        clearInterval(certWindowCheckInterval);
        certWindowCheckInterval = null;
    }
    
    // Перезагружаем страницу
    window.location.reload();
}

// Слушаем сообщение о принятии сертификата из дочернего окна
window.addEventListener('message', function(event) {
    if (event.data === 'certificate_accepted' || event.data === 'certificate_accept_complete') {
        console.log('Received certificate acceptance message');
        refreshAfterCertAccept();
    }
});

// Дополнительная проверка: если страница была открыта из окна сертификата
if (window.opener && window.location.pathname.includes('crt_accept')) {
    // Это страница принятия сертификата, уведомляем родителя
    setTimeout(() => {
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage('certificate_accepted', '*');
            console.log('Sent certificate acceptance to parent window');
        }
        // Закрываем это окно через 2 секунды
        setTimeout(() => window.close(), 2000);
    }, 1000);
}

// Запускаем проверку при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    console.log('Certificate checker initialized');
    
    // Первая проверка через 1 секунду
    setTimeout(() => {
        checkApiAvailability();
        
        // Запускаем периодическую проверку
        if (certCheckInterval) clearInterval(certCheckInterval);
        certCheckInterval = setInterval(checkApiAvailability, 10000);
    }, 1000);
    
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            console.log('Page became visible, checking API...');
            lastCheckTime = 0;
            checkApiAvailability();
        }
    });
});

// Экспортируем функции для отладки в консоли
window.debugCertChecker = {
    checkNow: checkApiAvailability,
    openWindow: showCertWindow,
    reset: () => {
        certWindowOpened = false;
        consecutiveErrors = 0;
        console.log('Certificate checker reset');
    },
    status: () => ({
        certWindowOpened,
        consecutiveErrors,
        certWindowExists: window.certWindow && !window.certWindow.closed,
        apiConfig: window.apiConfig
    })
};
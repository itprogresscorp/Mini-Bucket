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
 
async function loadHostsList() {
    try {
        const response = await fetch('api/host_selector.php?action=get_hosts');
        const data = await response.json();
        
        if (data.success && data.hosts) {
            const selector = document.getElementById('hostSelector');
            if (!selector) return;
            
            selector.innerHTML = '';
            
            data.hosts.forEach(host => {
                const option = document.createElement('option');
                option.value = host.idHost;
                
                const statusMarker = host.hostStatus === 'active' ? '🟢' : '🟡';
                
                option.textContent = `${statusMarker} ${host.hostName} (${host.hostIp})`;
                
                if (host.idHost == data.current_host_id) {
                    option.selected = true;
                }
                
                selector.appendChild(option);
            });
            
            selector.addEventListener('change', async function() {
                const hostId = this.value;
                
                const formData = new URLSearchParams();
                formData.append('action', 'set_current_host');
                formData.append('host_id', hostId);
                
                try {
                    const response = await fetch('api/host_selector.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showNotification('Host switched to ' + selector.options[selector.selectedIndex].textContent);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showNotification('Failed to switch host: ' + (result.error || 'Unknown error'), 'error');
                    }
                } catch (err) {
                    console.error('Error switching host:', err);
                    showNotification('Failed to switch host', 'error');
                }
            });
        } else {
            console.error('Failed to load hosts:', data.error);
            const selector = document.getElementById('hostSelector');
            if (selector) selector.innerHTML = '<option>Error loading hosts</option>';
        }
    } catch (err) {
        console.error('Failed to load hosts:', err);
        const selector = document.getElementById('hostSelector');
        if (selector) selector.innerHTML = '<option>Error loading hosts</option>';
    }
}

function showNotification(message, type = 'success') {
    const existingNotification = document.querySelector('.custom-notification');
    if (existingNotification) existingNotification.remove();
    
    const notification = document.createElement('div');
    notification.className = 'custom-notification';
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${type === 'error' ? '#ff3b30' : '#34c759'};
        color: white;
        padding: 12px 20px;
        border-radius: 10px;
        z-index: 10000;
        font-size: 14px;
        animation: fadeInOut 2s ease-in-out;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 2000);
	
}

if (!document.querySelector('#notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(20px); }
            15% { opacity: 1; transform: translateY(0); }
            85% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(20px); }
        }
    `;
    document.head.appendChild(style);
}

function updateTimestamp() {
    const timestampEl = document.getElementById('timestamp');
    if (timestampEl) {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('ru-RU');
        timestampEl.textContent = timeStr;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadHostsList();
    updateTimestamp();
    setInterval(updateTimestamp, 1000);
});
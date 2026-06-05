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

$menu_config = include(__DIR__ . '/config/conf_menu.php');
$current_page = basename($_SERVER['PHP_SELF']);

// Подключаем плагины
$plugins_dir = __DIR__ . '/plugins/menu/';
if (is_dir($plugins_dir)) {
    foreach (glob($plugins_dir . '*.php') as $plugin_file) {
        $plugin_data = include($plugin_file);
        if ($plugin_data['enabled'] ?? true) {
            $group = $plugin_data['group'];
            if (!isset($menu_config[$group])) {
                $menu_config[$group] = ['title' => $plugin_data['group_title'] ?? 'Плагины', 'items' => []];
            }
            $menu_config[$group]['items'][] = [
                'title' => $plugin_data['title'],
                'url' => $plugin_data['url'],
                'icon' => $plugin_data['icon'] ?? 'fas fa-plug'
            ];
        }
    }
}

// Сохраняем состояние групп в cookie
$open_groups = $_COOKIE['open_groups'] ?? '';
$open_groups = $open_groups ? explode(',', $open_groups) : [];

$group_index = 0;

$menu = '<aside class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-bucket"></i>
        <span>Mini-b</span>
    </div>
    <nav class="sidebar-nav">';

foreach ($menu_config as $group_key => $group) {
    if (empty($group['items'])) continue;
    
    $group_index++;
    $group_id = "menu-group-{$group_index}";
    
    $has_active = false;
    foreach ($group['items'] as $item) {
        if ($current_page == basename($item['url'])) {
            $has_active = true;
            break;
        }
    }
    
    $is_open = $has_active || in_array($group_id, $open_groups);
    $display_style = $is_open ? 'block' : 'none';
    $chevron_class = $is_open ? 'fa-chevron-down' : 'fa-chevron-right';
    
    $menu .= '<div class="menu-group" data-group-id="' . $group_id . '">
        <div class="menu-group-header" onclick="toggleMenuGroup(this)">
            <i class="fas fa-folder"></i>
            <span>' . htmlspecialchars($group['title']) . '</span>
            <i class="fas ' . $chevron_class . ' group-arrow"></i>
        </div>
        <div class="menu-group-content" style="display: ' . $display_style . '">';
    
    foreach ($group['items'] as $item) {
        $active = ($current_page == basename($item['url'])) ? 'active' : '';
        $menu .= '<a href="' . $item['url'] . '" class="nav-item ' . $active . '">
            <i class="' . $item['icon'] . '"></i>
            <span>' . htmlspecialchars($item['title']) . '</span>
        </a>';
    }
    
    $menu .= '</div></div>';
}

$menu .= '<hr>
        <!-- <a href="system.php" class="nav-item ' . ($current_page == 'system.php' ? 'active' : '') . '">
            <i class="fas fa-power-off"></i><span>System</span>
        </a> -->
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span>' . htmlspecialchars($_SESSION["username"] ?? 'Guest') . '</span>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</aside>';

// JS для групп
$menu .= '<script>
function toggleMenuGroup(header) {
    var group = header.parentElement;
    var content = group.querySelector(".menu-group-content");
    var arrow = header.querySelector(".group-arrow");
    var groupId = group.dataset.groupId;
    
    if (content.style.display === "none") {
        content.style.display = "block";
        arrow.classList.remove("fa-chevron-right");
        arrow.classList.add("fa-chevron-down");
        saveGroupState(groupId, true);
    } else {
        content.style.display = "none";
        arrow.classList.remove("fa-chevron-down");
        arrow.classList.add("fa-chevron-right");
        saveGroupState(groupId, false);
    }
}

function saveGroupState(groupId, isOpen) {
    var openGroups = getCookie("open_groups");
    var groups = openGroups ? openGroups.split(",") : [];
    
    if (isOpen) {
        if (!groups.includes(groupId)) {
            groups.push(groupId);
        }
    } else {
        groups = groups.filter(function(g) { return g !== groupId; });
    }
    
    document.cookie = "open_groups=" + groups.join(",") + "; path=/; max-age=31536000";
}

function getCookie(name) {
    var value = "; " + document.cookie;
    var parts = value.split("; " + name + "=");
    if (parts.length === 2) return parts.pop().split(";").shift();
    return "";
}
</script>';

return $menu;
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
 
return [
    'main' => [
        'title' => 'Panel',
        'icon' => 'fas fa-home',
        'items' => [
            ['title' => 'Dashboard', 'url' => 'index.php', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'File Manager', 'url' => 'files.php', 'icon' => 'fas fa-folder-open'],
			['title' => 'Mount Manager', 'url' => 'mount_master.php', 'icon' => 'fas fa-eject'],
			['title' => 'Hosts Manager', 'url' => 'hosts_manager.php', 'icon' => 'fas fa-cubes'],
        ]
    ],
    
    'storage' => [
        'title' => 'Storage',
        'icon' => 'fas fa-database',
        'items' => [
            ['title' => 'Disk Manager', 'url' => 'disk_manager.php', 'icon' => 'fas fa-hdd'],
			['title' => 'RAID Manager', 'url' => 'raid_manager.php', 'icon' => 'fas fa-server'],
            ['title' => 'LVM Manager', 'url' => 'lvm_manager.php', 'icon' => 'fas fa-layer-group'],
        ]
    ],
    
    'sharing' => [
        'title' => 'Resources',
        'icon' => 'fas fa-network-wired',
        'items' => [
            ['title' => 'SMB Shares', 'url' => 'shares_smb.php', 'icon' => 'fab fa-windows'],
            ['title' => 'NFS Shares', 'url' => 'shares_nfs.php', 'icon' => 'fab fa-linux'],
            ['title' => 'FTP Server', 'url' => 'ftp.php', 'icon' => 'fas fa-cloud-upload-alt'],
            ['title' => 'Rsync', 'url' => 'rsync.php', 'icon' => 'fas fa-sync-alt'],
        ]
    ],
    
    'users' => [
        'title' => 'Users',
        'icon' => 'fas fa-users',
        'items' => [
            ['title' => 'Users', 'url' => 'users.php', 'icon' => 'fas fa-user-friends'],
            
        ]
    ],
    
	'security' => [
        'title' => 'Security',
        'icon' => 'fas fa-shield',
        'items' => [
			['title' => 'SSL Manager', 'url' => 'ssl_manager.php', 'icon' => 'fa fa-certificate'],
			['title' => 'FireWall', 'url' => 'firewall.php', 'icon' => 'fa fa-exchange'],
        ]
    ],
	
	'tools' => [ 
        'title' => 'Tools',
        'icon' => 'fas fa-tools',
        'items' => [
			['title' => 'Diagnose', 'url' => 'diagnose.php', 'icon' => 'fa fa-bar-chart'],
			['title' => 'Health Monitor', 'url' => 'health_monitor.php', 'icon' => 'fa fa-heartbeat'],
        ]
    ],
	
    'system' => [
        'title' => 'System',
        'icon' => 'fas fa-cog',
        'items' => [
            ['title' => 'System Settings', 'url' => 'system.php', 'icon' => 'fas fa-sliders-h'],
			['title' => 'Mini-B Settings', 'url' => 'minib_settings.php', 'icon' => 'fas fa-bucket'],
			['title' => 'Update', 'url' => 'update_manager.php', 'icon' => 'fa fa-download'],
			['title' => 'Cron Jobs', 'url' => 'cron.php', 'icon' => 'fas fa-clock'],
			['title' => 'Console', 'url' => 'console.php', 'icon' => 'fas fa-terminal'],
        ]
    ]
];
 
# Mini Bucket — NAS Control Panel

![Image alt](https://github.com/itprogresscorp/Mini-Bucket/blob/main/screenshots/wall.png)


**Version 3.6.4**

A resource-efficient web-based NAS control panel for any hardware.  
`Debian 9` | `PHP 7.0` | `SQLite` | `Standard Linux Utilities`

No heavy frameworks. No Docker bloat. Just pure PHP, HTML, JS, and classic Linux tools.

---

## 📌 About

**Mini Bucket** is a lightweight yet powerful server management web panel designed for **resource efficiency on any hardware**.  
It works only with built-in PHP 7.0, HTML, JavaScript, and standard Linux utilities. No additional modules. No powerful hardware required.

### 🎯 Ideal for:

- Home NAS on any old PC or SBC
- Office file servers with limited resources
- **Raspberry Pi 1** and other single-board computers
- Repurposed enterprise hardware (128 MB RAM is enough)
- Edge servers and remote storage

### ✅ Tested on:

| Hardware | RAM | Status |
|----------|-----|--------|
| Raspberry Pi 1 | 256 MB | ✅ Fully working |
| Netgear Stora MS2000 | 128 MB | ✅ Fully working |
| Debian 9 on x86 | 512 MB+ | ✅ Fully working |

---

## 🚀 Key Features

### 📊 1. Live Dashboard
- CPU (total + per-core), temperature, RAM, Load Average
- Interactive graphs: CPU, network traffic (RX/TX), Disk I/O
- SMART status, temperature, disk space usage
- RAID & LVM status, mount points
- Network: IP, MAC, per-interface traffic

### 🔥 2. Firewall Manager (UFW)
- Enable/disable UFW, view status and rules
- CRUD operations + search & filter
- Preset rules: SSH, HTTP, HTTPS, FTP, MySQL, PostgreSQL
- IP blocking, active connections, colored UFW logs

### 📈 3. System Monitor + Diagnostics
- Real-time CPU, RAM, Disk, Uptime stats
- Tools: `ping`, `traceroute`, `netstat`, port scanner, DNS lookup, speed test
- Process and systemd service management
- System logs with filtering and export

### 🖥️ 4. Web Console (SSH via Browser)
- Full shell access
- VSCode-style dark theme with syntax highlighting
- Command history, XSS protection

### ⏰ 5. Cron Job Manager
- CRUD operations with flexible scheduling (5 cron fields)
- Quick presets, next run preview
- Logging, manual execution
- **Support for remote servers** — view and manage cron on other hosts

### 👥 6. User Management
- Panel Users (SQLite) + System Users (Linux)
- OS Group management
- Password generator with strength indicator

### 💾 7. Disk, RAID & LVM Management
- **Disk Manager:** GPT/MBR init, partitions, mounting, SMART
- **RAID Manager:** RAID 0,1,5,6,10, LINEAR, hot-spare, scrub
- **LVM Manager:** PV/VG/LV, snapshots
- **Mount Master:** mount local partitions, RAID, LVM, SMB/CIFS, NFS

### 📁 8. Dual-Panel File Manager
- Two independent panels, batch operations
- Background operations with progress & cancel
- Archive, permissions (chmod + ACL)
- Download folders as on-the-fly `.tar`

### 🌐 9. Sharing Services
- **FTP** (vsftpd) — start/stop, directories, SSL, limits
- **NFS** — exports, clients, statistics
- **SMB/CIFS** (Samba) — users, shares, sessions
- **Rsync** — daemon, modules, users

### 🛠️ 10. System Manager
- Service management (NFS, SMB, Rsync, FTP, SSH, Apache2, UFW, NTP)
- Power Management (reboot, shutdown)
- Date/Time (timezone, NTP), network (hostname, DHCP/Static, DNS)

### 🔍 11. System Checker
- **Check All / Fix All** buttons
- Categories: packages, services, permissions, config, firewall, network

---

### 🔌 12. Plugin System (NEW in 3.6.4)
 Mini Bucket now supports plugins — extend the panel with your own functionality!

 What plugins can do:

- Run on local host (where Mini-B is installed)

- Run on remote hosts via API

- Auto-install from main server repository to remote hosts

- Have their own SQLite database

- Use system APIs and authentication

- First plugins available:

📋 Log Manager — view, search, and manage system logs without SSH. Live mode, export, log clearing, file browser.

🧩 Plugin Template — starter template for developers. Ready structure, auth, DB examples, API integration.

Resources for developers:

📚 Plugin Development Documentation - https://mini-bucket.ru/wiki/knowledge-base/dev-pugins/plugin-development/

🗣 Forum for plugin discussions - https://mini-bucket.ru/community/community/plugins/

📦 All available plugins - https://mini-bucket.ru/plugins/

Install plugins via: System → Plugins → Repository → Install

---

### 🌍 13. Multilingual interface (NEW in 3.6.6)

- Available language packs: Russian, English
- Built-in plugin for convenient management of installed language packs
- Ability to edit and customize language packs

---

## 🔄 Key Rotation System (for multi-server setups)

In distributed systems with multiple servers (masters and slaves), Mini Bucket supports:

- **Dynamic API keys** — automatic rotation with zero downtime
- **Cascading key rotation** — changes automatically propagate to all connected servers
- **Unlimited hierarchy levels** — masters and slaves with arbitrary nesting depth
- **Fault tolerance** — exponential backoff retries

> This allows you to manage a whole cluster of NAS servers from a single panel.

---

## 🎨 UI/UX Features

- Card style with rounded corners, gradients, shadows
- Toast notifications (success/error/warning/info)
- Loading preloader
- Tab navigation with counters
- Hover effects and animations
- Auto-refresh for active tabs (2–30 seconds)
- Confirmation dialogs for dangerous actions

---

## 🔐 Security

- Critical files moved to `var/www/minib/` outside web root
- Authentication via `isAuthenticated()`
- Output escaping (`htmlspecialchars`)
- JSON storage (no direct writes to system configs without confirmation)
- AJAX API — all actions sync with the server
- Apache configuration hardened

---

## 📦 Installation

### Requirements:
- Debian 9 (Stretch) or compatible
- PHP 7.0+ (built-in modules only)
- SQLite 3
- Standard utilities: `mdadm`, `lvm2`, `ufw`, `vsftpd`, `samba`, `rsync`, `smartctl`, `nmap`, `traceroute`

### ⚠️ Important system requirement:
> **Mini Bucket must be installed on a clean system.**  
> It is not recommended to install it on a system where other services or packages are already present.  
> During installation, permissions for some system files will be changed, and modifications will be made to configuration files.

### Install command:
```bash
git clone https://github.com/itprogresscorp/Mini-Bucket.git
cd Mini-Bucket
chmod +x install.sh
bash install.sh
```

P.S. Feedback

The project lives and grows thanks to your ideas and bug reports.

Website - https://mini-bucket.ru/

Forum / Community — https://mini-bucket.ru/community/

Wiki — https://mini-bucket.ru/wiki/

Version history - https://mini-bucket.ru/updates/

Installation -  https://mini-bucket.ru/install/

Donations — https://mini-bucket.ru/donation/

And remember: Mini Bucket is not just another framework-based build. It's a lightweight panel for old hardware, built on pure PHP, JS, and HTML. And it works.







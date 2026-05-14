🖥️ Mini Bucket — NAS Control Panel

![](https://github.com/roman202401/Mini-Bucket/blob/main/screenshots/1.png)
![](https://github.com/roman202401/Mini-Bucket/blob/main/screenshots/2.png)

A resource-efficient web-based NAS control panel for any hardware
Debian 9 | PHP 7.0 | SQLite | Standard Linux Utilities

📌 About
Mini Bucket — NAS Control Panel is a lightweight yet powerful server management web panel designed for resource efficiency on any hardware. It works only with built-in PHP 7.0, HTML, JavaScript, and standard Linux utilities. No additional modules or powerful hardware required.

Ideal for:

Home NAS on any hardware

Office file servers with limited resources

Raspberry Pi and other SBCs

Repurposed enterprise hardware

Edge servers and remote storage

✅ Tested on:

Raspberry Pi 1 (256 MB RAM)

Netgear Stora MS2000 (128 MB RAM)

Debian 9 on x86 hardware

🚀 Key Features
📊 1. Live Dashboard
CPU (total + per-core), temperature, RAM, Load Average

Interactive graphs: CPU, network traffic (RX/TX), Disk I/O

SMART status, temperature, disk space usage

RAID & LVM status, mount points

Network: IP, MAC, per-interface traffic

🔥 2. Firewall Manager (UFW)
Enable/disable UFW, view status and rules

CRUD operations + search & filter

Preset rules: SSH, HTTP, HTTPS, FTP, MySQL, PostgreSQL

IP blocking, active connections, colored UFW logs

📈 3. System Monitor + Diagnostics
Real-time CPU, RAM, Disk, Uptime stats

Tools: ping, traceroute, netstat, port scanner, DNS lookup, speed test

Process and systemd service management

System logs with filtering and export

🖥️ 4. Web Console (SSH via Browser)
Full shell access

VSCode-style dark theme with syntax highlighting

Command history, XSS protection

⏰ 5. Cron Job Manager
CRUD operations with flexible scheduling (5 cron fields)

Quick presets, next run preview

Logging, manual execution

👥 6. User Management
Panel Users (SQLite) and System Users (Linux)

OS Group management

Password generator with strength indicator

💾 7. Disk, RAID & LVM Management
Disk Manager: GPT/MBR init, partitions, mounting, SMART

RAID Manager: RAID 0,1,5,6,10, LINEAR, hot-spare, scrub

LVM Manager: PV/VG/LV, snapshots

Mount Master: Mount local partitions, RAID, LVM, SMB/CIFS, NFS

📁 8. Dual-Panel File Manager
Two independent panels, batch operations

Background operations with progress & cancel

Archive (tar/zip), permissions (chmod + ACL)

Download folders as on-the-fly .tar

🌐 9. Sharing Services
FTP (vsftpd) — start/stop, directories, SSL, limits

NFS — exports, clients, statistics

SMB/CIFS (Samba) — users, shares, sessions

Rsync — daemon, modules, users

🛠️ 10. System Manager
Service management (NFS, SMB, Rsync, FTP, SSH, Apache2, UFW, NTP)

Power Management (reboot, shutdown)

Date/Time (timezone, NTP), network (hostname, DHCP/Static, DNS)

🔍 11. System Checker
Check All / Fix All

Categories: packages, services, permissions, config, firewall, network

🔁 Key Rotation System
In distributed systems with multiple servers (masters and slaves), the panel supports:

Dynamic API keys — automatic rotation with zero downtime

Cascading key rotation — changes automatically propagate to all connected servers

Unlimited hierarchy levels — masters and slaves with arbitrary nesting depth

Graceful rotation — old key remains active until new key is confirmed

Fault tolerance — exponential backoff retries

🎨 UI/UX Features
Card style with rounded corners, gradients, shadows

Toast notifications (success/error/warning/info)

Loading preloader

Tab navigation with counters

Hover effects and animations

Auto-refresh for active tabs (2–30 seconds)

Confirmation dialogs for dangerous actions

🔐 Security
Authentication via isAuthenticated()

Output escaping (htmlspecialchars)

JSON storage (no direct writes to system configs without confirmation)

AJAX API — all actions sync with the server

📦 Installation
Requirements:

Debian 9 (Stretch) or compatible

PHP 7.0+ (built-in modules only)

SQLite 3

Standard utilities: mdadm, lvm2, ufw, vsftpd, samba, rsync, smartctl, nmap, traceroute

Command install:

Important system requirement: Mini Bucket must be installed on a clean system. It is not recommended to install it on a system where other services or packages are already present. During the installation, permissions for some system files will be changed, and modifications will be made to configuration files.


<code>git clone https://github.com/itprogresscorp/Mini-Bucket.git</code>

<code>cd Mini-Bucket</code>

<code>chmod +x install.sh</code>

<code>bash install.sh</code>

The install script automatically checks compatibility, creates directories, configures permissions, initializes SQLite, and sets up base configurations.

🖥️ Multi-Server Management
Single sign-on for the entire cluster

API key rotation between servers

Centralized host status dashboard

📄 License
AGPLv3+ — GNU Affero General Public License version 3 or later.

✨ Summary
Mini Bucket — NAS Control Panel is a complete, resource-efficient, yet powerful NAS management panel for any hardware. Everything is managed from the browser — no command line knowledge required. With support for distributed infrastructure and automatic cascading key rotation.

Tested on Raspberry Pi 1 and Netgear Stora MS2000 (128 MB RAM).
Ready for home and small office environments.

Attention! Beta version! Under active development. Work is currently underway to resolve all identified issues. For any questions, please email me at sa@itp-corp.ru with the subject line "Mini-b".

https://mini-b.itp-corp.ru/

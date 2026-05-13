#!/bin/bash

# Скрипт установки Debian 9/10/11/12
# Лог установки: /var/log/install_script.log

LOG_FILE="/var/log/install_script.log"
ERROR_LOG="/var/log/install_script_error.log"

# Функция логирования
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

error_log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" | tee -a "$ERROR_LOG" >&2
}

# Функция проверки ошибок
check_error() {
    if [ $? -ne 0 ]; then
        error_log "$1"
        exit 1
    fi
}

# Проверка прав root
if [ "$EUID" -ne 0 ]; then 
    error_log "Пожалуйста, запустите скрипт с правами root (sudo)"
    exit 1
fi

# Получение IP адреса машины
SERVER_IP=$(ip -4 addr show | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | grep -v '127.0.0.1' | head -n1)
[ -z "$SERVER_IP" ] && SERVER_IP=$(hostname -I | awk '{print $1}')
[ -z "$SERVER_IP" ] && SERVER_IP="localhost"

log "Начало установки..."
log "IP адрес сервера: $SERVER_IP"

# Обновление системы
log "Обновление списка пакетов..."
apt update 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при выполнении apt update"

log "Обновление пакетов..."
apt upgrade -y 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при выполнении apt upgrade"

# Установка системных сервисов
log "Установка системных сервисов..."
apt install -y apache2 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке apache2"

apt install -y mc 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке mc"

apt install -y sqlite3 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке sqlite3"

apt install -y tar 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке tar"

apt install -y zip 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке zip"

apt install -y unzip 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке unzip"

apt install -y sudo 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке sudo"

apt install -y acl 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке acl"

apt install -y ntp 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке ntp"

apt install -y curl 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке curl"

apt install -y wget 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке wget"

apt install -y git 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке git"

# Установка сетевых утилит
log "Установка сетевых утилит..."
apt install -y traceroute 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке traceroute"

apt install -y dnsutils 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке dnsutils"

apt install -y net-tools 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке net-tools"

# Установка системных инструментов
log "Установка системных инструментов..."
apt install -y lvm2 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке lvm2"

apt install -y mdadm 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке mdadm"

apt install -y parted 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке parted"

apt install -y util-linux 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке util-linux"

apt install -y dosfstools 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке dosfstools"

apt install -y ntfs-3g 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке ntfs-3g"

apt install -y exfatprogs 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке exfatprogs"

apt install -y exfat-fuse 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке exfat-fuse"

apt install -y exfat-utils 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке exfat-utils"

apt install -y xfsprogs 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке xfsprogs"

apt install -y btrfs-progs 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке btrfs-progs"

apt install -y smartmontools 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке smartmontools"

apt install -y jq 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке jq"

# Установка сенсоров
log "Установка lm-sensors для мониторинга температуры..."
apt install -y lm-sensors 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке lm-sensors"

# Настройка сенсоров
log "Настройка сенсоров..."
if command -v sensors-detect &> /dev/null; then
    sensors-detect --auto 2>&1 | tee -a "$LOG_FILE"
fi

# Установка NFS, Samba и утилит
log "Установка NFS, Samba и утилит..."
apt install -y nfs-kernel-server 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке nfs-kernel-server"

apt install -y samba 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке samba"

apt install -y smbclient 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке smbclient"

apt install -y cifs-utils 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке cifs-utils"

apt install -y nfs-common 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке nfs-common"

apt install -y rsync 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке rsync"

apt install -y vsftpd 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке vsftpd"

apt install -y openssl 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке openssl"

# Установка PHP (стандартный)
log "Установка PHP..."
apt install -y php 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке php"

apt install -y php-cli 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке php-cli"

apt install -y php-fpm 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке php-fpm"

apt install -y php-common 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке php-common"

apt install -y php-zip 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке php-zip"

apt install -y php-sqlite3 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке php-sqlite3"

apt install -y php-ssh2 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке php-ssh2"

apt install -y php-curl 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке php-curl"

apt install -y php-mail 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке php-mail"

apt install -y php-mbstring 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при установке php-mbstring"

# Определение версии PHP и имени службы FPM
log "Определение версии PHP..."
PHP_VERSION=$(php -v 2>/dev/null | head -n1 | grep -oP 'PHP \K[0-9]+\.[0-9]+')
PHP_MAJOR_VERSION=$(echo $PHP_VERSION | cut -d. -f1)

if [ -n "$PHP_MAJOR_VERSION" ]; then
    PHP_FPM_SERVICE="php$PHP_MAJOR_VERSION-fpm"
    # Проверяем существует ли такая служба
    if ! systemctl list-unit-files | grep -q "$PHP_FPM_SERVICE"; then
        PHP_FPM_SERVICE="php-fpm"
    fi
else
    PHP_FPM_SERVICE="php-fpm"
fi

log "Версия PHP: $PHP_VERSION"
log "Служба PHP-FPM: $PHP_FPM_SERVICE"

# Создание директорий конфигурации
log "Создание директорий конфигурации..."
mkdir -p /etc/mdadm
mkdir -p /etc/lvm
mkdir -p /etc/samba/conf.d
mkdir -p /etc/apache2/sites-available
mkdir -p /var/www/minib
mkdir -p /var/www/html/admin/tmp

# Настройка прав для mdadm
log "Настройка прав для mdadm..."
chmod -R 777 /etc/mdadm 2>&1 | tee -a "$LOG_FILE"
chown -R www-data:www-data /etc/mdadm 2>&1 | tee -a "$LOG_FILE"

# Настройка прав для lvm
log "Настройка прав для lvm..."
chmod -R 777 /etc/lvm 2>&1 | tee -a "$LOG_FILE"
chown -R www-data:www-data /etc/lvm 2>&1 | tee -a "$LOG_FILE"

# Настройка Samba
log "Настройка Samba..."
chmod -R 777 /etc/samba/conf.d/ 2>&1 | tee -a "$LOG_FILE"
chown -R www-data:www-data /etc/samba/conf.d/ 2>&1 | tee -a "$LOG_FILE"

# Добавляем www-data в группу sudo
log "Добавление www-data в группу sudo..."
usermod -a -G sudo www-data
check_error "Ошибка при добавлении www-data в группу sudo"

# Настройка sudo без пароля для www-data
log "Настройка sudo без пароля для www-data..."
echo "www-data ALL=(ALL) NOPASSWD: ALL" > /etc/sudoers.d/www-data
chmod 440 /etc/sudoers.d/www-data
check_error "Ошибка при настройке sudo для www-data"

# Настройка ACL
log "Настройка ACL для /mnt..."
mkdir -p /mnt
setfacl -R -m u:www-data:rwX,d:u:www-data:rwX,g:users:rwX,d:g:users:rwX /mnt 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при настройке ACL"

# Копирование файлов проекта с проверкой существования
log "Копирование файлов проекта..."
if [ -d "minib" ] && [ -n "$(ls -A minib 2>/dev/null)" ]; then
    cp -rf minib/* /var/www/minib/ 2>&1 | tee -a "$LOG_FILE"
    log "Файлы minib скопированы"
else
    log "Директория minib не найдена или пуста, пропускаем..."
fi

if [ -d "admin" ] && [ -n "$(ls -A admin 2>/dev/null)" ]; then
    cp -rf admin/* /var/www/html/admin/ 2>&1 | tee -a "$LOG_FILE"
    log "Файлы admin скопированы"
else
    log "Директория admin не найдена или пуста, создаем тестовую..."
    echo "<?php phpinfo(); ?>" > /var/www/html/admin/index.php
fi

# Копирование базы данных SQLite
if [ -f "db.sqlite" ]; then
    cp db.sqlite /var/www/minib/ 2>&1 | tee -a "$LOG_FILE"
    log "База данных скопирована"
else
    log "Файл db.sqlite не найден, пропускаем..."
fi

# Настройка прав для директорий
log "Настройка прав для директорий..."
chmod -R 755 /var/www/minib 2>&1 | tee -a "$LOG_FILE"
chown -R www-data:www-data /var/www/minib 2>&1 | tee -a "$LOG_FILE"
chmod -R 755 /var/www/html/admin 2>&1 | tee -a "$LOG_FILE"
chown -R www-data:www-data /var/www/html/admin 2>&1 | tee -a "$LOG_FILE"
chmod -R 777 /var/www/html/admin/tmp 2>&1 | tee -a "$LOG_FILE"

# Включение модулей PHP
log "Включение модулей PHP..."
phpenmod ssh2 curl 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при включении модулей PHP"

# Включение модулей Apache
log "Включение модулей Apache..."
a2enmod rewrite 2>&1 | tee -a "$LOG_FILE"
a2enmod headers 2>&1 | tee -a "$LOG_FILE"
a2enmod ssl 2>&1 | tee -a "$LOG_FILE"

# Отключение стандартного сайта
log "Отключение стандартного сайта..."
a2dissite 000-default.conf 2>&1 | tee -a "$LOG_FILE"

# Создание конфигурации Apache для порта 1488
log "Создание конфигурации Apache для сайта на порту 1488..."
cat > /etc/apache2/sites-available/minib.conf << 'EOF'
Listen 1488

<VirtualHost *:1488>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/admin
    ServerName localhost
    
    <Directory /var/www/html/admin>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog /var/www/minib/admin_error.log
    CustomLog /var/www/minib/admin_access.log combined
    
    php_value max_execution_time 300
    php_value max_input_time 300
    php_value memory_limit 256M
</VirtualHost>
EOF

check_error "Ошибка при создании конфигурации Apache"

# Включение сайта
log "Включение сайта на порту 1488..."
a2ensite minib.conf 2>&1 | tee -a "$LOG_FILE"

# Проверка конфигурации Apache
log "Проверка конфигурации Apache..."
apache2ctl configtest 2>&1 | tee -a "$LOG_FILE"

# Перезапуск Apache
log "Перезапуск Apache..."
systemctl restart apache2 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при перезапуске Apache"

# Запуск PHP-FPM
log "Запуск PHP-FPM..."
systemctl enable "$PHP_FPM_SERVICE" 2>&1 | tee -a "$LOG_FILE"
systemctl restart "$PHP_FPM_SERVICE" 2>&1 | tee -a "$LOG_FILE"

# Запуск NTP
log "Запуск NTP..."
systemctl enable ntp 2>&1 | tee -a "$LOG_FILE"
systemctl restart ntp 2>&1 | tee -a "$LOG_FILE"

# Генерация SSH ключей
log "Генерация SSH ключей для root..."
if [ ! -f /root/.ssh/id_rsa ]; then
    ssh-keygen -t rsa -b 4096 -N "" -f /root/.ssh/id_rsa 2>&1 | tee -a "$LOG_FILE"
    check_error "Ошибка при генерации SSH ключей"
else
    log "SSH ключи уже существуют, пропускаем генерацию"
fi

# Добавление ключа в authorized_keys
log "Добавление публичного ключа в authorized_keys..."
cat /root/.ssh/id_rsa.pub >> /root/.ssh/authorized_keys
chmod 600 /root/.ssh/authorized_keys
chmod 700 /root/.ssh

# Копирование ключей в /key
log "Копирование SSH ключей в /key..."
mkdir -p /key
cp -rf /root/.ssh/* /key/ 2>&1 | tee -a "$LOG_FILE"
check_error "Ошибка при копировании SSH ключей"
chown -R www-data:www-data /key
check_error "Ошибка при смене владельца /key"

# Проверка SSH
log "Проверка работы SSH..."
ssh -o StrictHostKeyChecking=no localhost "echo OK" 2>&1 | tee -a "$LOG_FILE"
if [ $? -eq 0 ]; then
    log "SSH работает корректно"
else
    error_log "SSH проверка не удалась"
fi

# Настройка vsftpd
log "Настройка vsftpd..."
cat > /etc/vsftpd.conf << EOF
listen=YES
anonymous_enable=NO
local_enable=YES
write_enable=YES
local_umask=022
dirmessage_enable=YES
xferlog_enable=YES
connect_from_port_20=YES
local_root=/var/www/html
chroot_local_user=YES
allow_writeable_chroot=YES
EOF

systemctl restart vsftpd 2>&1 | tee -a "$LOG_FILE"
systemctl enable vsftpd 2>&1 | tee -a "$LOG_FILE"

# Настройка cron
log "Настройка cron для выполнения заданий..."
CRON_JOB_CRON="* * * * * php /var/www/html/admin/cron_runner.php > /dev/null 2>&1"
CRON_JOB_HEALTH="* * * * * php /var/www/html/admin/health_cron.php > /dev/null 2>&1"
CRON_JOB_ROTATE_KEY="* * * * * php /var/www/html/admin/api/key_rotation_task.php >> /var/www/minib/logs/cron_key_rotation.log 2>&1"
CRON_JOB_GENERATE_KEY="*/30 * * * * php /var/www/minib/cron/auto_rotate_key.php >> /var/www/minib/logs/cron_autorotate_key.log 2>&1"

if [ -f /var/www/html/admin/cron_runner.php ]; then
    (crontab -u www-data -l 2>/dev/null; echo "$CRON_JOB_CRON") | crontab -u www-data -
    log "Cron задание добавлено для www-data"
else
    log "Файл cron_runner.php не найден, пропускаем настройку cron"
fi

if [ -f /var/www/html/admin/health_cron.php ]; then
    (crontab -u www-data -l 2>/dev/null; echo "$CRON_JOB_HEALTH") | crontab -u www-data -
    log "Cron задание добавлено для www-data"
else
    log "Файл cron_runner.php не найден, пропускаем настройку cron"
fi

if [ -f /var/www/html/admin/api/key_rotation_task.php ]; then
    (crontab -u www-data -l 2>/dev/null; echo "$CRON_JOB_ROTATE_KEY") | crontab -u www-data -
    log "Cron задание добавлено для www-data"
else
    log "Файл key_rotation_task.php не найден, пропускаем настройку cron"
fi

if [ -f /var/www/minib/cron/auto_rotate_key.php ]; then
    (crontab -u www-data -l 2>/dev/null; echo "$CRON_JOB_GENERATE_KEY") | crontab -u www-data -
    log "Cron задание добавлено для www-data"
else
    log "Файл auto_rotate_key.php не найден, пропускаем настройку cron"
fi

# Настройка PHP параметров
log "Настройка PHP параметров..."
for php_ini in /etc/php/*/*/php.ini; do
    if [ -f "$php_ini" ]; then
        sed -i 's/max_execution_time = .*/max_execution_time = 300/' "$php_ini"
        sed -i 's/max_input_time = .*/max_input_time = 300/' "$php_ini"
        sed -i 's/memory_limit = .*/memory_limit = 256M/' "$php_ini"
        log "Обновлен $php_ini"
    fi
done

systemctl restart "$PHP_FPM_SERVICE" 2>&1 | tee -a "$LOG_FILE"

# Создание информационного файла
log "Создание информационного файла..."
cat > /root/installation_info.txt << EOF
=====================================
Установка завершена: $(date)
=====================================

Лог установки: $LOG_FILE
Лог ошибок: $ERROR_LOG

IP адрес сервера: $SERVER_IP
Версия PHP: $PHP_VERSION

Сайт доступен по адресу:
- http://$SERVER_IP:1488
- http://localhost:1488

Login: admin
Password: 1234

SSH ключи: /key/

=====================================
EOF

# Настройка UFW
log "Настройка UFW..."

# Установка UFW если не установлен
apt install -y ufw 2>&1 | tee -a "$LOG_FILE"

# Настройка правил
ufw allow 22/tcp comment 'Allow SSH access' 2>&1 | tee -a "$LOG_FILE"
ufw allow 1488/tcp comment '#MINI-B WEB Interface' 2>&1 | tee -a "$LOG_FILE"

# Включение UFW без подтверждения
ufw --force enable 2>&1 | tee -a "$LOG_FILE"

log "Установка успешно завершена!"
log "Информация сохранена в /root/installation_info.txt"

# Вывод информации
echo ""
echo "====================================="
echo "Установка успешно завершена!"
echo "====================================="
echo "Лог установки: $LOG_FILE"
echo "Лог ошибок: $ERROR_LOG"
echo ""
echo "Сайт доступен по адресу:"
echo "  http://$SERVER_IP:1488"
echo ""
echo "Перейдите в интерфейс для дальнейшей установки:"
echo "  http://$SERVER_IP:1488"
echo ""
echo "Login: admin"
echo "Password: 1234"
echo ""
echo "SSH ключи: /key/"
echo ""
echo "====================================="
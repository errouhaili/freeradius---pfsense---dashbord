#!/bin/bash
# =====================================================================
# Script d'installation complet - FreeRADIUS + pfSense + Dashboard
# Ubuntu Server - Installation "from scratch"
# =====================================================================

set -Eeuo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()  { echo -e "${YELLOW}[ATTENTION]${NC} $1"; }
error() { echo -e "${RED}[ERREUR]${NC} $1"; }

trap 'error "Échec à la ligne $LINENO. Consulte les messages ci-dessus."' ERR

if [ "$EUID" -ne 0 ]; then
    error "Ce script doit être lancé avec sudo/root. Exemple: sudo ./setup_full.sh"
    exit 1
fi

if [ -f /etc/os-release ]; then
    . /etc/os-release
    if [ "${ID:-}" != "ubuntu" ]; then
        warn "Ce script est prévu pour Ubuntu. Système détecté: ${PRETTY_NAME:-inconnu}"
        read -p "Continuer malgré tout ? (o/n) " CONTINUE_OS
        [ "$CONTINUE_OS" = "o" ] || exit 0
    fi
fi

echo "====================================================================="
echo "   INSTALLATION FreeRADIUS + pfSense + Dashboard"
echo "====================================================================="
echo ""

DEFAULT_REPO="https://github.com/errouhaili/freeradius---pfsense---dashbord.git"
read -p "URL du dépôt GitHub [$DEFAULT_REPO]: " REPO_URL
REPO_URL="${REPO_URL:-$DEFAULT_REPO}"

DEFAULT_DEST="/opt/freeradius-project"
read -p "Dossier de destination local [$DEFAULT_DEST]: " DEST_DIR
DEST_DIR="${DEST_DIR:-$DEFAULT_DEST}"

echo ""
info "Interfaces réseau disponibles :"
ip -o link show | awk -F': ' '{print "   - "$2}' | grep -v '^   - lo$' || true
echo ""
read -p "Nom de l'interface réseau à configurer (ex: eth0, ens33): " NET_IFACE
while [ -z "$NET_IFACE" ] || ! ip link show "$NET_IFACE" >/dev/null 2>&1; do
    warn "Interface inexistante ou vide."
    read -p "Nom de l'interface réseau: " NET_IFACE
done

DEFAULT_IP="192.168.0.20"
read -p "Adresse IP statique à assigner [$DEFAULT_IP]: " STATIC_IP
STATIC_IP="${STATIC_IP:-$DEFAULT_IP}"

DEFAULT_NETMASK="24"
read -p "Masque réseau (CIDR, ex: 24 pour /24) [$DEFAULT_NETMASK]: " NETMASK
NETMASK="${NETMASK:-$DEFAULT_NETMASK}"

DEFAULT_GATEWAY="192.168.0.200"
read -p "Passerelle (Gateway) [$DEFAULT_GATEWAY]: " GATEWAY
GATEWAY="${GATEWAY:-$DEFAULT_GATEWAY}"

DEFAULT_DNS="192.168.0.200"
read -p "Serveurs DNS (séparés par virgule) [$DEFAULT_DNS]: " DNS_SERVERS
DNS_SERVERS="${DNS_SERVERS:-$DEFAULT_DNS}"

echo ""
info "Configuration de la base de données MariaDB"
read -p "Nom de la base de données [radius]: " DB_NAME
DB_NAME="${DB_NAME:-radius}"

read -p "Nom d'utilisateur MariaDB pour l'application [radiususer]: " DB_APP_USER
DB_APP_USER="${DB_APP_USER:-radiususer}"

read -sp "Mot de passe pour cet utilisateur MariaDB: " DB_APP_PASS
echo ""
while [ -z "$DB_APP_PASS" ]; do
    warn "Le mot de passe ne peut pas être vide."
    read -sp "Mot de passe pour l'utilisateur MariaDB: " DB_APP_PASS
    echo ""
done

read -sp "Mot de passe root MariaDB (nouveau mot de passe): " DB_ROOT_PASS
echo ""
while [ -z "$DB_ROOT_PASS" ]; do
    warn "Le mot de passe root ne peut pas être vide."
    read -sp "Mot de passe root MariaDB: " DB_ROOT_PASS
    echo ""
done

echo ""
info "Sécurité RADIUS"
read -sp "Secret RADIUS partagé (clients.conf, pfSense <-> FreeRADIUS, min. 8 caractères): " RADIUS_SECRET
echo ""
while [ "${#RADIUS_SECRET}" -lt 8 ]; do
    warn "Le secret RADIUS doit contenir au moins 8 caractères."
    read -sp "Secret RADIUS partagé (min. 8 caractères): " RADIUS_SECRET
    echo ""
done

echo ""
info "Résumé de la configuration :"
echo "   Dépôt GitHub     : $REPO_URL"
echo "   Destination      : $DEST_DIR"
echo "   Interface réseau : $NET_IFACE"
echo "   IP statique      : $STATIC_IP/$NETMASK"
echo "   Gateway          : $GATEWAY"
echo "   DNS              : $DNS_SERVERS"
echo "   Base de données  : $DB_NAME (user: $DB_APP_USER)"
echo ""
warn "L'application de Netplan peut interrompre temporairement la connexion SSH."
read -p "Confirmer et lancer l'installation ? (o/n) " CONFIRM
[ "$CONFIRM" = "o" ] || { warn "Installation annulée."; exit 0; }

# ---------------------------------------------------------------------
# 1. Packages
# ---------------------------------------------------------------------
info "Étape 1/13 : Mise à jour du système et installation des paquets..."
export DEBIAN_FRONTEND=noninteractive
apt update
apt upgrade -y
apt install -y \
    curl wget git unzip net-tools vim htop tcpdump openssl \
    freeradius freeradius-mysql freeradius-utils \
    mariadb-server mariadb-client \
    apache2 php libapache2-mod-php php-mysql php-mysqli php-curl php-xml php-mbstring \
    netplan.io

systemctl enable --now mariadb
systemctl enable --now apache2

# ---------------------------------------------------------------------
# 2. Netplan
# ---------------------------------------------------------------------
info "Étape 2/13 : Configuration de l'adresse IP statique ($STATIC_IP)..."
NETPLAN_FILE="/etc/netplan/01-radius-static.yaml"
TIMESTAMP="$(date +%Y%m%d%H%M%S)"

if [ -f "$NETPLAN_FILE" ]; then
    cp "$NETPLAN_FILE" "${NETPLAN_FILE}.bak.$TIMESTAMP"
fi

IFS=',' read -ra DNS_ARR <<< "$DNS_SERVERS"
DNS_YAML=""
for dns in "${DNS_ARR[@]}"; do
    dns="$(echo "$dns" | xargs)"
    [ -n "$dns" ] && DNS_YAML="${DNS_YAML}
          - $dns"
done

cat > "$NETPLAN_FILE" <<EOF
network:
  version: 2
  ethernets:
    $NET_IFACE:
      dhcp4: false
      addresses:
        - $STATIC_IP/$NETMASK
      routes:
        - to: default
          via: $GATEWAY
      nameservers:
        addresses:$DNS_YAML
EOF

chmod 600 "$NETPLAN_FILE"

if command -v netplan >/dev/null 2>&1; then
    if netplan generate; then
        # "try" is safer than blindly applying a bad config.
        if netplan try --timeout 20; then
            info "Configuration Netplan appliquée."
        else
            warn "netplan try a échoué. La configuration réseau n'a pas été confirmée."
            warn "Vérifie $NETPLAN_FILE avant de continuer."
        fi
    else
        error "Configuration Netplan invalide. Vérifie $NETPLAN_FILE."
        exit 1
    fi
fi

# ---------------------------------------------------------------------
# 3. MariaDB
# ---------------------------------------------------------------------
info "Étape 3/13 : Configuration de MariaDB..."

# MariaDB/Ubuntu utilise souvent unix_socket pour root lors d'une première
# installation. Sur une machine déjà configurée par ce script, root a déjà
# un mot de passe. On essaie donc plusieurs méthodes de connexion, dans
# l'ordre, sans jamais faire échouer le script si l'une d'elles marche.

MYSQL_ROOT_CONNECT=""

if mysql --protocol=socket -u root -e "SELECT 1;" >/dev/null 2>&1; then
    info "Connexion root via socket (installation fraîche détectée)."
    MYSQL_ROOT_CONNECT="mysql --protocol=socket -u root"
elif mysql -u root -p"$DB_ROOT_PASS" -e "SELECT 1;" >/dev/null 2>&1; then
    info "Connexion root via mot de passe déjà configuré (script déjà exécuté avant)."
    MYSQL_ROOT_CONNECT="mysql -u root -p$DB_ROOT_PASS"
else
    error "Impossible de se connecter à MariaDB en tant que root (ni via socket, ni avec le mot de passe fourni)."
    error "Le mot de passe root actuel ne correspond pas à celui saisi. Réinitialise-le manuellement :"
    error "  sudo systemctl stop mariadb"
    error "  sudo mysqld_safe --skip-grant-tables --skip-networking &"
    error "  mysql -u root  # puis: ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('...');"
    exit 1
fi

$MYSQL_ROOT_CONNECT <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('${DB_ROOT_PASS//\'/\'\'}');
CREATE DATABASE IF NOT EXISTS \`${DB_NAME//\`/}\`;
CREATE USER IF NOT EXISTS '${DB_APP_USER//\'/\'\'}'@'localhost' IDENTIFIED BY '${DB_APP_PASS//\'/\'\'}';
ALTER USER '${DB_APP_USER//\'/\'\'}'@'localhost' IDENTIFIED BY '${DB_APP_PASS//\'/\'\'}';
GRANT ALL PRIVILEGES ON \`${DB_NAME//\`/}\`.* TO '${DB_APP_USER//\'/\'\'}'@'localhost';
FLUSH PRIVILEGES;
SQL

# ---------------------------------------------------------------------
# 4. GitHub
# ---------------------------------------------------------------------
info "Étape 4/13 : Récupération du dépôt GitHub..."
if [ -d "$DEST_DIR/.git" ]; then
    warn "Le dépôt existe déjà, récupération des dernières modifications..."
    git -C "$DEST_DIR" fetch --all --prune
    git -C "$DEST_DIR" pull --ff-only
elif [ -d "$DEST_DIR" ]; then
    warn "$DEST_DIR existe mais n'est pas un dépôt Git."
    warn "Sauvegarde puis remplacement impossible automatiquement."
    exit 1
else
    mkdir -p "$(dirname "$DEST_DIR")"
    git clone "$REPO_URL" "$DEST_DIR"
fi

cd "$DEST_DIR"

# ---------------------------------------------------------------------
# 5. Import SQL
# ---------------------------------------------------------------------
info "Étape 5/13 : Import de la base de données..."

# 5a. Schéma officiel FreeRADIUS (radcheck, radreply, radgroupcheck,
#     radgroupreply, radusergroup, radpostauth, radacct, nas...).
#     Fourni par le paquet freeradius-mysql. Nécessaire pour que
#     FreeRADIUS et le dashboard fonctionnent, en plus du radius.sql
#     "métier" du projet (admins, nas, nasreload...).
FR_SCHEMA="/etc/freeradius/3.0/mods-config/sql/main/mysql/schema.sql"
if [ -f "$FR_SCHEMA" ]; then
    info "Import du schéma officiel FreeRADIUS (schema.sql)..."
    # Tolérant aux ré-exécutions : les tables peuvent déjà exister.
    mysql -u root -p"$DB_ROOT_PASS" "$DB_NAME" < "$FR_SCHEMA" 2>/tmp/schema_import.log || \
        warn "Import de schema.sql : certaines tables existent peut-être déjà (voir /tmp/schema_import.log)."
else
    warn "schema.sql introuvable à $FR_SCHEMA (paquet freeradius-mysql absent ?)."
fi

# 5b. Données/tables spécifiques au projet (admins, nas, nasreload...).
if [ -f "database_mysql/radius.sql" ]; then
    mysql -u root -p"$DB_ROOT_PASS" "$DB_NAME" < database_mysql/radius.sql
    info "Base de données importée avec succès."
else
    warn "Aucun fichier database_mysql/radius.sql trouvé dans le dépôt, étape ignorée."
fi

# ---------------------------------------------------------------------
# 6. FreeRADIUS
# ---------------------------------------------------------------------
info "Étape 6/13 : Déploiement de la configuration FreeRADIUS..."

if [ -f "3.0.zip" ]; then
    TMP_EXTRACT="$(mktemp -d)"
    unzip -o -q "3.0.zip" -d "$TMP_EXTRACT"

    # Le zip peut contenir soit un dossier "3.0/..." soit directement
    # le contenu (mods-enabled/, clients.conf, etc.) à la racine.
    if [ -d "$TMP_EXTRACT/3.0" ]; then
        SRC_DIR="$TMP_EXTRACT/3.0"
    else
        SRC_DIR="$TMP_EXTRACT"
    fi

    BACKUP_DIR="/etc/freeradius/3.0.backup.$(date +%Y%m%d%H%M%S)"
    info "Sauvegarde de la config actuelle vers $BACKUP_DIR"
    mkdir -p "$BACKUP_DIR"
    cp -a /etc/freeradius/3.0/. "$BACKUP_DIR/" 2>/dev/null || true

    cp -a "$SRC_DIR/." /etc/freeradius/3.0/
    chown -R freerad:freerad /etc/freeradius/3.0/
    rm -rf "$TMP_EXTRACT"

    info "Configuration FreeRADIUS (3.0.zip) déployée."

    # ---- Synchronisation des identifiants MySQL dans mods-enabled/sql ----
    # Le fichier importé peut contenir un ancien login/password (ex: "radius")
    # qui ne correspond pas au compte MySQL réellement créé par ce script.
    SQL_MOD="/etc/freeradius/3.0/mods-enabled/sql"
    if [ -f "$SQL_MOD" ]; then
        info "Synchronisation des identifiants MySQL dans mods-enabled/sql..."
        cp -a "$SQL_MOD" "$BACKUP_DIR/sql.before-sync" 2>/dev/null || true

        sed -i -E "s/^([[:space:]]*login[[:space:]]*=[[:space:]]*).*/\1\"${DB_APP_USER//\"/\\\"}\"/" "$SQL_MOD"
        sed -i -E "s/^([[:space:]]*password[[:space:]]*=[[:space:]]*).*/\1\"${DB_APP_PASS//\"/\\\"}\"/" "$SQL_MOD"
        sed -i -E "s/^([[:space:]]*radius_db[[:space:]]*=[[:space:]]*).*/\1\"${DB_NAME//\"/\\\"}\"/" "$SQL_MOD"

        chown freerad:freerad "$SQL_MOD"
        info "Identifiants MySQL synchronisés (login=$DB_APP_USER, db=$DB_NAME)."
    else
        warn "Fichier mods-enabled/sql introuvable après déploiement, synchronisation ignorée."
    fi

    # ---- Synchronisation du secret RADIUS partagé dans clients.conf ----
    CLIENTS_CONF="/etc/freeradius/3.0/clients.conf"
    if [ -f "$CLIENTS_CONF" ]; then
        info "Synchronisation du secret RADIUS partagé dans clients.conf..."
        cp -a "$CLIENTS_CONF" "$BACKUP_DIR/clients.conf.before-secret-sync" 2>/dev/null || true

        sed -i -E "s/^([[:space:]]*secret[[:space:]]*=[[:space:]]*).*/\1\"${RADIUS_SECRET//\"/\\\"}\"/" "$CLIENTS_CONF"

        chown freerad:freerad "$CLIENTS_CONF"
        info "Secret RADIUS partagé synchronisé dans clients.conf."
        warn "Pense à reporter EXACTEMENT le même secret côté pfSense (config du serveur RADIUS)."
    else
        warn "clients.conf introuvable après déploiement, synchronisation du secret ignorée."
    fi
else
    warn "Aucun fichier 3.0.zip trouvé à la racine du dépôt, étape ignorée."
fi

# ---------------------------------------------------------------------
# 7. Dashboard
# ---------------------------------------------------------------------
info "Étape 7/13 : Déploiement du dashboard..."

if [ -d "radius-dashboard" ]; then
    mkdir -p /var/www/html/radius-dashboard
    cp -a radius-dashboard/. /var/www/html/radius-dashboard/

    # Si config.php existe, on crée une copie puis on remplace les variables
    # les plus courantes. Le script ne modifie pas arbitrairement un fichier
    # qui n'existe pas.
    if [ -f "/var/www/html/radius-dashboard/config.php" ]; then
        cp -a /var/www/html/radius-dashboard/config.php \
              "/var/www/html/radius-dashboard/config.php.bak.$TIMESTAMP"

        # Remplacement des constantes PHP define('DB_XXX', '...') utilisées
        # par ce projet (config.php généré via define(), pas des $variables).
        sed -i -E "s/(define\\([[:space:]]*'DB_NAME'[[:space:]]*,[[:space:]]*)'[^']*'/\\1'${DB_NAME//\'/\\\'}'/" \
            /var/www/html/radius-dashboard/config.php || true
        sed -i -E "s/(define\\([[:space:]]*'DB_USER'[[:space:]]*,[[:space:]]*)'[^']*'/\\1'${DB_APP_USER//\'/\\\'}'/" \
            /var/www/html/radius-dashboard/config.php || true
        sed -i -E "s/(define\\([[:space:]]*'DB_PASS'[[:space:]]*,[[:space:]]*)'[^']*'/\\1'${DB_APP_PASS//\'/\\\'}'/" \
            /var/www/html/radius-dashboard/config.php || true
        sed -i -E "s/(define\\([[:space:]]*'DB_HOST'[[:space:]]*,[[:space:]]*)'[^']*'/\\1'localhost'/" \
            /var/www/html/radius-dashboard/config.php || true

        # Compatibilité : au cas où une autre version du projet utiliserait
        # des $variables classiques plutôt que define().
        sed -i -E "s/^([[:space:]]*\\\$DB_NAME[[:space:]]*=[[:space:]]*).*/\\1'${DB_NAME//\'/\\\'}';/" \
            /var/www/html/radius-dashboard/config.php || true
        sed -i -E "s/^([[:space:]]*\\\$DB_USER[[:space:]]*=[[:space:]]*).*/\\1'${DB_APP_USER//\'/\\\'}';/" \
            /var/www/html/radius-dashboard/config.php || true
        sed -i -E "s/^([[:space:]]*\\\$DB_PASS(WORD|word|Word)?[[:space:]]*=[[:space:]]*).*/\\1'${DB_APP_PASS//\'/\\\'}';/" \
            /var/www/html/radius-dashboard/config.php || true
    else
        warn "config.php absent : aucun fichier de configuration DB automatique à modifier."
    fi

    chown -R www-data:www-data /var/www/html/radius-dashboard
    find /var/www/html/radius-dashboard -type d -exec chmod 755 {} \;
    find /var/www/html/radius-dashboard -type f -exec chmod 644 {} \;

    # Laisse la racine Apache pointer vers le dashboard si index.php existe.
    if [ -f "/var/www/html/radius-dashboard/index.php" ]; then
        cat > /etc/apache2/conf-available/radius-dashboard.conf <<EOF
Alias /radius-dashboard /var/www/html/radius-dashboard

<Directory /var/www/html/radius-dashboard>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php index.html
</Directory>
EOF
        a2enconf radius-dashboard >/dev/null
    fi

    info "Dashboard déployé dans /var/www/html/radius-dashboard"
else
    warn "Aucun dossier radius-dashboard trouvé."
fi

if [ -d "netplan" ]; then
    warn "Un dossier netplan existe dans le dépôt. Il n'est PAS appliqué automatiquement."
fi

# ---------------------------------------------------------------------
# 8. HTTPS (certificat auto-signé) pour le dashboard
# ---------------------------------------------------------------------
info "Étape 8/13 : Activation de HTTPS (certificat auto-signé) pour le dashboard..."

a2enmod ssl >/dev/null 2>&1 || true
mkdir -p /etc/ssl/radius-dashboard

if [ ! -f /etc/ssl/radius-dashboard/dashboard.crt ]; then
    openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
        -keyout /etc/ssl/radius-dashboard/dashboard.key \
        -out /etc/ssl/radius-dashboard/dashboard.crt \
        -subj "/C=MA/ST=Morocco/L=Local/O=RadiusDashboard/CN=${STATIC_IP}" \
        >/tmp/openssl_gen.log 2>&1 || warn "Génération du certificat auto-signé échouée, voir /tmp/openssl_gen.log"
fi

if [ -f /etc/ssl/radius-dashboard/dashboard.crt ]; then
    cat > /etc/apache2/sites-available/radius-dashboard-ssl.conf <<EOF
<VirtualHost *:443>
    ServerName ${STATIC_IP}
    DocumentRoot /var/www/html
    SSLEngine on
    SSLCertificateFile /etc/ssl/radius-dashboard/dashboard.crt
    SSLCertificateKeyFile /etc/ssl/radius-dashboard/dashboard.key
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
    a2ensite radius-dashboard-ssl.conf >/dev/null 2>&1 || true
    info "HTTPS activé (certificat auto-signé, valable 825 jours). Le navigateur affichera un avertissement de sécurité, c'est normal avec un certificat auto-signé."
    warn "Accès HTTPS : https://${STATIC_IP}/radius-dashboard/ (avertissement 'non sécurisé' attendu, cliquer sur Avancé -> Continuer)."
else
    warn "Certificat non généré, HTTPS non activé."
fi

# ---------------------------------------------------------------------
# 9. Pare-feu (ufw) : ouverture des ports nécessaires
# ---------------------------------------------------------------------
info "Étape 9/13 : Configuration du pare-feu (ports nécessaires au projet)..."

apt install -y ufw >/dev/null

# --- SSH en tout premier, pour ne jamais se couper l'accès distant ---
# Détecte le port SSH réellement configuré (par défaut 22).
SSH_PORT="$(grep -E '^[[:space:]]*Port[[:space:]]+[0-9]+' /etc/ssh/sshd_config 2>/dev/null | awk '{print $2}' | tail -n1)"
SSH_PORT="${SSH_PORT:-22}"
ufw allow "${SSH_PORT}/tcp" comment 'SSH' >/dev/null

# --- Dashboard web ---
ufw allow 80/tcp  comment 'Dashboard HTTP'  >/dev/null
ufw allow 443/tcp comment 'Dashboard HTTPS' >/dev/null

# --- FreeRADIUS (authentification + accounting) ---
ufw allow 1812/udp comment 'RADIUS Auth'       >/dev/null
ufw allow 1813/udp comment 'RADIUS Accounting' >/dev/null

# --- CoA / Disconnect-Request (utilisé par le dashboard pour déconnecter une session) ---
ufw allow 3799/udp comment 'RADIUS CoA/Disconnect' >/dev/null

# Active ufw sans invite interactive. Si déjà actif, cette commande ne fait
# que confirmer l'état (aucune règle existante n'est supprimée).
ufw --force enable >/dev/null

info "Pare-feu configuré : SSH (${SSH_PORT}/tcp), HTTP/HTTPS, RADIUS 1812-1813/udp, CoA 3799/udp autorisés."
warn "Si pfSense ou un autre NAS a besoin d'un port supplémentaire, ajoute-le avec : sudo ufw allow <port>/<tcp|udp>"

# ---------------------------------------------------------------------
# 10. fail2ban (protection brute-force FreeRADIUS + Apache)
# ---------------------------------------------------------------------
info "Étape 10/13 : Installation et configuration de fail2ban..."
apt install -y fail2ban >/dev/null

mkdir -p /etc/fail2ban/filter.d
cat > /etc/fail2ban/filter.d/freeradius.conf <<'EOF'
[Definition]
failregex = ^.*Login incorrect.*\[.*\].*\(from client .* port .* cli <HOST>\)$
ignoreregex =
EOF

cat > /etc/fail2ban/jail.d/radius-project.local <<EOF
[freeradius]
enabled  = true
filter   = freeradius
logpath  = /var/log/freeradius/radius.log
maxretry = 5
findtime = 300
bantime  = 3600
backend  = auto

[apache-auth]
enabled  = true
port     = http,https
logpath  = /var/log/apache2/error.log
maxretry = 6
findtime = 300
bantime  = 3600
EOF

systemctl enable --now fail2ban
systemctl restart fail2ban || warn "Échec du redémarrage de fail2ban, vérifier la configuration."
info "fail2ban activé (protection anti brute-force FreeRADIUS + Apache)."

# ---------------------------------------------------------------------
# 10. mod_evasive (anti brute-force / rate limiting sur le dashboard)
# ---------------------------------------------------------------------
info "Étape 11/13 : Installation du rate limiting (mod_evasive) sur le dashboard..."
apt install -y libapache2-mod-evasive >/dev/null

mkdir -p /var/log/mod_evasive
chown www-data:www-data /var/log/mod_evasive

cat > /etc/apache2/mods-available/evasive.conf <<'EOF'
<IfModule mod_evasive20.c>
    DOSHashTableSize    3097
    DOSPageCount        10
    DOSPageInterval     2
    DOSSiteCount        100
    DOSSiteInterval     2
    DOSBlockingPeriod   60
    DOSLogDir           "/var/log/mod_evasive"
</IfModule>
EOF

a2enmod evasive >/dev/null 2>&1 || true
info "mod_evasive activé (limite les requêtes répétées, ex: brute-force sur login.php)."

# ---------------------------------------------------------------------
# 11. Sauvegardes automatiques quotidiennes (MariaDB)
# ---------------------------------------------------------------------
info "Étape 12/13 : Configuration des sauvegardes automatiques de la base de données..."

BACKUP_SCRIPT="/usr/local/bin/radius-db-backup.sh"
BACKUP_STORE="/var/backups/radius-db"
mkdir -p "$BACKUP_STORE"

cat > "$BACKUP_SCRIPT" <<EOF
#!/bin/bash
# Sauvegarde quotidienne de la base $DB_NAME, conservée 14 jours.
set -e
TS="\$(date +%Y%m%d_%H%M%S)"
mysqldump -u root -p'${DB_ROOT_PASS//\'/\\\'}' '$DB_NAME' | gzip > "$BACKUP_STORE/${DB_NAME}_\${TS}.sql.gz"
find "$BACKUP_STORE" -name "*.sql.gz" -mtime +14 -delete
EOF

chmod 700 "$BACKUP_SCRIPT"
chmod 700 "$BACKUP_STORE"

CRON_LINE="0 3 * * * root $BACKUP_SCRIPT >/var/log/radius-db-backup.log 2>&1"
CRON_FILE="/etc/cron.d/radius-db-backup"
echo "$CRON_LINE" > "$CRON_FILE"
chmod 644 "$CRON_FILE"

info "Sauvegarde automatique configurée : tous les jours à 03h00, conservées 14 jours dans $BACKUP_STORE."

# ---------------------------------------------------------------------
# 12. Validation et services
# ---------------------------------------------------------------------
info "Étape 13/13 : Vérification de FreeRADIUS et démarrage des services..."

# Test de syntaxe/configuration sans démarrer le daemon.
if freeradius -XC >/tmp/freeradius_check.log 2>&1; then
    info "Configuration FreeRADIUS valide."
    systemctl enable freeradius
    systemctl restart freeradius
else
    error "Erreur dans la configuration FreeRADIUS !"
    cat /tmp/freeradius_check.log
    error "FreeRADIUS ne sera PAS redémarré."
    exit 1
fi

apache2ctl configtest
systemctl restart apache2
systemctl restart mariadb

echo ""
echo "====================================================================="
info "Installation terminée !"
echo "   - Dashboard (HTTP)  : http://$STATIC_IP/radius-dashboard/"
echo "   - Dashboard (HTTPS) : https://$STATIC_IP/radius-dashboard/ (certificat auto-signé)"
echo "   - FreeRADIUS        : sudo freeradius -X"
echo "   - MariaDB           : mysql -u $DB_APP_USER -p $DB_NAME"
echo "   - Secret RADIUS     : synchronisé dans clients.conf (à reporter sur pfSense)"
echo "   - fail2ban          : actif (jails freeradius + apache-auth)"
echo "   - Sauvegardes DB    : quotidiennes 03h00 -> $BACKUP_STORE (14 jours)"
echo "   - Projet            : $DEST_DIR"
echo "   - Backup config     : /etc/freeradius/3.0.backup.*"
echo "====================================================================="

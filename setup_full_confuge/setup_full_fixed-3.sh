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
info "Étape 1/8 : Mise à jour du système et installation des paquets..."
export DEBIAN_FRONTEND=noninteractive
apt update
apt upgrade -y
apt install -y \
    curl wget git unzip net-tools vim htop tcpdump \
    freeradius freeradius-mysql freeradius-utils \
    mariadb-server mariadb-client \
    apache2 php libapache2-mod-php php-mysql php-mysqli php-curl php-xml php-mbstring \
    netplan.io

systemctl enable --now mariadb
systemctl enable --now apache2

# ---------------------------------------------------------------------
# 2. Netplan
# ---------------------------------------------------------------------
info "Étape 2/8 : Configuration de l'adresse IP statique ($STATIC_IP)..."
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
info "Étape 3/8 : Configuration de MariaDB..."

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
info "Étape 4/8 : Récupération du dépôt GitHub..."
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
info "Étape 5/8 : Import de la base de données..."
if [ -f "database_mysql/radius.sql" ]; then
    mysql -u root -p"$DB_ROOT_PASS" "$DB_NAME" < database_mysql/radius.sql
    info "Base de données importée avec succès."
else
    warn "Aucun fichier database_mysql/radius.sql trouvé dans le dépôt, étape ignorée."
fi

# ---------------------------------------------------------------------
# 6. FreeRADIUS
# ---------------------------------------------------------------------
info "Étape 6/8 : Déploiement de la configuration FreeRADIUS..."

if [ -d "freeradius_configuration" ]; then
    BACKUP_DIR="/etc/freeradius/3.0.backup.$(date +%Y%m%d%H%M%S)"
    mkdir -p "$BACKUP_DIR"

    if [ -d "freeradius_configuration/mods-enabled" ]; then
        cp -a /etc/freeradius/3.0/mods-enabled "$BACKUP_DIR/mods-enabled" 2>/dev/null || true
        cp -a freeradius_configuration/mods-enabled/. /etc/freeradius/3.0/mods-enabled/
        chown -R freerad:freerad /etc/freeradius/3.0/mods-enabled/
        info "mods-enabled déployé."
    fi

    if [ -f "freeradius_configuration/clients.conf" ]; then
        cp -a /etc/freeradius/3.0/clients.conf "$BACKUP_DIR/clients.conf" 2>/dev/null || true
        cp -a freeradius_configuration/clients.conf /etc/freeradius/3.0/clients.conf
        chown freerad:freerad /etc/freeradius/3.0/clients.conf
        info "clients.conf déployé."
    fi
else
    warn "Aucun dossier freeradius_configuration trouvé dans le dépôt."
fi

# ---------------------------------------------------------------------
# 7. Dashboard
# ---------------------------------------------------------------------
info "Étape 7/8 : Déploiement du dashboard..."

if [ -d "radius-dashboard" ]; then
    mkdir -p /var/www/html/radius-dashboard
    cp -a radius-dashboard/. /var/www/html/radius-dashboard/

    # Si config.php existe, on crée une copie puis on remplace les variables
    # les plus courantes. Le script ne modifie pas arbitrairement un fichier
    # qui n'existe pas.
    if [ -f "/var/www/html/radius-dashboard/config.php" ]; then
        cp -a /var/www/html/radius-dashboard/config.php \
              "/var/www/html/radius-dashboard/config.php.bak.$TIMESTAMP"

        # Remplacement des valeurs simples si elles existent dans le fichier.
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
# 8. Validation et services
# ---------------------------------------------------------------------
info "Étape 8/8 : Vérification de FreeRADIUS et démarrage des services..."

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
echo "   - Dashboard : http://$STATIC_IP/radius-dashboard/"
echo "   - FreeRADIUS: sudo freeradius -X"
echo "   - MariaDB   : mysql -u $DB_APP_USER -p $DB_NAME"
echo "   - Projet    : $DEST_DIR"
echo "   - Backup    : /etc/freeradius/3.0.backup.*"
echo "====================================================================="

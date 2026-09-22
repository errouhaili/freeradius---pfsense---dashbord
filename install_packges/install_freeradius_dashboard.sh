#!/bin/bash
# =====================================================
# Script d'installation - Projet FreeRADIUS + pfSense + Dashboard
# Ubuntu 20.04 / 22.04 / 24.04
# =====================================================

set -e

echo ">>> Mise à jour du système..."
sudo apt update && sudo apt upgrade -y

echo ">>> Installation des outils de base..."
sudo apt install -y curl wget git unzip net-tools vim htop

# -----------------------------------------------------
# FreeRADIUS + module MySQL
# -----------------------------------------------------
echo ">>> Installation de FreeRADIUS..."
sudo apt install -y freeradius freeradius-mysql freeradius-utils

# -----------------------------------------------------
# Base de données MySQL/MariaDB (pour radius.sql)
# -----------------------------------------------------
echo ">>> Installation de MariaDB (MySQL)..."
sudo apt install -y mariadb-server mariadb-client
sudo systemctl enable mariadb
sudo systemctl start mariadb

# Sécurisation de MySQL (interactif)
echo ">>> N'oublie pas de lancer: sudo mysql_secure_installation"

# -----------------------------------------------------
# Serveur Web pour le dashboard (Apache + PHP)
# -----------------------------------------------------
echo ">>> Installation d'Apache + PHP (pour le dashboard)..."
sudo apt install -y apache2 php libapache2-mod-php php-mysql php-mysqli php-curl php-xml php-mbstring

sudo systemctl enable apache2
sudo systemctl start apache2

# -----------------------------------------------------
# Outils réseau (netplan est déjà inclus dans Ubuntu)
# -----------------------------------------------------
echo ">>> Vérification de netplan..."
which netplan || sudo apt install -y netplan.io

# -----------------------------------------------------
# Outils supplémentaires utiles pour RADIUS/pfSense
# -----------------------------------------------------
echo ">>> Installation d'outils supplémentaires..."
sudo apt install -y tcpdump freeradius-utils

echo ""
echo "====================================================="
echo " Installation terminée !"
echo " - FreeRADIUS : sudo freeradius -X (mode debug)"
echo " - MySQL      : sudo mysql -u root -p"
echo " - Apache     : http://localhost/"
echo " - N'oublie pas d'importer radius.sql :"
echo "     mysql -u root -p radius < radius.sql"
echo "====================================================="

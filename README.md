# freeradius---pfsense---dashbord

Solution de contrôle d'accès réseau (NAC) basée sur **pfSense**, **FreeRADIUS**, **MariaDB/MySQL** et un **tableau de bord d'administration** (PHP).

---

## ⚠️ Prérequis obligatoire : configuration pfSense

**Avant de lancer le script d'installation**, le pare-feu **pfSense doit déjà être configuré et opérationnel** sur le réseau. Le serveur FreeRADIUS (Ubuntu) ne fonctionne pas seul : pfSense agit comme **client RADIUS (NAS)** qui interroge le serveur FreeRADIUS pour authentifier les utilisateurs du réseau.

Étapes à faire **avant** d'exécuter le script :

1. Installer / démarrer pfSense sur la machine ou VM dédiée.
2. Restaurer ou appliquer la configuration pfSense fournie dans le dossier [`pfsense/`](./pfsense) (Diagnostics → Backup & Restore, ou configuration manuelle des interfaces réseau, DHCP, etc.).
3. S'assurer que pfSense est **joignable sur le réseau** à l'adresse configurée (par défaut dans ce projet : `192.168.0.200`), et que le serveur Ubuntu pourra communiquer avec lui sur `192.168.0.20`.
4. Sur pfSense, configurer le **serveur RADIUS** (Services → dans le contexte VPN/Captive Portal/etc. selon le besoin) en pointant vers `192.168.0.20` avec le secret partagé défini dans `clients.conf`.

➡️ **Si pfSense n'est pas configuré et actif avant le lancement du script, FreeRADIUS ne pourra pas être testé correctement de bout en bout** (le NAS `PFSENSE` déclaré dans `clients.conf` / la table SQL `nas` ne répondra à aucune requête réelle).

---

## Architecture

```
┌─────────────┐        RADIUS (1812/1813)        ┌──────────────────────────┐
│   pfSense    │ ───────────────────────────────► │   Ubuntu Server           │
│  192.168.0.200│ ◄─────────────────────────────── │   192.168.0.20            │
│  (NAS client) │                                  │  - FreeRADIUS 3.0         │
└─────────────┘                                    │  - MariaDB (radius DB)    │
                                                     │  - Apache + Dashboard PHP │
                                                     └──────────────────────────┘
```

- **pfSense** : pare-feu / routeur, agit comme client RADIUS (NAS) pour authentifier les connexions réseau.
- **FreeRADIUS** : serveur d'authentification, interrogé par pfSense.
- **MariaDB** : stocke les utilisateurs, groupes, clients NAS et journaux de sessions (tables `radcheck`, `radreply`, `radacct`, `nas`, etc.).
- **Dashboard PHP** : interface web d'administration (gestion des utilisateurs, groupes, NAS, rapports).

---

## Structure du dépôt

| Dossier / fichier                  | Contenu                                                              |
|-------------------------------------|------------------------------------------------------------------------|
| `pfsense/`                          | Fichiers de configuration pfSense à restaurer **avant** l'installation |
| `database_mysql/radius.sql`         | Dump SQL initial (schéma + données FreeRADIUS)                        |
| `3.0.zip`                            | Configuration FreeRADIUS complète (`mods-enabled`, `clients.conf`, etc.) |
| `radius-dashboard/`                 | Code source du tableau de bord PHP                                     |
| `netplan/`                           | Exemple(s) de configuration réseau Netplan (référence)                |
| `setup_full_confuge/`               | Script d'installation automatique (voir ci-dessous)                    |

---

## Installation automatique (serveur Ubuntu)

Le script `setup_full_confuge/setup_full_fixed-6.sh` installe et configure automatiquement tout le nécessaire côté Ubuntu : paquets système, IP statique, MariaDB, import de la base, déploiement de la config FreeRADIUS, déploiement du dashboard, et démarrage des services.

### Étapes

```bash
git clone https://github.com/errouhaili/freeradius---pfsense---dashbord.git
cd freeradius---pfsense---dashbord/setup_full_confuge
chmod +x setup_full_fixed-6.sh
sudo ./setup_full_fixed-6.sh
```

Le script demande interactivement :
- l'URL du dépôt et le dossier de destination,
- l'interface réseau à configurer,
- l'adresse IP statique (par défaut `192.168.0.20`), la passerelle et le DNS,
- le nom de la base de données, l'utilisateur applicatif et les mots de passe MySQL.

> Le script est **idempotent** : il peut être relancé plusieurs fois sans casser une installation déjà en place (détection automatique d'un mot de passe root déjà configuré, sauvegardes horodatées de la config FreeRADIUS avant chaque remplacement, etc.).

### Ce que fait le script (8 étapes)

1. Mise à jour du système + installation des paquets (FreeRADIUS, MariaDB, Apache/PHP, netplan, outils réseau).
2. Configuration de l'IP statique via `netplan try` (sécurisé : annule automatiquement en cas de perte de connexion).
3. Configuration de MariaDB (mot de passe root, base de données, utilisateur applicatif).
4. Récupération / mise à jour du dépôt GitHub.
5. Import de `database_mysql/radius.sql` dans la base de données.
6. Déploiement de la configuration FreeRADIUS (`3.0.zip`) + **synchronisation automatique** des identifiants MySQL (`login`, `password`, `radius_db`) dans `mods-enabled/sql`.
7. Déploiement du dashboard PHP dans `/var/www/html/radius-dashboard/` + synchronisation des identifiants dans `config.php`.
8. Vérification de la configuration FreeRADIUS (`freeradius -CX`) puis démarrage des services (FreeRADIUS, Apache, MariaDB).

---

## Vérification après installation

```bash
# Test de la config FreeRADIUS en mode debug
sudo freeradius -X
# -> chercher la ligne : rlm_sql_mysql: Connected to database 'radius' ...

# Accès au dashboard
http://192.168.0.20/radius-dashboard/
```

---

## Points d'attention connus

- **Client NAS en double (`PFSENSE`)** : si le NAS `192.168.0.200` est déclaré à la fois dans `clients.conf` (statique) et dans la table SQL `nas` (dynamique), FreeRADIUS affiche un avertissement `Failed to add duplicate client` au démarrage (non bloquant). Choisir une seule source de vérité : soit `clients.conf`, soit la table `nas` via le dashboard.
- **Identifiants MySQL** : `mods-enabled/sql` et `radius-dashboard/config.php` sont resynchronisés automatiquement par le script à chaque exécution avec les valeurs saisies (utilisateur/mot de passe applicatifs).

---

## Accès rapides

- Dashboard : `http://192.168.0.20/radius-dashboard/`
- Debug FreeRADIUS : `sudo freeradius -X`
- Connexion MySQL : `mysql -u <utilisateur_app> -p radius`
- Sauvegardes de configuration FreeRADIUS : `/etc/freeradius/3.0.backup.*`

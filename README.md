# freeradius---pfsense---dashbord

Solution de contrôle d'accès réseau (NAC) basée sur **pfSense**, **FreeRADIUS**, **MariaDB/MySQL** et un **tableau de bord d'administration** (PHP).

---

## ⚠️ Prérequis obligatoire : configuration pfSense

**Avant de lancer le script d'installation**, le pare-feu **pfSense doit déjà être configuré et opérationnel** sur le réseau. Le serveur FreeRADIUS (Ubuntu) ne fonctionne pas seul : pfSense agit comme **client RADIUS (NAS)** qui interroge le serveur FreeRADIUS pour authentifier les utilisateurs du réseau.

Étapes à faire **avant** d'exécuter le script :

1. Installer / démarrer pfSense sur la machine ou VM dédiée.
2. Restaurer ou appliquer la configuration pfSense fournie dans le dossier [`pfsense/`](./pfsense) (Diagnostics → Backup & Restore, ou configuration manuelle des interfaces réseau, DHCP, etc.).
3. S'assurer que pfSense est **joignable sur le réseau** à l'adresse configurée (par défaut dans ce projet : `192.168.0.200`), et que le serveur Ubuntu pourra communiquer avec lui sur `192.168.0.20`.
4. Sur pfSense, configurer le **serveur RADIUS** (Captive Portal / VPN / User Manager → Authentication Servers selon le besoin) en pointant vers `192.168.0.20`, ports `1812`/`1813`, avec le **même secret partagé** que celui saisi lors de l'exécution du script (voir plus bas).

> ⚠️ **Attention** : si le secret RADIUS saisi côté pfSense ne correspond pas **exactement** (caractère près) à celui synchronisé dans `clients.conf` côté Ubuntu, l'authentification échouera côté pfSense, avec côté FreeRADIUS un message du type `Failed decrypting ... shared secret mismatch` visible dans `sudo freeradius -X`. C'est l'erreur la plus fréquente lors d'une première installation — vérifie toujours ce point en premier en cas de problème.

➡️ **Si pfSense n'est pas configuré et actif avant le lancement du script, FreeRADIUS ne pourra pas être testé correctement de bout en bout** (le NAS `PFSENSE` déclaré dans `clients.conf` / la table SQL `nas` ne répondra à aucune requête réelle).

---

## Architecture

```
┌─────────────┐        RADIUS (1812/1813)        ┌──────────────────────────┐
│   pfSense    │ ───────────────────────────────► │   Ubuntu Server           │
│  192.168.0.200│ ◄─────────────────────────────── │   192.168.0.20            │
│  (NAS client) │        CoA/Disconnect (3799)     │  - FreeRADIUS 3.0         │
└─────────────┘                                    │  - MariaDB (radius DB)    │
                                                     │  - Apache + Dashboard PHP │
                                                     └──────────────────────────┘
```

- **pfSense** : pare-feu / routeur, agit comme client RADIUS (NAS) pour authentifier les connexions réseau.
- **FreeRADIUS** : serveur d'authentification, interrogé par pfSense.
- **MariaDB** : stocke les utilisateurs, groupes, clients NAS et journaux de sessions (tables `radcheck`, `radreply`, `radacct`, `nas`, etc.).
- **Dashboard PHP** : interface web d'administration (utilisateurs, groupes, NAS, sessions, rapports, quotas, sécurité).

---

## Structure du dépôt

| Dossier / fichier                  | Contenu                                                              |
|-------------------------------------|------------------------------------------------------------------------|
| `pfsense/`                          | Fichiers de configuration pfSense à restaurer **avant** l'installation |
| `database_mysql/radius.sql`         | Dump SQL initial (schéma + données métier FreeRADIUS)                 |
| `3.0.zip`                            | Configuration FreeRADIUS complète (`mods-enabled`, `clients.conf`, etc.) |
| `radius-dashboard/`                 | Code source du tableau de bord PHP                                     |
| `netplan/`                           | Exemple(s) de configuration réseau Netplan (référence)                |
| `setup_full_confuge/`               | Script d'installation automatique (voir ci-dessous)                    |
| `.github/workflows/`                | Vérification automatique (syntax + shellcheck) du script à chaque push |

---

## Installation automatique (serveur Ubuntu)

Le script `setup_full_confuge/setup_full_fixed-10.sh` installe et configure automatiquement tout le nécessaire côté Ubuntu, en **13 étapes**, et est **idempotent** (peut être relancé sans casser une installation existante).

### Étapes

```bash
git clone https://github.com/errouhaili/freeradius---pfsense---dashbord.git
cd freeradius---pfsense---dashbord/setup_full_confuge
chmod +x setup_full_fixed-10.sh
sudo ./setup_full_fixed-10.sh
```

Le script demande interactivement :
- l'URL du dépôt et le dossier de destination,
- l'interface réseau, l'IP statique (par défaut `192.168.0.20`), la passerelle et le DNS,
- le nom de la base de données, l'utilisateur applicatif et les mots de passe MySQL,
- le **secret RADIUS partagé** (une valeur forte par défaut est proposée — Entrée pour l'accepter, ou saisir la tienne, 8 caractères minimum).

### Les 13 étapes

1. Mise à jour système + installation des paquets (FreeRADIUS, MariaDB, Apache/PHP, netplan, ufw, fail2ban, mod_evasive, outils réseau).
2. Configuration de l'IP statique via `netplan try` (sécurisé : annule automatiquement en cas de perte de connexion — **il faut appuyer sur Entrée dans les 20 secondes pour confirmer**, sinon la config revient en arrière).
3. Configuration de MariaDB (mot de passe root, base de données, utilisateur applicatif — détection automatique si déjà configuré lors d'un run précédent).
4. Récupération / mise à jour du dépôt GitHub.
5. Import du schéma officiel FreeRADIUS + `database_mysql/radius.sql` + création d'un **utilisateur RADIUS de test (`admin` / `admin`)** pour valider rapidement l'authentification de bout en bout.
6. Déploiement de la configuration FreeRADIUS (`3.0.zip`) + synchronisation automatique des identifiants MySQL (`login`, `password`, `radius_db`) et du **secret RADIUS partagé** dans `clients.conf`.
7. Déploiement du dashboard PHP dans `/var/www/html/radius-dashboard/` + synchronisation des identifiants dans `config.php`.
8. Activation de **HTTPS** (certificat auto-signé, 825 jours) pour le dashboard.
9. Configuration du **pare-feu (ufw)** : ouverture uniquement des ports nécessaires — SSH (détecté automatiquement), 80/443 (dashboard), 1812-1813/udp (RADIUS), 3799/udp (CoA).
10. Installation et configuration de **fail2ban** (jails FreeRADIUS + Apache, anti brute-force).
11. Installation de **mod_evasive** (rate limiting sur le dashboard, anti brute-force sur `login.php`).
12. Configuration des **sauvegardes automatiques quotidiennes** de la base de données (03h00, conservées 14 jours dans `/var/backups/radius-db`).
13. Vérification de la configuration FreeRADIUS (`freeradius -CX`) puis démarrage des services.

---

## Fonctionnalités du dashboard

- **Gestion des utilisateurs, groupes et NAS** (CRUD complet).
- **Sessions actives** : liste en temps réel, avec **déconnexion à distance (CoA / Disconnect-Request)** directement depuis le dashboard.
- **Rapports** : consommation par jour (graphique), nombre de sessions par jour (graphique), top utilisateurs.
- **Export CSV** des sessions et des rapports ; **export PDF** via impression navigateur (bouton dédié, mise en page automatiquement nettoyée pour l'impression).
- **Quotas par utilisateur** (optionnels, dans le formulaire utilisateur) :
  - *Durée maximale de session* (`Session-Timeout`) : réellement appliquée par le NAS.
  - *Débit max. montant/descendant* (`WISPr-Bandwidth-Max-Up/Down`) : nécessite le support de ces attributs côté NAS/pfSense.
- **Authentification à deux facteurs (2FA / TOTP)** pour les comptes admin du dashboard : compatible Google Authenticator, Authy, Microsoft Authenticator (voir ci-dessous).
- **Multilingue** : arabe / français / anglais.

### Activer la 2FA (compte admin du dashboard)

La table `admins` d'une installation existante doit d'abord être mise à jour **une seule fois** :

```bash
mysql -u root -p radius < radius-dashboard/sql/2fa_migration.sql
```

(pour une toute nouvelle installation, `sql/admins.sql` inclut déjà les colonnes nécessaires — rien à faire de plus).

Ensuite, dans le dashboard : **Sidebar → Authentification 2FA → Activer la 2FA**, scanner le QR code avec une application TOTP, puis confirmer avec le code affiché. À la prochaine connexion, un code à 6 chiffres sera demandé après le mot de passe. La 2FA peut être désactivée à tout moment depuis la même page (confirmation par mot de passe requise).

---

## Vérification après installation

```bash
# Test rapide de bout en bout avec l'utilisateur de test créé automatiquement
radtest admin admin localhost 0 "<le secret RADIUS affiché en fin d'installation>"
# -> doit répondre : Received Access-Accept

# Test de la config FreeRADIUS en mode debug
sudo freeradius -X
# -> chercher la ligne : rlm_sql_mysql: Connected to database 'radius' ...

# Accès au dashboard
http://192.168.0.20/radius-dashboard/
https://192.168.0.20/radius-dashboard/   (certificat auto-signé, avertissement navigateur normal)
```

---

## Points d'attention connus

- **Client NAS en double (`PFSENSE`)** : si le NAS `192.168.0.200` est déclaré à la fois dans `clients.conf` (statique) et dans la table SQL `nas` (dynamique), FreeRADIUS affiche un avertissement `Failed to add duplicate client` au démarrage (non bloquant). Choisir une seule source de vérité : soit `clients.conf`, soit la table `nas` via le dashboard.
- **Secret RADIUS unique** : le script synchronise **le même secret** sur toutes les entrées `secret = ...` de `clients.conf`. Si plusieurs NAS distincts avec des secrets différents sont ajoutés par la suite, les ajuster manuellement après coup.
- **fail2ban (jail FreeRADIUS)** : le filtre est basé sur le format de log standard de FreeRADIUS ; vérifier son efficacité avec `sudo fail2ban-regex /var/log/freeradius/radius.log /etc/fail2ban/filter.d/freeradius.conf`.
- **CoA / Disconnect** : nécessite que pfSense soit configuré pour accepter les paquets `Disconnect-Request` sur le port **3799**, avec le même secret que celui défini pour le NAS. Sans cela, le bouton "Déconnecter" du dashboard renverra une erreur explicite (pas un blocage silencieux).
- **2FA** : si un admin perd l'accès à son application d'authentification, il faut réinitialiser manuellement `totp_enabled = 0` dans la table `admins` (accès base de données) pour lui permettre de se reconnecter sans code.
- **Utilisateur de test `admin`/`admin`** : credential volontairement faible, créé uniquement pour valider rapidement l'installation. **À changer ou supprimer avant toute mise en production.**

---

## ⚠️ Dernier point, important : si tu n'as pas de sauvegarde (backup)

Si tu réinstalles ou relances le script **sans disposer d'une sauvegarde préalable** (base de données, `clients.conf`, `config.php`), fais impérativement les vérifications suivantes **après** l'exécution du script, avant de considérer l'installation comme terminée :

1. **Le secret RADIUS** affiché en fin de script correspond **exactement** à celui configuré côté pfSense (copier-coller direct recommandé, ne jamais le retaper à la main).
2. **La connexion Ubuntu ↔ pfSense fonctionne réellement** : depuis pfSense, tester l'authentification (ou lancer `radtest admin admin <IP_Ubuntu> 0 <secret>` depuis le serveur Ubuntu lui-même) et confirmer une réponse `Access-Accept`, pas seulement que les services sont "actifs".
3. Les **ports nécessaires sont bien ouverts** des deux côtés (1812/1813/udp et 3799/udp) — vérifier qu'aucun pare-feu intermédiaire (ufw, pfSense lui-même) ne bloque le trafic entre les deux machines.

Sans sauvegarde, une mauvaise synchronisation du secret ou une coupure réseau entre Ubuntu et pfSense est l'erreur la plus fréquente et la plus difficile à diagnostiquer après coup — mieux vaut la vérifier tout de suite plutôt que de découvrir le problème plus tard.

---

## Accès rapides

- Dashboard : `http://192.168.0.20/radius-dashboard/` · `https://192.168.0.20/radius-dashboard/`
- Debug FreeRADIUS : `sudo freeradius -X`
- Connexion MySQL : `mysql -u <utilisateur_app> -p radius`
- Sauvegardes de configuration FreeRADIUS : `/etc/freeradius/3.0.backup.*`
- Sauvegardes de la base de données : `/var/backups/radius-db/`

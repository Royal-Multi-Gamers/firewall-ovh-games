# Guide d'Installation et de Test - Firewall OVH Games Plugin

## 📋 Prérequis

Avant d'installer le plugin, assurez-vous d'avoir:

- ✅ Pelican Panel v1.0.0-beta31 ou supérieur installé
- ✅ PHP 8.1 ou supérieur
- ✅ Composer installé
- ✅ Accès SSH au serveur
- ✅ Compte OVH avec accès API
- ✅ Au moins une IP OVH Game configurée

## 🚀 Installation Étape par Étape

### Étape 1: Installation du Plugin

```bash
# Se placer dans le répertoire du panel
cd /var/www/pelican

# Installer le plugin
php artisan p:plugin:install

# Sélectionner 'firewall-ovh-games' dans la liste
```

### Étape 2: Installation des Dépendances

```bash
# Installer le package OVH
composer require ovh/ovh
```

### Étape 3: Exécuter les Migrations

```bash
# Exécuter les migrations de base de données
php artisan migrate

# Vérifier que les tables ont été créées
php artisan db:show
```

Les tables suivantes doivent être créées:
- `ovh_firewall_settings`
- `ovh_firewall_sync_logs`
- `ovh_firewall_ip_configs`

### Étape 4: Obtenir les Credentials OVH API

1. Aller sur: https://api.ovh.com/createToken/index.cgi?GET=/*&PUT=/*&POST=/*&DELETE=/ip/*/game/*/rule*

2. Remplir le formulaire:
   - **Application Name**: Pelican Firewall Manager
   - **Application Description**: Manage OVH Game firewall rules
   - **Validity**: Unlimited

3. Permissions requises:
   ```
   GET    /ip/*
   GET    /ip/*/game/*
   GET    /ip/*/game/*/rule
   GET    /ip/*/game/*/rule/*
   POST   /ip/*/game/*/rule
   DELETE /ip/*/game/*/rule/*
   ```

4. Cliquer sur **Create keys**

5. Sauvegarder:
   - Application Key
   - Application Secret
   - Consumer Key

### Étape 5: Configuration dans le Panel

1. Se connecter au panel admin
2. Aller dans **Firewall OVH** → **OVH Firewall Settings**
3. Entrer les credentials OVH:
   - Application Key
   - Application Secret
   - Endpoint: `ovh-eu` (ou votre région)
   - Consumer Key
4. Configurer la synchronisation:
   - Enable Automatic Sync: ✅
   - Sync Interval: `5` minutes
   - Sync on Allocation Events: ✅
5. Default Protocol: `other`
6. Cliquer sur **Save Settings**
7. Cliquer sur **Test Connection** pour vérifier

### Étape 6: Configurer les IPs

1. Aller dans **Firewall OVH** → **IP Configurations**
2. Cliquer sur **Create**
3. Pour chaque IP du panel:
   - **Panel IP**: L'IP dans Pelican (ex: `192.168.1.100`)
   - **OVH IP**: L'IP dans OVH (ex: `51.210.100.50`)
   - **OVH IP on Game**: L'identifiant game OVH (ex: `51.210.100.50`)
   - **Enabled**: ✅
   - **Notes**: Description optionnelle
4. Cliquer sur **Create**

### Étape 7: Synchronisation Initiale

```bash
# Test de connexion
php artisan firewall:test-connection

# Synchronisation initiale (dry-run pour voir ce qui sera fait)
php artisan firewall:sync --dry-run

# Synchronisation réelle
php artisan firewall:sync --force
```

### Étape 8: Activer le système de Queue (recommandé)

Le plugin est maintenant optimisé pour exécuter la synchronisation via la queue.

```bash
# .env
QUEUE_CONNECTION=redis
OVH_FIREWALL_USE_QUEUE=true
OVH_FIREWALL_QUEUE_NAME=default
```

Si vous n'utilisez pas Redis:

```bash
# Alternative simple
QUEUE_CONNECTION=database
php artisan queue:table
php artisan migrate
```

Lancer le worker:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

Avec systemd / systemctl (production), exemple minimal:

```ini
[Unit]
Description=Pelican Queue Worker
After=network.target mariadb.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/pelican
ExecStart=/usr/bin/php /var/www/pelican/artisan queue:work --queue=default --sleep=1 --tries=3 --timeout=120
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Créer et activer le service:

```bash
sudo nano /etc/systemd/system/pelican-queue.service
sudo systemctl daemon-reload
sudo systemctl enable --now pelican-queue
sudo systemctl status pelican-queue
```

Redémarrage après changement de code/config:

```bash
sudo systemctl restart pelican-queue
php artisan queue:restart
```

## 🧪 Tests de Validation

### Test 1: Vérifier la Connexion OVH

```bash
php artisan firewall:test-connection
```

**Résultat attendu**: ✓ Successfully connected to OVH API

### Test 2: Vérifier les Configurations IP

```bash
php artisan firewall:test-connection --ip=VOTRE_IP
```

**Résultat attendu**: ✓ Successfully accessed firewall rules

### Test 3: Test de Synchronisation

```bash
# Dry run (ne fait aucun changement)
php artisan firewall:sync --dry-run

# Synchronisation réelle
php artisan firewall:sync
```

**Résultat attendu**: 
```
Starting firewall synchronization...
Syncing all configured IPs...

┌────────────────────┬───────┐
│ Metric             │ Count │
├────────────────────┼───────┤
│ Total Operations   │ X     │
│ Successful         │ X     │
│ Failed             │ 0     │
│ Skipped            │ 0     │
└────────────────────┴───────┘

Synchronization completed successfully!
```

### Test 4: Créer une Allocation

1. Dans le panel, créer une nouvelle allocation pour un serveur
2. Vérifier dans **Firewall OVH** → **Sync Logs**
3. Une entrée avec action "add" et status "success" doit apparaître

### Test 5: Supprimer une Allocation

1. Supprimer une allocation d'un serveur
2. Vérifier dans **Firewall OVH** → **Sync Logs**
3. Une entrée avec action "delete" et status "success" doit apparaître

### Test 6: Vérifier le Scheduler

```bash
# Vérifier que la commande est dans le scheduler
php artisan schedule:list

# Tester le scheduler manuellement
php artisan schedule:run
```

**Résultat attendu**: un job de synchronisation firewall est planifié à l'intervalle configuré.

### Test 7: Vérifier la Queue

```bash
# Vérifier les jobs en attente (driver database)
php artisan tinker
>>> DB::table('jobs')->count()
>>> exit

# Lancer le worker manuellement pour test
php artisan queue:work --queue=default --once
```

## 📊 Vérification dans le Panel Admin

### Dashboard Widget

Vérifier que le widget affiche:
- ✅ Nombre d'IPs configurées
- ✅ Nombre d'allocations actives
- ✅ Synchronisations réussies/échouées (24h)
- ✅ Dernière synchronisation
- ✅ Statut de la synchronisation

### Page Settings

Vérifier que tous les champs sont sauvegardés correctement:
- ✅ Credentials OVH masqués
- ✅ Bouton "Test Connection" fonctionne
- ✅ Bouton "Sync Now" fonctionne

### Page IP Configurations

Vérifier:
- ✅ Liste des IPs configurées
- ✅ Compteur d'allocations par IP
- ✅ Dernière synchronisation par IP
- ✅ Bouton "Sync" par IP fonctionne
- ✅ Bouton "Sync All" fonctionne
- ✅ Bouton "Detect Missing IPs" fonctionne

### Page Sync Logs

Vérifier:
- ✅ Liste des logs de synchronisation
- ✅ Filtres par statut, action, date
- ✅ Détails complets d'un log
- ✅ Bouton "Cleanup Old Logs" fonctionne

## 🔧 Dépannage

### Problème: "Failed to connect to OVH API"

**Solutions**:
```bash
# Vérifier les credentials
php artisan tinker
>>> $settings = \RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting::getInstance();
>>> $settings->hasCredentials()
>>> exit

# Tester la connexion réseau
curl -I https://eu.api.ovh.com/1.0/

# Vérifier les logs
tail -f storage/logs/laravel.log
```

### Problème: "Sync fails for specific IP"

**Solutions**:
```bash
# Tester l'IP spécifique
php artisan firewall:test-connection --ip=VOTRE_IP

# Vérifier la configuration IP dans la base de données
php artisan tinker
>>> \RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallIpConfig::all()
>>> exit

# Vérifier les logs de sync
php artisan tinker
>>> \RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSyncLog::where('status', 'failed')->latest()->get()
>>> exit
```

### Problème: "Scheduler not running"

**Solutions**:
```bash
# Vérifier le cron
crontab -l

# Ajouter le cron si manquant
* * * * * cd /var/www/pelican && php artisan schedule:run >> /dev/null 2>&1

# Tester manuellement
php artisan schedule:run
```

### Problème: "Permissions denied"

**Solutions**:
```bash
# Vérifier les permissions des fichiers
ls -la /var/www/pelican/plugins/firewall-ovh-games

# Corriger les permissions si nécessaire
chown -R www-data:www-data /var/www/pelican/plugins/firewall-ovh-games
chmod -R 755 /var/www/pelican/plugins/firewall-ovh-games
```

## 📝 Logs et Monitoring

### Logs Laravel

```bash
# Voir les logs en temps réel
tail -f /var/www/pelican/storage/logs/laravel.log

# Rechercher les erreurs du plugin
grep "Firewall" /var/www/pelican/storage/logs/laravel.log
```

### Logs de Synchronisation

Dans le panel admin:
1. **Firewall OVH** → **Sync Logs**
2. Filtrer par statut "failed" pour voir les erreurs
3. Cliquer sur un log pour voir les détails complets

### Monitoring des Performances

```bash
# Vérifier le temps d'exécution de la sync
php artisan firewall:sync --verbose

# Vérifier la charge du serveur pendant la sync
top -b -n 1 | grep php
```

## ✅ Checklist de Validation Finale

Avant de considérer l'installation comme réussie:

- [ ] Connexion OVH API fonctionne
- [ ] Au moins une IP configurée et testée
- [ ] Synchronisation manuelle réussie
- [ ] Logs de synchronisation visibles dans le panel
- [ ] Widget dashboard affiche les bonnes données
- [ ] Création d'allocation déclenche une sync
- [ ] Suppression d'allocation déclenche une sync
- [ ] Scheduler configuré et fonctionnel
- [ ] Aucune erreur dans les logs Laravel
- [ ] Permissions correctes sur tous les fichiers

## 🎉 Installation Réussie!

Si tous les tests passent, votre plugin est correctement installé et fonctionnel!

Pour toute question ou problème:
- Consulter le README.md
- Vérifier les logs de synchronisation
- Rejoindre le Discord Pelican Panel

# 📊 Système de Récapitulatif Quotidien des Commandes

## 🎯 Description

Ce système automatisé envoie chaque nuit un récapitulatif par email des nouvelles commandes du jour aux administrateurs du système Gala 2025.

## 📁 Structure des Fichiers

```
cron/daily_order_summary/
├── run_summary.php           # Script principal du cron
├── order_summary.class.php   # Classe de traitement des données
├── email_template.php        # Template HTML pour l'email
├── cron_config.json         # Configuration technique du système
└── README.md               # Cette documentation
```

## ⚙️ Configuration

### 1. Configuration Technique (`cron_config.json`)

- **Heure d'envoi**: Modifiable via `send_hour` et `send_minute`
- **Fuseau horaire**: Configurable via `timezone`
- **Retry**: Gestion des tentatives d'envoi échouées
- **Limites**: Nombre maximum de commandes affichées en détail

### 2. Liste des Destinataires (`../../data/email_recipients.json`)

Fichier JSON contenant la liste des administrateurs recevant le récapitulatif:

```json
{
  "recipients": [
    {
      "email": "admin@example.com",
      "name": "Administrateur Gala",
      "active": true
    }
  ]
}
```

## 🚀 Installation du Cron

### Linux/Unix (crontab)

```bash
# Éditer le crontab
crontab -e

# Ajouter cette ligne pour exécution à 00:30 chaque nuit
30 0 * * * /usr/bin/php /chemin/vers/gala/cron/daily_order_summary/run_summary.php

# Ou avec logs
30 0 * * * /usr/bin/php /chemin/vers/gala/cron/daily_order_summary/run_summary.php >> /var/log/gala_cron.log 2>&1
```

### Windows (Planificateur de tâches)

1. Ouvrir le Planificateur de tâches
2. Créer une tâche de base
3. Configurer le déclencheur: Quotidien à 00:30
4. Action: Démarrer un programme
5. Programme: `php.exe`
6. Arguments: `run_summary.php`
7. Dossier de démarrage: `chemin\vers\gala\cron\daily_order_summary`

## 🧪 Tests

### Test Manuel

```bash
# Via ligne de commande
php run_summary.php

# Via navigateur (mode test)
http://votre-domaine/cron/daily_order_summary/run_summary.php?run_cron=true
```

### Vérifications

1. **Logs**: Vérifier dans `../../logs/cron_order_summary_*.log`
2. **Configuration**: S'assurer que les paths dans `cron_config.json` sont corrects
3. **Permissions**: Vérifier les droits d'écriture sur le dossier logs
4. **Email**: Tester l'envoi avec une configuration SMTP valide

## 📧 Contenu du Récapitulatif

L'email envoyé contient:

- **Statistiques globales**: Nombre de commandes, chiffre d'affaires
- **Répartition par type**: Photos, USB
- **Breakdown par activité**: Nombre de commandes par événement
- **Détail des commandes**: Informations client et articles commandés
- **Design responsive**: Template HTML professionnel

## 🔧 Personnalisation

### Modifier l'Heure d'Envoi

Éditer `cron_config.json`:
```json
{
  "email_settings": {
    "send_hour": "01",    // Nouvelle heure (format 24h)
    "send_minute": "00"   // Nouvelles minutes
  }
}
```

### Ajouter/Retirer des Destinataires

Éditer `../../data/email_recipients.json` et mettre `"active": false` pour désactiver temporairement un destinataire.

### Modifier le Template Email

Le fichier `email_template.php` contient le design HTML. Il est possible de:
- Modifier les couleurs et styles CSS
- Ajouter de nouveaux éléments d'information
- Changer la structure du rapport

## 🚨 Dépannage

### Problèmes Courants

1. **Emails non envoyés**: Vérifier la configuration SMTP dans `config.php`
2. **Erreurs de path**: Ajuster les chemins dans `cron_config.json`
3. **Permissions**: S'assurer des droits d'écriture sur `logs/`
4. **Fuseau horaire**: Vérifier que le timezone est correct

### Logs de Debug

Tous les événements sont loggés dans:
- `../../logs/cron_order_summary_YYYY-MM.log`

Le niveau de log peut être ajusté dans `cron_config.json` (`log_level`).

## 📊 Métriques de Performance

Le système track automatiquement:
- Temps d'exécution
- Nombre d'emails envoyés/échoués
- Nombre de commandes traitées
- Montant total du chiffre d'affaires

Ces métriques sont disponibles dans les logs et dans la réponse JSON du script.

---

**💡 Note**: Ce système est complètement isolé du code principal de l'application et peut être géré indépendamment.
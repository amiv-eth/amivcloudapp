# Amiv Cloud App

## Setup

* Place this app in **nextcloud/custom_apps/**
* Add the following keys to the system configuration `config/config.php`
* Apply the core patch for your corresponding version with `patch -p1 < custom_apps/amivcloudapp/nextcloud-\<version\>.patch`.

  *(You must be in the base directory of Nextcloud, which is most probably `~/public_html`)*

```php
'amiv.api_url' => 'https://api.amiv.ethz.ch/',
'amiv.api_key' => '<api-key-used-for-background-sync>',
'amiv.oauth_client_identifier' => 'AMIV Cloud',
'amiv.oauth_autoredirect' => true,      // Automatically redirect to OAuth login page.
'amiv.file_owner' => 'amivadmin',       // Owner of all group folders
'amiv.api_admin_groups' => ['<admin-group-id>'],
'amiv.group_share_retention' => 172800  // how long deleted group folders are kept in seconds.
```

The provided API key needs `read` permissions for `users`, `groups` and `groupmemberships`.

## Prepare for new Nextcloud version

Increase the max Nextcloud version in `appinfo/info.xml`. Verify the apps functionality in a test environment. If everything is working, deploy it to the production environment. Please make sure that you enable the maintenance mode first in Nextcloud before you continue the deploy of the new app version.

**Do a backup beforehand!**

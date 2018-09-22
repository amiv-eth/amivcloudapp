# Amiv Cloud App

## Setup

* Place this app in **nextcloud/custom_apps/**
* Add the following keys to the system configuration `config/config.php`

```php
'amiv.api_url' => 'https://api.amiv.ethz.ch/',
'amiv.api_key' => '<api-key-used-for-background-sync>',
'amiv.oauth_client_identifier' => 'AMIV Cloud',
'amiv.oauth_autoredirect' => true,
'amiv.file_owner' => 'amivadmin',
'amiv.api_admin_groups' => ['<admin-group-id>']
```

The provided API key needs `read` permissions for `users`, `groups` and `groupmemberships`.

## Prepare for new Nextcloud version

Increase the max Nextcloud version in `appinfo/info.xml`. Verify the apps functionality in a test environment. If everything is working, deploy it to the production environment. Please make sure that you enable the maintenance mode first in Nextcloud before you continue the deploy of the new app version.

** Do a backup beforehand!**

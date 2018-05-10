# Amiv Cloud App

## Setup

* Place this app in **nextcloud/apps/**
* Open terminal and change to the root directory of your nextcloud installation (named ```nextcloud``` by default)
* Apply patch file

      $ patch -p3 < nextcloud-\<version\>.patch

  where you have to replace *\<version\>* with your Nextcloud version number (if available in this repository!)
* Add the following keys to the system configuration `config/config.php`

      'amiv.api_url' => 'https://api.amiv.ethz.ch/',
      'amiv.api_key' => '<api-key-used-for-background-sync>',
      'amiv.oauth_client_identifier' => 'AMIV Cloud',
      'amiv.oauth_autoredirect' => true,
      'amiv.file_owner' => 'amivadmin',
      'amiv.api_admin_groups' => ['Administrator'],
      'amiv.internal_group' => 'member'

Please note that the patch file for Nextcloud version 11 does also apply for version 10!

## Prepare for new Nextcloud version

Check the latest patch file and create a new version based on the previous one for the latest version of Nextcloud. Increase the max Nextcloud version in `appinfo/info.xml`. Verify the apps functionality in a test environment. If everything is working, deploy it to the production environment. Please make sure that you enable the maintenance mode first in Nextcloud before you continue the deploy of the new app version.

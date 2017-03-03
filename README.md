# Amiv Cloud App

## Setup

* Place this app in **nextcloud/apps/**
* Open terminal and change to the root directory of your nextcloud installation (named ```nextcloud``` by default)
* Apply patch file 

      $ patch -p3 < nextcloud-\<version\>.patch
  
  where you have to replace *\<version\>* with your Nextcloud version number (if available in this repository!)
* Create file **lib/AMIVConfig.php** (See example file lib/AMIVConfig.example.php)

Please note that the patch file for Nextcloud verion 11 does also apply for version 10!

## Deploy

To prepare the app for production environment, the **curl options for SSL-Certificates** need to be set to **true** before using in a production environment.

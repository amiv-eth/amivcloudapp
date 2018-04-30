<?php
/**
 * @copyright Copyright (c) 2018, AMIV an der ETH
 *
 * @author Sandro Lutz <code@temparus.ch>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */


namespace OCA\AmivCloudApp;

use OCP\IConfig;
use OCP\ILogger;

/**
 * Application configutarion
 *
 * @package OCA\AmivCloudApp
 */
class AppConfig {
    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * Config service
     *
     * @var OCP\IConfig
     */
    private $config;

    /**
     * Logger
     *
     * @var OCP\ILogger
     */
    private $logger;

    /**
     * The config key for the api server address
     *
     * @var string
     */
    private $_apiServerUrl = "ApiServerUrl";

    /**
     * The config key for the api key
     * 
     * @var string
     */
    private $_apiKey = 'ApiKey';

    /**
     * The config key for the file owner account name
     *
     * @var string
     */
    private $_fileOwnerAccount = "FileOwnerAccount";

    /**
     * The config key for the api admin groups
     *
     * @var string
     */
    private $_apiAdminGroups = "ApiAdminGroups";

    /**
     * The config key for the internal group
     *
     * @var string
     */
    private $_internalGroup = "InternalGroup";

    /**
     * The config key for the settings error
     *
     * @var string
     */
    private $_settingsError = "settings_error";

    /**
     * @param string $AppName - application name
     */
    public function __construct($AppName) {
        $this->appName = $AppName;
        $this->config = \OC::$server->getConfig();
        $this->logger = \OC::$server->getLogger();
    }

    /**
     * Get value from the system configuration
     * 
     * @param string $key - key configuration
     *
     * @return string
     */
    public function GetSystemValue($key) {
        if (!empty($this->config->getSystemValue($this->appName))
            && array_key_exists($key, $this->config->getSystemValue($this->appName))) {
            return $this->config->getSystemValue($this->appName)[$key];
        }
        return NULL;
    }

    /**
     * Save the api service address to the application configuration
     *
     * @param string $apiServer - api service address
     */
    public function SetApiServerUrl($apiServer) {
        $apiServer = strtolower(trim($apiServer));
        if (strlen($apiServer) > 0) {
            $apiServer = rtrim($apiServer, "/") . "/";
            if (!preg_match("/(^https?:\/\/)|^\//i", $apiServer)) {
                $apiServer = "https://" . $apiServer;
            }
        }
        $this->logger->info("SetApiServerUrl: " . $apiServer, ["app" => $this->appName]);
        $this->config->setAppValue($this->appName, $this->_apiServerUrl, $apiServer);
    }
    /**
     * Get the api service address from the application configuration
     *
     * @return string
     */
    public function GetApiServerUrl() {
        $url = $this->config->getAppValue($this->appName, $this->_apiServerUrl, "");
        if (empty($url)) {
            $url = $this->getSystemValue($this->_apiServerUrl);
        }
        if ($url !== "/") {
            $url = rtrim($url, "/");
            if (strlen($url) > 0) {
                $url = $url . "/";
            }
        }
        return $url;
    }

    /**
     * Save the api key to the application configuration
     *
     * @param string $apiKey - api key
     */
    public function SetApiKey($apiKey) {
        $this->logger->info("SetApiKey: " . $apiKey, ["app" => $this->appName]);
        $this->config->setAppValue($this->appName, $this->_apiKey, $apiKey);
    }
    
    /**
     * Get the api key from the application configuration
     *
     * @return string
     */
    public function GetApiKey() {
        return $this->config->getAppValue($this->appName, $this->_apiKey, "");
    }

    /**
     * Save the file owner account to the application configuration
     *
     * @param string $account - file owner account
     */
    public function SetFileOwnerAccount($account) {
        $account = trim($account);
        $this->logger->info("SetFileOwnerAccount: " . $account, ["app" => $this->appName]);
        $this->config->setAppValue($this->appName, $this->_fileOwnerAccount, $account);
    }

    /**
     * Get the file owner account from the application configuration
     *
     * @return string
     */
    public function GetFileOwnerAccount() {
        return $this->config->getAppValue($this->appName, $this->_fileOwnerAccount, "");
    }

    /**
     * Save the admin groups from the api server to the application configuration
     *
     * @param array $groups - string of group names separated by comma
     */
    public function SetApiAdminGroups($groups) {
        $this->logger->info("SetApiAdminGroups: " . $groups, ["app" => $this->appName]);
        $this->config->setAppValue($this->appName, $this->_apiAdminGroups, $groups);
    }

    /**
     * Get the admin groups from the api server from the application configuration
     *
     * @return string
     */
    public function GetApiAdminGroups() {
        return $this->config->getAppValue($this->appName, $this->_apiAdminGroups, "");
    }

    /**
     * Get the admin groups from the api server from the application configuration as array
     *
     * @return array
     */
    public function GetApiAdminGroupsArray() {
        return array_map('trim', explode(',', $this->config->getAppValue($this->appName, $this->_apiAdminGroups, "")));
    }

    /**
     * Save the internal group to the application configuration
     *
     * @param string $internalGroup - internal group name
     */
    public function SetInternalGroup($internalGroup) {
        $this->logger->info("SetInternalGroup: " . $internalGroup, ["app" => $this->appName]);
        $this->config->setAppValue($this->appName, $this->_internalGroup, $internalGroup);
    }

    /**
     * Get the internal group from the application configuration
     *
     * @return string
     */
    public function GetInternalGroup() {
        return $this->config->getAppValue($this->appName, $this->_internalGroup, "");
    }

    /**
     * Save the status settings
     *
     * @param boolean $value - error
     */
    public function SetSettingsError($value) {
        $this->config->setAppValue($this->appName, $this->_settingsError, $value);
    }

    /**
     * Get the status settings
     *
     * @return boolean
     */
    public function SettingsAreSuccessful() {
        return empty($this->config->getAppValue($this->appName, $this->_settingsError, ""));
    }
}
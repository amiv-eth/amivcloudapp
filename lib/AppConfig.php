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
 * Application configuration
 *
 * @package OCA\AmivCloudApp
 */
class AppConfig {
    /**
     * Config service
     *
     * @var OCP\IConfig
     */
    public $config;

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

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
    private $_apiServerUrl = "amiv.api_url";

    /**
     * The config key for the api key
     * 
     * @var string
     */
    private $_apiKey = 'amiv.api_key';

    /**
     * The config key for the oauth client identifier
     * 
     * @var string
     */
    private $_oauthClientIdentifier = 'amiv.oauth_client_identifier';

    /**
     * The config key for the oauth redirect option
     */
    private $_oauthAutoRedirect = 'amiv.oauth_autoredirect';

    /**
     * The config key for the file owner account name
     *
     * @var string
     */
    private $_fileOwnerAccount = "amiv.file_owner";

    /**
     * The config key for the api admin groups
     *
     * @var string
     */
    private $_apiAdminGroups = "amiv.api_admin_groups";

    /**
     * The config key for the internal group
     *
     * @var string
     */
    private $_internalGroup = "amiv.internal_group";


    /**
     * @param string $AppName - application name
     */
    public function __construct($AppName) {
        $this->appName = $AppName;
        $this->config = \OC::$server->getConfig();
        $this->logger = \OC::$server->getLogger();
    }

    /**
     * Get system configuration value
     */
    public function getSystemValue($key, $defaultValue) {
        return $this->config->getSystemValue($key, $defaultValue);
    }

    /**
     * Get the api service address from the configuration
     *
     * @return string
     */
    public function getApiServerUrl() {
        $url = $this->config->getSystemValue($this->_apiServerUrl, "https://api.amiv.ethz.ch/");
        if ($url !== "/") {
            $url = rtrim($url, "/");
            if (strlen($url) > 0) {
                $url = $url . "/";
            }
        }
        return $url;
    }
    
    /**
     * Get the api key from the configuration
     *
     * @return string
     */
    public function getApiKey() {
        return $this->config->getSystemValue($this->_apiKey, "");
    }
    
    /**
     * Get the OAuth client identifier from the configuration
     *
     * @return string
     */
    public function getOAuthClientId() {
        return $this->config->getSystemValue($this->_oauthClientIdentifier, "AMIV Cloud");
    }

    /**
     * Get the OAuth auto redirect from the configuration
     */
    public function getOAuthAutoRedirect() {
        return $this->config->getSystemValue($this->_oauthAutoRedirect, false);
    }

    /**
     * Get the file owner account from the configuration
     *
     * @return string
     */
    public function getFileOwnerAccount() {
        return $this->config->getSystemValue($this->_fileOwnerAccount, "admin");
    }

    /**
     * Get the admin groups from the api server from the configuration
     *
     * @return array
     */
    public function getApiAdminGroups() {
        return $this->config->getSystemValue($this->_apiAdminGroups, []);
    }

    /**
     * Get the internal group from the configuration
     *
     * @return string
     */
    public function getInternalGroup() {
        return $this->config->getSystemValue($this->_internalGroup, "member");
    }
}
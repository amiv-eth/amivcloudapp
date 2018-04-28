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


namespace OCA\AmivCloudApp\Controller;

use OCP\App;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCA\AmivCloudApp\AppConfig;

/**
 * Settings controller for the administration page
 */
class SettingsController extends Controller {
    /**
     * Logger
     *
     * @var ILogger
     */
    private $logger;

    /**
     * Application configuration
     *
     * @var OCA\AmivCloudApp\AppConfig
     */

    private $config;

    /**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IURLGenerator $urlGenerator - url generator service
     * @param ILogger $logger - logger
     * @param OCA\AmivCloudApp\AppConfig $config - application configuration
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IURLGenerator $urlGenerator,
                                    ILogger $logger,
                                    AppConfig $config
                                    ) {
        parent::__construct($AppName, $request);
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->config = $config;
    }
    /**
     * Print config section
     *
     * @return TemplateResponse
     */
    public function index() {
        return new TemplateResponse($this->appName, "settings", $this->GetSettings(), "blank");
    }
    /**
     * Save app settings
     *
     * @param string $apiServerUrl - AMIV API server address
     * @param string $fileOwnerAccount - username of the shared folder's owner
     * @param string $apiAdminGroups - admin groups within the AMIV API
     * @param string $internalGroup - internal group name
     *
     * @return array
     */
    public function SaveSettings($apiServerUrl,
                                    $fileOwnerAccount,
                                    $apiAdminGroups,
                                    $internalGroup
                                    ) {
        $this->config->SetApiServerUrl($apiServerUrl);
        $this->config->SetFileOwnerAccount($fileOwnerAccount);
        $this->config->SetApiAdminGroups($apiAdminGroups);
        $this->config->SetInternalGroup($internalGroup);
        $apiServer = $this->config->GetApiServerUrl();
        if (!empty($apiServer)) {
            $error = $this->checkApiUrl($apiServer);
            $this->config->SetSettingsError($error);
        }
        return [
            "apiServerUrl" => $this->config->GetApiServerUrl(),
            "fileOwnerAccount" => $this->config->GetFileOwnerAccount(),
            "apiAdminGroups" => $this->config->GetApiAdminGroups(),
            "internalGroup" => $this->config->GetInternalGroup(),
            "error" => $error
            ];
    }

    /**
     * Get app settings
     *
     * @return array
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function GetSettings() {
        return [
          "apiServerUrl" => $this->config->GetApiServerUrl(),
          "fileOwnerAccount" => $this->config->GetFileOwnerAccount(),
          "apiAdminGroups" => $this->config->GetApiAdminGroups(),
          "internalGroup" => $this->config->GetInternalGroup()
        ];
    }
    
    /**
     * Checking AMIV API location
     *
     * @param string $apiServer - AMIV API service address
     *
     * @return string
     */
    private function checkApiUrl() {
        try {
            if (substr($this->urlGenerator->getAbsoluteURL("/"), 0, strlen("https")) === "https"
                && substr($this->config->GetApiServerUrl("/"), 0, strlen("https")) !== "https") {
                throw new \Exception($this->trans->t("Mixed Active Content is not allowed. HTTPS address for AMIV API Server is required."));
            }
        } catch (\Exception $e) {
            $this->logger->error("CommandRequest on check error: " . $e->getMessage(), array("app" => $this->appName));
            return $e->getMessage();
        }
        return "";
    }
}
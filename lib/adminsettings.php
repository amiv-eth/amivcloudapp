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

use OCP\Settings\ISettings;
use OCA\AmivCloudApp\AppInfo\Application;
use OCA\AmivCloudApp\Controller\SettingsController;

/**
 * Settings controller for the administration page
 */
class AdminSettings implements ISettings {
    public function __construct() {
    }

    /**
     * Print config section
     *
     * @return TemplateResponse
     */
    public function getForm() {
        $app = new Application();
        $container = $app->getContainer();
        $response = $container->query(SettingsController::class)->index();
        return $response;
    }
  
    /**
     * Get section ID
     *
     * @return string
     */
    public function getSection() {
        return "server";
    }

    /**
     * Get priority order
     *
     * @return int
     */
    public function getPriority() {
        return 50;
    }
}
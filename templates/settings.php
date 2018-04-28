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

style("amivcloudapp", "settings");
script("amivcloudapp", "settings");
?>

<div class="section section-amivcloudapp">
    <h2>Amiv Cloud App</h2>
    <a target="_blank" class="icon-info svg" title="" href="https://gitlab.ethz.ch/amiv/amivcloudapp" data-original-title="Documentation"></a>

    <p>AMIV API Server Location specifies the address of the server with the api services installed. Please change the '<apiserver>' for the server address in the line below.</p>

    <p class="amivcloudapp-header">AMIV API Server address</p>
    <input id="amivcloudappApiServerUrl" value="<?php p($_["apiServerUrl"]) ?>" placeholder="https://<amiv-api>/" type="text">

    <p class="amivcloudapp-header">File Owner Account Name (for shared folders)</p>
    <input id="amivcloudappFileOwnerAccount" value="<?php p($_["fileOwnerAccount"]) ?>" placeholder="file-owner" type="text">

    <p class="amivcloudapp-header">Nextcloud Administrators (API groups, separated by comma)</p>
    <input id="amivcloudappApiAdminGroups" value="<?php p($_["apiAdminGroups"]) ?>" placeholder="administrator-group" type="text">

    <p class="amivcloudapp-header">Internal Group (name of group used for members)</p>
    <input id="amivcloudappInternalGroup" value="<?php p($_["internalGroup"]) ?>" placeholder="internal-group" type="text">

    <a id="amivcloudappSave" class="button amivcloudapp-header">Save</a>
</div>


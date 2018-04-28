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

(function ($, OC) {

  $(document).ready(function () {
      OCA.AmivCloudApp = _.extend({}, OCA.AmivCloudApp);
      if (!OCA.AmivCloudApp.AppName) {
          OCA.AmivCloudApp = {
              AppName: "amivcloudapp"
          };
      }

      $("#amivcloudappSave").click(function () {
          $(".section-amivcloudapp").addClass("icon-loading");
          var amivcloudappApiServerUrl = $("#amivcloudappApiServerUrl").val().trim();
          var amivcloudappFileOwnerAccount = $("#amivcloudappFileOwnerAccount").val().trim();
          var amivcloudappApiAdminGroups = $("#amivcloudappApiAdminGroups").val().trim();
          var amivcloudappInternalGroup = $("#amivcloudappInternalGroup").val().trim();

          $.ajax({
              method: "PUT",
              url: OC.generateUrl("apps/amivcloudapp/ajax/settings"),
              data: {
                  apiServerUrl: amivcloudappApiServerUrl,
                  fileOwnerAccount: amivcloudappFileOwnerAccount,
                  apiAdminGroups: amivcloudappApiAdminGroups,
                  internalGroup: amivcloudappInternalGroup
              },
              success: function onSuccess(response) {
                  $(".section-amivcloudapp").removeClass("icon-loading");
                  if (response && response.documentserver != null) {
                      $("#amivcloudappApiServerUrl").val(response.apiServerUrl);
                      $("#amivcloudappFileOwnerAccount").val(response.fileOwnerAccount);
                      $("#amivcloudappApiAdminGroups").val(response.apiAdminGroups);
                      $("#amivcloudappInternalGroup").val(response.internalGroup);

                      var message =
                          response.error
                              ? (t(OCA.AmivCloudApp.AppName, "An Error occurred") + " (" + response.error + ")")
                              : t(OCA.AmivCloudApp.AppName, "Settings have been successfully updated");
                      var row = OC.Notification.show(message);
                      setTimeout(function () {
                          OC.Notification.hide(row);
                      }, 3000);
                  }
              }
          });
      });

      $(".section-amivcloudapp input").keypress(function (e) {
          var code = e.keyCode || e.which;
          if (code === 13) {
              $("#amivcloudappSave").click();
          }
      });
  });

})(jQuery, OC);
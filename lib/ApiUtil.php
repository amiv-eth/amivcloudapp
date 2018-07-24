<?php
/**
 * @copyright Copyright (c) 2016, AMIV an der ETH
 *
 * @author Sandro Lutz <code@temparus.ch>
 * @author Marco Eppenberger <mail@mebg.ch>
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

class ApiUtil {

    /**
     * send GET request to AMIV API
     * 
     * @param string $url
     * @param string $request
     * @param string $token
     */
    public static function get($url, $request, $token=null) {
        return self::rawreq($url, $request, null, null, $token);
    }

    /**
     * send POST request to AMIV API
     * 
     * @param string $url
     * @param string $request
     * @param string $postData
     * @param string $token
     */
    public static function post($url, $request, $postData, $token=null) {
        return self::rawreq($url, $request, $postData, null, $token);
    }

    /**
     * send DELETE request to AMIV API
     * 
     * @param string $url
     * @param string $request
     * @param string $etag
     * @param string $token
     */
    public static function delete($url, $request, $etag, $token=null) {
        return self::rawreq($url, $request, null, $etag, $token);
    }

    /**
     * assemble request and send it
     * 
     * @param string $url
     * @param string $request
     * @param string $postData
     * @param string $token
     */
    private static function rawreq($url, $request, $postData=null, $etag=null, $token=null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url .$request);
        // if we have post data, put it in request
        if ($postData != null) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); //timeout in seconds
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $header = [];
        if ($token != null) {
            $header[] = 'Authorization: ' .$token;
        }
        if ($etag != null) {
            $header[] = 'If-Match: ' .$etag;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $response = json_decode(curl_exec($ch));
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);
        return [$httpcode, $response];
    }
}

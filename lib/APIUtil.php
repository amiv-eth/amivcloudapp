<?php

namespace OCA\AmivCloudApp;

class APIUtil {
    static function get($request, $token=null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"https://amiv-apidev.vsos.ethz.ch/" .$request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // TODO: change SSL options to true
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); //timeout in seconds
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        if ($token != null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' .$token]);
        }
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $response = json_decode(curl_exec($ch));
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);
        return [$httpcode, $response];
    }

    static function post($request, $postData, $token=null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"https://amiv-apidev.vsos.ethz.ch/" .$request);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        // TODO: change SSL options to true
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); //timeout in seconds
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        if ($token != null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' .$token]);
        }
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $response = json_decode(curl_exec($ch));
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);
        return [$httpcode, $response];
    }
}
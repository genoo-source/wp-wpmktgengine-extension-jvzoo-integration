<?php
/**
 * This file is part of the WPMKTGENGINE plugin.
 *
 * Copyright 2016 Genoo, LLC. All rights reserved worldwide.  (web: http://www.wpmktgengine.com/)
 * GPL Version 2 Licensing:
 *  PHP code is licensed under the GNU General Public License Ver. 2 (GPL)
 *  Licensed "As-Is"; all warranties are disclaimed.
 *  HTML: http://www.gnu.org/copyleft/gpl.html
 *  Text: http://www.gnu.org/copyleft/gpl.txt
 *
 * Proprietary Licensing:
 *  Remaining code elements, including without limitation:
 *  images, cascading style sheets, and JavaScript elements
 *  are licensed under restricted license.
 *  http://www.wpmktgengine.com/terms-of-service
 *  Copyright 2016 Genoo LLC. All rights reserved worldwide.
 */

namespace WPME\JVZOO;

/**
 * Class JVZIPN
 *
 * @package WPME\JVZOO
 */
class JVZIPN
{
    /** @type \Genoo\Api|\WPMKTENGINE\Api */
    public $api;


    /**
     * Is Purchase notification from JVZOO?
     *
     * @return bool
     */
    public function isPurchaseNotification()
    {
        if(isset($_POST) && is_array($_POST) && array_key_exists('cverify', $_POST)){
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Validates Cipher Against POST data
     *
     * @param string $secretKey
     * @param array $data
     * @return bool
     */
    public static function isValidCipher($secretKey, $data = array())
    {
        // Only if valid data set
        if(isset($data) && is_array($data) && array_key_exists('cverify', $data) && !empty($secretKey)){
            // Calculator string
            $r = '';
            // Cipher verifier, get and remove
            $verify = $data['cverify'];
            unset($data['cverify']);
            // JVZOO magic code from their website
            // here; https://jvzoo.zendesk.com/hc/en-us/articles/206456857
            $ipnFields = array();
            foreach ($data as $key => $value){
                if($key == "cverify"){ continue; }
                $ipnFields[] = $key;
            }
            sort($ipnFields);
            foreach($ipnFields as $field){
                // if Magic Quotes are enabled $_POST[$field] will need to be
                // un-escaped before being appended to $pop
                $r = $r . $data[$field] . "|";
            }
            $r = $r . $secretKey;
            if(function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')){
                if('UTF-8' != mb_detect_encoding($r)){
                    $r = mb_convert_encoding($r, "UTF-8");
                }
            }
            $calcedVerify = sha1($r);
            $calcedVerify = strtoupper(substr($calcedVerify, 0, 8));
            // Ok is, is it valid?
            return $calcedVerify == $verify;
        }
        return FALSE;
    }

    /**
     * @param $api
     */
    public function setApi($api)
    {
        if(isset($api) && is_object($api)){
            if($api instanceof \Genoo\Api || $api instanceof \WPMKTENGINE\Api || $api instanceof \WPME\ApiFactory){
                $this->api = $api;
            }
        }
    }

    /**
     * @return mixed
     */
    public function getRequest()
    {
        return $_POST;
    }

    /**
     * @param int $product_id_internal
     * @param array $data
     * @return array
     */
    public static function createCartContents($product_id_internal, $data = array())
    {
        $r = array();
        $array['product_id'] = (int)$product_id_internal;
        $array['quantity'] = 1;
        $array['total_price'] = (float)$data['ctransamount'];
        $array['unit_price'] = (float)$data['ctransamount'];
        $array['external_product_id'] = (int)$data['cproditem'];
        $array['name'] = $data['cprodtitle'];
        $r[] = $array;
        return $r;
    }
}
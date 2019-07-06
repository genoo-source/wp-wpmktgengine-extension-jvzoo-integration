<?php
/*
    Plugin Name: JVZoo - WPMktgEngine | Genoo Extension
    Description: Genoo, LLC
    Author:  Genoo, LLC
    Author URI: http://www.genoo.com/
    Author Email: info@genoo.com
    Version: 1.2.60
    License: GPLv2
*/
/*
    Copyright 2015  WPMKTENGINE, LLC  (web : http://www.genoo.com/)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('WPMKTENGINE_JBZOO_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);

/**
 * On activation
 */

register_activation_hook(__FILE__, function(){
	// Basic extension data
	$fileFolder = basename(dirname(__FILE__));
	$file = basename(__FILE__);
	$filePlugin = $fileFolder . DIRECTORY_SEPARATOR . $file;
	// Activate?
	$activate = FALSE;
	$isGenoo = FALSE;
	// Get api / repo
    if(class_exists('\WPME\ApiFactory') && class_exists('\WPME\RepositorySettingsFactory')){
        $activate = TRUE;
        $repo = new \WPME\RepositorySettingsFactory();
        $api = new \WPME\ApiFactory($repo);
        if(class_exists('\Genoo\Api')){
            $isGenoo = TRUE;
        }
    } elseif(class_exists('\Genoo\Api') && class_exists('\Genoo\RepositorySettings')){
		$activate = TRUE;
		$repo = new \Genoo\RepositorySettings();
		$api = new \Genoo\Api($repo);
		$isGenoo = TRUE;
	} elseif(class_exists('\WPMKTENGINE\Api') && class_exists('\WPMKTENGINE\RepositorySettings')){
		$activate = TRUE;
		$repo = new \WPMKTENGINE\RepositorySettings();
		$api = new \WPMKTENGINE\Api($repo);
	}
	// 1. First protectoin, no WPME or Genoo plugin
	if($activate == FALSE){
		genoo_wpme_deactivate_plugin(
			$filePlugin,
			'This extension requires WPMktgEngine or Genoo plugin to work with.'
		);
	} else {
		// Right on, let's run the tests etc.
		// 2. Second test, can we activate this extension?
		// Active
		$active = get_option('wpmktengine_extension_ecommerce', NULL);
		$activeLeadType = FALSE;
		if($isGenoo === TRUE){
			$active = TRUE;
		}
		if($active === NULL || $active == FALSE || $active == '' || is_string($active) || $active == TRUE){
			// Oh oh, no value, lets add one
			try {
				$ecoomerceActivate = $api->getPackageEcommerce();
				if($ecoomerceActivate == TRUE || $isGenoo){
					// Might be older package
					$ch = curl_init();
					if(defined('GENOO_DOMAIN')){
						curl_setopt($ch, CURLOPT_URL, 'https:' . GENOO_DOMAIN . '/api/rest/ecommerceenable/true');
					} else {
						curl_setopt($ch, CURLOPT_URL, 'https:' . WPMKTENGINE_DOMAIN . '/api/rest/ecommerceenable/true');
					}
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-API-KEY: " . $api->key));
					$resp = curl_exec($ch);
					if(!$resp){
						$active = FALSE;
						$error = curl_error($ch);
						$errorCode = curl_errno($ch);
					} else {
						if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 202){
							// Active whowa whoooaa
							$active = TRUE;
							// now, get the lead_type_id
							$json = json_decode($resp);
							if(is_object($json) && isset($json->lead_type_id)){
								$activeLeadType = $json->lead_type_id;
							}
						}
					}
					curl_close($ch);
				}
			} catch (\Exception $e){
				$active = FALSE;
			}
			// Save new value
			update_option('wpmktengine_extension_ecommerce', $active, TRUE);
		}
		// 3. Check if we can activate the plugin after all
		if($active == FALSE){
			genoo_wpme_deactivate_plugin(
				$filePlugin,
				'This extension is not allowed as part of your package.'
			);
		} else {
			// 4. After all we can activate, that's great, lets add those calls
			try {
				$api->setStreamTypes(
					array(
						array(
							'name' => 'viewed product',
							'description' => ''
						),
						array(
							'name' => 'added product to cart',
							'description' => ''
						),
						array(
							'name' => 'order completed',
							'description' => ''
						),
						array(
							'name' => 'order canceled',
							'description' => ''
						),
						array(
							'name' => 'cart emptied',
							'description' => ''
						),
						array(
							'name' => 'order refund full',
							'description' => ''
						),
						array(
							'name' => 'order refund partial',
							'description' => ''
						),
						array(
							'name' => 'new cart',
							'description' => ''
						),
						array(
							'name' => 'new order',
							'description' => ''
						),
						array(
							'name' => 'order cancelled',
							'description' => ''
						),
						array(
							'name' => 'order refund full',
							'description' => ''
						),
						array(
							'name' => 'order refund partial',
							'description' => ''
						),
					)
				);
			} catch(\Exception $e){
				// Decide later
			}
			// Activate and save leadType, import products
			if($activeLeadType == FALSE || is_null($activeLeadType)){
				// Leadtype not provided, or NULL, they have to set up for them selfes
				// Create a NAG for setting up the field
				// Shouldnt happen
			} else {
				// Set up lead type
				$option = get_option('WPME_ECOMMERCE', array());
				// Save option
				$option['genooLeadUsercustomer'] = $activeLeadType;
				update_option('WPME_ECOMMERCE', $option);
			}
		}
	}
});

/**
 * WPMKTENGINE Extension
 */

add_action('wpmktengine_init', function($repositarySettings, $api, $cache){

    // Load libs
    require_once 'libs/WPME/JVZOO/JVZIPN.php';
    require_once 'libs/FullnameParser.php';

	/**
	 * Add settings page
	 *  - if not already in
	 */
	add_filter('wpmktengine_settings_sections', function($sections){
		if(is_array($sections) && !empty($sections)){
			$isEcommerce = FALSE;
			foreach($sections as $section){
				if($section['id'] == 'ECOMMERCE'){
					$isEcommerce = TRUE;
					break;
				}
			}
			if(!$isEcommerce){
				$sections[] = array(
					'id' => 'WPME_ECOMMERCE',
					'title' => __('Ecommerce', 'wpmktengine')
				);
			}
		}
		return $sections;
	}, 10, 1);

	/**
	 * Add fields to settings page
	 */
	add_filter('wpmktengine_settings_fields', function($fields){
		if(is_array($fields) && array_key_exists('genooLeads', $fields) && is_array($fields['genooLeads'])){
			if(!empty($fields['genooLeads'])){
				$exists = FALSE;
				$rolesSave = FALSE;
				foreach($fields['genooLeads'] as $key => $role) {
					if($role['type'] == 'select'
						&&
						$role['name'] == 'genooLeadUsercustomer'
					){
						// Save
						$keyToRemove = $key;
						$field = $role;
						// Remove from array
						unset($fields['genooLeads'][$key]);
						// Add field
						$field['label'] = 'Save ' . $role['label'] . ' lead as';
						$fields['WPME_ECOMMERCE'] = array($field);
						$exists = TRUE;
						break;
					}
				}
				if($exists === FALSE && isset($fields['genooLeads'][1]['options'])){
					$fields['WPME_ECOMMERCE'] = array(
						array(
							'label' => 'Save Costumer lead as',
							'name' => 'genooLeadUsercustomer',
							'type' => 'select',
							'options' => $fields['genooLeads'][1]['options']
						)
					);
				}
			}
		}
		return $fields;
	}, 909, 1);


	/**
	 * Genoo Leads, recompile to add ecommerce
	 */
	add_filter('option_genooLeads', function($array){
		// Lead type
		$leadType = 0;
		// Get saved
		$leadTypeSaved = get_option('WPME_ECOMMERCE');
		if(is_array($leadTypeSaved) && array_key_exists('genooLeadUsercustomer', $leadTypeSaved)){
			$leadType = $leadTypeSaved['genooLeadUsercustomer'];
		}
		$array['genooLeadUsercustomer'] = $leadType;
		return $array;
	}, 10, 1);



	/**
     * Add extensions to the Extensions list
     */

    add_filter('wpmktengine_tools_extensions_widget', function($array){
        // Empty string
        $r = '';
        // Get api
        global $WPME_API;
        // Do we have it?
        if(isset($WPME_API)){
            $settings = $WPME_API->settingsRepo;
            $settingsJVZOO = $settings->getOption('jvzoo_cipher_key', 'JVZOO');
            if(empty($settingsJVZOO)){
                $r .= ' - <strong style="color:orange">Not set up!</strong>';
            }
        }
        $array['JVZoo'] = '<span style="color:green">Active</span>' . $r;
        return $array;
    }, 10, 1);


    /**
     * Add settings page
     */

    add_filter('wpmktengine_settings_sections', function($sections){
        $sections[] = array(
            'id' => 'JVZOO',
            'title' => __('JVZOO', 'wpmktengine')
        );
        return $sections;
    }, 10, 1);

    /**
     * Add fields to settings page
     */
    add_filter('wpmktengine_settings_fields', function($fields){
        $fields['JVZOO'] = array(
            array(
                'name' => 'jvzoo_cipher_key',
                'id' => 'jvzoo_cipher_key',
                'label' => __('Cipher', 'wpmktengine'),
                'type' => 'text',
                'default' => '',
                'desc' =>
                    __('A cipher is used to determine that the information being sent is from a trusted source.  Your script will contain a secret key that will also be the seed for the data encryption JVZIPN uses to send the information to your script.  If this key is not the same on both ends, the encryption will not be able to be read by your script.', 'wpmktengine')
                    . '<br />'
                    . '<br />'
                    . __('To get your Cipher, Log into your account -> Click the My Account tab -> Click the Edit Account button (top right) -> Enter your Secret Key (Secret Key is Cipher)', 'wpmktengine')
            ),
            array(
                'label' => __('Notification URL', 'wpmktengine'),
                'type' => 'text',
                'name' => 'jv_invisible',
                'id' => 'jv_invisible',
                'attr' => array(
                    'style' => 'margin-bottom: -22px !important; visibility: hidden !important; height: 0 !important; display: block !important; width: 0 !important;'
                ),
                'desc' =>
                    __('In order for JVZoo integration to work, you have to set your product notification URL to your installation URL', 'wpmktengine') . '&nbsp;&#8594;&nbsp;<strong>'. home_url('/') .'</strong>'
                    . '<br />'
                    . '<br />'
                    . __('Please note, that the URL should be <strong>https://</strong> accessible.', 'wpmktengine')
                    . '<br />'
                    . '<br />'
                    . __('To set up your product, Click the Sellers tab -> Click your product in the sub nav -> Enter an Instant Notification URL in the JVZIPN URL field (ports 80 or 443 – SSL Recommended) -> Click Save Changes', 'wpmktengine')
            ),
        );
        return $fields;
    }, 10, 1);

    /**
     * Test WP request for POST
     */

    add_action('wp_loaded', function(){
        if(isset($_POST) && is_array($_POST)){
            $jvzoo = new \WPME\JVZOO\JVZIPN();
            if($jvzoo->isPurchaseNotification()){
                // Ok we have a valid notification
                // Get api
                global $WPME_API;
                $settings = $WPME_API->settingsRepo;
                $api = $WPME_API;
                // Get cipher key
                $settingsJVZOOCipher = $settings->getOption('jvzoo_cipher_key', 'JVZOO');
                if(!empty($settingsJVZOOCipher) && $jvzoo->isValidCipher($settingsJVZOOCipher, $_POST)){
                    // Ok with the cipher, we can continue
                    $jvzoo->setApi($WPME_API);
                    $data = $jvzoo->getRequest();
                    // Get email etc.
                    $email = isset($data) && is_array($data) && array_key_exists('ccustemail', $data) && !empty($data['ccustemail']) && filter_var($data['ccustemail'], FILTER_VALIDATE_EMAIL) !== FALSE ? $data['ccustemail'] : FALSE;
                    if($email !== FALSE){
                        // Lead null for now
                        $lead_id = NULL;
                        try {
                            // Lead exists, ok, set up Lead ID
                            $lead = $WPME_API->getLeadByEmail($email);
                            if(!is_null($lead) && is_array($lead)){
                                // We have a lead id
                                $lead_id = $lead[0]->genoo_id;
                            } else {
                                // NO lead, create one
                                $leadType = wpme_get_customer_lead_type();
	                            // Parse name
	                            $name1 = new \FullnameParser($data['ccustname']);
	                            $name1 = $name1->getNamePartials();
	                            $lead_first = $name1->fname;
	                            $lead_last = $name1->lname;
	                            // Set lead
                                $leadNew = $WPME_API->setLead(
	                                (int)$leadType,
	                                $email,
	                                $lead_first,
	                                $lead_last,
	                                '',
	                                FALSE,
	                                array(
		                                'source' => 'JVZoo' . $data['cprodtitle']
	                                )
                                );
                                $leadNew = (int)$leadNew;
                                if(!is_null($leadNew)){
                                    // We have a lead id
                                    $lead_id = $leadNew;
                                }
                            }
                        } catch (\Exception $e){}
                        // Ok we have a lead at this point, hopefully, lets continue
                        // Start order
                        if($lead_id !== NULL){
                            $product_id_external = $data['cproditem'];
                            if(method_exists($api, 'callCustom')){
                                try {
                                    $product_id = FALSE;
                                    $product = $api->callCustom('/wpmeproductbyextid/' . $product_id_external, 'GET', NULL);
                                    if($api->http->getResponseCode() == 204){
                                        // No content, product not set
                                        // set product and then continue
                                        $product_id = FALSE;
                                    } elseif($api->http->getResponseCode() == 200){
                                        if(is_object($product) && isset($product->product_id)){
                                            $product_id = $product->product_id;
                                        }
                                    }
                                } catch(Exception $e){
                                    if($api->http->getResponseCode() == 404){
                                        // Api call not implemented, we have to get all products and crawl through
                                        try {
                                            $products = $api->callCustom('/wpmeproducts', 'GET', NULL);
                                            if(is_array($products) && !empty($products)){
                                                foreach($products as $product){
                                                    if($product->external_product_id == $product_id_external){
                                                        $product_id = $product->product_id;
                                                        break;
                                                    }
                                                }
                                            }
                                        } catch(Exception $e){}
                                    } elseif($api->http->getResponseCode() == 204){
                                        // No content, product not set
                                        // set product and then continue
                                        $product_id = FALSE;
                                    }
                                }
                                // Do we have internal product_id?
                                if($product_id == FALSE){
                                    // We do not have internal product_id, let's create it
                                    try {
                                        $data = array(
                                            'categories' => array(),
                                            'id' => $product_id_external,
                                            'name' => $data['cprodtitle'],
                                            'price' => $data['ctransamount'],
                                            'sku' => '',
                                            'tags' => '',
                                            'type' => $data['cprodtype'],
                                            'url' => '',
                                            'vendor' => '',
                                            'weight' => 0,
                                            'option1_name' => '',
                                            'option1_value' => '',
                                            'option2_name' => '',
                                            'option2_value' => '',
                                            'option3_name' => '',
                                            'option3_value' => '',
                                        );
                                        $result = $api->setProduct($data);
                                        if(is_array($result) && isset($result[0])){
                                            $product_id = $result[0]->product_id;
                                        }
                                    } catch (\Exception $e){
                                        $product_id = FALSE;
                                    }
                                }
                                // Let's see if it's saved, if not we just don't continue, if yes, we do
                                if($product_id !== FALSE){
                                    // Prep data
                                    $cartContents = \WPME\JVZOO\JVZIPN::createCartContents($product_id, $_POST);
                                    $cartTotal = $data['ctransamount'];
                                    // We have a LEAD_ID and PRODUCT_ID ... we can finish the ORDER ...
                                    // Start order if product in
                                    try {
                                        $cartOrder = new \WPME\Ecommerce\CartOrder();
                                        $cartOrder->setApi($WPME_API);
                                        $cartOrder->addItemsArray($cartContents);
                                        $cartOrder->actionNewOrder();
	                                    $cartOrder->actionOrderFullfillment();
                                        $cartOrder->setUser($lead_id);
                                        $cartOrder->setBillingAddress('', '', '', '', '', '', '', '');
                                        $cartOrder->setAddressShippingSameAsBilling();
                                        $cartOrder->order_number = $data['caffitid'];
                                        $cartOrder->setTotal($cartTotal);
	                                    // Status?
	                                    $cartOrder->financial_status = 'paid';
	                                    $cartOrder->changed->financial_status = 'paid';
	                                    // Completed
	                                    $cartOrder->completed_date = \WPME\Ecommerce\Utils::getDateTime();
	                                    $cartOrder->changed->completed_date = \WPME\Ecommerce\Utils::getDateTime();
	                                    // Completed?
	                                    $cartOrder->order_status = 'completed';
	                                    $cartOrder->changed->order_status = 'completed';
	                                    // Send!
                                        $cartOrder->startNewOrder();
                                    } catch (Exception $e){}
                                }
                            }
                        }
                    }
                    // Kill it in the end of course
                    exit;
                }
            }
        }
    }, 10);

}, 20, 3);


if(!function_exists('wpme_get_customer_lead_type'))
{
	/**
	 * Get Customer Lead Type
	 *
	 * @return bool|int
	 */
	function wpme_get_customer_lead_type()
	{
		$leadType = FALSE;
		$leadTypeSaved = get_option('WPME_ECOMMERCE');
		if(is_array($leadTypeSaved) && array_key_exists('genooLeadUsercustomer', $leadTypeSaved)){
			$leadType = (int)$leadTypeSaved['genooLeadUsercustomer'];
		}
		return $leadType === 0 ? FALSE : $leadType;
	}
}


/**
 * Genoo / WPME deactivation function
 */
if(!function_exists('genoo_wpme_deactivate_plugin')){

    /**
     * @param $file
     * @param $message
     * @param string $recover
     */

    function genoo_wpme_deactivate_plugin($file, $message, $recover = '')
    {
        // Require files
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        // Deactivate plugin
        deactivate_plugins($file);
        unset($_GET['activate']);
        // Recover link
        if(empty($recover)){
            $recover = '</p><p><a href="'. admin_url('plugins.php') .'">&laquo; ' . __('Back to plugins.', 'wpmktengine') . '</a>';
        }
        // Die with a message
        wp_die($message . $recover);
        exit();
    }
}
<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Stephan Schmidt <schst@php.net>                             |
// +----------------------------------------------------------------------+
//
// $Id: Ebay.php,v 1.6 2004/03/16 23:12:43 schst Exp $

/**
 * Services/Ebay.php
 *
 * package to access the eBay API
 *
 * @package  Services_Ebay
 * @author   Stephan Schmidt <schst@php.net>
 */

/**
 *  uses local api configs
 */
 require_once 'config_api.php';
 require_once 'connect.php';
 require_once 'util.php';

/**
 * uses PEAR error handling
 */
 require_once 'PEAR.php';
 
/**
 * uses XML_Serializer to build the request XML
 */
 require_once 'XML/Serializer.php';
 
/**
 * uses XML_Unserializer to parse the response XML
 */
 require_once 'XML/Unserializer.php';
 
/**
 * error happened 
 */
define( 'SERVICES_EBAY_ERROR_TRANSPORT', 20000 );

/**
 * error happened 
 */
define( 'SERVICES_EBAY_ERROR_NO_RETURN_VALUE', 20001 );

/**
 * Services/Ebay.php
 *
 * package to access the eBay API
 *
 * @package  Services_Ebay
 * @author   Stephan Schmidt <schst@php.net>
 * @link     http://developer.ebay.com/DevProgram/developer/api.asp
 */
class Services_Ebay
{
    var $siteId;
   /**
    * developer ID
    *
    * @access    public
    * @var       string
    */
    var $devId;

   /**
    * application ID
    *
    * @access    public
    * @var       string
    */
    var $appId;

   /**
    * certificate ID
    *
    * @access    public
    * @var       string
    */
    var $certId;

   /**
    * compatibility level
    *
    * @access    public
    * @var       integer
    */
    var $compatLevel = 631; //443;//305;

   /**
    * username
    *
    * @access    public
    * @var       string
    */
    var $username;

   /**
    * password
    *
    * @access    public
    * @var       string
    */
    var $passwd;

   /**
    * debug flag
    *
    * If set to 1, outgoing and incoming xml will
    * be stored in _wire
    *
    * @access    private
    * @var       integer
    */
    var $_debug = 1;

   /**
    * XML wire
    *
    * @access    private
    * @var       string
    */
    var $_wire;

   /**
    * XML_Serializer object
    *
    * @access    private
    * @var       object
    */
    var $_ser;

   /**
    * URL of the API
    *
    * @access    private
    * @var       string
    */
    //var $_apiUrl = 'https://api.ebay.com/ws/api.dll';
    //var $_apiUrl = 'https://api.sandbox.ebay.com/ws/api.dll';
	var $_apiUrl;

   /**
    * Transport driver
    *
    * @access    private
    * @var       string
    */
    var $_driver = 'Curl';

   /**
    * constructor
    *
    * @access   public
    * @param    string  developer id
    * @param    string  application id
    * @param    string  certificate id
    */
    function Services_Ebay( $devId, $appId, $certId, $siteId=77, $options_ser=0, $options_unser=0 )
    {
		global $db, $dbr;
        $this->_logfile = 'ebay.log';
        $this->devId  = $devId;
        $this->appId  = $appId;
        $this->certId = $certId;
		$this->siteId = $siteId;
//        $this->_apiUrl = $GLOBALS["_apiUrl_value"];
        $this->_apiUrl = getParByName($db, $dbr, $siteId, "_apiUrl_value");

        $opts = array(
                         'indent'             => '  ',
                         'linebreak'          => "\n",
                         'typeHints'          => false,
                         'addDecl'            => true,
                         'scalarAsAttributes' => false,
                         'encoding' => 'utf-8',
                        'attributesArray' => '__attrs__',
//                         'rootName'           => 'request',
                         'mode' => 'simplexml',
                         'rootAttributes'     => array( 'xmlns' => 'urn:ebay:apis:eBLBaseComponents' )
                    );
		if ($options_ser) { 
			$opts=$options_ser;
		};	
        $this->_ser   =new XML_Serializer( $opts );

        $opts = array(
                    );
		if ($options_unser) { 
			$opts=$options_unser;
		};	
        $this->_us =new XML_Unserializer( $opts );
    }

   /**
    * set the username and password used for
    * transactions.
    *
    * @access   public
    * @param    string      username
    * @param    string      password
    */
    function setAuth( $aaToken )
    {
        $this->aaToken= $aaToken;
//		echo $this->aaToken.'<br>';
    }

   /**
    * sets the debug level
    *
    * Supported levels are:
    * - 0, to disable debugging
    * - 1, to store XML data in the _wire property
    *
    * @access   public
    * @param    integer     debug level
    */
    function setDebugLevel( $debug )
    {
        $this->_debug = $debug;
    }

   /**
    * return API version
    *
    * @access   public
    * @return   string  $version API version
    */
    function apiVersion()
    {
        return '0.1';
    }

   /**
    * get the official eBay Time
    *
    * @access public
    * @return string    current eBay time (GMT)
    */
    function GetLogoURL( $size = 'Medium' )
    {
        $params = array( 'Size' => $size );
        return $this->sendRequest( 'GetLogoURL', $params, 'Logo' );
    }
    
   /**
    * get the logo URL
    *
    * @access public
    * @param  string    size of the logo (Small, Medium, Large)
    * @return array     array containing width, height and URL of the logo
    */
    function GeteBayOfficialTime()
    {
        return $this->sendRequest( 'GeteBayOfficialTime', null, 'Timestamp' );
    }
    
   /**
    * get information about a user
    *
    * @access public
    * @param  string    user id
    * @param  integer   detail level, defines which information you want to retrieve
    * @param  string    item id, needed to retrieve the email address of a user
    * @return array     array containing user info
    */
    function GetUser( $userId, $DetailLevel = 0, $itemId = null )
    {
        $params = array( 'UserID' => $userId, 'DetailLevel' => $DetailLevel );
        if ($itemId !== null) {
            $params['ItemID'] = $itemId;
        }
        return $this->sendRequest( 'GetUser', $params);
    }
    
    function GetUserContactDetails( $ContactID, $ItemID, $RequesterID)
    {
        $params = array( 'ContactID' => $ContactID, 'ItemID' => $ItemID, 'RequesterID' => $RequesterID );
        return $this->sendRequest( 'GetUserContactDetails', $params);
    }
    
    function GetStore()
    {
        $params = array(
                        'CategoryStructureOnly'    => true,
                    );
        return $this->sendRequest( 'GetStore', $params, null );
    }
    
    function GetCategoryFeatures($CategoryID, $FeatureID=0)
    {
		if ($FeatureID) {
	        $params = array(
                        'CategoryID'    => $CategoryID,
                        'FeatureID'    => $FeatureID,
						'DetailLevel' => 'ReturnAll',
                    );
		} else {
	        $params = array(
						'DetailLevel' => 'ReturnAll',
                    );
			if ($CategoryID) {
				$params['AllFeaturesForCategory'] = true;
				$params['CategoryID'] = $CategoryID;
			} else {
				$params['ViewAllNodes'] = true;
			}
		}
        return $this->sendRequest( 'GetCategoryFeatures', $params, null );
    }
    
    function GetApiAccessRules()
    {
        $params = array(
                    );
        return $this->sendRequest( 'GetApiAccessRules', $params, null );
    }
    
   /**
    * get categories
    *
    * @access public
    * @param  integer   parent category
    * @param  integer   detail level, setting this to zero will return only version
    * @param  integer   view only leaf nodes (0) or all nodes (1)
    * @return array     array containing categories
    */
    function GetCategories( $CategoryParent = 0, $SiteId, $DetailLevel, $ViewAllNodes = 0, $LevelLimit = 0)
    {
        $params = array(
                        'DetailLevel'    => $DetailLevel,
                        'ViewAllNodes'   => $ViewAllNodes,
                        'CategorySiteID'         => $SiteId
                    );
//			$params['Item'] = array('Site'=> $SiteId);
        if ($CategoryParent) {
            $params['CategoryParent'] = $CategoryParent;
        }
        if ($LevelLimit) {
            $params['LevelLimit'] = $LevelLimit;
        }
//		echo $SiteId;
        return $this->sendRequest( 'GetCategories', $params, null );
    }
    

   
    function GetAttributesCS ($SiteId, $csid, $DetailLevel)
    {
        $params = array(
                        'DetailLevel'    => $DetailLevel,
//                        'SiteId'         => $SiteId,
                    );
        if ($csid) {
			$params['Characteristics'] = array('CSId'=> $csid);
        }
        return $this->sendRequest( 'GetAttributesCS', $params);
    }

    function GetCategory2CS ($SiteId, $CategoryId, $DetailLevel)
    {
        $params = array(
                        'DetailLevel'    => $DetailLevel,
//                        'SiteId'         => $SiteId,
                        'CategoryID'      => $CategoryId,
                    );
        if (!$CategoryId) {
            unset($params['CategoryID']);
        }
        return $this->sendRequest( 'GetCategory2CS', $params);
    }

   
   /**
    * get an item
    *
    * @access public
    * @param  array     parameters for the request
    * @return array     array containing categories
    */
    function GeteBayDetails( $DetailName)
    {
		if (strlen($DetailName))
	        $params = array(
                        'DetailName'             => $DetailName,
                    );
		else 		
	        $params = array();
        return $this->sendRequest( 'GeteBayDetails', $params);
    }

    function GetCategorySpecifics( $category_id, $file=false)
    {
        $params = array(
                        'CategorySpecificsFileInfo'             => $file,
                        'CategoryID'             => array($category_id),
                        'Version'             => 631,
                    );
        return $this->sendRequest( 'GetCategorySpecifics', $params);
    }

    function GetItem( $Id = 0, $DetailLevel = 1 )
    {
        $params = array(
                        'ItemID'             => $Id,
                        'DetailLevel'    => $DetailLevel,
                    );
        return $this->sendRequest( 'GetItem', $params, 'Item' );
    }

    function GetAllBidders( $Id = 0, $DetailLevel = 1 )
    {
        $params = array(
                        'ItemID'             => $Id,
                        'CallMode'    => $DetailLevel,
						'IncludeBiddingSummary' => true,
                    );
        return $this->sendRequest( 'GetAllBidders', $params);
    }

   /**
    * get item transactions
    *
    * @access public
    * @param  array     parameters for the request
    * @return array     array containing categories
    */
    function GetItemTransactions( $Id = 0, $params=array(), $DetailLevel = 1 )
    {
        $params['ItemID'] = $Id;
        $params['DetailLevel']  = $DetailLevel;
			return $this->sendRequest( 'GetItemTransactions', $params, null );
    }

   /**
    * get an item
    *
    * @access public
    * @param  array     parameters for the request
    * @return array     array containing categories
    */
    function ValidateTestUserRegistration( $Id = 0, $DetailLevel = 1 )
    {
        $params = array(
                        'Id'             => $Id,
                        'DetailLevel'    => $DetailLevel,
                    );
        return $this->sendRequest( 'ValidateTestUserRegistration', $params, 'CallStatus' );
    }

    function downloadFileRequest( $taskReferenceId, $fileReferenceId)
    {
        $params = array(
                        'fileReferenceId' => $fileReferenceId,
                        'taskReferenceId' => $taskReferenceId,
                    );
        return $this->sendSimpleRequest( 'downloadFile', $params);
    }


    function EndItem($params)
    {
//        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest( 'EndItem', $params);
    }

    function EndFixedPriceItem($params)
    {
//        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest( 'EndFixedPriceItem', $params);
    }

    function AddItem($params, $DetailLevel = 1)
    {
//        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest( 'AddItem', $params, null, array(&$this, 'transformAttrs') );
    }

    function RelistItem($params, $DetailLevel = 1)
    {
//        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest( 'RelistItem', $params, null, array(&$this, 'transformAttrs') );
    }

    function RelistFixedPriceItem($params, $DetailLevel = 1)
    {
//        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest( 'RelistFixedPriceItem', $params, null, array(&$this, 'transformAttrs') );
    }

    function ReviseItem($params, $DetailLevel = 1)
    {
//        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest( 'ReviseItem', $params, null, array(&$this, 'transformAttrs') );
    }

    function ReviseFixedPriceItem($params, $DetailLevel = 1)
    {
//        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest( 'ReviseFixedPriceItem', $params, null, array(&$this, 'transformAttrs') );
    }

    function AddSecondChanceItem($params)
    {
//        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest( 'AddSecondChanceItem', $params, null, array(&$this, 'transformAttrs') );
    }

    function VerifyAddItem($params, $DetailLevel = 1)
    {
        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest( 'VerifyAddItem', $params, 'Item', array(&$this, 'transformAttrs') );
    }

   
    function transformAttrs($xml)
    {
//        $xml = preg_replace ('~\<Condition\>(.+)\</Condition\>~Us', '<Attributes><AttributeSet id="1950"><Attribute id="10244"><ValueList><Value id="10425"><ValueLiteral><![CDATA[Neu]]></ValueLiteral></Value></ValueList></Attribute></AttributeSet></Attributes>', $xml);
//        $xml = preg_replace ('~\<Guarantee\>(.+)\</Guarantee\>~Us', '<Attributes><AttributeSet id="2136"><Attribute id="3805"><ValueList><Value id="32037"><ValueLiteral><![CDATA[Money Back]]></ValueLiteral></Value></ValueList></Attribute><Attribute id="3804"><ValueList><Value id="32035"><ValueLiteral><![CDATA[14 Days]]></ValueLiteral></Value></ValueList></Attribute><Attribute id="3803"><ValueList><Value id="32040"><ValueLiteral><![CDATA[14-Tage-Geld-zuruck]]></ValueLiteral></Value></ValueList></Attribute><Attribute id="3806"><ValueList><Value id="-3"><ValueLiteral><![CDATA[Return policy custom text.]]></ValueLiteral></Value></ValueList></Attribute></AttributeSet></Attributes>', $xml);
        return $xml;
    }

    function GetSellerList ($params=array(), $DetailLevel)
    {
        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest('GetSellerList', $params, null );
    }

    function GetFeedback ($params=array(), $DetailLevel)
    {
        $params['DetailLevel'] = $DetailLevel;
        return $this->sendRequest('GetFeedback', $params, null );
    }

    function LeaveFeedback ($params=array())
    {
        return $this->sendRequest('LeaveFeedback', $params, null);
    }

   /**
    * build XML code for a request
    *
    * @access   private
    * @param    string      verb of the request
    * @param    array|null  parameters of the request
    * @return   string      XML request
    */
    function _buildRequestBody( $verb, $params = array(), $interceptor = '' )
    {
        $request = array(
                            'RequesterCredentials' => array('eBayAuthToken'    => $this->aaToken),
//                            'DetailLevel'     => 0,
//                            'ErrorLevel'      => 1,
//                            'SiteId'          => 0
//                            'Verb'            => $verb
                        );
        $request = array_merge($request, $params);

        #$this->_2utf($request);

        $opt = array(
                         'rootName'           => $verb.'Request'
					);	 

        $this->_ser->serialize($request, $opt);

        $ret = $this->_ser->getSerializedData();
        

        if (is_callable($interceptor)) {
            $ret = call_user_func($interceptor, $ret);
        }

        return $ret;
    }

   /**
    * send a request
    *
    * This method is used by the API methods. You
    * may call it directly to access any eBay function that
    * is not yet implemented.
    *
    * @access   public
    * @param    string      function to call
    * @param    array       associative array containing all parameters for the function call
    * @param    string|null name of the value that should be returned. If set to null the complete responsewill be returned
    * @return   array       response
    */
    function sendRequest( $verb, $params = array(), $return = null, $interceptor = '' )
    {
		global $debug;
        $f = @fopen($this->_logfile, 'a');
        if ($f) {
            fwrite($f, sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $verb));
            fclose($f);
        }
            
        $this->_wire = '';

        if(!isset($params['Item']['ProductListingDetails']['UPC'])){
            $params['Item']['ProductListingDetails']['UPC'] = $params['Item']['ProductListingDetails']['EAN'];
        }
        if (!isset($params['DetailLevel'])) {
//            $params['DetailLevel'] = 0;
        }
        if (!isset($params['SiteId'])) {
//            $params['SiteId'] = 0;
        }
        $params['ErrorLanguage'] = 'en_GB';

        foreach($params['ItemSpecifics']['NameValueList'] as $key => $spc){
            if($params['ItemSpecifics']['NameValueList'][$key]['Name'] == 'Material'){
                $params['ItemSpecifics']['NameValueList'][$key]['Value'] = implode(', ', $params['ItemSpecifics']['NameValueList'][$key]['Value']);
            }
        }

        echo '=== Test ===';
        print_r($params);

        $body    = $this->_buildRequestBody($verb, $params, $interceptor);
        //print("<textarea style='width:500;height:200;'>".$body."</textarea>");
        //exit;
		if (!isset($params['DetailLevel'])) $params['DetailLevel']='ReturnAll';
        $headers = array(
                            'X-EBAY-API-SESSION-CERTIFICATE' => sprintf( '%s;%s;%s', $this->devId, $this->appId, $this->certId ),   // Required. Used to authenticate the function call. Use this format, where DevId is the same as the value of the X-EBAY-API-DEV-NAME header, AppId is the same as the value of the X-EBAY-API-APP-NAME header, and CertId  is the same as the value of the X-EBAY-API-CERT-NAME header: DevId;AppId;CertId
                            'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,                                               // Required. Regulates versioning of the XML interface for the API.
                            'X-EBAY-API-DEV-NAME'            => $this->devId,                                                       // Required. Developer ID, as registered with the Developer's Program. This value should match the first value (DevId) in the X-EBAY-API-SESSION-CERTIFICATE header. Used to authenticate the function call.
                            'X-EBAY-API-APP-NAME'            => $this->appId,                                                       // Required. Application ID, as registered with the Developer's Program. This value should match the second value (AppId) in the X-EBAY-API-SESSION-CERTIFICATE header. Used to authenticate the function call.
                            'X-EBAY-API-CERT-NAME'           => $this->certId,                                                      // Required. Certificate ID, as registered with the Developer's Program. This value should match the third value (CertId) in the X-EBAY-API-SESSION-CERTIFICATE header. Used to authenticate the function call.
                            'X-EBAY-API-CALL-NAME'           => $verb,                                                              // Required. Name of the function being called, for example: 'GetItem' (without the quotation marks). This must match the information passed in the Verb input argument for each function.
                            'X-EBAY-API-SITEID'              => $this->siteId/*$params['Site']*/,                                                  // Required. eBay site an item is listed on or that a user is registered on, depending on the purpose of the function call. This must match the information passed in the SiteId input argument for all functions.
                            'X-EBAY-API-DETAIL-LEVEL'        => $params['DetailLevel'],                                             // Required. Controls amount or level of data returned by the function call. May be zero if the function does not support varying detail levels. This must match the information passed in the DetailLevel input argument for each function.
                            'Content-Type'                   => 'text/xml',                                                         // Required. Specifies the kind of data being transmitted. The value must be 'text/xml'. Sending any other value (e.g., 'application/x-www-form-urlencoded') may cause the call to fail.
                            'Content-Length'                 => strlen( $body )                                                     // Recommended. Specifies the size of the data (i.e., the length of the XML string) you are sending. This is used by eBay to determine how much data to read from the stream.
                        );
//		echo "Headers:<br>"; print_r($headers); echo "<br><br>";
        $file  = dirname( __FILE__ ).'/Ebay/Transport/'.$this->_driver.'.php';
        $class = 'Services_Ebay_Transport_'.$this->_driver;

        include_once $file;
        $tp =new $class;
        
        if ($this->_debug > 0) {
            $this->_wire .= $body."\n\n";
        }
/*		try {
			$this->_us->unserialize($body);
		} catch (Exception $e) {
		 	echo 'exception: ',  $e->getMessage(), "\n";
            return $e->getMessage();
		}*/
        $response = $tp->sendRequest($this->_apiUrl, $body, $headers);
        if (PEAR::isError($response)) {
			echo '!ERROR!';
			print_r($response);
			die('!ERROR!');
            return $response;
        }
		file_put_contents ( 'lastxml', $response);
		try {
			$this->_us->unserialize($response);
		} catch (Exception $e) {
			$r = simplexml_load_file('lastxml');
//		 	echo 'exception: ',  $e->getMessage(), "\n";
//            return $e->getMessage();
		}

        if (PEAR::isError($r)) {
			echo 'cant unserialize the result!<br>';
//            print_r($r);
			echo '----------------------------------<br>';
			$p = xml_parser_create();
			xml_parse_into_struct($p, $response, $vals, $index);
			xml_parser_free($p);
			echo "Index array\n";
			print_r($index);
			echo "\nVals array\n";
			print_r($vals);
        }
		
        $result = $this->_us->getUnserializedData();
//		if ($return == 'GetItemTransactionsResult') {
//			print_r($result);
//		}	

        $this->_2iso($result);
//		echo '<br>Result <br>';
		global $db, $dbr;
		$r = $db->query("insert into prologis_log.api_call_log set datetime=now(), verb='$verb'
			, devId='{$this->devId}'
			, appId='{$this->appId}'
			, certId='{$this->certId}'
			, siteId='{$this->siteId}'
			, REQUEST_TIME=".$_SERVER['REQUEST_TIME']."
			, REQUEST_URI='".$_SERVER['REQUEST_URI']."'
			, SERVER_ADDR='".$_SERVER['SERVER_ADDR']."'
			, HTTP_REFERER='".$_SERVER['HTTP_REFERER']."'
			, result_Ack='".$result['Ack']."'
			, result_Errors='".mysql_real_escape_string(print_r($result['Errors'],true))."'
			, username=(select username from seller_information where aatoken='{$this->aaToken}')
			");
    	if (PEAR::isError($r)) {
			print_r($r);
		}
        if (/*isset($result['Errors']) || */$result['Ack'] == 'Failure') {
            if (isset($result['Errors'][0])) {
                $error = array();
                $error['LongMessage'] = array_reduce($result['Errors'], create_function('$a,$b', 'return $a."\n".$b["LongMessage"];'));
                $error['Code'] = $result['Errors'][0]['ErrorCode'];
            } else {
                $error['LongMessage'] = $result['Errors']['LongMessage'];
                $error['Code'] = $result['Errors']['ErrorCode'];
//                $error = $result['Errors']; print_r($result['Errors']);
            }
            return PEAR::raiseError( $error['LongMessage'], $error['Code'] );
        }

        if ($return === null) {
            return $result;
        }
        
/*		echo '<br>3';
		print_r($result);*/
        if (!isset($result[$return])) {
            return PEAR::raiseError( 'Expected return value has not been found', SERVICES_EBAY_ERROR_NO_RETURN_VALUE );
        }
		global $db, $dbr;
        return $result[$return];
    }

    function sendSimpleRequest( $verb, $params = array(), $return = null, $interceptor = '' )
    {
        $this->_wire = '';

        #$this->_2utf($params);
        $opt = array(
                         'rootName'           => $verb.'Request'
					);	 
        $this->_ser->serialize($params, $opt);
        $body    = $this->_ser->getSerializedData();

        $this->_apiUrl = 'https://storage.ebay.com/FileTransferService';

		if (!isset($params['DetailLevel'])) $params['DetailLevel']='ReturnAll';
        $headers = array(
                            'X-EBAY-SOA-SECURITY-TOKEN'      => $this->aaToken,                                                      // Required. Certificate ID, as registered with the Developer's Program. This value should match the third value (CertId) in the X-EBAY-API-SESSION-CERTIFICATE header. Used to authenticate the function call.
                            'X-EBAY-SOA-OPERATION-NAME'      => $verb,                                                              // Required. Name of the function being called, for example: 'GetItem' (without the quotation marks). This must match the information passed in the Verb input argument for each function.
                            'X-EBAY-SOA-SERVICE-NAME'        => "FileTransferService",                                                  // Required. eBay site an item is listed on or that a user is registered on, depending on the purpose of the function call. This must match the information passed in the SiteId input argument for all functions.
                            'Content-Type'                   => 'text/xml',                                                         // Required. Specifies the kind of data being transmitted. The value must be 'text/xml'. Sending any other value (e.g., 'application/x-www-form-urlencoded') may cause the call to fail.
                            'Content-Length'                 => strlen( $body )                                                     // Recommended. Specifies the size of the data (i.e., the length of the XML string) you are sending. This is used by eBay to determine how much data to read from the stream.
                        );
//		echo "Headers:<br>"; print_r($headers); echo "<br><br>";
        $file  = dirname( __FILE__ ).'/Ebay/Transport/'.$this->_driver.'.php';
        $class = 'Services_Ebay_Transport_'.$this->_driver;

        include_once $file;
        $tp =new $class;
        
		$this->_us->unserialize($body);
//		echo 'Body <br>'; 
//		print_r($this->_us->getUnserializedData());
//		echo '<br>Body 2<br>'; 
//		print_r($body);
//		echo '<br><br>'; 
        $response = $tp->sendRequest($this->_apiUrl, $body, $headers);
        if (PEAR::isError($response)) {
			print_r($response);
			die();
            return $response;
        }
//		file_put_contents ( 'xml'. $this->siteId.$verb, $response);
		return $response;
		$this->_us->unserialize($response);
//		echo 'Response<br>'; 
//		print_r($this->_us->getUnserializedData());
		echo '<br>Response 2<br>'; 
		print_r($response);

        
        $r = $this->_us->unserialize( $response );
        if (PEAR::isError($r)) {
			echo 'cant unserialize the result!<br>';
//            print_r($r);
			echo '----------------------------------<br>';
			$p = xml_parser_create();
			xml_parse_into_struct($p, $response, $vals, $index);
			xml_parser_free($p);
			echo "Index array\n";
			print_r($index);
			echo "\nVals array\n";
			print_r($vals);
        }
		
        $result = $this->_us->getUnserializedData();

        $this->_2iso($result);

        if (/*isset($result['Errors']) || */$result['Ack'] == 'Failure') {
            if (isset($result['Errors'][0])) {
                $error = array();
                $error['LongMessage'] = array_reduce($result['Errors'], create_function('$a,$b', 'return $a."\n".$b["LongMessage"];'));
                $error['Code'] = $result['Errors'][0]['ErrorCode'];
            } else {
                $error['LongMessage'] = $result['Errors']['LongMessage'];
                $error['Code'] = $result['Errors']['ErrorCode'];
//                $error = $result['Errors']; print_r($result['Errors']);
            }
            return PEAR::raiseError( $error['LongMessage'], $error['Code'] );
        }

        if ($return === null) {
            return $result;
        }
        
        if (!isset($result[$return])) {
            return PEAR::raiseError( 'Expected return value has not been found', SERVICES_EBAY_ERROR_NO_RETURN_VALUE );
        }
        return $result[$return];
    }


    function _2utf(&$array) /* {{{ */
    {
        static $table = array (
              '€' => 'Â€',
              '' => 'Â',
              '‚' => 'Â‚',
              'ƒ' => 'Âƒ',
              '„' => 'Â„',
              '…' => 'Â…',
              '†' => 'Â†',
              '‡' => 'Â‡',
              'ˆ' => 'Âˆ',
              '‰' => 'Â‰',
              'Š' => 'ÂŠ',
              '‹' => 'Â‹',
              'Œ' => 'ÂŒ',
              '' => 'Â',
              'Ž' => 'ÂŽ',
              '' => 'Â',
              '' => 'Â',
              '‘' => 'Â‘',
              '’' => 'Â’',
              '“' => 'Â“',
              '”' => 'Â”',
              '•' => 'Â•',
              '–' => 'Â–',
              '—' => 'Â—',
              '˜' => 'Â˜',
              '™' => 'Â™',
              'š' => 'Âš',
              '›' => 'Â›',
              'œ' => 'Âœ',
              '' => 'Â',
              'ž' => 'Âž',
              'Ÿ' => 'ÂŸ',
              ' ' => 'Â ',
              '¡' => 'Â¡',
              '¢' => 'Â¢',
              '£' => 'Â£',
              '¤' => 'Â¤',
              '¥' => 'Â¥',
              '¦' => 'Â¦',
              '§' => 'Â§',
              '¨' => 'Â¨',
              '©' => 'Â©',
              'ª' => 'Âª',
              '«' => 'Â«',
              '¬' => 'Â¬',
              '­' => 'Â­',
              '®' => 'Â®',
              '¯' => 'Â¯',
              '°' => 'Â°',
              '±' => 'Â±',
              '²' => 'Â²',
              '³' => 'Â³',
              '´' => 'Â´',
              'µ' => 'Âµ',
              '¶' => 'Â¶',
              '·' => 'Â·',
              '¸' => 'Â¸',
              '¹' => 'Â¹',
              'º' => 'Âº',
              '»' => 'Â»',
              '¼' => 'Â¼',
              '½' => 'Â½',
              '¾' => 'Â¾',
              '¿' => 'Â¿',
              'À' => 'Ã€',
              'Á' => 'Ã',
              'Â' => 'Ã‚',
              'Ã' => 'Ãƒ',
              'Ä' => 'Ã„',
              'Å' => 'Ã…',
              'Æ' => 'Ã†',
              'Ç' => 'Ã‡',
              'È' => 'Ãˆ',
              'É' => 'Ã‰',
              'Ê' => 'ÃŠ',
              'Ë' => 'Ã‹',
              'Ì' => 'ÃŒ',
              'Í' => 'Ã',
              'Î' => 'ÃŽ',
              'Ï' => 'Ã',
              'Ð' => 'Ã',
              'Ñ' => 'Ã‘',
              'Ò' => 'Ã’',
              'Ó' => 'Ã“',
              'Ô' => 'Ã”',
              'Õ' => 'Ã•',
              'Ö' => 'Ã–',
              '×' => 'Ã—',
              'Ø' => 'Ã˜',
              'Ù' => 'Ã™',
              'Ú' => 'Ãš',
              'Û' => 'Ã›',
              'Ü' => 'Ãœ',
              'Ý' => 'Ã',
              'Þ' => 'Ãž',
              'ß' => 'ÃŸ',
              'à' => 'Ã ',
              'á' => 'Ã¡',
              'â' => 'Ã¢',
              'ã' => 'Ã£',
              'ä' => 'Ã¤',
              'å' => 'Ã¥',
              'æ' => 'Ã¦',
              'ç' => 'Ã§',
              'è' => 'Ã¨',
              'é' => 'Ã©',
              'ê' => 'Ãª',
              'ë' => 'Ã«',
              'ì' => 'Ã¬',
              'í' => 'Ã­',
              'î' => 'Ã®',
              'ï' => 'Ã¯',
              'ð' => 'Ã°',
              'ñ' => 'Ã±',
              'ò' => 'Ã²',
              'ó' => 'Ã³',
              'ô' => 'Ã´',
              'õ' => 'Ãµ',
              'ö' => 'Ã¶',
              '÷' => 'Ã·',
              'ø' => 'Ã¸',
              'ù' => 'Ã¹',
              'ú' => 'Ãº',
              'û' => 'Ã»',
              'ü' => 'Ã¼',
              'ý' => 'Ã½',
              'þ' => 'Ã¾',
              'ÿ' => 'Ã¿',
        );

        if (is_string($array)) {
            $array = strtr($array, $table);
        } elseif (is_array($array)) {
            foreach ($array as $k => $v) {
                $this->_2utf($array[$k]);
            }
        }
    } /* }}} */

    function _2iso(&$array) /* {{{ */
    {
        static $table = array (
              'Â€' => '€',
              'Â' => '',
              'Â‚' => '‚',
              'Âƒ' => 'ƒ',
              'Â„' => '„',
              'Â…' => '…',
              'Â†' => '†',
              'Â‡' => '‡',
              'Âˆ' => 'ˆ',
              'Â‰' => '‰',
              'ÂŠ' => 'Š',
              'Â‹' => '‹',
              'ÂŒ' => 'Œ',
              'Â' => '',
              'ÂŽ' => 'Ž',
              'Â' => '',
              'Â' => '',
              'Â‘' => '‘',
              'Â’' => '’',
              'Â“' => '“',
              'Â”' => '”',
              'Â•' => '•',
              'Â–' => '–',
              'Â—' => '—',
              'Â˜' => '˜',
              'Â™' => '™',
              'Âš' => 'š',
              'Â›' => '›',
              'Âœ' => 'œ',
              'Â' => '',
              'Âž' => 'ž',
              'ÂŸ' => 'Ÿ',
              'Â ' => ' ',
              'Â¡' => '¡',
              'Â¢' => '¢',
              'Â£' => '£',
              'Â¤' => '¤',
              'Â¥' => '¥',
              'Â¦' => '¦',
              'Â§' => '§',
              'Â¨' => '¨',
              'Â©' => '©',
              'Âª' => 'ª',
              'Â«' => '«',
              'Â¬' => '¬',
              'Â­' => '­',
              'Â®' => '®',
              'Â¯' => '¯',
              'Â°' => '°',
              'Â±' => '±',
              'Â²' => '²',
              'Â³' => '³',
              'Â´' => '´',
              'Âµ' => 'µ',
              'Â¶' => '¶',
              'Â·' => '·',
              'Â¸' => '¸',
              'Â¹' => '¹',
              'Âº' => 'º',
              'Â»' => '»',
              'Â¼' => '¼',
              'Â½' => '½',
              'Â¾' => '¾',
              'Â¿' => '¿',
              'Ã€' => 'À',
              'Ã' => 'Á',
              'Ã‚' => 'Â',
              'Ãƒ' => 'Ã',
              'Ã„' => 'Ä',
              'Ã…' => 'Å',
              'Ã†' => 'Æ',
              'Ã‡' => 'Ç',
              'Ãˆ' => 'È',
              'Ã‰' => 'É',
              'ÃŠ' => 'Ê',
              'Ã‹' => 'Ë',
              'ÃŒ' => 'Ì',
              'Ã' => 'Í',
              'ÃŽ' => 'Î',
              'Ã' => 'Ï',
              'Ã' => 'Ð',
              'Ã‘' => 'Ñ',
              'Ã’' => 'Ò',
              'Ã“' => 'Ó',
              'Ã”' => 'Ô',
              'Ã•' => 'Õ',
              'Ã–' => 'Ö',
              'Ã—' => '×',
              'Ã˜' => 'Ø',
              'Ã™' => 'Ù',
              'Ãš' => 'Ú',
              'Ã›' => 'Û',
              'Ãœ' => 'Ü',
              'Ã' => 'Ý',
              'Ãž' => 'Þ',
              'ÃŸ' => 'ß',
              'Ã ' => 'à',
              'Ã¡' => 'á',
              'Ã¢' => 'â',
              'Ã£' => 'ã',
              'Ã¤' => 'ä',
              'Ã¥' => 'å',
              'Ã¦' => 'æ',
              'Ã§' => 'ç',
              'Ã¨' => 'è',
              'Ã©' => 'é',
              'Ãª' => 'ê',
              'Ã«' => 'ë',
              'Ã¬' => 'ì',
              'Ã­' => 'í',
              'Ã®' => 'î',
              'Ã¯' => 'ï',
              'Ã°' => 'ð',
              'Ã±' => 'ñ',
              'Ã²' => 'ò',
              'Ã³' => 'ó',
              'Ã´' => 'ô',
              'Ãµ' => 'õ',
              'Ã¶' => 'ö',
              'Ã·' => '÷',
              'Ã¸' => 'ø',
              'Ã¹' => 'ù',
              'Ãº' => 'ú',
              'Ã»' => 'û',
              'Ã¼' => 'ü',
              'Ã½' => 'ý',
              'Ã¾' => 'þ',
              'Ã¿' => 'ÿ',
        );

        if (is_string($array)) {
            $array = strtr($array, $table);
        } elseif (is_array($array)) {
            foreach ($array as $k => $v) {
                $this->_2iso($array[$k]);
            }
        }
    } /* }}} */


}
?>

<?php
/**
 * Billmate
 *
 * Billmate API - PHP Class
 *
 * LICENSE: This source file is part of Billmate API, that is fully owned by Billmate AB
 * This is not open source. For licensing queries, please contact Billmate AB at info@billmate.se.
 *
 * @category Billmate
 * @package Billmate
 * @author Yuksel Findik <yuksel@billmate.se>
 * @copyright 2013-2014 Billmate AB
 * @license Proprietary and fully owned by Billmate AB
 * @version 2.1.6
 * @link http://www.billmate.se
 *
 * History:
 * 2.0 20140625 Yuksel Findik: Second Version
 * 2.0.8 20141125 Yuksel Findik: Url is updated. Some variables are updated
 * 2.0.9 20141204 Yuksel Findik: Returns array and verifies the data is safe
 * 2.1.0 20141215 Yuksel Findik: Unnecessary variables are removed
 * 2.1.1 20141218 Yuksel Findik: If response can not be json_decoded, will return actual response
 * 2.1.2 20150112 Yuksel Findik: verify_hash function is added.
 * 2.1.4 20150115 Yuksel Findik: verify_hash is improved. The serverdata is added instead of useragent
 * 2.1.5 20150122 Yuksel Findik: Will make a utf8_decode before it returns the result
 * 2.1.6 20150129 Yuksel Findik: Language is added as an optional paramater in credentials, version_compare is added for Curl setup
 * 2.1.7 20150922 Yuksel Findik: PHP Notice for CURLOPT_SSL_VERIFYHOST is fixed
 * 2.1.8 20151103 Yuksel Findik: CURLOPT_CONNECTTIMEOUT is added
 * 2.1.9 20151103 Yuksel Findik: CURLOPT_CAINFO is added, Check for Zero length data.
 */

class BillmateConnection_Billmate
{
    const BILLMATE_LANGUAGE = 'sv';

    const BILLMATE_SERVER = '2.1.7';

    const BILLMATE_CLIENT = 'MAGENTO:3.1.0';

    /**
     * @var string
     */
	protected $id = "";

    /**
     * @var string
     */
    protected $secretKey = "";

    /**
     * @var string
     */
    protected $URL = "api.billmate.se";

    /**
     * @var string
     */
    protected $MODE = "CURL";

    /**
     * @var bool
     */
    protected $ssl = true;
    /**
     * @var bool
     */
    protected $test = false;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var array|bool
     */
    protected $referer = false;

	public function __construct(
	    $id,
        $key,
        $ssl=true,
        $test=false,
        $debug=false,
        $referer=array()
    ) {
		$this->id = $id;
		$this->secretKey = $key;
		$this->ssl = $ssl;
		$this->debug = $debug;
		$this->test = $test;
		$this->referer = $referer;
	}

	public function __call($name,$args)
    {
	 	if(count($args)==0) return; //Function call should be skipped
	 	return $this->call($name,$args[0]);
	}

	public function call($function,$params)
    {
		$values = array(
			"credentials" => array(
				"id" => $this->id,
				"hash" => $this->hash(json_encode($params)),
				"version" => self::BILLMATE_SERVER,
				"client" => self::BILLMATE_CLIENT,
				"serverdata" => array_merge($_SERVER,$this->referer),
				"time" => microtime(true),
				"test" => $this->test?"1":"0",
				"language" => self::BILLMATE_LANGUAGE
			),
			"data"=> $params,
			"function"=>$function,
		);
		$this->out("CALLED FUNCTION",$function);
		$this->out("PARAMETERS TO BE SENT",$values);

		switch ($this->MODE) {
			case "CURL":
				$response = $this->curl(json_encode($values));
				break;
		}
		return $this->verify_hash($response);
	}

	public function verify_hash($response)
    {
		$response_array = is_array($response)?$response:json_decode($response,true);
		//If it is not decodable, the actual response will be returnt.
		if(!$response_array && !is_array($response))
			return $response;
		if (is_array($response) && !is_array($response['credentials']) && !is_array($response['data'])) {
			$response_array['credentials'] = json_decode($response['credentials'], true);
			$response_array['data'] = json_decode($response['data'],true);
		}
		//If it is a valid response without any errors, it will be verified with the hash.
		if(isset($response_array["credentials"])){
			$hash = $this->hash(json_encode($response_array["data"]));
			//If hash matches, the data will be returnt as array.
			if($response_array["credentials"]["hash"]==$hash)
				return $response_array["data"];
			else return array("code"=>9511,"message"=>"Verification error","hash"=>$hash,"hash_received"=>$response_array["credentials"]["hash"]);
		}
		return array_map("utf8_decode",$response_array);
	}

	public function curl($parameters)
    {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "http".($this->ssl?"s":"")."://".$this->URL);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,10);

		// Start Mod Jesper.  Added cacert.pem to make sure server has the latest ssl certs.
		$path = __DIR__.'/cacert.pem';
		curl_setopt($ch,CURLOPT_CAINFO,$path);
		// End mod Jesper
		$vh = $this->ssl?((function_exists("phpversion") && function_exists("version_compare") && version_compare(phpversion(),'5.4','>=')) ? 2 : true):false;
		if($this->ssl){
			if(function_exists("phpversion") && function_exists("version_compare")){
				$cv = curl_version();
				if(version_compare(phpversion(),'5.4','>=') || version_compare($cv["version"],'7.28.1','>='))
					$vh = 2;
				else $vh = true;
			} else {
                $vh = true;
            }
		} else {
            $vh = false;
        }

		@curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $vh);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		    'Content-Type: application/json',
		    'Content-Length: ' . strlen($parameters))
		);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
		$data = curl_exec($ch);

		if (curl_errno($ch)) {
	        $curlerror = curl_error($ch);
	        return json_encode(array("code"=>9510,"message"=>htmlentities($curlerror)));
		} else {
            curl_close($ch);
        }

		if (strlen($data) == 0) {
			return json_encode(array("code" => 9510,"message" => htmlentities("Communication Error")));
		}
	    return $data;
	}

	public function hash($args)
    {
		$this->out("TO BE HASHED DATA",$args);
    	return hash_hmac('sha512',$args,$this->secretKey);
    }

    public function out($name,$out)
    {
    	if (!$this->debug) {
            return;
        }

    	print "$name: '";
    	if(is_array($out) or  is_object($out)) {
            print_r($out);
        } else {
            print $out;
        }

    	print "'\n";
    }
}
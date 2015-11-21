<?php

require_once("RiscResponse.php");

class Risc
{
	private static $token = "";
	private static $secret = "";
	private static $http_basic_auth = true;
	private static $get_params_in_url = false;
	private static $api_url;
	private static $initialized = false;

	/**
	 * Constructor.
	 *
	 * @param string $api_key Api Public Key
	 * @param string $api_secret Api Secret Key
	 * @param string $http_basic_auth Optional: Set true to switch from digest to HTTP basic authorization (not recommended)
	 * @param string $api_url Optional: api url
	 */
	public static function Initialize($api_key, $api_secret, $http_basic_auth = true, $api_url = "https://risc.lastwall.com/")
	{
		self::$token = $api_key;
		self::$secret = $api_secret;
		self::$http_basic_auth = $http_basic_auth;
		self::$api_url = $api_url;
		self::$initialized = true;

		function endsWith($haystack, $needle) {
			return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
		}
		if (!endsWith($api_url, "/"))
			self::$api_url = $api_url . "/";
	}

	/**
	 * Verify your API key.
	 *
	 * @return RiscResponse the server response
	 */
	public static function Verify()
	{
		return self::CallAPI("GET", self::$api_url . "verify");
	}

	/**
	 * Get the base URL for the RISC javascript. This url must be postfixed with a username.
	 *
	 * @return string The base script URL.
	 */
	public static function GetScriptUrl()
	{
		return self::$api_url . 'risc/script/' . self::$token . '/';
	}

	/**
	 * Decrypts an encrypted RISC snapshot and returns the decrypted result.
	 *
	 * @param string $enc_snapshot    The encrypted snapshot object, which should be a JSON string returned from the Lastwall RISC server
	 *
	 * @return object The decrypted snapshot result. The return object should contain the following values:
	 * string   snapshot_id
	 * string   browser_id
	 * date     date
	 * number   score
	 * string   status
	 * boolean  passed
	 * boolean  risky
	 * boolean  failed
	 */
	public static function DecryptSnapshot($enc_snapshot)
	{
		$obj = json_decode($enc_snapshot);
		$password = substr(self::$secret . self::$secret, $obj->ix, 32);
		$key = pack('H*', $password);
		$iv = pack('H*', $obj->iv);
		$data = base64_decode($obj->data);
		$decrypted = openssl_decrypt($data, "aes-128-cbc", $key, OPENSSL_RAW_DATA, $iv);
		$ret = json_decode($decrypted);
		$ret->passed = ($ret->status == 'passed');
		$ret->risky = ($ret->status == 'risky');
		$ret->failed = ($ret->status == 'failed');
		return $ret;
	}

	/**
	 * Call the Validate API to ensure the integrity of the encrypted RISC result.
	 *
	 * @param object $snapshot    The snapshot object, as decrypted by the DecryptSnapshot() function
	 *
	 * @return RiscResponse the server response
	 */
	public static function ValidateSnapshot($snapshot)
	{
		$obj = (object) array("snapshot_id" => $snapshot->snapshot_id,
			"browser_id" => $snapshot->browser_id,
			"date" => $snapshot->date,
			"score" => $snapshot->score,
			"status" => $snapshot->status);
		return self::CallAPI("GET", self::$api_url . "api/validate", $obj);
	}


	private static function CallAPI($method, $url, $data = false)
	{
		$curl = curl_init();
		$full_url = $url;

		// Set the request parameters, either in the URL or in the body
		if (self::$get_params_in_url && $method == "GET")
		{
			if ($data)
				$full_url = sprintf("%s?%s", $url, http_build_query($data));
		}
		else
		{
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
			if ($data)
				curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		}

		// Determine authorization type
		if (self::$http_basic_auth)
		{
			// Set HTTP basic authorization headers
			$auth = self::$token . ":" . self::$secret;

			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($curl, CURLOPT_USERPWD, $auth);
		}
		else
		{
			// Set digest authentication headers
			date_default_timezone_set("UTC");
			$timestamp = time();
			$request_id = self::CreateGuid();
			$hash_str = $full_url . $request_id . $timestamp;
			$signature = base64_encode(hash_hmac("sha1", $hash_str, self::$secret, true));

			$headers = array();
			$headers[] = "Content-Type: application/x-www-form-urlencoded";
			$headers[] = "X-Lastwall-Token: " . self::$token;
			$headers[] = "X-Lastwall-Timestamp: " . $timestamp;
			$headers[] = "X-Lastwall-Request-Id: " . $request_id;
			$headers[] = "X-Lastwall-Signature: " . $signature;

			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		}

		curl_setopt($curl, CURLOPT_URL, $full_url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$response = curl_exec($curl);
		$http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);
		return new RiscResponse($http_status_code, $response);
	}


	private static function CreateGuid()
	{
		$data = openssl_random_pseudo_bytes(16);

		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

		return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($data), 4));
	}
}

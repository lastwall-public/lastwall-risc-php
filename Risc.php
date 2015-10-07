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
	public static function Initialize($api_key, $api_secret, $http_basic_auth = true, $api_url = "https://risc.lastwall.com/api")
	{
		self::$token = $api_key;
		self::$secret = $api_secret;
		self::$http_basic_auth = $http_basic_auth;
		self::$api_url = $api_url;
		self::$initialized = true;
	}

	/**
	 * Verify your API key.
	 *
	 * @return RiscResponse the server response
	 */
	public static function Verify()
	{
		return self::CallAPI("GET", self::$api_url . "/verify");
	}

	/**
	 * Create a new user account.
	 *
	 * @param string $user_id  The user account ID
	 * @param string $email    The user's email address
	 * @param string $phone    The user's phone number
	 * @param string $name     Optional: the user's name for display
	 *
	 * @return RiscResponse the server response
	 */
	public static function CreateUser($user_id, $email, $phone, $name = null)
	{
		$obj = (object) array("user_id" => $user_id, "email" => $email, "phone" => $phone);
		if (isset($name))
			$obj->name = $name;
		return self::CallAPI("POST", self::$api_url . "/users", $obj);
	}

	/**
	 * Get a user's account details.
	 *
	 * @param string $user_id  The user account ID
	 *
	 * @return RiscResponse the server response
	 */
	public static function GetUser($user_id)
	{
		$obj = (object) array("user_id" => $user_id);
		return self::CallAPI("GET", self::$api_url . "/users", $obj);
	}

	/**
	 * Modify an existing user account.
	 *
	 * @param string $user_id  The user account ID
	 * @param string $email    Optional: the user's new email address
	 * @param string $phone    Optional: the user's new phone number
	 * @param string $name     Optional: the user's new name for display
	 *
	 * @return RiscResponse the server response
	 */
	public static function ModifyUser($user_id, $email = null, $phone = null, $name = null)
	{
		$obj = (object) array("user_id" => $user_id);
		if (isset($email))
			$obj->email = $email;
		if (isset($phone))
			$obj->phone = $phone;
		if (isset($name))
			$obj->name = $name;
		return self::CallAPI("PUT", self::$api_url . "/users", $obj);
	}

	/**
	 * Delete an existing user account.
	 *
	 * @param string $user_id  The user account ID
	 *
	 * @return RiscResponse the server response
	 */
	public static function DeleteUser($user_id)
	{
		$obj = (object) array("user_id" => $user_id);
		return self::CallAPI("DELETE", self::$api_url . "/users", $obj);
	}

	/**
	 * Create a new session for a given user.
	 *
	 * @param string $user_id    The user account ID
	 *
	 * @return RiscResponse the server response
	 */
	public static function CreateSession($user_id)
	{
		$obj = (object) array("user_id" => $user_id);
		return self::CallAPI("POST", self::$api_url . "/sessions", $obj);
	}

	/**
	 * Check the status of an existing session.
	 *
	 * @param string $session_id The session ID to check
	 *
	 * @return RiscResponse the server response
	 */
	public static function GetSession($session_id)
	{
		$obj = (object) array("session_id" => $session_id);
		return self::CallAPI("GET", self::$api_url . "/sessions", $obj);
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

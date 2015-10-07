<?php

class RiscResponse
{
	protected $response = null;
	protected $body = null;
	protected $code = 0;
	protected $error = "";

	public function __construct($status_code, $response)
	{
		if (!$response)
		{
			$this->error = "No response from server";
			return;
		}

		$this->response = $response;
		$this->code = $status_code;
		$this->body = json_decode($response);

		// Handle errors
		if (isset($this->body->status))
		{
			if ($this->body->status == "Error")
			{
				$this->error = $this->body->error;
			}
		}
		else if (isset($this->body->error))
		{
			$this->error = $this->body->error;
		}
		else if ($status_code < 200 || $status_code >= 400)
		{
			$this->error = $this->body;
		}
	}

	/**
	 * Check whether the API request was successful.
	 *
	 * @return boolean False if any error occurred.
	 */
	public function OK()
	{
		return $this->code >= 200 && $this->code < 400;
	}

	/**
	 * Get the HTTP status code of the request.
	 *
	 * @return integer The HTTP status code, or 0 if the server didn't respond.
	 */
	public function Code()
	{
		return $this->code;
	}

	/**
	 * Get the status of the Lastwall Risc request.
	 *
	 * @return string 'Error' if there was an error, otherwise returns the status of the request (typically 'OK', but can also be a session status).
	 */
	public function Status()
	{
		if (isset($this->body->status))
			return $this->body->status;
		else if ($this->OK())
			return "OK";
		return "Error";
	}

	/**
	 * Get the specific error message.
	 *
	 * @return string The specific error message, or an empty string if there was no error.
	 */
	public function Error()
	{
		return $this->error;
	}

	/**
	 * Get the message response as an object, ie. the JSON-decoded message body.
	 *
	 * @return object The message body, converted from JSON to an object.
	 */
	public function Body()
	{
		return $this->body;
	}

	/**
	 * Get the raw response text of the HTTP request.
	 *
	 * @return string The unparsed response body text.
	 */
	public function RawResponse()
	{
		return $this->response;
	}

	/**
	 * Get a specific value from the response object.
	 *
	 * @param string $key The name of the key
	 *
	 * @return object The value of the response for that key, or null if the key is not defined.
	 */
	public function Get($key)
	{
		if (isset($this->body->$key))
			return $this->body->$key;
		return null;
	}
}

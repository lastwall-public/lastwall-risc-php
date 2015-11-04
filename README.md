# ![Lastwall Logo](logo.png) Lastwall RISC PHP Module

Lastwall risk-based authentication module for PHP

## Overview

This document provides pseudo-code to describe our PHP integration module.

Before reading this, please read our general [integratrion documentation](Integration.md).

For bare-bones API documentation, please [click here](API.md).


## Initialization

First, download Risc.php and RiscResponse.php from our github repository `lastwall-risc-php`. Initialize like this:

```
$token = "LWK150D380544E303C57E57036F628DA2195FDFEE3DE404F4AA4D7D5397D5D35010";
$secret = "2B60355A24C907761DA3B09C7B8794C7F9B8BE1D70D2488C36CAF85E37DB2C";
require_once("Risc.php");
Risc::Initialize($token, $secret);
```


## Verify API Key

```
$result = Risc::verify(onOk, onError);
if ($result->OK())
	echo "Verified!";
else
	echo "Error: " . $result->Error();
```


## Create Session

```
// Create a session for the current user given by $user_id
$result = Risc::CreateSession($user_id);
if ($result->OK())
{
	// TODO: store the RISC session ID and URL somewhere connected to your active user session
	$session_id = $result->Get("session_id");
	$session_url = $result->Get("session_url");
}
else
{
	// TODO: handle errors
	echo "Error: " . $result->Error();
}
```


## Check Session Results

```
// Get the session results for the current RISC session ID
$result = Risc::GetSession($session_id);
if ($result->OK())
{
	$user_id = result->Get("user_id");
	$score = result->Get("score");
	$status = result->Get("status");
    echo "Risc session ended for user " . $user_id . " with score " . $score . ", status: " . $status);
	if ($result->Get("authenticated"))
	{
		// TODO: user was authenticated. Proceed with login
	}
	else if ($result->Get("risky"))
	{
		// TODO: user was reported as somewhat risky. Handle appropriately
	}
	else
	{
		// TODO: user failed the RISC session. Auto-logout?
	}
}
else
{
	// TODO: handle errors
	echo "Error: " . $result->Error();
}
```

# ![Lastwall Logo](logo.png) Lastwall RISC PHP Module

Lastwall risk-based authentication module for PHP

## Overview

The Lastwall RISC platform allows your website to perform risk-based spot checks on a user's browsing session and verify his identity. You may perform RISC spot checks at any point during a user's browsing session. These spot checks will occur automatically and invisibly to your end users.

This document provides pseudo-code to describe our PHP integration module.

Before reading this document, please read our general [integratrion documentation](Integration.md).

For bare-bones API documentation, please [click here](API.md).


## Initialization

First, download Risc.php and RiscResponse.php from our github repository `lastwall-risc-php`. Initialize like this:

```
$token = "LWK150D380544E303C57E57036F628DA2195FDFEE3DE404F4AA4D7D5397D5D35010";   // replace with your API token
$secret = "2B60355A24C907761DA3B09C7B8794C7F9B8BE1D70D2488C36CAF85E37DB2C";       // replace with your API secret
require_once("Risc.php");
Risc::Initialize($token, $secret);
```


## Verify API Key

Not really necessary, but good for peace of mind.

```
$result = Risc::Verify();
if ($result->OK())
	echo "Verified!";
else
	echo "Error: " . $result->Error();
```


## Get URL for RISC Script

Our javascript requires both a public API token and a user ID in the URL. The specific format is `https://risc.lastwall.com/risc/script/API_TOKEN/USER_ID`. The username is typically available on the client side before creating a RISC snapshot. To construct the URL, you can use our convenience function within the client-side javascript like this:

```
var script_url = '<?php echo Risc::GetScriptUrl(); ?>' + encodeURIComponent(username);
// script_url looks like this: https://risc.lastwall.com/risc/script/API_TOKEN/USER_ID
loadRiscScript(script_url);
```

NOTE: when you append the username to the URL, don't forget to URI-encode it!


## Decrypt Snapshot

An encrypted snapshot is obtained by the client browser by running the asynchronous RISC javascript. This encrypted blob (which is just a string) must be passed to your server somehow (typically via hidden form submission). Only your server can decrypt it, using your API secret.

```
// $encr_snapshot is the Lastwall encrypted snapshot
$snapshot = Risc::DecryptSnapshot($encr_snapshot);
echo 'RISC session ended with score ' . $snapshot->score . ', status: ' . $snapshot->status;
// TODO: error handling
```


## Validate Snapshot (optional, recommended)

The `validate` API call will compare your decrypted RISC snapshot result against the one saved in the Lastwall database. They should be identical. If they aren't, the only explanation is that a hacker has decrypted the result client-side, modified it, then re-encrypted it before sending it to your server. This is only possible if he has access to your API secret, or the computing power of an array of super computers stretching from here to Saturn.

```
// Decrypt the snapshot first, then validate it by API call to Lastwall
$snapshot = Risc::DecryptSnapshot($encr_snapshot);
$result = Risc::ValidateSnapshot($snapshot);
if ($result->OK())
{
    // Snapshot is valid. Lets use the result.
    if ($snapshot->failed)
    {
        // User failed the RISC session. We can force a logout here, or do something fancier like track the user.
        echo 'Risc score: ' . $snapshot->score . '%. Logging user out...';
        // TODO: force logout
    }
    else if ($snapshot->risky)
    {
        // User passed the RISC session but not by much.
        echo 'Risc score: ' . $snapshot->score . '%. User validated.';
        // TODO: redirect user to main site
    }
    else if ($snapshot->passed)
    {
        // User passed the RISC session with no issues.
        echo 'Risc score: ' . $snapshot->score . '%. User validated.';
        // TODO: redirect user to main site
    }
    else
    {
        // NO-MAN's land. This code should never be reached - all RISC results are either risky, passed or failed.
    }

}
else
{
    // Snapshot is invalid. This is bad news - it means your API secret likely isn't a secret, and a hacker is logging in.
	echo "Error validating snapshot: " . $result->Error();
    // TODO: Panic. Call the admins. Then go to the RISC admin console and generate a new API key.
}
```


## Pre-authenticate User for a Specific Browser (optional)

The `preauth` API call can be used when a user has a high RISC score on a particular browser, but you are certain it is the correct user. This API call will effectively set the RISC score back to 0% the next time the user does a RISC snapshot from that specific browser. This can be used in a variety of scenarios, the most common being after you have performed a successful second-factor authentication for that user, and you want his next RISC snapshot to be successful.

You will need a valid user ID and browser ID to call this API function. The browser ID will be contained in the most recent RISC snapshot result.

```
$result = Risc::PreAuthenticateUser($snapshot->user_id, $snapshot->browser_id);
if ($result->OK())
    echo 'User is now pre-authed. His next login will yield a 0% RISC score.';
else
    echo 'Error pre-authing user: ' . $result->Error();
```


## Email-based Second Factor Authentication (optional)

The `save_email` API call can be used to force an email-based second factor authentication when a user has a high RISC score on a particular browser. The goal is to verify a user's identity by ensuring he has access to the specified email account. An email will be sent to the specified address with a one-time unlock link. If the user logs into his email and clicks the unlock link, his RISC score will be set back to 0% the next time he does a RISC snapshot from the specified browser. This API call is typically used after a risky or failed RISC snapshot, in order to allow the user a chance to recover access from that browser.

You will need a valid user ID and browser ID to call this API function. The browser ID will be contained in the most recent RISC snapshot result. You may optionally specify the email address to use. If you don't specify one, we will try to use the one stored in the RISC user account (if there is one).

```
$result = Risc::EmailAuthenticateUser($snapshot->user_id, $snapshot->browser_id, $user_email);
if ($result->OK())
    echo 'An authentication email has been sent. The user should be instructed to check his email to recover access.';
else
    echo 'Error email-authing user: ' . $result->Error();
```

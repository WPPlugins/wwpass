=== WWPass for WordPress ===
Contributors: v.korshunov
Tags: authentication, login, security, WWPass
Requires at least: 2.8.6
Tested up to: 3.9.1
Stable tag: trunk
License: Apache 2.0 license
License URI: http://www.apache.org/licenses/LICENSE-2.0


== Description ==

The WWPass Plugin for WordPress provides strong hardware authentication by using
a PassKey instead of legacy username and password combinations.

= Requirements and technical data to support WWPass Plugin for WordPress =

* Dependent on [cURL][]
* Requires SSH or any other secure connection to a server running WordPress

[cURL]: http://curl.haxx.se/


== Installation ==

1. Download the WWPass Wordpress Plugin from [WWPass Developers site][] under 
   the Downloads section.
2. There are two options to upload the plugin to a server:
    1. Unzip the WWPass Wordpress Plugin zip archive (.zip) file and place its 
       contents in the `/wp-content/plugins/` directory.
    2. Log in to your own Wordpress Administrator Dashboard and navigate to 
       the Plugins -> Add New page. Click the Upload tab at the top of the page 
       and click Browse... Select the downloaded WWPass Wordpress Plugin zip 
       archive (.zip) and click Open.
3. Activate the plugin using WordPress Dashboard/Plugins.
4. WWPass Plugin for WordPress requires a WWPass Service Provider ID. You can 
   either use your existing Service Provider ID or create a new one by visiting 
   [WWPass Developers site][]. 
5. Create a new directory on your server. It is recommended to create this 
   directory outside of your web server DocumentRoot. Upload your WWPass SPID 
   credentials - key (.key), certificate (.crt) and certificate 
   authority (wwpass_ca.crt) files - to the newly created directory.
6. If the configuration of your server does not allow to store files outside of 
   DocumentRoot, access to the uploaded files should be restricted.
   For example, if your web-server is running Apache you can create an .htaccess 
   file with the following contents in the directory with the key and 
   certificate files:
    
        deny from all
    
   In any other cases contact technical support of your web hosting provider or 
   a system administrator which supports your server.
7. Login to WordPress using an account with administrative privileges and 
   configure your WWPass plugin using Dashboard/Settings/WWPass. Enter paths to 
   the credential files into corresponding fields of the form and click "Check 
   and save settings".
   
The administrator configuration options are mapped below:

Path to WWPass SPID Private Key (.key)
   Service Provider ID (SPID) Private Key, issued by WWPass

Path to WWPass SPID Certificate (.crt)
   Service Provider ID (SPID) Certificate, issued by WWPass

Path to WWPass Certificate Authority (ca.crt)
   WWPass RootCA Certificate. Can be downloaded from [WWPass Developers site][]

Authentication with WWPass access code
   If enabled, forces users to input their WWPass Access Code when 
   authenticating (recommended)

[WWPass Developers site]: https://developers.wwpass.com/


== Troubleshooting ==

= Invalid paths or insufficient rights = 

Error messages: 
   * Error occurred during connection to SPFE: Cannot communicate to SPFE: 
     unable to use client certificate (no key found or wrong pass phrase) 
   * Error occurred during connection to SPFE: Cannot communicate to SPFE: 
     unable to set private key file: … type PEM

Possible reason: 
   Invalid paths or insufficient rights to the files specified in one or more 
   fields of the configuration form. 

Solution: 
   Make sure that correct paths are entered into configuration form`s fields:
      * The paths should exist and point to WWPass credential files.
      * The paths should be readable by the web-server.
      * The paths should be of a correct type for a particular field 
        (for example, a path to a key file is required for “Path to WWPass SPID 
        Private Key” field).

= Blocked or invalid Service Provider ID = 

Error message: 
   Error occurred during connection to SPFE: SPFE returns error: Service 
   Provider is disabled.

Possible reason: 
   The Service Provider ID is blocked or invalid.

Solution: 
   Contact support@wwpass.com.


== Frequently Asked Questions == 

= How to Use your WWPass PassKey to Authenticate with WordPress =

Use your existing WordPress registration and associate it with your 
WWPass PassKey:
   1. Login to WordPress using your traditional login/password
   2. In the WordPress User Dashboard select the "WWPass" tab
   3. Press the "Associate" button


== Screenshots == 

1. Login form
2. WWPass Plugin Settings
3. WWPass Authentication

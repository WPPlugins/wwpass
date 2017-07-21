<?php
/*
Plugin Name: WWPass 
Plugin URI: http://www.wwpass.com 
Description: WWPass authentication plugin for WordPress 
Author: WWPass Corporation
Version: 2.2.0
Author URI: http://www.wwpass.com 
*/

/**
 * wordpress_integration.php
 *
 * WWPass plugin for WordPress
 *
 * @copyright (c) WWPass Corporation, 2011-2013
 * @author Vladimir Korshunov <v.korshunov@wwpass.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *   http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

function wwpass_install() {
    global $wpdb;
    $wpdb->show_errors();
    $wpdb->query('CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix . 'wwpass` (
            `user_id` bigint(20) unsigned NOT NULL, 
            `wwpass_puid` varchar(64) NOT NULL DEFAULT "", 
            KEY `user_id` (`user_id`), 
            KEY `wwpass_puid` (`wwpass_puid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

function wwpass_uninstall() {
    global $wpdb;
    $wpdb->show_errors();
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wwpass;');
}

register_activation_hook(__FILE__ , 'wwpass_install');
register_uninstall_hook(__FILE__ , 'wwpass_uninstall');


/* fix for not defined magic constants */

try {
    include('include/wwpass.php');
} catch (Exception $e) {}

if ( ! class_exists('WWPASSConnection')) {
    try {
        include(plugin_dir_path(__FILE__) . 'include/wwpass.php');
    } catch (Exception $e) {}
}

/* end of fix */

function wwpass_cw_ticket() {
    return get_option('WWPASS_SPNAME') . (get_option('WWPASS_ASKPASS', false) ? ':p' : '');
}

add_action('login_head', 'wwpass_wp_login_head');
add_action('login_form', 'wwpass_wp_login_form');

add_action('authenticate', 'wwpass_wp_auth');

function wwpass_wp_login_head() {
    if (get_option('WWPASS_SPNAME', false)) {
        ?>
        <script type="text/javascript" src="//cdn.wwpass.com/packages/wwpass.js/1.3/wwpass.js"></script>
        <script type="text/javascript" charset="utf-8">

        function OnAuth() {
            wwpass_auth('<?php echo wwpass_cw_ticket();?>', function (status, response) {
                if (status != 603) {
                    var form_status = document.createElement('input');
                    form_status.type = 'hidden';
                    form_status.name = 'wwpass_status';
                    form_status.value = status;
                    
                    var form_response = document.createElement('input');
                    form_response.type = 'hidden';
                    form_response.name = 'wwpass_response';
                    form_response.value = response;
                    
                    var f = document.getElementById('loginform');
                    f.appendChild(form_status);
                    f.appendChild(form_response);
                    f.submit();
                }
            });
        } 
        </script>
        
        <?php
        
    } // if 
}

function wwpass_wp_login_form()
{
    if (get_option('WWPASS_SPNAME', false)) {
        ?>
        <p style="text-align: center; margin-bottom: 16px;">
            <input type="button" class="button button-large" style="width:100%; " onClick="javascript:OnAuth();" value="Log In with WWPass">
        </p>
        <?php
    }
}

function wwpass_wp_auth($user)
{
    global $error;

    if ( array_key_exists('wwpass_response', $_POST) && $_POST['wwpass_response'] )
    {
        $redirect_to = array_key_exists('redirect_to', $_REQUEST) ? $_REQUEST['redirect_to'] : null;
        $status = array_key_exists('wwpass_status', $_REQUEST) ? $_REQUEST['wwpass_status'] : null;
        $response = array_key_exists('wwpass_response', $_REQUEST) ? $_REQUEST['wwpass_response'] : null;
                
        if ($status == 200) {
            try {
                $SPFE = new WWPASSConnection(
                    get_option('WWPASS_PATH_KEY'), 
                    get_option('WWPASS_PATH_CRT'), 
                    get_option('WWPASS_PATH_CA', false)
                    );
                
                $puid = $SPFE->getPUID($response);
                global $wpdb;
                $wpdb->show_errors();
                
                $user_id = $wpdb->get_var(
                    $wpdb->prepare('SELECT `user_id` FROM `' . $wpdb->prefix . 'wwpass` WHERE `wwpass_puid` = %s LIMIT 1;', $puid)
                    ); 
               
                if ($user_id) 
                    return new WP_User($user_id);
                else
                    $error .= 'This PassKey is not associated with any WordPress Profile. ';
            } catch (Exception $e) {
                $error .= 'PassKey authentication error. Please, try again. ';
                $error .= $e->getMessage();
            }
        } else {
            $error .= $response;
        }
        
        $user = new WP_Error();
        $user->add('error', __($error));

        return $user;
    }
}

add_action('admin_menu', 'wwpass_admin_actions'); 

function wwpass_admin_actions() {  
    add_options_page("WWPass Authentication Plugin Settings", "WWPass", 10, __FILE__ . 'admin', "wwpass_admin");
    if (get_option('WWPASS_SPNAME', false)) { 
        add_menu_page("WWPass Authentication", "WWPass", 0, __FILE__ . 'user', "wwpass_user");
    }
}

function wwpass_admin() {
    if (array_key_exists('wwp_key', $_POST) && $_POST['wwp_key'] &&
        array_key_exists('wwp_crt', $_POST) && $_POST['wwp_crt']) {
        $wwp_settings['WWPASS_PATH_KEY'] = $_POST['wwp_key'];
        $wwp_settings['WWPASS_PATH_CRT'] = $_POST['wwp_crt'];
        
        $fi = 0;
        global $error;
        
        if (@$h_key = fopen($wwp_settings['WWPASS_PATH_KEY'], 'r'))
        {
            $fi++;
            fclose($h_key);
        } else {
            $error .= "<p>Invalid path to Private Key (.key).</p>";
        }

        if (@$h_crt = fopen($wwp_settings['WWPASS_PATH_CRT'], 'r'))
        {
            $fi++;
            fclose($h_crt);
        } else {
            $error .= "<p>Invalid path to Certificate (.crt).</p>";
        }

        if (array_key_exists('wwp_ca', $_POST) )
        {
            if ($_POST['wwp_ca']) {
                if (@$h_crt = fopen($_POST['wwp_ca'], 'r')) {
                    $wwp_settings['WWPASS_PATH_CA'] = $_POST['wwp_ca'];
                    fclose($h_crt);
                }
                else
                    $error .= "<p>Invalid path to WWPass Certificate Authority (ca.crt).</p>"; // @todo not valid CA
            }
            else
                $wwp_settings['WWPASS_PATH_CA'] = false;
        } else {
            $wwp_settings['WWPASS_PATH_CA'] = false;
        }
        
        if ( ! $error ) {
            try {
                $SPFE = new WWPASSConnection(
                    $wwp_settings['WWPASS_PATH_KEY'], 
                    $wwp_settings['WWPASS_PATH_CRT'],
                    $wwp_settings['WWPASS_PATH_CA']
                );
                $wwp_settings['WWPASS_SPNAME'] = $SPFE->getName();
            } catch (Exception $e) {
                $error .= '<p>Error occured during connection to SPFE: '.$e->getMessage().'</p>';
            }
            
            $wwp_settings['WWPASS_ASKPASS'] = array_key_exists('wwp_askpass', $_POST) && $_POST['wwp_askpass'] ? ':p' : '';
            if (array_key_exists('WWPASS_SPNAME', $wwp_settings)) {
                foreach ($wwp_settings as $option_name => $value)
                {
                    if ( get_option($option_name, false) !== false) {
                        update_option( $option_name, $value );
                    } else {
                        $deprecated = ' ';
                        $autoload = 'no';
                        add_option( $option_name, $value, $deprecated, $autoload );
                    }
                }
                
                $message = "Settings were saved.";
            }
        }
        
    }

    if (array_key_exists('wwp_spfe', $_POST) && $_POST['wwp_spfe']) {
        $p_spfe = $_POST['wwp_spfe'];
    } else
        $p_spfe = get_option('WWPASS_SPFE', 'spfe.wwpass.com');

    if (array_key_exists('wwp_key', $_POST) && $_POST['wwp_key']) {
        $p_key = $_POST['wwp_key'];
    } else 
        $p_key = get_option('WWPASS_PATH_KEY', '');

    if (array_key_exists('wwp_crt', $_POST) && $_POST['wwp_crt']) {
        $p_crt = $_POST['wwp_crt'];
    } else 
        $p_crt = get_option('WWPASS_PATH_CRT', '');
    
    if (array_key_exists('wwp_ca', $_POST)) {
        if ($_POST['wwp_ca'])
            $p_ca = $_POST['wwp_ca'];
        else
            $p_ca = false;
            
    } else 
        $p_ca = get_option('WWPASS_PATH_CA', '');
    
    $p_ask = isset($wwp_settings['WWPASS_ASKPASS']) ? $wwp_settings['WWPASS_ASKPASS'] : get_option('WWPASS_ASKPASS', '');
    
    $sp_name = get_option('WWPASS_SPNAME', 'Name not gived');
    
    if (get_option('WWPASS_PATH_CRT', false)) {
        $data = openssl_x509_parse(file_get_contents(get_option('WWPASS_PATH_CRT')));
        $validFrom = date('Y-m-d H:i:s', $data['validFrom_time_t']);
        $validTo = date('Y-m-d H:i:s', $data['validTo_time_t']);

        $delta = round(($data['validTo_time_t'] - time())/60/60/24); 
        if ($delta > 1)
            $hdelta = 'Certificate expires in ' . $delta . ' days';
        elseif ($delta == 1) 
            $hdelta = 'Certificate expires in ' . $delta . ' day';
        elseif ($delta == 0) 
            if ($data['validTo_time_t'] - time() > 0)
                $hdelta = 'Expires today';
            else
                $hdelta = 'Expired';
        else
            $hdelta = 'Expired';
    }
    
    # include_once('templates/wwpass_admin.html');
?>
    <div class="wrap">
    <div id="icon-options-general" class="icon32"><br /></div><h2>WWPass Plugin Settings</h2>

<?php
    if (@$message) {
    ?>
    <div class="updated">
        <p><strong><?php echo $message;?></strong></p>
        <p><?php echo $hdelta;?> (<?php echo $validTo;?> UTC)</p>
    </div>
    <?php
    }

    if (@$error) {
    ?>
    <div id="m-error" class="error">
        <p><strong>Settings were not saved.</strong></p>
        <p><?php echo $error;?></p>
    </div>
    <?php
    }

    if ( ! get_option('WWPASS_SPNAME', false)) { ?>
    <p>A valid WWPass Service Provider ID is required to use the WWPass Wordpress Plugin. Please register for a WWPass SPID at the <a href="https://dev.wwpass.com/">WWPass Developers website</a></p>
    <?php } ?>
    
    <h3 onClick="toggleClass('current-settings', 'hidden');"><span class="h3-point">Current connection settings</span></h3>
    <table class="form-table hidden" id="current-settings">
        <tr valign="top">
            <th scope="row">Path to WWPass SPID Private Key (.key)</th>
            <td>
                <p><?php echo get_option('WWPASS_PATH_KEY', '');?></p>
            </td>
        </tr>
    
        <tr valign="top">
            <th scope="row">Path to WWPass SPID Certificate (.crt)</th>
            <td>
                <p><?php echo get_option('WWPASS_PATH_CRT', '');?></p>
                <p class="description"><?php echo $hdelta;?> (<?php echo $validTo; ?> UTC)</p>
            </td>
        </tr>
        
        <tr valign="top">
            <th scope="row">Path to WWPass Certificate Authority (ca.crt)</th>
            <td>
                <p><?php echo get_option('WWPASS_PATH_CA', '');?></p>
            </td>
        </tr>
    
        <tr valign="top">
            <th scope="row">SP Name (encoded)</th>
            <td>
                <p><?php echo get_option('WWPASS_SPNAME', 'Name not gived');?></p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">SP Name (decoded)</th>
            <td>
                <p><?php echo urldecode(get_option('WWPASS_SPNAME', ''));?></p>
                <p class="description">User readable record</p>
            </td>
        </tr>
        
        <tr valign="top">
            <th scope="row"></th>
            <td>
                <?php echo get_option('WWPASS_ASKPASS', false) ? '<p>All PassKey authentication requests will require WWPass access code</p>' : '<p>All PassKey authentication requests will not require WWPass access code</p>'; ?>
            </td>
        </tr>
    </table>

    <form method="post">
        <h3>Connection settings</h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="wwp_key">Path to WWPass SPID Private Key (.key)</label></th>
                <td>
                    <input name="wwp_key" type="text" id="wwp_key" value="<?php echo $p_key;?>" class="regular-text" />
                </td>
            </tr>
        
            <tr valign="top">
                <th scope="row"><label for="wwp_crt">Path to WWPass SPID Certificate (.crt)</label></th>
                <td>
                    <input name="wwp_crt" type="text" id="wwp_crt" value="<?php echo $p_crt;?>" class="regular-text" />
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row"><label for="wwp_ca">Path to WWPass Certificate Authority (<a href="https://developers.wwpass.com/downloads/wwpass.ca">ca.crt</a>)</label></th>
                <td>
                    <input name="wwp_ca" type="text" id="wwp_ca" value="<?php echo $p_ca;?>" class="regular-text" />
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row"></th>
                <td>
                    <input name="wwp_askpass" type="checkbox" value="1" id="wwp_askpass" <?php echo $p_ask ? 'checked="checked"': '';?> />&nbsp;<label for="wwp_askpass">Authentication with WWPass access code</label>
                    <p class="description">All WWPass PassKey authentication requests will prompt for your WWPass access code</p>
                </td>
            </tr>
        </table>
        <p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Check and Save Settings"  /></p>
    </form>
</div>

<style type="text/css">
    .hidden {
        display:none;
    }
    
    .h3-point {
        cursor: pointer;
        font-weight: bold;
        color: rgb(33, 117, 155);
        border-bottom: 1px dashed rgb(33, 117, 155);
    }
    
    .form-table th {
        width: 250px;
    }
    
    input.regular-text, #adduser .form-field input {
        width: 35em;
    }
</style>

<script>

function toggleClass(eid, myclass) {
    var theEle = document.getElementById(eid);
    var eClass = theEle.className;
    if (eClass.indexOf(myclass) >= 0 ) {
        theEle.className = eClass.replace(myclass, '');
    } else {
        theEle.className  +=  ' ' + myclass;
    }
}

</script>

<?php
}

function wwpass_user() {
    $user = wp_get_current_user();
    global $wpdb;
    if (array_key_exists('action', $_POST) && $_POST['action']) {
        $action = $_POST['action'];
        
        $status = array_key_exists('wwpass_status', $_REQUEST) ? $_REQUEST['wwpass_status'] : null;
        $response = array_key_exists('wwpass_response', $_REQUEST) ? $_REQUEST['wwpass_response'] : null;
        
        if ($action == 'unbind') {
            // unbind
            // global $wpdb;
            $wpdb->show_errors();
            $wpdb->query(
                $wpdb->prepare('DELETE FROM `' . $wpdb->prefix . 'wwpass` WHERE `user_id` = %s;', $user->ID)
                );
            ?>
            <div id="message" class="updated">
                <p><strong>Your PassKeys have been dissociated with the WordPress profile.</strong></p>
            </div>
            <?php
            
            $user->wwpass_puid = '';
        } elseif ($action == 'bind' && $status == 200 && $response) {
            // bind
                $SPFE = new WWPASSConnection(
                    get_option('WWPASS_PATH_KEY', ''), 
                    get_option('WWPASS_PATH_CRT', ''),
                    get_option('WWPASS_PATH_CA', false)
                    );
                
                try {
                    $puid = $SPFE->getPUID($response);
                    
                    $wpdb->show_errors();
                    $result = $wpdb->get_var(
                        $wpdb->prepare('SELECT `user_id` FROM `' . $wpdb->prefix . 'wwpass` WHERE `wwpass_puid` = %s LIMIT 1;', $puid)
                        );
                    
                    if ( ! $result)
                    {
                        $wpdb->query(
                            $wpdb->prepare('INSERT INTO `' . $wpdb->prefix . 'wwpass` VALUES (%s, %s);', $user->ID, $puid)
                            );
                        $message = 'Your PassKeys have been associated with this WordPress profile.';
                    } elseif ($result == $user->ID) {
                        $message = 'Your PassKeys is already associated with this WordPress profile.';
                    } else {
                        // print_r($result);
                        $error .= 'This PassKey is already associated with another WordPress profile. Disassociate this PassKey before using it with this WordPress profile.';
                    }
                } catch (Exception $e) {
                    $error .= $e->getMessage();
                }
        } else {
            $error .= $response;
        }
    }
    $puid = $wpdb->get_var($wpdb->prepare('SELECT wwpass_puid FROM `'. $wpdb->prefix .'wwpass` WHERE user_id = %s LIMIT 1;', $user->ID)) or false;
?>

<div class="wrap">
    
    <div id="icon-users" class="icon32"><br /></div><h2>WWPass Authentication</h2>
    
    <?php if ( @$message ) { ?>
    <div id="message" class="updated">
        <p><strong><?php echo $message;?></strong></p>
    </div>
    <?php } elseif ( @$error ) { ?>
        <div class="error">
            <p><strong>Settings were not saved.</strong></p>
            <p><?php echo $error; ?></p>
        </div>
    <?php } ?>
    
    <p>To use your WWPass PassKey to access this WordPress profile, please present your PassKey and press the Associate button. <?php echo ( get_option('WWPASS_ASKPASS', false) ? 'You will need to enter your WWPass access code.' : '' ) ;?></p>
    
    <form method="POST" id="bindform" name="bindform">
        <input type="hidden" name="action" value="bind">
        <p><input type="submit" name="btn-bind" value="Associate" class="button-primary" onClick="javascript:OnBind();return false;"></p>
    </form>
    
    <?php if ((bool) $puid) { ?>
        <p>To stop using your WWPass PassKeys to access this WordPress profile, please press the Dissociate button.</p>
        
        <form method="POST" id="unbindform" name="unbindform">
            <input type="hidden" name="action" value="unbind">
            <p><input type="submit" name="btn-unbind" value="Dissociate" class="button-primary"></p>
        </form>
    <?php } else { ?>
        <p>Your account is not associated.</p>
    <?php } ?>

</div>

    <script type="text/javascript" src="//cdn.wwpass.com/packages/wwpass.js/1.3/wwpass.js"></script>
    <script type="text/javascript" charset="utf-8">
    
    function OnBind() {
        wwpass_auth('<?php echo wwpass_cw_ticket();?>', function (status, response) {
            if (status != 603) {
                var form_status = document.createElement('input');
                form_status.type = 'hidden';
                form_status.name = 'wwpass_status';
                form_status.value = status;
                
                var form_response = document.createElement('input');
                form_response.type = 'hidden';
                form_response.name = 'wwpass_response';
                form_response.value = response;
                
                var f = document.getElementById('bindform');
                f.appendChild(form_status);
                f.appendChild(form_response);
                f.submit();
            }
        });
    }
    </script>
    <?php
}

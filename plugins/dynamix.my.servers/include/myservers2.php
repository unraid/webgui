    <!-- myservers2 -->
    <?
    if (file_exists('/boot/config/plugins/dynamix.my.servers/myservers.cfg')) {
      @extract(parse_ini_file('/boot/config/plugins/dynamix.my.servers/myservers.cfg',true));
    }
    // upc translations
    $upc_translations = [
      ($_SESSION['locale']) ? $_SESSION['locale'] : 'en_US' => [
        'getStarted' => _('Get Started'),
        'signIn' => _('Sign In'),
        'signUp' => _('Sign Up'),
        'signOut' => _('Sign Out'),
        'error' => _('Error'),
        'fixError' => _('Fix Error'),
        'closeLaunchpad' => _('Close Launchpad and continue to webGUI'),
        'learnMore' => _('Learn more'),
        'popUp' => _('Pop-up'),
        'close' => _('Close'),
        'backToPopUp' => sprintf(_('Back to %s'), _('Pop-up')),
        'closePopUp' => sprintf(_('Close %s'), _('Pop-up')),
        'contactSupport' => _('Contact Support'),
        'lanIp' => sprintf(_('Click to copy LAN IP %s'), '{0}'),
        'lanIpCopied' => _('LAN IP Copied'),
        'continueToUnraid' => _('Continue to Unraid'),
        'description' => _('Description'),
        'year' => _('year'),
        'years' => _('years'),
        'month' => _('month'),
        'months' => _('months'),
        'day' => _('day'),
        'days' => _('days'),
        'hour' => _('hour'),
        'hours' => _('hours'),
        'minute' => _('minute'),
        'minutes' => _('minutes'),
        'second' => _('second'),
        'seconds' => _('seconds'),
        'ago' => _('ago'),
        'basicPlusPro' => [
          'heading' => _('Thank you for choosing Unraid OS').'!',
          'message' => [
            'registered' => _('Get started by signing in to Unraid.net'),
            'upgradeEligible' => _('To support more storage devices as your server grows click Upgrade Key')
          ]
        ],
        'actions' => [
          'purchase' => _('Purchase Key'),
          'upgrade' => _('Upgrade Key'),
          'recover' => _('Recover Key'),
          'replace' => _('Replace Key'),
          'extend' => _('Extend Trial'),
          'signOutUnraidNet' => _('Sign Out of Unraid.net'),
        ],
        'upc' => [
          'avatarAlt' => '{0} '._('Avatar'),
          'confirmClosure' => _('Confirm closure then continue to webGUI'),
          'closeDropdown' => _('Close dropdown'),
          'openDropdown' => _('Open dropdown'),
          'pleaseConfirmClosureYouHaveOpenPopUp' => _('Please confirm closure').'. '._('You have an open pop-up').'.',
          'trialHasExpiredSeeOptions' => _('Trial has expired see options below'),
          'errorCertRequiresSignIn' => _('Sign In before your Unraid.net SSL certificate expires'),
          'noRemoteApikeyRegisteredWithPlg' => [
            'heading' => _('My Servers Error'),
            'msg' => _('Unraid.net re-authentication required'),
          ],
          'errorTooManyDisks' => [
            'heading' => 'Too many devices',
            'msg' => [
              'base' => 'You must upgrade your key to support more devices.',
              'basic' => 'Your Basic key supports 6 devices.',
              'plus' => 'Your Plus key supports 12 devices.',
            ],
          ],
          'extraLinks' => [
            'newTab' => sprintf(_('Opens %s in new tab'), '{0}'),
            'myServers' => _('My Servers Dashboard'),
            'forums' => _('Unraid Forums'),
            'settings' => [
              'text' => _('Settings'),
              'title' => _('Settings > Management Access • Unraid.net'),
            ],
          ],
          'meta' => [
            'trial' => [
              'active' => [
                'date' => sprintf(_('Trial key expires at %s'), '{date}'),
                'timeDiff' => sprintf(_('Trial expires in %s'), '{timeDiff}'),
              ],
              'expired' => [
                'date' => sprintf(_('Trial key expired at %s'), '{date}'),
                'timeDiff' => sprintf(_('Trial expired %s'), '{timeDiff}'),
              ],
            ],
            'uptime' => [
              'date' => sprintf(_('Server up since %s'), '{date}'),
              'readable' => sprintf(_('Uptime %s'), '{timeDiff}'),
            ],
          ],
          'myServers' => [
            'heading' => _('My Servers'),
            'beta' => _('beta'),
            'errors' => [
              'unraidApi' => [
                'heading' => _('Unraid API Error'),
                'message' => _('Failed to connect to Unraid API'),
              ],
              'myServers' => [
                'heading' => _('My Servers Error'),
                'message' => _('Please wait a moment and reload the page'),
              ],
            ],
            'closeDetails' => _('Close Details'),
            'loading' => _('Loading My Servers data'),
            'displayingLastKnown' => _('Displaying last known server data'),
            'mothership' => [
              'connected' => _('Connected to Mothership'),
              'notConnected' => _('Not Connected to Mothership'),
            ],
            'accessLabels' => [
              'current' => _('Current server'),
              'local' => _('Local access'),
              'offline' => _('Server Offline'),
              'remote' => _('Remote access'),
              'unavailable' => _('Access unavailable'),
            ],
          ],
          'opensNewHttpsWindow' => [
            'base' => sprintf(_('Opens new HTTPS window to %s'), '{0}'),
            'signIn' => sprintf(_('Opens new HTTPS window to %s'), _('Sign In')),
            'signOut' => sprintf(_('Opens new HTTPS window to %s'), _('Sign Out')),
            'purchase' => sprintf(_('Opens new HTTPS window to %s'), _('Purchase Key')),
            'upgrade' => sprintf(_('Opens new HTTPS window to %s'), _('Upgrade Key')),
          ],
          'signInActions' => [
            'resolve' => _('Sign In to resolve'),
            'purchaseKey' => _('Sign In to Purchase Key'),
            'purchaseKeyOrExtendTrial' => '@:upc.signInActions.purchaseKey or @:actions.extend',
          ],
        ],
        'stateData' => [
          'ENOKEYFILE' => [
            'humanReadable' => _('No Keyfile'),
            'heading' => [
              'registered' => _('Thanks for supporting Unraid').'!',
              'notRegistered' => _("Let's unleash your hardware"),
            ],
            'message' => [
              'registered' => _('You are all set 👍'),
              'notRegistered' => _('Sign in or sign up to get started'),
            ],
          ],
          'TRIAL' => [
            'humanReadable' => _('Trial'),
            'heading' => _('Thank you for choosing Unraid OS').'!',
            'message' => _('Your Trial key includes all the functionality and device support of a Pro key').'. '._('After your Trial has reached expiration your server still functions normally until the next time you Stop the array or reboot your server').'. '._('At that point you may either purchase a license key or request a Trial extension').'.',
            '_extraMsg' => sprintf(_('You have %s remaining on your Trial key'), '{parsedExpireTime}'),
          ],
          'EEXPIRED' => [
            'humanReadable' => _('Trial Expired'),
            'heading' => _('Your Trial has expired'),
            'message' => [
              'base' => _('To continue using Unraid OS you may purchase a license key').'. ',
              'extensionNotEligible' => _('You have used all your Trial extensions').'. @:stateData.EEXPIRED.message.base',
              'extensionEligible' => '@:stateData.EEXPIRED.message.base '._('Alternately, you may request a Trial extension').'.',
            ],
          ],
          'BASIC' => [
            'humanReadable' => _('Basic'),
          ],
          'PLUS' => [
            'humanReadable' => _('Plus'),
          ],
          'PRO' => [
            'humanReadable' => _('Pro'),
          ],
          'EGUID' => [
            'humanReadable' => _('GUID Error'),
            'error' => [
              'heading' => _('Registration key / GUID mismatch'),
              'message' => [
                'default' => _('The license key file does not correspond to the USB Flash boot device').'. '._('Please copy the correct key file to the */config* directory on your USB Flash boot device or choose Purchase Key').'.',
                'replacementIneligible' => _('Your Unraid registration key is ineligible for replacement as it has been replaced within the last 12 months').'.',
                'replacementEligible' => _('The license key file does not correspond to the USB Flash boot device').'. '._('Please copy the correct key file to the */config* directory on your USB Flash boot device or choose Purchase Key or Replace Key').'.',
              ],
            ],
          ],
          'ENOKEYFILE2' => [
            'humanReadable' => _('Missing key file'),
            'error' => [
              'heading' => '@:stateData.ENOKEYFILE2.humanReadable',
              'message' => _('It appears that your license key file is corrupted or missing').". "._('The key file should be located in the */config* directory on your USB Flash boot device').'. '._('If you do not have a backup copy of your license key file you may attempt to recover your key').'. '._('If this was a Trial installation, you may purchase a license key').'.',
            ],
          ],
          'ETRIAL' => [
            'humanReadable' => _('Invalid installation'),
            'error' => [
              'heading' => '@:stateData.ETRIAL.humanReadable',
              'message' => _('It is not possible to use a Trial key with an existing Unraid OS installation').'. '._('You may purchase a license key corresponding to this USB Flash device to continue using this installation').'.',
            ],
          ],
          'ENOKEYFILE1' => [
            'humanReadable' => _('No Keyfile'),
            'error' => [
              'heading' => _('No USB flash configuration data'),
              'message' => _('There is a problem with your USB Flash device'),
            ],
          ],
          'ENOFLASH' => [
            'humanReadable' => _('No Flash'),
            'error' => [
              'heading' => _('Cannot access your USB Flash boot device'),
              'message' => _('There is a physical problem accessing your USB Flash boot device'),
            ],
          ],
          'EGUID1' => [
            'humanReadable' => _('Multiple License Keys Present'),
            'error' => [
              'heading' => '@:stateData.EGUID1.humanReadable',
              'message' => _('There are multiple license key files present on your USB flash device and none of them correspond to the USB Flash boot device').'. '._('Please remove all key files except the one you want to replace from the */config* directory on your USB Flash boot device').'. '._('Alternately you may purchase a license key for this USB flash device').'. '._('If you want to replace one of your license keys with a new key bound to this USB Flash device please first remove all other key files first').'.',
            ],
          ],
          'EBLACKLISTED' => [
            'humanReadable' => _('BLACKLISTED'),
            'error' => [
              'heading' => _('Blacklisted USB Flash GUID'),
              'message' => _('This USB Flash boot device has been blacklisted').'. '._('This can occur as a result of transferring your license key to a replacement USB Flash device, and you are currently booted from your old USB Flash device').'. '._('A USB Flash device may also be blacklisted if we discover the serial number is not unique – this is common with USB card readers').'.',
            ],
          ],
          'EBLACKLISTED1' => [
            'humanReadable' =>'@:stateData.EBLACKLISTED.humanReadable',
            'error' => [
              'heading' => _('USB Flash device error'),
              'message' => _('This USB Flash device has an invalid GUID').'. '._('Please try a different USB Flash device').'.',
            ],
          ],
          'EBLACKLISTED2' => [
            'humanReadable' => '@:stateData.EBLACKLISTED.humanReadable',
            'error' => [
              'heading' => _('USB Flash has no serial number'),
              'message' => '@:stateData.EBLACKLISTED.error.message',
            ],
          ],
          'ENOCONN' => [
            'humanReadable' => _('Trial Requires Internet Connection'),
            'error' => [
              'heading' => _('Cannot validate Unraid Trial key'),
              'message' => _('Your Trial key requires an internet connection').'. '._('Please check Settings > Network').'.',
            ],
          ],
          'STALE' => [
            'humanReadable' => _('Stale'),
            'error' => [
              'heading' => _('Stale Server'),
              'message' => _('Please refresh the page to ensure you load your latest configuration'),
            ],
          ],
        ],
        'regWizPopUp' => [
          'regWiz' => _('Registration Wizard'),
          'toHome' => _('To Registration Wizard Home'),
          'continueTrial' => _('Continue Trial'),
          'serverInfoToggle' => _('Toggle server info visibility'),
          'youCanSafelyCloseThisWindow' => _('You can safely close this window'),
          'automaticallyClosingIn' => sprintf(_('Auto closing in %s'), '{0}'),
          'byeBye' => _('bye bye 👋'),
          'browserWillSelfDestructIn' => sprintf(_('Browser will self destruct in %s'), '{0}'),
          'closingPopUpMayLeadToErrors' => _('Closing this pop-up window while actions are being preformed may lead to unintended errors'),
          'goBack' => _('Go Back'),
          'shutDown' => _('Shut Down'),
          'haveAccountSignIn' => _('Already have an account').'? '._('Sign In'),
          'noAccountSignUp' => _('Do not have an account').'? '._('Sign Up'),
          'serverInfo' => [
            'flash' => _('Flash'),
            'product' => _('Product'),
            'GUID' => _('GUID'),
            'name' => _('Name'),
            'ip' => _('IP'),
          ],
          'forms' => [
            'displayName' => _('Display Name'),
            'emailAddress' => _('Email Address'),
            'displayNameOrEmailAddress' => _('Display Name or Email Address'),
            'displayNameRootMessage' => _('Use your Unraid.net credentials, not your local server credentials'),
            'honeyPotCopy' => _('If you fill this field out then your email will not be sent'),
            'fieldRequired' => _('This field is required'),
            'submit' => _('Submit'),
            'submitting' => _('Submitting'),
            'notValid' => _('Form not valid'),
            'cancel' => _('Cancel'),
            'confirm' => _('Confirm'),
            'createMyAccount' => _('Create My Account'),
            'subject' => _('Subject'),
            'password' => _('Password'),
            'togglePasswordVisibility' => _('Toggle Password Visibility'),
            'message' => _('Message'),
            'confirmPassword' => _('Confirm Password'),
            'passwordMinimum' => _('8 or more characters'),
            'comments' => _('comments'),
            'newsletterCopy' => _('Sign me up for the monthly Unraid newsletter').': '._('a digest of recent blog posts, community videos, popular forum threads, product announcements, and more'),
            'terms' => [
              'iAgree' => _('I agree to the'),
              'text' => _('Terms of Use'),
            ],
          ],
          'routes' => [
            'extendTrial' => [
              'heading' => [
                'loading' => _('Extending Trial'),
                'error' => _('Trial Extension Failed'),
              ],
            ],
            'forgotPassword' => [
              'heading' => _('Forgot Password'),
              'subheading' => _("After resetting your password come back to the Registration Wizard pop-up window to Sign In and complete your server's registration"),
              'resetPasswordNow' => _('Reset Password Now'),
              'backToSignIn' => _('Back to Sign In'),
            ],
            'signIn' => [
              'heading' => [
                'signIn' => _('Unraid.net Sign In'),
                'recover' => _('Unraid.net Sign In to Recover Key'),
                'replace' => _('Unraid.net Sign In to Replace Key'),
              ],
              'subheading' => _('Please sign in with your Unraid.net forum account'),
              'form' => [
                'replacementConditions' => [
                  'name' => _('Acknowledge Replacement Conditions'),
                  'label' => _('I acknowledge that replacing a license key results in permanently blacklisting the previous USB Flash GUID'),
                ],
                'label' => [
                  'password' => [
                    'replace' => _('Unraid.net account password'),
                  ],
                ],
              ],
            ],
            'signUp' => [
              'heading' => _('Create Unraid.net Account'),
              'subheading' => _('This will start your free 30-day Trial'),
            ],
            'signOut' => [
              'heading' => _('Unraid.net Sign Out'),
              'warnings' => [
                'remoteAccessDisabled' => _('Remote access will be disabled'),
                'remoteAccessInaccessible' => sprintf(_('You will no longer have access to this server using <abbr title="%s" class="italic">this url</abbr>'), '{0}'),
                'disablingFlashBackup' => _('Automated flash backups will be disabled until you sign in again'),
                'downloadFlashBackup' => _('Download latest backup from My Servers Dashboard'),
              ],
            ],
            'success' => [
              'heading' => [
                'username' => sprintf(_('Hi %s'), '{0}'),
                'default' => _('Success'),
              ],
              'subheading' => [
                'extention' => _('Your trial will expire in 15 days'),
                'newTrial' => _('Your trial will expire in 30 days'),
              ],
              'signIn' => [
                'tileTitle' => [
                  'actionFail' => sprintf(_('%s was not signed in to your Unraid.net account'), '{0}'),
                  'actionSuccess' => sprintf(_('%s is signed in to your Unraid.net account'), '{0}'),
                  'loading' => sprintf(_('Signing in %s to Unraid.net account'), '{0}'),
                ],
              ],
              'signOut' => [
                'tileTitle' => [
                  'actionFail' => sprintf(_('%s was not signed out of your Unraid.net account'), '{0}'),
                  'actionSuccess' => sprintf(_('%s was signed out of your Unraid.net account'), '{0}'),
                  'loading' => sprintf(_('Signing out %s from Unraid.net account'), '{0}'),
                ],
              ],
              'keys' => [
                'trial' => _('Trial'),
                'basic' => _('Basic'),
                'plus' => _('Plus'),
                'pro' => _('Pro'),
              ],
              'extended' => sprintf(_('%s Key Extended'), '{0}'),
              'recovered' => sprintf(_('%s Key Recovered'), '{0}'),
              'replaced' => sprintf(_('%s Key Replaced'), '{0}'),
              'created' => sprintf(_('%s Key Created'), '{0}'),
              'install' => [
                'loading' => sprintf(_('Installing %s Key'), '{0}'),
                'error' => sprintf(_('%s Key Install Error'), '{0}'),
                'success' => sprintf(_('Installed %s Key'), '{0}'),
                'manualInstructions' => _("To manually install the key paste the key file url into the Key file URL field on the webGUI Tools > Registration page and then click Install Key") . '.',
                'copyFail' => _('Unable to copy'),
                'copySuccess' => _('Copied key url') . '!',
                'copyButton' => _('Copy Key URL'),
                'copyBeforeClose' => _('Please copy the Key URL before closing this window'),
              ],
              'timeout' => sprintf(_('Communication with %s has timed out'), '{0}'),
              'loading1' => _('Please keep this window open'),
              'loading2' => _('Still working our magic'),
              'countdown' => [
                'success' => [
                  'prefix' => sprintf(_('Auto closing in %s'), '{0}'),
                  'text' => _('You can safely close this window'),
                ],
                'error' => [
                  'prefix' => sprintf(_('Auto redirecting in %s'), '{0}'),
                  'text' => _('Back to Registration Home'),
                  'complete' => _('Back in a flash ⚡️'),
                ],
              ],
            ],
            'troubleshoot' => [
              'heading' => [
                'default' => _('Troubleshoot'),
                'success' => _('Thank you for contacting Unraid'),
              ],
              'subheading' => [
                'default' => _("Forgot what Unraid.net account you used").'? '._("Have a USB flash device that already has an account associated with it").'? '._("Just give us the details about what happened and we'll do our best to get you up and running again").'.',
                'success' => _('We have received your e-mail and will respond in the order it was received').'. '._('While we strive to respond to all requests as quickly as possible please allow for up to 3 business days for a response').'.',
              ],
              'relevantServerData' => _('Your USB Flash GUID and other relevant server data will also be sent'),
            ],
            'verifyEmail' => [
              'heading' => _('Verify Email'),
              'subheading' => sprintf(_('We have sent a verifcation email to %s'), '{0}'),
              'form' => [
                'verificationCode' => _('verification code'),
                'verifyCode' => _('Paste or Enter code'),
              ],
              'noCode' => _("Didn't get code?"),
            ],
            'verifyEmailResend' => [
              'heading' => _('Resend Email Verification Code'),
              'goBack' => _("Have the code now? Go Back"),
              'resend' => _("Resend Code"),
            ],
            'whatIsUnraidNet' => [
              'heading' => _('What is Unraid.net?'),
              'subheading' => _('Expand your servers capabilities'),
              'copy' => _('With an Unraid.net account you can start using My Servers (beta) which gives you access to the following features:'),
              'features' => [
                'secureRemoteAccess' => [
                  'heading' => _('Secure remote access'),
                  'copy' => _("Whether you need to add a share container or virtual machine do it all from the webGui from anytime and anywhere using HTTPS").'. '._("Best of all all SSL certificates are verified by Let's Encrypt so no browser security warnings").'.',
                ],
                'realTimeMonitoring' => [
                  'heading' => _('Real-time Monitoring'),
                  'copy' => _('Get quick real-time info on the status of your servers such as storage, container, and VM usage').'. '._('And not just for one server but all the servers in your Unraid fleet'),
                ],
                'usbFlashBackup' => [
                  'heading' => _('USB Flash Backup'),
                  'copy' => _('Click a button and your flash is automatically backed up to Unraid.net enabling easy recovery in the event of a device failure').'. '._('Never self-manage/host your flash backups again'),
                ],
                'regKeyManagement' => [
                  'heading' => _('Registration key management'),
                  'copy' => _('Download any registration key linked to your account').'. '._('Upgrade keys to higher editions').'.',
                ],
              ],
            ],
            'notFound' => [
              'subheading' => _('Page Not Found'),
            ],
            'notAllowed' => [
              'subheading' => _('Page Not Allowed'),
            ],
          ],
        ],
        'wanIpCheck' => [
          'checking' => _('Checking Wan IPs'),
          'match' => sprintf(_('Remark: your WAN IPv4 is **%s**'), '{0}'),
          'mismatch' => sprintf(_("Remark: Unraid's WAN IPv4 **%1s** does not match your client's WAN IPv4 **%2s**"), '{0}', '{1}').'. '._('This may indicate a complex network that will not work with this Remote Access solution').'. '._('Ignore this message if you are currently connected via Remote Access or VPN').'.',
          'resolveError' => _('DNS issue, unable to resolve wanip.unraid.net'),
        ],
      ],
    ];
    $configErrorEnum = [ // used to map $var['configValid'] value to mimic unraid-api's `configError` ENUM
      "error" => 'UNKNOWN_ERROR',
      "invalid" => 'INVALID',
      "nokeyserver" => 'NO_KEY_SERVER',
      "withdrawn" => 'WITHDRAWN',
    ];
    $nginx = parse_ini_file('/var/local/emhttp/nginx.ini');
    $serverstate = [ // feeds server vars to Vuex store in a slightly different array than state.php
      "avatar" => $remote['avatar'],
      "config" => [
        'valid' => $var['configValid'] === 'yes',
        'error' => $var['configValid'] !== 'yes' ? (array_key_exists($var['configValid'], $configErrorEnum) ? $configErrorEnum[$var['configValid']] : 'UNKNOWN_ERROR') : null,
      ],
      "deviceCount" => $var['deviceCount'],
      "email" => $remote['email'] ?? '',
      "flashproduct" => $var['flashProduct'],
      "flashvendor" => $var['flashVendor'],
      "guid" => $var['flashGUID'],
      'hasUnraidNetSSL' => file_exists('/boot/config/ssl/certs/certificate_bundle.pem') ? preg_match('/.*\.unraid\.net$/', $_SERVER['SERVER_NAME']) : 0, // required for boolean to check if user has unraid.net Let's Encrypt cert. Using for a less expensive check w/ $_SERVER['SERVER_NAME'] compared to reading cert file contents on every page load
      "internalip" => ipaddr(),
      "internalport" => $_SERVER['SERVER_PORT'],
      "keyfile" => str_replace(['+','/','='], ['-','_',''], trim(base64_encode(@file_get_contents($var['regFILE'])))),
      "osVersion" => $var['version'],
      "plgVersion" => 'base-'.$var['version'],
      "protocol" => $_SERVER['REQUEST_SCHEME'],
      "reggen" => (int)$var['regGen'],
      "regGuid" => $var['regGUID'],
      "registered" => !empty($remote['username']),
      "servername" => $var['NAME'],
      "site" => $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'],
      "state" => strtoupper(empty($var['regCheck']) ? $var['regTy'] : $var['regCheck']),
      "ts" => time(),
      "username" => $remote['username'],
      "wanFQDN" => $nginx['NGINX_WANFQDN'] ?? '',
    ];
    ?>
    <unraid-user-profile
      apikey="<?=@$upc['apikey']?>"
      banner="<?=$display['banner'] ?? ''?>"
      bgcolor="<?=($backgnd) ? '#'.$backgnd : ''?>"
      csrf="<?=$var['csrf_token']?>"
      displaydesc="<?=($display['headerdescription']!='no') ? 'true' : ''?>"
      expiretime="<?=1000*($var['regTy']=='Trial'||strstr($var['regTy'],'expired')?$var['regTm2']:0)?>"
      hide-my-servers="<?=(file_exists('/usr/local/sbin/unraid-api')) ? '' : 'yes' ?>"
      locale="<?=($_SESSION['locale']) ? $_SESSION['locale'] : 'en_US'?>"
      locale-messages="<?=rawurlencode(json_encode($upc_translations, JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE))?>"
      metacolor="<?=($display['headermetacolor']) ? '#'.$display['headermetacolor'] : ''?>"
      plg-path="dynamix.my.servers"
      reg-wiz-time="<?=$remote['regWizTime'] ?? ''?>"
      serverdesc="<?=$var['COMMENT']?>"
      servermodel="<?=$var['SYS_MODEL']?>"
      serverstate="<?=rawurlencode(json_encode($serverstate, JSON_UNESCAPED_SLASHES))?>"
      show-banner-gradient="<?=$display['showBannerGradient'] ?? 'yes'?>"
      textcolor="<?=($header) ? '#'.$header : ''?>"
      theme="<?=$display['theme']?>"
      uptime="<?=1000*(time() - round(strtok(exec("cat /proc/uptime"),' ')))?>"
      ></unraid-user-profile>
    <!-- /myservers2 -->

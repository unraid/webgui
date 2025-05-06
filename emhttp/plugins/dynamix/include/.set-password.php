<?php
// included in login.php

$REMOTE_ADDR = $_SERVER['REMOTE_ADDR'] ?? "unknown";
$MAX_PASS_LENGTH = 128;
$VALIDATION_MESSAGES = [
    'empty' => _('root requires a password'),
    'mismatch' => _('Password confirmation does not match'),
    'maxLength' => _('Max password length is 128 characters'),
    'saveError' => _('Unable to set password'),
];
$POST_ERROR = '';

/**
 * POST handler
 */
if (!empty($_POST['password']) && !empty($_POST['confirmPassword'])) {
    if ($_POST['password'] !== $_POST['confirmPassword']) return $POST_ERROR = $VALIDATION_MESSAGES['mismatch'];
    if (strlen($_POST['password']) > $MAX_PASS_LENGTH) return $POST_ERROR = $VALIDATION_MESSAGES['maxLength'];

    $userName = 'root';
    $userPassword = base64_encode($_POST['password']);

    exec("/usr/local/sbin/emcmd 'cmdUserEdit=Change&userName=$userName&userPassword=$userPassword'", $output, $result);
    if ($result == 0) {
        // PAM service will log to syslog: "password changed for root"
        if (session_status()==PHP_SESSION_NONE) session_start();
        $_SESSION['unraid_login'] = time();
        $_SESSION['unraid_user'] = 'root';
        session_regenerate_id(true);
        session_write_close();

        // Redirect the user to the start page
        header("Location: /".$start_page);
        exit;
    }

    // Error when attempting to set password
    my_logger("{$VALIDATION_MESSAGES['saveError']} [REMOTE_ADDR]: {$REMOTE_ADDR}");
    return $POST_ERROR = $VALIDATION_MESSAGES['saveError'];
}

require_once "$docroot/plugins/dynamix/include/ThemeHelper.php";
$themeHelper = new ThemeHelper($display['theme']);
$isDarkTheme = $themeHelper->isDarkTheme();
?>
<!DOCTYPE html>
<html lang="en" class="<?= $themeHelper->getThemeHtmlClass() ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
    <meta name="referrer" content="same-origin">
    <title><?=$var['NAME']?>/SetPassword</title>
    <link rel="icon" href="webGui/images/animated-logo.svg" sizes="any" type="image/svg+xml">
    <style>
    /************************
    /
    /  Fonts
    /
    /************************/
    @font-face{font-family:clear-sans;font-weight:normal;font-style:normal; src:url('/webGui/styles/clear-sans.woff?v=20220513') format('woff')}
    @font-face{font-family:clear-sans;font-weight:bold;font-style:normal; src:url('/webGui/styles/clear-sans-bold.woff?v=20220513') format('woff')}
    @font-face{font-family:clear-sans;font-weight:normal;font-style:italic; src:url('/webGui/styles/clear-sans-italic.woff?v=20220513') format('woff')}
    @font-face{font-family:clear-sans;font-weight:bold;font-style:italic; src:url('/webGui/styles/clear-sans-bold-italic.woff?v=20220513') format('woff')}
    @font-face{font-family:bitstream;font-weight:normal;font-style:normal; src:url('/webGui/styles/bitstream.woff?v=20220513') format('woff')}
    @font-face{font-family:bitstream;font-weight:bold;font-style:normal; src:url('/webGui/styles/bitstream-bold.woff?v=20220513') format('woff')}
    @font-face{font-family:bitstream;font-weight:normal;font-style:italic; src:url('/webGui/styles/bitstream-italic.woff?v=20220513') format('woff')}
    @font-face{font-family:bitstream;font-weight:bold;font-style:italic; src:url('/webGui/styles/bitstream-bold-italic.woff?v=20220513') format('woff')}

    /************************
    /
    /  General styling
    /
    /************************/
    :root {
        --body-bg: <?= $isDarkTheme ? '#1c1b1b' : '#f2f2f2' ?>;
        --body-text-color: <?= $isDarkTheme ? '#fff' : '#1c1b1b' ?>;
        --section-bg: <?= $isDarkTheme ? '#1c1b1b' : '#f2f2f2' ?>;
        --shadow: <?= $isDarkTheme ? 'rgba(115,115,115,.12)' : 'rgba(0,0,0,.12)' ?>;
        --form-text-color:  <?= $isDarkTheme ? '#f2f2f2' : '#1c1b1b' ?>;
        --form-bg-color: <?= $isDarkTheme ? 'rgba(26,26,26,0.4)' : '#f2f2f2' ?>;
        --form-border-color: <?= $isDarkTheme ? '#2B2A29' : '#ccc' ?>;
    }
    body {
        background: var(--body-bg);
        color: var(--body-text-color);
        font-family: clear-sans, sans-serif;
        font-size: .875rem;
        padding: 0;
        margin: 0;
    }
    a {
        text-transform: uppercase;
        font-weight: bold;
        letter-spacing: 2px;
        color: #FF8C2F;
        text-decoration: none;
    }
    a:hover {
        color: #f15a2c;
    }
    h1 {
        font-size: 1.8rem;
        margin: 0;
    }
    h2 {
        font-size: .8rem;
        margin-top: 0;
        margin-bottom: 1em;
    }
    .button {
        color: #ff8c2f;
        font-family: clear-sans, sans-serif;
        background: -webkit-gradient(linear,left top,right top,from(#e03237),to(#fd8c3c)) 0 0 no-repeat,-webkit-gradient(linear,left top,right top,from(#e03237),to(#fd8c3c)) 0 100% no-repeat,-webkit-gradient(linear,left bottom,left top,from(#e03237),to(#e03237)) 0 100% no-repeat,-webkit-gradient(linear,left bottom,left top,from(#fd8c3c),to(#fd8c3c)) 100% 100% no-repeat;
        background: linear-gradient(90deg,#e03237 0,#fd8c3c) 0 0 no-repeat,linear-gradient(90deg,#e03237 0,#fd8c3c) 0 100% no-repeat,linear-gradient(0deg,#e03237 0,#e03237) 0 100% no-repeat,linear-gradient(0deg,#fd8c3c 0,#fd8c3c) 100% 100% no-repeat;
        background-size: 100% 2px,100% 2px,2px 100%,2px 100%;
    }
    .button:disabled {
        opacity: .5;
        cursor: not-allowed;
    }
    .button:hover,
    .button:focus {
        color: #fff;
        background-color: #f15a2c;
        background: -webkit-gradient(linear,left top,right top,from(#e22828),to(#ff8c2f));
        background: linear-gradient(90deg,#e22828 0,#ff8c2f);
        -webkit-box-shadow: none;
        box-shadow: none;
        cursor: pointer;
        outline: none;
    }
    .button--small {
        font-size: .875rem;
        font-weight: 600;
        line-height: 1;
        text-transform: uppercase;
        letter-spacing: 2px;
        text-align: center;
        text-decoration: none;
        display: inline-block;
        background-color: transparent;
        border-radius: .125rem;
        border: 0;
        -webkit-transition: none;
        transition: none;
        padding: .75rem 1.5rem;
    }

    [type=password],
    [type=text] {
        color: var(--form-text-color);
        font-family: clear-sans, sans-serif;
        font-size: .875rem;
        background-color: var(--form-bg-color);
        width: 100%;
        margin-top: .25rem;
        margin-bottom: 1rem;
        border: 2px solid var(--form-border-color);
        padding: .75rem 1rem;
        -webkit-box-sizing: border-box;
        box-sizing: border-box;
        border-radius: 0;
        -webkit-appearance: none;
    }

    [type=password]:focus,
    [type=text]:focus  {
        border-color: #ff8c2f;
        outline: none;
    }

    [type=password]:disabled,
    [type=text]:disabled {
        cursor: not-allowed;
        opacity: .5;
    }

    /************************
    /
    /  Utility Classes
    /
    /************************/
    .w-100px { width: 100px }
    .w-full { width: 100% }
    .relative { position: relative }
    .flex { display: flex }
    .flex-auto { flex: auto }
    .flex-col { flex-direction: column }
    .flex-row { flex-direction: row }
    .justify-between { justify-content: space-between }
    .justify-end { justify-content: flex-end }
    .invisible { visibility: hidden }

    /************************
    /
    /  Login spesific styling
    /
    /************************/
    section {
        width: 500px;
        margin: 6rem auto;
        border-radius: 10px;
        background: var(--section-bg);
        -webkit-box-shadow: 0 2px 8px 0 var(--shadow);
        box-shadow: 0 2px 8px 0 var(--shadow);
    }
    .logo {
        z-index: 1;
        position: relative;
        padding: 2rem;
        width: 100px;
    }
    .error {
        color: #E22828;
        font-weight: bold;
        margin-top: 0;
    }
    .content { padding: 2rem }
    .angle {
        position: relative;
        overflow: hidden;
        height: 120px;
        border-radius: 10px 10px 0 0;
    }
    .angle:after {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 120px;
        background-color: #f15a2c;
        background: -webkit-gradient(linear,left top,right top,from(#e22828),to(#ff8c2f));
        background: linear-gradient(90deg,#e22828 0,#ff8c2f);
        -webkit-transform-origin: bottom left;
        transform-origin: bottom left;
        -webkit-transform: skewY(-6deg);
        transform: skewY(-6deg);
        -webkit-transition: -webkit-transform .15s linear;
        transition: -webkit-transform .15s linear;
        transition: transform .15s linear;
        transition: transform .15s linear,-webkit-transform .15s linear;
    }

    .pass-toggle {
        color: #ff8c2f;
        border: 0;
        appearance: none;
        background: transparent;
    }

    .pass-toggle:hover,
    .pass-toggle:focus {
        color: #f15a2c;
        outline: none;
    }

    .pass-toggle svg {
        fill: currentColor;
        height: 1rem;
        width: 1rem;
    }

    /************************
    /
    /  Media queries for mobile responsive
    /
    /************************/
    @media (max-width: 500px) {
        body {
            background: var(--section-bg);
        }
        [type=password],
        [type=text] {
            font-size: 16px; /* This prevents the mobile browser from zooming in on the input-field. */
        }
        section {
            margin: 0;
            border-radius: 0;
            width: 100%;
            box-shadow: none;
        }

        .angle { border-radius: 0 }
    }
    </style>
    <noscript>
        <style type="text/css">
            .js-validate { display: none }
        </style>
    </noscript>
</head>

<body>
    <section>
        <div class="angle">
            <div class="logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 222.4 39"><path fill="#ffffff" d="M146.70000000000002 29.5H135l-3 9h-6.5L138.9 0h8l13.4 38.5h-7.1L142.6 6.9l-5.8 16.9h8.2l1.7 5.7zM29.7 0v25.4c0 8.9-5.8 13.6-14.9 13.6C5.8 39 0 34.3 0 25.4V0h6.5v25.4c0 5.2 3.2 7.9 8.2 7.9 5.2 0 8.4-2.7 8.4-7.9V0h6.6zM50.9 12v26.5h-6.5V0h6.1l17 26.5V0H74v38.5h-6.1L50.9 12zM171.3 0h6.5v38.5h-6.5V0zM222.4 24.7c0 9-5.9 13.8-15.2 13.8h-14.5V0h14.6c9.2 0 15.1 4.8 15.1 13.8v10.9zm-6.6-10.9c0-5.3-3.3-8.1-8.5-8.1h-8.1v27.1h8c5.3 0 8.6-2.8 8.6-8.1V13.8zM108.3 23.9c4.3-1.6 6.9-5.3 6.9-11.5 0-8.7-5.1-12.4-12.8-12.4H88.8v38.5h6.5V5.7h6.9c3.8 0 6.2 1.8 6.2 6.7s-2.4 6.8-6.2 6.8h-3.4l9.2 19.4h7.5l-7.2-14.7z"></path></svg>
            </div>
        </div>
        <div class="content">
            <header>
                <h1><?=htmlspecialchars($var['NAME'])?></h1>
                <h2><?=htmlspecialchars($var['COMMENT'])?></h2>
                <p><?=_('Please set a password for the root user account')?>.</p>
                <p><?=_('Max password length is 128 characters')?>.</p>
            </header>
            <noscript>
                <p class="error"><?=_('The Unraid OS webgui requires JavaScript')?>. <?=_('Please enable it')?>.</p>
                <p class="error"><?=_('Please also ensure you have cookies enabled')?>.</p>
            </noscript>
            <form action="/login" method="POST" class="js-validate w-full flex flex-col">
                <label for="password"><?= _('Username') ?></label>
                <input name="username" type="text" value="root" disabled title="<?=_('Username not changeable')?>">

                <div class="flex flex-row items-center justify-between">
                    <label for="password" class="flex-auto"><?=_('Password')?></label>
                    <button type="button" tabIndex="-1" class="js-pass-toggle pass-toggle" title="<?=_('Show Password')?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                            <path d="M24,9A23.654,23.654,0,0,0,2,24a23.633,23.633,0,0,0,44,0A23.643,23.643,0,0,0,24,9Zm0,25A10,10,0,1,1,34,24,10,10,0,0,1,24,34Zm0-16a6,6,0,1,0,6,6A6,6,0,0,0,24,18Z"/>
                            <g class="js-pass-toggle-hide">
                                <rect x="20.133" y="2.117" height="44" transform="translate(23.536 -8.587) rotate(45)" />
                                <rect x="22" y="3.984" width="4" height="44" transform="translate(25.403 -9.36) rotate(45)" fill="#f2f2f2" />
                            </g>
                        </svg>
                    </button>
                </div>
                <input id="password" name="password" type="password" max="128" autocomplete="new-password" autofocus required>

                <label for="confirmPassword"><?=_('Confirm Password')?></label>
                <input id="confirmPassword" name="confirmPassword" type="password" max="128" autocomplete="new-password" required>
                <p class="js-error error"><?=@$POST_ERROR?></p>
                <div class="flex justify-end">
                    <button disabled type="submit" class="js-submit button button--small"><?=_('Set Password')?></button>
                </div>
            </form>
        </div>
    </section>
    <script type="text/javascript">
        // cookie check
        document.cookie = "cookietest=1";
        cookieEnabled = document.cookie.indexOf("cookietest=")!=-1;
        document.cookie = "cookietest=1; expires=Thu, 01-Jan-1970 00:00:01 GMT";
        if (!cookieEnabled) {
            const errorElement = document.createElement('p');
            errorElement.classList.add('error');
            errorElement.textContent = "<?=_('Please enable cookies to use the Unraid webGUI')?>";

            document.body.textContent = '';
            document.body.appendChild(errorElement);
        }
        // Password toggling
        const $passToggle = document.querySelector('.js-pass-toggle');
        const $passToggleHideSvg = $passToggle.querySelector('.js-pass-toggle-hide');
        const $passInputs = document.querySelectorAll('[type=password]');
        let hidePass = true;

        $passToggle.addEventListener('click', () => {
            hidePass = !hidePass;
            if (!hidePass) $passToggleHideSvg.classList.add('invisible'); // toggle svg elements
            else $passToggleHideSvg.classList.remove('invisible');
            $passInputs.forEach($el => $el.type = hidePass ? 'password' : 'text'); // change input types
            $passToggle.setAttribute('title', hidePass ? "<?=_('Show Password')?>" : "<?=_('Hide Password')?>"); // change toggle title
        });
        // front-end validation
        const $submitBtn = document.querySelector('.js-submit');
        const $passInput = document.querySelector('[name=password]');
        const $confirmPassInput = document.querySelector('[name=confirmPassword]');
        const $errorTarget = document.querySelector('.js-error');
        const maxPassLength = <?= $MAX_PASS_LENGTH ?>;
        let displayValidation = false; // user put values in both inputs. now always check on change or debounced blur.
        // helper functions
        function debounce(func, timeout = 300){
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => { func.apply(this, args); }, timeout);
            };
        }
        function validate() {
            // User has entered values into both password fields. Let's start to nag them until they can submit
            if ($passInput.value && $confirmPassInput.value) displayValidation = true;
            const inputsEmpty = !$passInput.value || !$confirmPassInput.value;
            const inputsMismatch = $passInput.value !== $confirmPassInput.value;
            const passTooLong = $passInput.value.length > maxPassLength || $confirmPassInput.value.length > maxPassLength;
            if (inputsEmpty || inputsMismatch || passTooLong) {
                $submitBtn.setAttribute('disabled', true); // always ensure we keep disabled when no match
                // only display error when we know the user has put values into both fields. Don't want to annoy the crap out of them too much.
                if (displayValidation) {
                    if (inputsMismatch) return $errorTarget.innerText = '<?=$VALIDATION_MESSAGES['mismatch']?>';
                    if (inputsEmpty) return $errorTarget.innerText = '<?=$VALIDATION_MESSAGES['empty']?>';
                    if (passTooLong) return $errorTarget.innerText = '<?=$VALIDATION_MESSAGES['maxLength']?>';
                }
                return false;
            }
            // passwords match – remove errors and allow submission
            $errorTarget.innerText = '';
            $submitBtn.removeAttribute('disabled');
            return true;
        };
        // event 🦻
        $passInputs.forEach($el => {
            $el.addEventListener('change', () => debounce(validate()));
            $el.addEventListener('keyup', () => {
                if (displayValidation) debounce(validate()); // Wait until displayValidation is swapped in a change event
            });
        });
    </script>
</body>
</html>

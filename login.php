<?php
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Helpers.php";

function fileRead($file) {
    $text = "";
    if (file_exists($file)) {
        $fp = fopen($file,"r");
        if (flock($fp, LOCK_EX)) {
            $text = fread($fp, filesize($file));
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
    return $text;
}
function fileAppend($file, $text) {
    $fp = fopen($file,"a");
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $text);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
function fileWrite($file, $text) {
    $fp = fopen($file,"w");
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $text);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

$var = parse_ini_file('state/var.ini');
$error = '';

$maxfails = 3;
$cooldown = 15*60;
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? "unknown";
@mkdir("/var/log/pwfail/", 0755);
$failfile = "/var/log/pwfail/{$remote_addr}";

if ($_SERVER['REQUEST_URI'] == '/logout') {
    // User Logout
    if (isset($_COOKIE[session_name()])) {
        session_start();
        unset($_SESSION['unraid_login']);
        unset($_SESSION['unraid_user']);
        // delete session file
        session_destroy();
        // delete the session cookie
        $params = session_get_cookie_params();
        setcookie(session_name(), '', 0, '/', $params['domain'], $params['secure'], isset($params['httponly']));
    }
    $error = 'Successfully logged out';
} else if (!empty($_POST['username']) && !empty($_POST['password'])) {

    $failtext = fileRead($failfile);
    $fails = explode("\n", trim($failtext));
    $time = time();

    // remove entries older than $cooldown minutes
    $updatefails = false;
    foreach ((array) $fails as $key => $value) {
        if ($value && $time - $value > $cooldown) {
            unset ($fails[$key]);
            $updatefails = true;
        }
    }
    if ($updatefails) {
        $failtext = implode("\n", $fails);
        fileWrite($failfile, $failtext);
    }

    if (count($fails) >= $maxfails) {
        $error = 'Too many invalid login attempts';
        if (count($fails) == $maxfails)
           exec("logger -t webGUI ".escapeshellarg("Ignoring login attempts for {$_POST['username']} from {$remote_addr}"));

    } else {

        // User Login attempt
        foreach (file('/etc/nginx/htpasswd') as $strCredentials) {
            list($user,$pwhash) = explode(':', trim($strCredentials));

            // Validate credentials
            if ($_POST['username'] == $user && password_verify($_POST['password'], $pwhash)) {
                // Successful login, start session
                @unlink($failfile);
                session_start();
                $_SESSION['unraid_login'] = time();
                $_SESSION['unraid_user'] = $_POST['username'];
                session_regenerate_id(true);
                session_write_close();
                exec("logger -t webGUI ".escapeshellarg("Successful login user {$_POST['username']} from {$remote_addr}"));
                header("Location: /".$var['START_PAGE']);
                exit;
            }
        }

        // Invalid login
        $error = 'Invalid Username or Password';
        exec("logger -t webGUI ".escapeshellarg("Unsuccessful login user {$_POST['username']} from {$remote_addr}"));

    }
    fileAppend($failfile, $time."\n");
}


$boot   = "/boot/config/plugins/dynamix";
$myfile = "case-model.cfg";
$mycase = file_exists("$boot/$myfile") ? file_get_contents("$boot/$myfile") : false;

extract(parse_plugin_cfg('dynamix',true));
$theme_dark = in_array($display['theme'],['black','gray']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
    <meta name="referrer" content="same-origin">
    <title><?=$var['NAME']?>/Login</title>
    <style>
    /************************
    /
    /  Fonts
    /
    /************************/
    @font-face {
        font-family: 'ClearSans';
        src: url('/webGui/styles/clear-sans-bold-italic.eot');
        src: url('/webGui/styles/clear-sans-bold-italic.eot?#iefix') format('embedded-opentype'),
             url('/webGui/styles/clear-sans-bold-italic.woff') format('woff'),
             url('/webGui/styles/clear-sans-bold-italic.ttf') format('truetype'),
             url('/webGui/styles/clear-sans-bold-italic.svg#clear_sansbold_italic') format('svg');
        font-weight: bold;
        font-style: italic;
    }
    @font-face {
        font-family: 'ClearSans';
        src: url('/webGui/styles/clear-sans-bold.eot');
        src: url('/webGui/styles/clear-sans-bold.eot?#iefix') format('embedded-opentype'),
             url('/webGui/styles/clear-sans-bold.woff') format('woff'),
             url('/webGui/styles/clear-sans-bold.ttf') format('truetype'),
             url('/webGui/styles/clear-sans-bold.svg#clear_sansbold') format('svg');
        font-weight: bold;
        font-style: normal;
    }
    @font-face {
        font-family: 'ClearSans';
        src: url('/webGui/styles/clear-sans-italic.eot');
        src: url('/webGui/styles/clear-sans-italic.eot?#iefix') format('embedded-opentype'),
             url('/webGui/styles/clear-sans-italic.woff') format('woff'),
             url('/webGui/styles/clear-sans-italic.ttf') format('truetype'),
             url('/webGui/styles/clear-sans-italic.svg#clear_sansitalic') format('svg');
        font-weight: normal;
        font-style: italic;
    }
    @font-face {
        font-family: 'ClearSans';
        src: url('/webGui/styles/clear-sans.eot');
        src: url('/webGui/styles/clear-sans.eot?#iefix') format('embedded-opentype'),
             url('/webGui/styles/clear-sans.woff') format('woff'),
             url('/webGui/styles/clear-sans.ttf') format('truetype'),
             url('/webGui/styles/clear-sans.svg#clear_sansregular') format('svg');
        font-weight: normal;
        font-style: normal;
    }

    /************************
    /
    /  General styling
    /
    /************************/
    body {
        background: #<?=$theme_dark?'1C1B1B':'F2F2F2'?>;
        color: #<?=$theme_dark?'fff':'1c1b1b'?>;
        font-family: ClearSans, sans-serif;
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
        font-size: 1.8em;
        margin: 0;
    }
    h2 {
        font-size: 0.8em;
        margin-top: 0;
        margin-bottom: 1.8em;
    }
    .button {
        color: #ff8c2f;
        font-family: ClearSans, sans-serif;
        background: -webkit-gradient(linear,left top,right top,from(#e03237),to(#fd8c3c)) 0 0 no-repeat,-webkit-gradient(linear,left top,right top,from(#e03237),to(#fd8c3c)) 0 100% no-repeat,-webkit-gradient(linear,left bottom,left top,from(#e03237),to(#e03237)) 0 100% no-repeat,-webkit-gradient(linear,left bottom,left top,from(#fd8c3c),to(#fd8c3c)) 100% 100% no-repeat;
        background: linear-gradient(90deg,#e03237 0,#fd8c3c) 0 0 no-repeat,linear-gradient(90deg,#e03237 0,#fd8c3c) 0 100% no-repeat,linear-gradient(0deg,#e03237 0,#e03237) 0 100% no-repeat,linear-gradient(0deg,#fd8c3c 0,#fd8c3c) 100% 100% no-repeat;
        background-size: 100% 2px,100% 2px,2px 100%,2px 100%;
    }
    .button:hover {
        color: #fff;
        background-color: #f15a2c;
        background: -webkit-gradient(linear,left top,right top,from(#e22828),to(#ff8c2f));
        background: linear-gradient(90deg,#e22828 0,#ff8c2f);
        -webkit-box-shadow: 0;
        box-shadow: 0;
        cursor: pointer;
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
    [type=email], [type=number], [type=password], [type=search], [type=tel], [type=text], [type=url], textarea {
        font-family: ClearSans, sans-serif;
        font-size: .875rem;
        background-color: #F2F2F2;
        width: 100%;
        margin-bottom: 1rem;
        border: 2px solid #ccc;
        padding: .75rem 1rem;
        -webkit-box-sizing: border-box;
        box-sizing: border-box;
        border-radius: 0;
        -webkit-appearance: none;
    }
    [type=email]:active, [type=email]:focus, [type=number]:active, [type=number]:focus, [type=password]:active, [type=password]:focus, [type=search]:active, [type=search]:focus, [type=tel]:active, [type=tel]:focus, [type=text]:active, [type=text]:focus, [type=url]:active, [type=url]:focus, textarea:active, textarea:focus {
        border-color: #ff8c2f;
        outline: none;
    }

    /************************
    /
    /  Login spesific styling
    /
    /************************/
    #login {
        width: 500px;
        margin: 6rem auto;
        border-radius: 10px;
        background: #<?=$theme_dark?'2B2A29':'fff'?>;
    }
    #login .logo {
        position: relative;
        overflow: hidden;
        height: 120px;
        border-radius: 10px 10px 0 0;
    }
    #login .wordmark {
        z-index: 1;
        position: relative;
        padding: 2rem;
    }
    #login .wordmark svg {
        width: 100px;
    }
    #login .case {
        float: right;
        width: 30%;
        font-size: 6rem;
        text-align: center;
    }
    #login .case img {
        max-width: 96px;
        max-height: 96px;
    }
    #login .error {
        color: red;
        margin-top: -20px;
    }
    #login .content {
        padding: 2rem;
    }
    #login .form {
        width: 65%;
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
    .shadow {
        -webkit-box-shadow: 0 2px 8px 0 rgba(0,0,0,.12);
        box-shadow: 0 2px 8px 0 rgba(0,0,0,.12);
    }

    /************************
    /
    /  Cases
    /
    /************************/
    [class^="case-"], [class*=" case-"] {
        /* use !important to prevent issues with browser extensions that change fonts */
        font-family: 'cases' !important;
        speak: none;
        font-style: normal;
        font-weight: normal;
        font-variant: normal;
        text-transform: none;
        line-height: 1;

        /* Better Font Rendering =========== */
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /************************
    /
    /  Media queries for mobile responsive
    /
    /************************/
    @media (max-width: 500px) {
        body {
            background: #<?=$theme_dark?'2B2A29':'fff'?>;
        }
        [type=email], [type=number], [type=password], [type=search], [type=tel], [type=text], [type=url], textarea {
            font-size: 16px; /* This prevents the mobile browser from zooming in on the input-field. */
        }
        #login {
            margin: 0;
            border-radius: 0;
            width: 100%;
        }
        #login .logo {
            border-radius: 0;
        }
        .shadow {
            box-shadow: none;
        }
    }
    </style>
    <link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-cases.css")?>">
    <link type="image/png" rel="shortcut icon" href="/webGui/images/<?=$var['mdColor']?>.png">
</head>

<body>
    <section id="login" class="shadow">
        <div class="logo angle">
            <div class="wordmark"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 222.4 39" class="Nav__logo--white"><path fill="#ffffff" d="M146.70000000000002 29.5H135l-3 9h-6.5L138.9 0h8l13.4 38.5h-7.1L142.6 6.9l-5.8 16.9h8.2l1.7 5.7zM29.7 0v25.4c0 8.9-5.8 13.6-14.9 13.6C5.8 39 0 34.3 0 25.4V0h6.5v25.4c0 5.2 3.2 7.9 8.2 7.9 5.2 0 8.4-2.7 8.4-7.9V0h6.6zM50.9 12v26.5h-6.5V0h6.1l17 26.5V0H74v38.5h-6.1L50.9 12zM171.3 0h6.5v38.5h-6.5V0zM222.4 24.7c0 9-5.9 13.8-15.2 13.8h-14.5V0h14.6c9.2 0 15.1 4.8 15.1 13.8v10.9zm-6.6-10.9c0-5.3-3.3-8.1-8.5-8.1h-8.1v27.1h8c5.3 0 8.6-2.8 8.6-8.1V13.8zM108.3 23.9c4.3-1.6 6.9-5.3 6.9-11.5 0-8.7-5.1-12.4-12.8-12.4H88.8v38.5h6.5V5.7h6.9c3.8 0 6.2 1.8 6.2 6.7s-2.4 6.8-6.2 6.8h-3.4l9.2 19.4h7.5l-7.2-14.7z"></path></svg></div>
        </div>
        <div class="content">
            <h1>
                <?=$var['NAME']?>
            </h1>
            <h2>
               	<?=$var['COMMENT']?>
            </h2>

            <div class="case">
            <?if ($mycase):?>
                <?if (substr($mycase,-4)!='.png'):?>
                <span class='case-<?=$mycase?>'></span>
                <?else:?>
                <img src='<?=autov("/webGui/images/$mycase")?>'>
                <?endif;?>
            <?endif;?>
            </div>

            <div class="form">

                <form action="/login" method="POST">
                    <p>
                        <input name="username" type="text" placeholder="Username" autocapitalize="none" autocomplete="off" autofocus required>
                        <input name="password" type="password" placeholder="Password" required>
                    </p>
                    <? if ($error) echo '<p class="error">'.$error.'</p>'; ?>
                    <script type="text/javascript">
                        document.cookie = "cookietest=1";
                        cookieEnabled = document.cookie.indexOf("cookietest=")!=-1;
                        document.cookie = "cookietest=1; expires=Thu, 01-Jan-1970 00:00:01 GMT";
                        if (!cookieEnabled) {
                            document.write('<p class="error">Browser cookie support required for login</p>');
                        }
                    </script>
                    <p>
                        <button type="submit" class="button button--small">Login</button>
                    </p>
                </form>
            </div>

            <p><a href="https://wiki.unraid.net/Unraid_6/Troubleshooting#Lost_root_Password" target="_blank">Password recovery</a></p>
        </div>
    </section>
</body>
</html>

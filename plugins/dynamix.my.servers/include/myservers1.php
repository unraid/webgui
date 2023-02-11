<!-- myservers1 -->
<style>
#header {
  z-index: 102 !important;
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  -webkit-box-pack: justify;
  -ms-flex-pack: justify;
  justify-content: space-between;
  -webkit-box-align: center;
  -ms-flex-align: center;
  align-items: center;
}
vue-userprofile,
unraid-user-profile {
  font-size: 16px;
  margin-left: auto;
  height: 100%;
}

unraid-launchpad,
unraid-promo {
  position: relative;
  z-index: 10001;
}
</style>
<?
$myservers_flash_cfg_path='/boot/config/plugins/dynamix.my.servers/myservers.cfg';
$myservers = file_exists($myservers_flash_cfg_path) ? @parse_ini_file($myservers_flash_cfg_path,true) : [];
$ALLOWED_UPC_ENV_VALS = [
  'production',
  'staging',
  'stagingLogs',
  'development',
  'local',
];
$UPC_ENV_CK = in_array($_COOKIE['UPC_ENV']??'', $ALLOWED_UPC_ENV_VALS)
  ? $_COOKIE['UPC_ENV']
  : null;
// Determine what source we should use for web components
if (!file_exists('/usr/local/sbin/unraid-api')) { // When NOT using the plugin we should load the UPC from the file system unless $UPC_ENV_CK exists.
  $UPC_ENV = $UPC_ENV_CK ?? 'local';
} else { // When PLG exists load from local when not signed in but when signed in load UPC from production.
  $UPC_ENV = $UPC_ENV_CK ?? ((empty($myservers['remote']['username']) || empty($var['regFILE'])) ? 'local' : 'production');
}
$upcLocalSrc = autov('/plugins/dynamix.my.servers/webComps/unraid.min.js', true);
switch ($UPC_ENV) {
  case 'production':
    $upcSrc = 'https://registration.unraid.net/webComps/unraid.min.js';
    break;
  case 'staging':
    $upcSrc = 'https://registration-dev.unraid.net/webComps/unraid.min.js';
    break;
  case 'stagingLogs':
    $upcSrc = 'https://registration-dev-logs.unraid.net/webComps/unraid.min.js';
    break;
  case 'development':
    $upcSrc = 'https://launchpad.unraid.test:6969/webComps/unraid.js?t=' . time();
    break;
  default: // load from webGUI filesystem.
    $upcSrc = $upcLocalSrc;
    break;
}
// add the intended web component source to the DOM
echo '<script id="unraid-wc" defer src="' . $upcSrc . '"></script>';
?>
<script type="text/javascript">
const upcEnvCookie = "<?=$UPC_ENV_CK??''?>";
if (upcEnvCookie) console.debug('[UPC_ENV] ✨', upcEnvCookie);
// If the UPC isn't defined after 2secs inject UPC via
setTimeout(() => {
  if (!window.customElements.get('unraid-user-profile')) {
    console.log('[UPC] Fallback to filesystem src 😖');
    const el = document.createElement('script');
    el.type = 'text/javascript';
    el.src = '<?=$upcLocalSrc?>';
    document.head.appendChild(el);
    return upcEnv('local', false, true); // set session cookie to prevent delayed loads of UPC
  }
  return false;
}, 2000);
function upcEnv(str, reload = true, session = false) { // overwrite upc src
  const ckName = 'UPC_ENV';
  const ckDate = new Date();
  const ckDays = 30;
  ckDate.setTime(ckDate.getTime()+(ckDays*24*60*60*1000));
  const ckExpire = `expires=${session ? 0 : ckDate.toGMTString()};`;
  if (!str) { // if no str param provided we remove the cookie to fallback to the enviroment's default JS source
    console.log(`✨ ${ckName} removed…reloading ♻️ `);
    document.cookie = `${ckName}=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT`;
    return window.location.reload();
  }
  if (reload) {
    console.log(`✨ ${ckName} set…reloading ✨ `);
    setTimeout(() => {
      window.location.reload();
    }, 2000);
  } else {
    console.log(`✨ ${ckName}=${str} for session ✨ `);
  }
  return document.cookie = `${ckName}=${str}; path=/; ${ckExpire}`;
};
</script>
<!-- /myservers1 -->

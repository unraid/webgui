<?
###################################################
#                                                 #
# GUI Search copyright 2021-2023, Andrew Zawadzki #
#           Licenced under GPLv2                  #
#                                                 #
###################################################
@mkdir("/tmp/gui.search");

extract(@parse_ini_file("/boot/config/plugins/dynamix/dynamix.cfg") ?: []);
$locale = $locale ?? "";
if ( $locale == "en_US")
	$locale = "";

if ( $locale != @file_get_contents("/tmp/gui.search/locale") ) {
	@unlink("/tmp/gui.search/searchResults.json");
}

file_put_contents("/tmp/gui.search/locale",$locale);

$uri = "";
if ( $locale ) {
	if ( is_dir("/usr/local/emhttp/languages/$locale") ) {
		$dotFiles = glob("/usr/local/emhttp/languages/$locale/*.txt");
		foreach ($dotFiles as $dot) {
			$uri .= basename($dot,".txt")."/";
		}
		$uri = rtrim($uri,"/");
	}
}

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');

$_SERVER['REQUEST_URI'] = $uri;
$_SESSION['locale'] = $locale;
require_once "$docroot/plugins/dynamix/include/Translations.php";

$searchPages = array();
$pageFiles = glob("/usr/local/emhttp/plugins/*/*.page");
if ( is_file("/tmp/gui.search/searchResults.json") ) {
	$searchPages = unserialize(file_get_contents("/tmp/gui.search/searchResults.json"));
}
if ( ! $searchPages ) {
	$MainPages = array("About","WebGui","UNRAID-OS","SystemInformation","OtherSettings","Settings","NetworkServices","Utilities","DiskUtilities","UserPreferences","WGX");
	// pass 1 - get all the "main" pages
	foreach ($pageFiles as $page) {
		if ( $page == "/usr/local/emhttp/plugins/dynamix/WGX.page") continue;
		$file = explode("---",file_get_contents($page));
		$pageInfo = parse_ini_string($file[0],true);

		if ( isset($pageInfo['Menu']) && $pageInfo['Menu'] && in_array(explode(":",$pageInfo['Menu'])[0],$MainPages) && ! in_array(basename($page,".page"),$MainPages) ) {
			$newPage[basename($page,".page")] = array(sanitizeQuote(_($pageInfo['Title'])),basename($page));
			if ( $locale )
				$newPage[basename($page,".page")] = array(sanitizeQuote($pageInfo['Title']),basename($page));
		}
	}

	//pass 2 - link back any sub-pages
	foreach ($pageFiles as $page) {
		$file = explode("---",file_get_contents($page));
		if ( ! isset($file[1]) ) continue;
		$pageInfo = parse_ini_string($file[0],true);

		if (isset($pageInfo['Menu']) && $pageInfo['Menu']) {
			$pageLinked = explode(":",$pageInfo['Menu'])[0];
			if (isset($newPage[$pageLinked])  && ! in_array($pageLinked,$MainPages) ) {
				$newPage[] = array(sanitizeQuote(_($pageInfo['Title']))." ("._($newPage[$pageLinked][0]).")",$pageLinked);
				if ( $locale )
					$newPage[] = array(sanitizeQuote(($pageInfo['Title']))." (".($newPage[$pageLinked][0]).")",$pageLinked);

			}
			getSettings();
		}
	}
	//pass 3 - cleanup
	foreach ($newPage as $page) {
		if ( ! in_array(array("label"=>sanitizeQuote(_($page[0])),"value"=>basename($page[1],".page")),$searchPages) )
			$searchPages[] = array("label"=>sanitizeQuote(_($page[0])),"value"=>basename($page[1],".page"));
	}
	file_put_contents("/tmp/gui.search/searchResults.json",serialize($searchPages));
}
echo json_encode($searchPages);

function getSettings() {
	global $searchPages, $pageInfo, $page,$file,$locale;

	$bannedPages = array("ShareEdit","UserEdit","Device","community.applications","Selftest","DeviceInfo","EthX","CA_Notices","SecuritySMB","SecurityNFS");
	if (in_array(basename($page,".page"),$bannedPages) ) return;
	foreach (explode("\n",$file[1]) as $line) {
		$line = trim($line);
		if ( startsWith($line,"_(") && (endsWith($line,")_:") || endsWith($line,")_):") ) ) {
			preg_match("/<!--search:.*-->/i",$line,$extra,PREG_OFFSET_CAPTURE);
			$string = str_replace(["_(",")_:",")_?",")_"],["","","",""],$line);

			$extraEng = "";
			$extraTra = "";
			if ( $extra ) {
				$extrasearch = trim(str_replace(["<!--search:","-->"],["",""],$extra[0][0]));
				$string = str_replace($extra[0][0],"",$string);
				foreach ( explode("|",$extrasearch) as $term ) {
					$extraEng .= $term."|";
					$extraTra .= _($term)."|";
				}
				$extraEng = " [".rtrim($extraEng,"|")."]";
				$extraTra = " [".rtrim($extraTra,"|")."]";
			}
			$string = sanitizeQuote($string);

			$linkPage = basename($page,".page");
			if (strpos($linkPage,"WG") === 0) {
				$linkPage = "VPNmanager";
			}
			if ( stripos(str_replace(" ","",$line),"<!--donotindex-->") )
				continue;
			if ( ! in_array(array("label"=>"$string $extraTra (".sanitizeQuote($pageInfo['Title']).")","value"=>"$linkPage**"._($string)),$searchPages) ) {
				$searchPages[] = array("label"=>_($string)." $extraTra (".sanitizeQuote(_($pageInfo['Title'])).")","value"=>"$linkPage**"._($string));
				if ( $locale ) {
					if ( _($string) !== $string )
						$searchPages[] = array("label"=>($string)." $extraEng (".sanitizeQuote($pageInfo['Title']).")","value"=>"$linkPage**"._($string));
				}
			}
		}
	}
}

function sanitizeQuote($string) {
	return str_replace("'","",str_replace('"',"",$string));
}

##############################################
# Determine if $haystack begins with $needle #
##############################################
function startsWith($haystack, $needle) {
	if ( !is_string($haystack) || ! is_string($needle) ) return false;
	return $needle === "" || strripos($haystack, $needle, -strlen($haystack)) !== FALSE;
}
#############################################
# Determine if $string ends with $endstring #
#############################################
function endsWith($string, $endString) {
	$len = strlen($endString);
	if ($len == 0) {
		return true;
	}
	return (substr($string, -$len) === $endString);
}
?>

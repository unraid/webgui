<?PHP
/* Copyright 2005-2021, Lime Technology
 * Copyright 2015-2021, Derek Macias, Eric Schultz, Jon Panozzo.
 * Copyright 2012-2021, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
/**
 * Array2XML: A class to convert array in PHP to XML
 * It also takes into account attributes names unlike SimpleXML in PHP
 * It returns the XML in form of DOMDocument class for further manipulation.
 * It throws exception if the tag name or attribute name has illegal chars.
 *
 * Author : Lalit Patel
 * Website: http://www.lalit.org/lab/convert-php-array-to-xml-with-attributes
 * License: Apache License 2.0
 *          http://www.apache.org/licenses/LICENSE-2.0
 * Version: 0.1 (10 July 2011)
 * Version: 0.2 (16 August 2011)
 *          - replaced htmlentities() with htmlspecialchars() (Thanks to Liel Dulev)
 *          - fixed a edge case where root node has a false/null/0 value. (Thanks to Liel Dulev)
 * Version: 0.3 (22 August 2011)
 *          - fixed tag sanitize regex which didn't allow tagnames with single character.
 * Version: 0.4 (18 September 2011)
 *          - Added support for CDATA section using @cdata instead of @value.
 * Version: 0.5 (07 December 2011)
 *          - Changed logic to check numeric array indices not starting from 0.
 * Version: 0.6 (04 March 2012)
 *          - Code now doesn't @cdata to be placed in an empty array
 * Version: 0.7 (24 March 2012)
 *          - Reverted to version 0.5
 * Version: 0.8 (02 May 2012)
 *          - Removed htmlspecialchars() before adding to text node or attributes.
 *
 * Usage:
 *       $xml = Array2XML::createXML('root_node_name', $php_array);
 *       echo $xml->saveXML();
 */
class Array2XML {
	private static $xml = null;
private static $encoding = 'UTF-8';
	/**
	 * Initialize the root XML node [optional]
	 * @param $version
	 * @param $encoding
	 * @param $format_output
	 */
	public static function init($version = '1.0', $encoding = 'UTF-8', $format_output = true) {
			self::$xml = new DomDocument($version, $encoding);
			self::$xml->formatOutput = $format_output;
	self::$encoding = $encoding;
	}
	/**
	 * Convert an Array to XML
	 * @param string $node_name - name of the root node to be converted
	 * @param array $arr - aray to be converterd
	 * @return DomDocument
	 */
	public static function &createXML($node_name, $arr=array()) {
			$xml = self::getXMLRoot();
			$xml->appendChild(self::convert($node_name, $arr));
			self::$xml = null;    // clear the xml node in the class for 2nd time use.
			return $xml;
	}
	/**
	 * Convert an Array to XML
	 * @param string $node_name - name of the root node to be converted
	 * @param array $arr - aray to be converterd
	 * @return DOMNode
	 */
	private static function &convert($node_name, $arr=array()) {
			//print_arr($node_name);
			$xml = self::getXMLRoot();
			$node = $xml->createElement($node_name);
			if(is_array($arr)){
					// get the attributes first.;
					if(isset($arr['@attributes'])) {
							foreach($arr['@attributes'] as $key => $value) {
									if(!self::isValidTagName($key)) {
											throw new Exception('[Array2XML] Illegal character in attribute name. attribute: '.$key.' in node: '.$node_name);
									}
									$node->setAttribute($key, self::bool2str($value));
							}
							unset($arr['@attributes']); //remove the key from the array once done.
					}
					// check if it has a value stored in @value, if yes store the value and return
					// else check if its directly stored as string
					if(isset($arr['@value'])) {
							$node->appendChild($xml->createTextNode(self::bool2str($arr['@value'])));
							unset($arr['@value']);    //remove the key from the array once done.
							//return from recursion, as a note with value cannot have child nodes.
							return $node;
					} else if(isset($arr['@cdata'])) {
							$node->appendChild($xml->createCDATASection(self::bool2str($arr['@cdata'])));
							unset($arr['@cdata']);    //remove the key from the array once done.
							//return from recursion, as a note with cdata cannot have child nodes.
							return $node;
					}
			}
			//create subnodes using recursion
			if(is_array($arr)){
					// recurse to get the node for that key
					foreach($arr as $key=>$value){
							if(!self::isValidTagName($key)) {
									throw new Exception('[Array2XML] Illegal character in tag name. tag: '.$key.' in node: '.$node_name);
							}
							if(is_array($value) && is_numeric(key($value))) {
									// MORE THAN ONE NODE OF ITS KIND;
									// if the new array is numeric index, means it is array of nodes of the same kind
									// it should follow the parent key name
									foreach($value as $k=>$v){
											$node->appendChild(self::convert($key, $v));
									}
							} else {
									// ONLY ONE NODE OF ITS KIND
									$node->appendChild(self::convert($key, $value));
							}
							unset($arr[$key]); //remove the key from the array once done.
					}
			}
			// after we are done with all the keys in the array (if it is one)
			// we check if it has any text value, if yes, append it.
			if(!is_array($arr)) {
					$node->appendChild($xml->createTextNode(self::bool2str($arr ?? "")));
			}
			return $node;
	}
	/*
	 * Get the root XML node, if there isn't one, create it.
	 */
	private static function getXMLRoot(){
			if(empty(self::$xml)) {
					self::init();
			}
			return self::$xml;
	}
	/*
	 * Get string representation of boolean value
	 */
	private static function bool2str($v){
			//convert boolean to text value.
			$v = $v === true ? 'true' : $v;
			$v = $v === false ? 'false' : $v;
			return $v;
	}
	/*
	 * Check if the tag name or attribute name contains illegal characters
	 * Ref: http://www.w3.org/TR/xml/#sec-common-syn
	 */
	private static function isValidTagName($tag){
			$pattern = '/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i';
			return preg_match($pattern, $tag, $matches) && $matches[0] == $tag;
	}
}


	$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
	require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt.php";

	// Load emhttp variables if needed.
	if (!isset($var)){
		$var = @parse_ini_file("$docroot/state/var.ini");
		$disks = @parse_ini_file("$docroot/state/disks.ini", true);
		extract(parse_plugin_cfg("dynamix",true));
	}
	$ethX = 'eth0';
	if (!isset($$ethX) && is_file("$docroot/state/network.ini")) {
		extract(parse_ini_file("$docroot/state/network.ini",true));
	}

	// Check if program is running and
	$libvirt_running = trim(shell_exec( "[ -f /proc/`cat /var/run/libvirt/libvirtd.pid 2> /dev/null`/exe ] && echo 'yes' || echo 'no' 2> /dev/null" ));

	$arrAllTemplates = [
		' Windows ' => '', /* Windows Header */

		'Windows 11' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows11.png',
			'os' => 'windowstpm',
			'overrides' => [
				'domain' => [
					'ovmf' => 2,
					'mem' => 4096 * 1024,
					'maxmem' => 4096 * 1024
				],
				'disk' => [
					[
						'size' => '64G'
					]
				]
			]
		],

		'Windows 10' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows.png',
			'os' => 'windows10',
			'overrides' => [
				'domain' => [
					'mem' => 2048 * 1024,
					'maxmem' => 2048 * 1024
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows 8.x' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows.png',
			'os' => 'windows',
			'overrides' => [
				'domain' => [
					'name' => 'Windows 8.1',
					'mem' => 2048 * 1024,
					'maxmem' => 2048 * 1024
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows 7' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows7.png',
			'os' => 'windows7',
			'overrides' => [
				'domain' => [
					'mem' => 2048 * 1024,
					'maxmem' => 2048 * 1024,
					'ovmf' => 0,
					'usbmode' => 'usb2'
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows XP' => [
			'form' => 'Custom.form.php',
			'icon' => 'windowsxp.png',
			'os' => 'windowsxp',
			'overrides' => [
				'domain' => [
					'ovmf' => 0,
					'usbmode' => 'usb2'
				],
				'disk' => [
					[
						'size' => '15G'
					]
				]
			]
		],

		'Windows Server 2016' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows.png',
			'os' => 'windows2016',
			'overrides' => [
				'domain' => [
					'mem' => 2048 * 1024,
					'maxmem' => 2048 * 1024
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows Server 2012' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows.png',
			'os' => 'windows2012',
			'overrides' => [
				'domain' => [
					'mem' => 2048 * 1024,
					'maxmem' => 2048 * 1024
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows Server 2008' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows7.png',
			'os' => 'windows2008',
			'overrides' => [
				'domain' => [
					'usbmode' => 'usb2'
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows Server 2003' => [
			'form' => 'Custom.form.php',
			'icon' => 'windowsxp.png',
			'os' => 'windows2003',
			'overrides' => [
				'domain' => [
					'usbmode' => 'usb2'
				],
				'disk' => [
					[
						'size' => '15G'
					]
				]
			]
		],

		' Pre-packaged ' => '', /* Pre-built Header */

		'LibreELEC' => [
			'form' => 'LibreELEC.form.php',
			'icon' => 'libreelec.png'
		],

		'OpenELEC' => [
			'form' => 'OpenELEC.form.php',
			'icon' => 'openelec.png'
		],

		' Linux ' => '', /* Linux Header */

		'Linux' => [
			'form' => 'Custom.form.php',
			'icon' => 'linux.png',
			'os' => 'linux'
		],
		'Arch' => [
			'form' => 'Custom.form.php',
			'icon' => 'arch.png',
			'os' => 'arch'
		],
		'CentOS' => [
			'form' => 'Custom.form.php',
			'icon' => 'centos.png',
			'os' => 'centos'
		],
		'ChromeOS' => [
			'form' => 'Custom.form.php',
			'icon' => 'chromeos.png',
			'os' => 'chromeos'
		],
		'CoreOS' => [
			'form' => 'Custom.form.php',
			'icon' => 'coreos.png',
			'os' => 'coreos'
		],
		'Debian' => [
			'form' => 'Custom.form.php',
			'icon' => 'debian.png',
			'os' => 'debian'
		],
		'Fedora' => [
			'form' => 'Custom.form.php',
			'icon' => 'fedora.png',
			'os' => 'fedora'
		],
		'FreeBSD' => [
			'form' => 'Custom.form.php',
			'icon' => 'freebsd.png',
			'os' => 'freebsd'
		],
		'OpenSUSE' => [
			'form' => 'Custom.form.php',
			'icon' => 'opensuse.png',
			'os' => 'opensuse'
		],
		'RedHat' => [
			'form' => 'Custom.form.php',
			'icon' => 'redhat.png',
			'os' => 'redhat'
		],
		'Scientific' => [
			'form' => 'Custom.form.php',
			'icon' => 'scientific.png',
			'os' => 'scientific'
		],
		'Slackware' => [
			'form' => 'Custom.form.php',
			'icon' => 'slackware.png',
			'os' => 'slackware'
		],
		'SteamOS' => [
			'form' => 'Custom.form.php',
			'icon' => 'steamos.png',
			'os' => 'steamos'
		],
		'Ubuntu' => [
			'form' => 'Custom.form.php',
			'icon' => 'ubuntu.png',
			'os' => 'ubuntu'
		],
		'VyOS' => [
			'form' => 'Custom.form.php',
			'icon' => 'vyos.png',
			'os' => 'vyos'
		],

		' ' => '', /* Custom / XML Expert Header */

		'Custom' => [
			'form' => 'XML_Expert.form.php',
			'icon' => 'default.png'
		]
	];

	$arrOpenELECVersions = [
		'6.0.3_1' => [
			'name' => '6.0.3',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/OpenELEC/OpenELEC-unRAID.x86_64-6.0.3_1.tar.xz',
			'size' => 178909136,
			'md5' => 'c584312831d7cd93a40e61ac9f186d32',
			'localpath' => '',
			'valid' => '0'
		],
		'6.0.0_1' => [
			'name' => '6.0.0',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/OpenELEC/OpenELEC-unRAID.x86_64-6.0.0_1.tar.xz',
			'size' => 165658636,
			'md5' => '66fb6c3f1b6db49c291753fb3ec7c15c',
			'localpath' => '',
			'valid' => '0'
		],
		'5.95.3_1' => [
			'name' => '5.95.3 (6.0.0 Beta3)',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/OpenELEC/OpenELEC-unRAID.x86_64-5.95.3_1.tar.xz',
			'size' => 153990180,
			'md5' => '8936cda74c28ddcaa165cc49ff2a477a',
			'localpath' => '',
			'valid' => '0'
		],
		'5.95.2_1' => [
			'name' => '5.95.2 (6.0.0 Beta2)',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/OpenELEC/OpenELEC-unRAID.x86_64-5.95.2_1.tar.xz',
			'size' => 156250392,
			'md5' => 'ac70048eecbda4772e386c6f271cb5e9',
			'localpath' => '',
			'valid' => '0'
		]
	];

	$arrLibreELECVersions = [
		'7.0.1_1' => [
			'name' => '7.0.1',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/LibreELEC/LibreELEC-unRAID.x86_64-7.0.1_1.tar.xz',
			'size' => 209748564,
			'md5' => 'c1e8def2ffb26a355e7cc598311697f6',
			'localpath' => '',
			'valid' => '0'
		]
	];

	$fedora = '/var/tmp/fedora-virtio-isos';
	// set variable to obtained information
	if (file_exists($fedora)) $virtio_isos = unserialize(file_get_contents($fedora)); else {
	// else initialize variable
	$virtio_isos = [
		'virtio-win-0.1.208-1' => [
			'name' => 'virtio-win-0.1.208-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.208-1/virtio-win-0.1.208.iso',
			'size' => 556431360,
			'md5' => '3bbc69bdcf1d46f4ee0ddaf35c2656f3'
		],
		'virtio-win-0.1.190-1' => [
			'name' => 'virtio-win-0.1.190-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.190-1/virtio-win-0.1.190.iso',
			'size' => 501745664,
			'md5' => '6e30288fa45ba99a1434740204b8e8e8'
		],
		'virtio-win-0.1.189-1' => [
			'name' => 'virtio-win-0.1.189-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.189-1/virtio-win-0.1.189.iso',
			'size' => 500496384,
			'md5' => '86c924cf591c275de81f0e64eefe69a3'
		],
		'virtio-win-0.1.173-2' => [
			'name' => 'virtio-win-0.1.173-2.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.173-2/virtio-win-0.1.173.iso',
			'size' => 394303488,
			'md5' => '88fcd398b7d54301b559d1762240aa67'
		],
		'virtio-win-0.1.160-1' => [
			'name' => 'virtio-win-0.1.160-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.160-1/virtio-win-0.1.160.iso',
			'size' => 322842624,
			'md5' => 'eec0b91dd72fb2b42774d5d0b39175c7'
		],
		'virtio-win-0.1.141-1' => [
			'name' => 'virtio-win-0.1.141-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.141-1/virtio-win-0.1.141.iso',
			'size' => 316628992,
			'md5' => '6327d722bdea72bcb1849ce99604bbe0'
		],
		'virtio-win-0.1.126-2' => [
			'name' => 'virtio-win-0.1.126-2.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.126-2/virtio-win-0.1.126.iso',
			'size' => 155856896,
			'md5' => 'b8379138ae5f8d0adecb839f9debf875'
		],
		'virtio-win-0.1.126-1' => [
			'name' => 'virtio-win-0.1.126-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.126-1/virtio-win-0.1.126.iso',
			'size' => 155856896,
			'md5' => '85637076191887d4cd425bf8d59f8dd9'
		],
		'virtio-win-0.1.118-2' => [
			'name' => 'virtio-win-0.1.118-2.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.118-2/virtio-win-0.1.118.iso',
			'size' => 56967168,
			'md5' => '9cb51bde60decfafdf8119ce01b7c1cf'
		],
		'virtio-win-0.1.118-1' => [
			'name' => 'virtio-win-0.1.118-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.118-1/virtio-win-0.1.118.iso',
			'size' => 56967168,
			'md5' => 'cc5771f2f0ea5097946d3d447f21cce8'
		],
		'virtio-win-0.1.117-1' => [
			'name' => 'virtio-win-0.1.117-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.117-1/virtio-win-0.1.117.iso',
			'size' => 56999936,
			'md5' => '2a79d6036ea4292f81c3370dd0a8b6d6'
		],
		'virtio-win-0.1.113-1' => [
			'name' => 'virtio-win-0.1.113-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.113-1/virtio-win-0.1.113.iso',
			'size' => 56936448,
			'md5' => '11ed773055e19eca75ed186ff12d354c'
		],
		'virtio-win-0.1.112-1' => [
			'name' => 'virtio-win-0.1.112-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.112-1/virtio-win-0.1.112.iso',
			'size' => 56926208,
			'md5' => '7db0211d7aec3e08fadd21c8eaaf35db'
		],
		'virtio-win-0.1.110-2' => [
			'name' => 'virtio-win-0.1.110-2.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.110-2/virtio-win-0.1.110.iso',
			'size' => 56586240,
			'md5' => '93357a5105f1255591f1c389748288a9'
		],
		'virtio-win-0.1.110-1' => [
			'name' => 'virtio-win-0.1.110-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.110-1/virtio-win-0.1.110.iso',
			'size' => 56586240,
			'md5' => '239e0eb442bb63c177deb4af39397731'
		],
		'virtio-win-0.1.109-2' => [
			'name' => 'virtio-win-0.1.109-2.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.109-2/virtio-win-0.1.109.iso',
			'size' => 56606720,
			'md5' => '2a9f78f648f03fe72decdadb38837db3'
		],
		'virtio-win-0.1.109-1' => [
			'name' => 'virtio-win-0.1.109-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.109-1/virtio-win-0.1.109.iso',
			'size' => 56606720,
			'md5' => '1b0da008d0ec79a6223d21be2fcce2ee'
		],
		'virtio-win-0.1.108-1' => [
			'name' => 'virtio-win-0.1.108-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.108-1/virtio-win-0.1.108.iso',
			'size' => 56598528,
			'md5' => '46deb991f8c382f2d9af0fb786792990'
		],
		'virtio-win-0.1.106-1' => [
			'name' => 'virtio-win-0.1.106-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.106-1/virtio-win-0.1.106.iso',
			'size' => 56586240,
			'md5' => '66228ea20fae1a28d7a1583b9a5a1b8b'
		],
		'virtio-win-0.1.105-1' => [
			'name' => 'virtio-win-0.1.105-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.105-1/virtio-win-0.1.105.iso',
			'size' => 56584192,
			'md5' => 'c3194fa62a4a1ccbecfe784a52feda66'
		],
		'virtio-win-0.1.104-1' => [
			'name' => 'virtio-win-0.1.104-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.104-1/virtio-win-0.1.104.iso',
			'size' => 56584192,
			'md5' => '9aa28b6f5b18770d796194aaaeeea31a'
		],
		'virtio-win-0.1.103-2' => [
			'name' => 'virtio-win-0.1.103-2.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.103-2/virtio-win-0.1.103.iso',
			'size' => 56340480,
			'md5' => '07c4356880f0b385d6908392e48d6e75'
		],
		'virtio-win-0.1.103' => [
			'name' => 'virtio-win-0.1.103.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.103/virtio-win-0.1.103.iso',
			'size' => 49903616,
			'md5' => 'd31069b620820b75730d2def7690c271'
		],
		'virtio-win-0.1.102' => [
			'name' => 'virtio-win-0.1.102.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.102/virtio-win-0.1.102.iso',
			'size' => 160755712,
			'md5' => '712561dd78ef532c54f8fee927c1ce2e'
		],
		'virtio-win-0.1.101' => [
			'name' => 'virtio-win-0.1.101.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.101/virtio-win-0.1.101.iso',
			'size' => 160755712,
			'md5' => 'cf73576efc03685907c1fa49180ea388'
		],
		'virtio-win-0.1.100' => [
			'name' => 'virtio-win-0.1.100.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.100/virtio-win-0.1.100.iso',
			'size' => 160704512,
			'md5' => '8b21136f988bef7981ee580e9101b6b4'
		],
		'virtio-win-0.1.96' => [
			'name' => 'virtio-win-0.1.96.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.96/virtio-win-0.1.96.iso',
			'size' => 160659456,
			'md5' => 'd406bf6748b9ba4c872c5b5301ba7272'
		]
	];}

	// Read configuration file (guaranteed to exist)
	$domain_cfgfile = "/boot/config/domain.cfg";
	$domain_cfg = parse_ini_file($domain_cfgfile);

	if ($domain_cfg['DEBUG'] != "yes") {
		error_reporting(0);
	}

	if (empty($domain_cfg['VMSTORAGEMODE'])) {
		$domain_cfg['VMSTORAGEMODE'] = "auto";
	}
	if (!empty($domain_cfg['DOMAINDIR'])) {
		$domain_cfg['DOMAINDIR'] = rtrim($domain_cfg['DOMAINDIR'], '/') . '/';
	}
	if (!empty($domain_cfg['MEDIADIR'])) {
		$domain_cfg['MEDIADIR'] = rtrim($domain_cfg['MEDIADIR'], '/') . '/';
	}
	if (empty($domain_cfg['TIMEOUT'])) {
		$domain_cfg['TIMEOUT'] = 60;
	}

	$domain_bridge = (!($domain_cfg['BRNAME'])) ? 'virbr0' : $domain_cfg['BRNAME'];
	$msg = (empty($domain_bridge)) ? "Error: Setup Bridge in Settings/Network Settings" : false;
	$libvirt_service = isset($domain_cfg['SERVICE']) ? $domain_cfg['SERVICE'] : "disable";

	if ($libvirt_running == "yes"){
		$lv = new Libvirt('qemu:///system', null, null, false);
		$arrHostInfo = $lv->host_get_node_info();
		$maxcpu = (int)$arrHostInfo['cpus'];
		$maxmem = number_format(($arrHostInfo['memory'] / 1048576), 1, '.', ' ');
	}

	function mk_dropdown_options($arrOptions, $strSelected) {
		foreach ($arrOptions as $key => $label) {
			echo mk_option($strSelected, $key, $label);
		}
	}

	function appendOrdinalSuffix($number) {
		$ends = ['th','st','nd','rd','th','th','th','th','th','th'];

		if (($number % 100) >= 11 && ($number % 100) <= 13) {
			$abbreviation = $number . 'th';
		} else {
			$abbreviation = $number . $ends[$number % 10];
		}

		return $abbreviation;
	}

	function sanitizeVendor($strVendor) {
		// Specialized vendor name cleanup
		// e.g.: Advanced Micro Devices, Inc. [AMD/ATI] --> Advanced Micro Devices, Inc.
		if (preg_match('/(?P<gpuvendor>.+) \[.+\]/', $strVendor, $arrGPUMatch)) {
			$strVendor = $arrGPUMatch['gpuvendor'];
		}

		$strVendor = str_replace('Advanced Micro Devices', 'AMD', $strVendor);
		$strVendor = str_replace('Samsung Electronics Co.', 'Samsung', $strVendor);
		$strVendor = str_replace([' Corporation', ' Semiconductor ', ' Technology Group Ltd.', ' System, Inc.', ' Systems, Inc.'], '', $strVendor);
		$strVendor = str_replace([' Co., Ltd.', ', Ltd.', ', Ltd', ', Inc.'], '', $strVendor);
		return $strVendor;
	}

	function sanitizeProduct($strProduct) {
		$strProduct = str_replace(' PCI Express', ' PCIe', $strProduct);
		$strProduct = str_replace(' High Definition ', ' HD ', $strProduct);
		return $strProduct;
	}

	function getDiskImageInfo($strImgPath) {
		$arrJSON = json_decode(shell_exec("qemu-img info --output json " . escapeshellarg($strImgPath) . " 2>/dev/null"), true);
		return $arrJSON;
	}

	$cacheValidPCIDevices = null;
	function getValidPCIDevices() {
		global $cacheValidPCIDevices;
		global $disks;

		if (!is_null($cacheValidPCIDevices)) {
			return $cacheValidPCIDevices;
		}

		$strOSNetworkDevice = trim(exec("udevadm info -q path -p /sys/class/net/eth0 2>/dev/null | grep -Po '0000:\K\w{2}:\w{2}\.\w{1}'"));

		$arrOSDiskControllers = [];
		foreach ($disks as $strDisk => $arrDisk) {
			if (!empty($arrDisk['device']) && file_exists('/dev/'.$arrDisk['device'])) {
				$strOSDiskController = trim(exec("udevadm info -q path -n /dev/".$arrDisk['device']." | grep -Po '0000:\K\w{2}:\w{2}\.\w{1}'"));
				if (!empty($strOSDiskController)) {
					$arrOSDiskControllers[] = $strOSDiskController;
				}
			}
		}
		$arrOSDiskControllers = array_values(array_unique($arrOSDiskControllers));

		$arrBlacklistIDs = $arrOSDiskControllers;
		if (!empty($strOSNetworkDevice)) {
			$arrBlacklistIDs[] = $strOSNetworkDevice;
		}
		$arrBlacklistClassIDregex = '/^(05|06|08|0a|0b|0c05)/';
		// Got Class IDs at the bottom of /usr/share/hwdata/pci.ids
		$arrWhitelistGPUClassIDregex = '/^(0001|03)/';
		$arrWhitelistAudioClassIDregex = '/^(0403)/';

		$arrValidPCIDevices = [];

		exec("lspci -m -nn 2>/dev/null", $arrAllPCIDevices);

		foreach ($arrAllPCIDevices as $strPCIDevice) {
			// Example: 00:1f.0 "ISA bridge [0601]" "Intel Corporation [8086]" "Z77 Express Chipset LPC Controller [1e44]" -r04 "Micro-Star International Co., Ltd. [MSI] [1462]" "Device [7759]"
			if (preg_match('/^(?P<id>\S+) \"(?P<type>[^"]+) \[(?P<typeid>[a-f0-9]{4})\]\" \"(?P<vendorname>[^"]+) \[(?P<vendorid>[a-f0-9]{4})\]\" \"(?P<productname>[^"]+) \[(?P<productid>[a-f0-9]{4})\]\"/', $strPCIDevice, $arrMatch)) {

				$boolBlacklisted = false;
				if (in_array($arrMatch['id'], $arrBlacklistIDs) || preg_match($arrBlacklistClassIDregex, $arrMatch['typeid'])) {
					// Device blacklisted, skip device
					$boolBlacklisted = true;
				}

				$strClass = 'other';
				if (preg_match($arrWhitelistGPUClassIDregex, $arrMatch['typeid'])) {
					$strClass = 'vga';
					// Specialized product name cleanup for GPU
					// GF116 [GeForce GTX 550 Ti] --> GeForce GTX 550 Ti
					if (preg_match('/.+\[(?P<gpuname>.+)\]/', $arrMatch['productname'], $arrGPUMatch)) {
						$arrMatch['productname'] = $arrGPUMatch['gpuname'];
					}
				} elseif (preg_match($arrWhitelistAudioClassIDregex, $arrMatch['typeid'])) {
					$strClass = 'audio';
				}

				if (!file_exists('/sys/bus/pci/devices/0000:' . $arrMatch['id'] . '/iommu_group/')) {
					// No IOMMU support for device, skip device
					continue;
				}

				// Attempt to get the current kernel-bound driver for this pci device
				$strDriver = '';
				if (is_link('/sys/bus/pci/devices/0000:' . $arrMatch['id'] . '/driver')) {
					$strLink = @readlink('/sys/bus/pci/devices/0000:' . $arrMatch['id'] . '/driver');
					if (!empty($strLink)) {
						$strDriver = basename($strLink);
					}
				}

				// Clean up the vendor and product name
				$arrMatch['vendorname'] = sanitizeVendor($arrMatch['vendorname']);
				$arrMatch['productname'] = sanitizeProduct($arrMatch['productname']);

				$arrValidPCIDevices[] = [
					'id' => $arrMatch['id'],
					'type' => $arrMatch['type'],
					'typeid' => $arrMatch['typeid'],
					'vendorid' => $arrMatch['vendorid'],
					'vendorname' => $arrMatch['vendorname'],
					'productid' => $arrMatch['productid'],
					'productname' => $arrMatch['productname'],
					'class' => $strClass,
					'driver' => $strDriver,
					'name' => $arrMatch['vendorname'] . ' ' . $arrMatch['productname'],
					'blacklisted' => $boolBlacklisted
				];
			}
		}

		$cacheValidPCIDevices = $arrValidPCIDevices;

		return $arrValidPCIDevices;
	}

	function getValidGPUDevices() {
		$arrValidPCIDevices = getValidPCIDevices();

		$arrValidGPUDevices = array_filter($arrValidPCIDevices, function($arrDev) {
			return ($arrDev['class'] == 'vga' && !$arrDev['blacklisted']);
		});

		return $arrValidGPUDevices;
	}

	function getValidAudioDevices() {
		$arrValidPCIDevices = getValidPCIDevices();

		$arrValidAudioDevices = array_filter($arrValidPCIDevices, function($arrDev) {
			return ($arrDev['class'] == 'audio' && !$arrDev['blacklisted']);
		});

		return $arrValidAudioDevices;
	}

	function getValidOtherDevices() {
		$arrValidPCIDevices = getValidPCIDevices();

		$arrValidOtherDevices = array_filter($arrValidPCIDevices, function($arrDev) {
			return ($arrDev['class'] == 'other' && !$arrDev['blacklisted']);
		});

		return $arrValidOtherDevices;
	}

	function getValidOtherStubbedDevices() {
		$arrValidPCIDevices = getValidPCIDevices();

		$arrValidOtherStubbedDevices = array_filter($arrValidPCIDevices, function($arrDev) {
			return ($arrDev['class'] == 'other' && !$arrDev['blacklisted'] && in_array($arrDev['driver'], ['pci-stub', 'vfio-pci']));
		});

		return $arrValidOtherStubbedDevices;
	}

	$cacheValidUSBDevices = null;
	function getValidUSBDevices() {
		global $cacheValidUSBDevices;

		if (!is_null($cacheValidUSBDevices)) {
			return $cacheValidUSBDevices;
		}

		$arrValidUSBDevices = [];

		// Get a list of all usb hubs so we can blacklist them
		exec("cat /sys/bus/usb/drivers/hub/*/modalias | grep -Po 'usb:v\K\w{9}' | tr 'p' ':'", $arrAllUSBHubs);

		exec("lsusb 2>/dev/null", $arrAllUSBDevices);

		foreach ($arrAllUSBDevices as $strUSBDevice) {
			if (preg_match('/^.+: ID (?P<id>\S+)(?P<name>.*)$/', $strUSBDevice, $arrMatch)) {
				if (stripos($GLOBALS['var']['flashGUID'], str_replace(':', '-', $arrMatch['id'])) === 0) {
					// Device id matches the unraid boot device, skip device
					continue;
				}

				if (in_array(strtoupper($arrMatch['id']), $arrAllUSBHubs)) {
					// Device class is a Hub, skip device
					continue;
				}

				$arrMatch['name'] = trim($arrMatch['name']);

				if (empty($arrMatch['name'])) {
					// Device name is blank, attempt to lookup usb details
					exec("lsusb -d ".$arrMatch['id']." -v 2>/dev/null | grep -Po '^\s+(iManufacturer|iProduct)\s+[1-9]+ \K[^\\n]+'", $arrAltName);
					$arrMatch['name'] = trim(implode(' ', (array)$arrAltName));

					if (empty($arrMatch['name'])) {
						// Still blank, replace using fallback default
						$arrMatch['name'] = '[unnamed device]';
					}
				}

				// Clean up the name
				$arrMatch['name'] = sanitizeVendor($arrMatch['name']);

				$arrValidUSBDevices[] = [
					'id' => $arrMatch['id'],
					'name' => $arrMatch['name'],
				];
			}
		}

		uasort($arrValidUSBDevices, function ($a, $b) {
			return strcasecmp($a['id'], $b['id']);
		});

		$cacheValidUSBDevices = $arrValidUSBDevices;

		return $arrValidUSBDevices;
	}

	function getValidMachineTypes() {
		global $lv;

		$arrValidMachineTypes = [];

		$arrQEMUInfo = $lv->get_connect_information();
		$arrMachineTypes = $lv->get_machine_types('x86_64');

		$strQEMUVersion = $arrQEMUInfo['hypervisor_major'] . '.' . $arrQEMUInfo['hypervisor_minor'];

		foreach ($arrMachineTypes as $arrMachine) {
			if ($arrMachine['name'] == 'q35') {
				// Latest Q35
				$arrValidMachineTypes['pc-q35-' . $strQEMUVersion] = 'Q35-' . $strQEMUVersion;
			}
			if (strpos($arrMachine['name'], 'q35-') !== false) {
				// Prior releases of Q35
				$arrValidMachineTypes[$arrMachine['name']] = str_replace(['q35', 'pc-'], ['Q35', ''], $arrMachine['name']);
			}
			if ($arrMachine['name'] == 'pc') {
				// Latest i440fx
				$arrValidMachineTypes['pc-i440fx-' . $strQEMUVersion] = 'i440fx-' . $strQEMUVersion;
			}
			if (strpos($arrMachine['name'], 'i440fx-') !== false) {
				// Prior releases of i440fx
				$arrValidMachineTypes[$arrMachine['name']] = str_replace('pc-', '', $arrMachine['name']);
			}
		}

		uksort($arrValidMachineTypes, 'version_compare');
		$arrValidMachineTypes = array_reverse($arrValidMachineTypes);

		return $arrValidMachineTypes;
	}

	function getLatestMachineType($strType = 'i440fx') {
		$arrMachineTypes = getValidMachineTypes();

		foreach ($arrMachineTypes as $key => $value) {
			if (stripos($key, $strType) !== false) {
				return $key;
			}
		}

		return array_shift(array_keys($arrMachineTypes));
	}

	function getValidDiskDrivers() {
		$arrValidDiskDrivers = [
			'raw' => 'raw',
			'qcow2' => 'qcow2'
		];

		return $arrValidDiskDrivers;
	}

	function getValidDiskBuses() {
		$arrValidDiskBuses = [
			'virtio' => 'VirtIO',
			'scsi' => 'SCSI',
			'sata' => 'SATA',
			'ide' => 'IDE',
			'usb' => 'USB'
		];

		return $arrValidDiskBuses;
	}

	function getValidCdromBuses() {
		$arrValidCdromBuses = [
			'scsi' => 'SCSI',
			'sata' => 'SATA',
			'ide' => 'IDE',
			'usb' => 'USB'
		];

		return $arrValidCdromBuses;
	}

	function getValidVNCModels() {
		$arrValidVNCModels = [
			'cirrus' => 'Cirrus',
			'qxl' => 'QXL (best)',
			'vmvga' => 'vmvga'
		];

		return $arrValidVNCModels;
	}
	function getValidVMRCProtocols() {
		$arrValidProtocols = [
			'vnc' => 'VNC',
			'spice' => 'SPICE'
		];

		return $arrValidProtocols;
	}

	function getValidKeyMaps() {
		$arrValidKeyMaps = [
			'ar' => 'Arabic (ar)',
			'hr' => 'Croatian (hr)',
			'cz' => 'Czech (cz)',
			'da' => 'Danish (da)',
			'nl' => 'Dutch (nl)',
			'en-gb' => 'English-United Kingdom (en-gb)',
			'en-us' => 'English-United States (en-us)',
			'es' => 'Español (es)',
			'et' => 'Estonian (et)',
			'fo' => 'Faroese (fo)',
			'fi' => 'Finnish (fi)',
			'fr' => 'French (fr)',
			'bepo' => 'French-Bépo (bepo)',
			'fr-be' => 'French-Belgium (fr-be)',
			'fr-ca' => 'French-Canadian (fr-ca)',
			'fr-ch' => 'French-Switzerland (fr-ch)',
			'de-ch' => 'German-Switzerland (de-ch)',
			'de' => 'German (de)',
			'hu' => 'Hungarian (hu)',
			'is' => 'Icelandic (is)',
			'it' => 'Italian (it)',
			'ja' => 'Japanese (ja)',
			'lv' => 'Latvian (lv)',
			'lt' => 'Lithuanian (lt)',
			'mk' => 'Macedonian (mk)',
			'no' => 'Norwegian (no)',
			'pl' => 'Polish (pl)',
			'pt' => 'Portuguese (pt)',
			'pt-br' => 'Portuguese-Brazil (pt-br)',
			'ru' => 'Russian (ru)',
			'sl' => 'Slovene (sl)',
			'sv' => 'Swedish (sv)',
			'th' => 'Thailand (th)',
			'tr' => 'Turkish (tr)'
		];

		return $arrValidKeyMaps;
	}

	function getHostCPUModel() {
		$cpu = explode('#', exec("dmidecode -q -t 4|awk -F: '{if(/Version:/) v=$2; else if(/Current Speed:/) s=$2} END{print v\"#\"s}'"));
		[$strCPUModel] = my_explode('@', str_replace(["Processor","CPU","(C)","(R)","(TM)"], ["","","&#169;","&#174;","&#8482;"], $cpu[0]) . '@', 1);
		return trim($strCPUModel);
	}

	function getValidNetworks() {
		global $lv;
		$arrValidNetworks = [];
		exec("brctl show|grep -Po '^(vir)?br\d\S*'", $arrBridges);

		if (!is_array($arrBridges)) {
			$arrBridges = [];
		}

		// Make sure the default libvirt bridge is first in the list
		if (($key = array_search('virbr0', $arrBridges)) !== false) {
			unset($arrBridges[$key]);
		}
		// We always list virbr0 because libvirt might not be started yet (thus the bridge doesn't exists)
		array_unshift($arrBridges, 'virbr0');

		$arrValidNetworks['bridges'] = array_values($arrBridges);

		// This breaks VMSettings.page if libvirt is not running
        	if ($libvirt_running == "yes") {
			$arrVirtual = $lv->libvirt_get_net_list($lv->get_connection());
			
			if (($key = array_search('default', $arrVirtual)) !== false) {
				unset($arrVirtual[$key]);
			}
			
			array_unshift($arrVirtual, 'default');
			
			$arrValidNetworks['libvirt'] = array_values($arrVirtual);
		}

		return $arrValidNetworks;
	}

	function domain_to_config($uuid) {
		global $lv;
		global $domain_cfg;

		$arrValidGPUDevices = getValidGPUDevices();
		$arrValidAudioDevices = getValidAudioDevices();
		$arrValidOtherDevices = getValidOtherDevices();
		$arrValidUSBDevices = getValidUSBDevices();
		$arrValidDiskDrivers = getValidDiskDrivers();

		$res = $lv->domain_get_domain_by_uuid($uuid);
		$dom = $lv->domain_get_info($res);
		$medias = $lv->get_cdrom_stats($res);
		$disks = $lv->get_disk_stats($res, false);
		$arrNICs = $lv->get_nic_info($res);
		$arrHostDevs = $lv->domain_get_host_devices_pci($res);
		$arrUSBDevs = $lv->domain_get_host_devices_usb($res);
		$getcopypaste=getcopypaste($res) ;

		// Metadata Parsing
		// libvirt xpath parser sucks, use php's xpath parser instead
		$strDOMXML = $lv->domain_get_xml($res);
		$xmldoc = new DOMDocument();
		$xmldoc->loadXML($strDOMXML);
		$xpath = new DOMXPath($xmldoc);
		$objNodes = $xpath->query('//domain/metadata/*[local-name()=\'vmtemplate\']/@*');

		$arrTemplateValues = [];
		if ($objNodes->length > 0) {
			foreach ($objNodes as $objNode) {
				$arrTemplateValues[$objNode->nodeName] = $objNode->nodeValue;
			}
		}

		if (empty($arrTemplateValues['name'])) {
			$arrTemplateValues['name'] = 'Custom';
		}

		$arrGPUDevices = [];
		$arrAudioDevices = [];
		$arrOtherDevices = [];

		// check for vnc/spice; add to arrGPUDevices
		$vmrcport = $lv->domain_get_vnc_port($res);
		$autoport = $lv->domain_get_vmrc_autoport($res);
		if (empty($vmrcport) && $autoport == "yes") $vmrcport = -1 ;
		if (!empty($vmrcport)) {
			$arrGPUDevices[] = [
				'id' => 'virtual',
				'protocol' => $lv->domain_get_vmrc_protocol($res),
				'model' => $lv->domain_get_vnc_model($res),
				'keymap' => $lv->domain_get_vnc_keymap($res),
				'port' => $vmrcport,
				'wsport' => $lv->domain_get_ws_port($res),
				'autoport' => $autoport,
				'copypaste' => $getcopypaste,
			];
		}

		foreach ($arrHostDevs as $arrHostDev) {
			$arrFoundGPUDevices = array_filter($arrValidGPUDevices, function($arrDev) use ($arrHostDev) {return ($arrDev['id'] == $arrHostDev['id']);});
			if (!empty($arrFoundGPUDevices)) {
				$arrGPUDevices[] = ['id' => $arrHostDev['id'], 'rom' => $arrHostDev['rom']];
				continue;
			}

			$arrFoundAudioDevices = array_filter($arrValidAudioDevices, function($arrDev) use ($arrHostDev) {return ($arrDev['id'] == $arrHostDev['id']);});
			if (!empty($arrFoundAudioDevices)) {
				$arrAudioDevices[] = ['id' => $arrHostDev['id']];
				continue;
			}

			$arrFoundOtherDevices = array_filter($arrValidOtherDevices, function($arrDev) use ($arrHostDev) {return ($arrDev['id'] == $arrHostDev['id']);});
			if (!empty($arrFoundOtherDevices)) {
				$arrOtherDevices[] = ['id' => $arrHostDev['id'],'boot' => $arrHostDev['boot']];
				continue;
			}
		}

		// Add claimed USB devices by this VM to the available USB devices
		/*
		foreach($arrUSBDevs as $arrUSB) {
			$arrValidUSBDevices[] = [
				'id' => $arrUSB['id'],
				'name' => $arrUSB['product'],
			];
		}
		*/

		$arrDisks = [];
		foreach ($disks as $i => $disk) {
			$strPath = (empty($disk['file']) ? $disk['partition'] : $disk['file']);

			$default_option = 'auto';
			if (empty($domain_cfg['DOMAINDIR']) ||
				!file_exists($domain_cfg['DOMAINDIR']) ||
				!is_file($strPath) ||
				strpos($domain_cfg['DOMAINDIR'], dirname(dirname($strPath))) === false ||
				basename($strPath) != 'vdisk'.($i+1).'.img') {

				$default_option = 'manual';
			}

			$arrDisks[] = [
				'new' => $strPath,
				'size' => '',
				'driver' => 'raw',
				'dev' => $disk['device'],
				'bus' => $disk['bus'],
				'boot' => $disk['boot order'],
				'serial' => $disk['serial'],
				'select' => $default_option
			];
		}
		if (empty($arrDisks)) {
			$arrDisks[] = [
				'new' => '',
				'size' => '',
				'driver' => 'raw',
				'dev' => 'hda',
				'select' => '',
				'bus' => 'virtio'
			];
		}

		// HACK: If there's only 1 cdrom and the dev=hdb then it's most likely a VirtIO Driver ISO instead of the OS Install ISO
		if (!empty($medias) && count($medias) == 1 && array_key_exists('device', $medias[0]) && $medias[0]['device'] == 'hdb') {
			$medias[] = null;
			$medias = array_reverse($medias);
		}

		$strUSBMode = 'usb2';
		if ($lv->_get_single_xpath_result($res, '//domain/devices/controller[@model=\'nec-xhci\']')) {
			$strUSBMode = 'usb3';
		} else if ($lv->_get_single_xpath_result($res, '//domain/devices/controller[@model=\'qemu-xhci\']')) {
			$strUSBMode = 'usb3-qemu';
		}

		$strOVMF = '0';
		if (!empty($lv->domain_get_ovmf($res))) {
			$ovmfloader = $lv->domain_get_ovmf($res);
		  if (strpos($ovmfloader, '_CODE-pure-efi.fd') !== false) {
			  $strOVMF = '1';
		  } else if (strpos($ovmfloader, '_CODE-pure-efi-tpm.fd') !== false) {
			  $strOVMF = '2';
		  }
  	}

		if ($lv->domain_get_boot_devices($res)[0] == "fd") $osbootdev = "Yes" ; else $osbootdev = "No" ;

		return [
			'template' => $arrTemplateValues,
			'domain' => [
				'name' => $lv->domain_get_name($res),
				'desc' => $lv->domain_get_description($res),
				'persistent' => 1,
				'uuid' => $lv->domain_get_uuid($res),
				'clock' => $lv->domain_get_clock_offset($res),
				'arch' => $lv->domain_get_arch($res),
				'machine' => $lv->domain_get_machine($res),
				'mem' => $lv->domain_get_current_memory($res),
				'maxmem' => $lv->domain_get_memory($res),
				'password' => '', //TODO?
				'cpumode' => $lv->domain_get_cpu_type($res),
				'vcpus' => $dom['nrVirtCpu'],
				'vcpu' => $lv->domain_get_vcpu_pins($res),
				'hyperv' => ($lv->domain_get_feature($res, 'hyperv') ? 1 : 0),
				'autostart' => ($lv->domain_get_autostart($res) ? 1 : 0),
				'state' => $lv->domain_state_translate($dom['state']),
				'ovmf' => $strOVMF,
				'usbboot' => $osbootdev,
				'usbmode' => $strUSBMode,
				'memoryBacking' => getmemoryBacking($res) 
			],
			'media' => [
				'cdrom' => (!empty($medias) && !empty($medias[0]) && array_key_exists('file', $medias[0])) ? $medias[0]['file'] : '',
				'cdromboot' => (!empty($medias) && !empty($medias[0]) && array_key_exists('file', $medias[0])) ? $medias[0]['boot order'] : '',
				'cdrombus' => (!empty($medias) && !empty($medias[0]) && array_key_exists('bus', $medias[0])) ? $medias[0]['bus'] : (stripos($lv->domain_get_machine($res), 'q35')!==false ? 'sata': 'ide'),
				'drivers' => (!empty($medias) && !empty($medias[1]) && array_key_exists('file', $medias[1])) ? $medias[1]['file'] : '',
				'driversbus' => (!empty($medias) && !empty($medias[1]) && array_key_exists('bus', $medias[1])) ? $medias[1]['bus'] : (stripos($lv->domain_get_machine($res), 'q35')!==false ? 'sata': 'ide')
			],
			'disk' => $arrDisks,
			'gpu' => $arrGPUDevices,
			'audio' => $arrAudioDevices,
			'pci' => $arrOtherDevices,
			'nic' => $arrNICs,
			'usb' => $arrUSBDevs,
			'shares' => $lv->domain_get_mount_filesystems($res)
		];
	}

	function create_vdisk(&$new) {
		global $lv;
		$index = 0;
		foreach ($new['disk'] as $i => $disk) {
			$index++;
			if ($disk['new']) {
				$disk = $lv->create_disk_image($disk, $new['domain']['name'], $index);
				if ($disk['error']) return $disk['error'];
				$new['disk'][$i] = $disk;
			}
		}
		return false;
	}

	function array_update_recursive(&$old, &$new) {
		$hostold = $old['devices']['hostdev']; // existing devices including custom settings
		$hostnew = $new['devices']['hostdev']; // GUI generated devices
		// update USB & PCI host devices
		foreach ($hostnew as $key => $device) {
			$auto = $device['tag'];
			$vendor = $device['source']['vendor']['@attributes']['id'];
			$remove_usb = $remove_pci = false;
			[$product,$remove_usb] = my_explode('#',$device['source']['product']['@attributes']['id']);
			$pci = $device['source']['address']['@attributes'];
			[$function,$remove_pci] = my_explode('#',$pci['function']);
			if ($remove_usb || $remove_pci) unset($new['devices']['hostdev'][$key]);
			foreach ($hostold as $k => $d) {
				$v = $d['source']['vendor']['@attributes']['id'];
				$p = $d['source']['product']['@attributes']['id'];
				$p2 = $d['source']['address']['@attributes'];
				if ($v && $p && $v==$vendor && $p==$product) unset($old['devices']['hostdev'][$k]);
				if ($p2['bus'] && $p2['slot'] && $p2['function'] && $p2['bus']==$pci['bus'] && $p2['slot']==$pci['slot'] && $p2['function']==$function) unset($old['devices']['hostdev'][$k]);
			}
		}
		// remove and rebuild usb controllers
		$devices = $old['devices']['controller'];
		foreach ($devices as $key => $controller) {
			if ($controller['@attributes']['type']=='usb') unset($old['devices']['controller'][$key]);
		}
		// preserve existing disk driver settings
		foreach ($new['devices']['disk'] as $key => $disk) {
			$source = $disk['source']['@attributes']['file'];
			foreach ($old['devices']['disk'] as $k => $d) if ($source==$d['source']['@attributes']['file']) $new['devices']['disk'][$key]['driver']['@attributes'] = $d['driver']['@attributes'];
		}
		// settings not in the GUI, but maybe customized
		unset($new['clock']); 
		// preserve vnc/spice port settings
		// unset($new['devices']['graphics']['@attributes']['port'],$new['devices']['graphics']['@attributes']['autoport']);
		if (!$new['devices']['graphics']) unset($old['devices']['graphics']);
		// update parent arrays
		if (!$old['devices']['hostdev']) unset($old['devices']['hostdev']);
		if (!$new['devices']['hostdev']) unset($new['devices']['hostdev']);
		// preserve tpm
		if (!$new['devices']['tpm']) unset($old['devices']['tpm']);
		// remove existing auto-generated settings
		unset($old['cputune']['vcpupin'],$old['devices']['video'],$old['devices']['disk'],$old['devices']['interface'],$old['devices']['filesystem'],$old['cpu']['@attributes'],$old['os']['boot'],$old['os']['loader'],$old['os']['nvram']);
		// Remove old CPU cache and features
		unset($old['cpu']['cache'], $old['cpu']['feature']) ;
		unset($old['features']['hyperv'],$old['devices']['channel']) ;
		// set namespace
		$new['metadata']['vmtemplate']['@attributes']['xmlns'] = 'unraid';
	}

	function getVMUSBs($strXML){
		$arrValidUSBDevices = getValidUSBDevices() ;
		foreach($arrValidUSBDevices as $key => $data) {

			$array[$key] = [
					'id' => $data['id'],
					'name' => $data["name"],
					'checked' => '',
					'startupPolicy' => '',
					'usbboot' => '' 
					];
		}
		if ($strXML !="") { 
			$VMxml = new SimpleXMLElement($strXML);
			$VMUSB=$VMxml->xpath('//devices/hostdev[@type="usb"]') ;
			foreach($VMUSB as $USB){
				$vendor=$USB->source->vendor->attributes()->id ;
				$product=$USB->source->product->attributes()->id ;
				$startupPolicy=$USB->source->attributes()->startupPolicy ;
				$usbboot= $USB->boot->attributes()->order  ;
				$id = str_replace('0x', '', $vendor . ':' . $product) ;
				$found = false ;
				foreach($arrValidUSBDevices as $key => $data) {
					if ($data['id'] == $id) {
						$array[$key]['checked'] = "checked" ;
						$array[$key]['startupPolicy'] = $startupPolicy ;
						$array[$key]['usbboot'] = $usbboot ;
						$found = true ;
						break ;
					}
				}
				if (!$found) {
						$array[] = [
						'id' => $id,
						'name' => _("USB device is missing"),
						'checked' => 'checked',
						'startupPolicy' => $startupPolicy,
						'usbboot' => $usbboot 
						];
				}
			}
		}	
		return $array ;
	} 

	function sharesOnly($disk) {
		return strpos('Data,Cache',$disk['type'])!==false && $disk['exportable']=='yes';
	  }

	function getUnraidShares(){
		$shares  = parse_ini_file('state/shares.ini',true);
		uksort($shares,'strnatcasecmp');
		$arrreturn[] = "Manual" ;
		foreach ($shares as $share) {
			$arrreturn[] = "User:".$share["name"] ;
		}
		$disks   = parse_ini_file('state/disks.ini',true);
		$disks = array_filter($disks,'sharesOnly');

		foreach ($disks as $name => $disk) {
			$arrreturn[] = "Disk:".$name ;
		}
		return $arrreturn ;
	}

	function getgastate($res) {
        global $lv ;
        $xml = new SimpleXMLElement($lv->domain_get_xml($res)) ;
        $data = $xml->xpath('//channel/target[@name="org.qemu.guest_agent.0"]/@state') ;
        $data = $data[0]->state ;
        return $data ;
	}

	function getmemoryBacking($res) {
		global $lv ;
		$xml = $lv->domain_get_xml($res) ;
		$memoryBacking = new SimpleXMLElement($xml);
		$memorybacking = $memoryBacking->memoryBacking ;
		return json_encode($memorybacking); ;
	}
	
	function getchannels($res) {
		global $lv ;
        $xml = $lv->domain_get_xml($res) ;
		$x = strpos($xml,"<channel", 0) ;
		$y = strpos($xml,"</channel>", 0)  ;
		$z=$y ;
		while ($y!=false) {
			$y = strpos($xml,"</channel>", $z +10)  ;
			if ($y != false) $z =$y  ;
		}
		$channels = substr($xml,$x, ($z + 10) -$x) ;
		return $channels ;
	}

	function getcopypaste($res) {
		$channels = getchannels($res) ;
		$spicevmc = $qemuvdaagent = $copypaste = false ;
		if (strpos($channels,"spicevmc",0)) $spicevmc = true ;
		if (strpos($channels,"qemu-vdagent",0)) $qemuvdaagent = true ;
		if ($spicevmc || $qemuvdaagent) $copypaste = true ; else $copypaste = false ;
		return $copypaste ;
	}
?>

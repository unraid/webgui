<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
 * Copyright 2015-2021, Derek Macias, Eric Schultz, Jon Panozzo.
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
**/

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

	$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
	require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt.php";
	require_once "$docroot/webGui/include/Custom.php";

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

	$arrDefaultClocks = [
		"windows" => [
			"hpet" => [
				"present" => "no",
				"tickpolicy" => "delay"
			],
			"hypervclock" => [
				"present" => "yes",
				"tickpolicy" => "delay"
			],
			"pit" => [
				"present" => "yes",
				"tickpolicy" => "delay"
			],
			"rtc" => [
				"present" => "yes",
				"tickpolicy" => "catchup"
			]
		],
		"hyperv" => [
			"hpet" => [
				"present" => "no",
				"tickpolicy" => "delay"
			],
			"hypervclock" => [
				"present" => "yes",
				"tickpolicy" => "delay"
			],
			"pit" => [
				"present" => "no",
				"tickpolicy" => "delay"
			],
			"rtc" => [
				"present" => "no",
				"tickpolicy" => "catchup"
			]
		] ,
		"other" => [
			"hpet" => [
				"present" => "no",
				"tickpolicy" => "delay"
			],
			"hypervclock" => [
				"present" => "no",
				"tickpolicy" => "delay"
			],
			"pit" => [
				"present" => "yes",
				"tickpolicy" => "delay"
			],
			"rtc" => [
				"present" => "yes",
				"tickpolicy" => "catchup"
			]
		]
	];

	#<model type='qxl' ram='65536' vram='65536' vgamem='16384' heads='1' primary='yes'/>

	$arrDisplayOptions = [
		"H1.16M" => [
			"text" => "1 Display 16Mb Memory",
			"qxlxml" => "ram='65536' vram='16384' vgamem='16384' heads='1' primary='yes'",
		],
		"H1.32M" => [
			"text" => "1 Display 32Mb Memory",
			"qxlxml" => "ram='65536' vram='32768' vgamem='32768' heads='1' primary='yes'",
		],
		"H1.64M" => [
			"text" => "1 Display 64Mb Memory",
			"qxlxml" => "ram='65536' vram='65536' vram64='65535' vgamem='65536' heads='1' primary='yes'",
		],
		"H1.128M" => [
			"text" => "1 Display 128Mb Memory",
			"qxlxml"=> "ram='65536' vram='131072' vram64='131072' vgamem='65536' heads='1' primary='yes'",
		],
		"H1.256M" => [
			"text" => "1 Display 256Mb Memory",
			"qxlxml" => "ram='65536' vram='262144' vram64='262144' vgamem='65536' heads='1' primary='yes'",
		],
		"H2.64M" => [
			"text" => "2 Displays 64Mb Memory",
			"qxlxml" => "ram='65536' vram='65536' vram64='65535' vgamem='65536' heads='2' primary='yes'",
		],
		"H2.128M" => [
			"text" => "2 Displays 128Mb Memory",
			"qxlxml" => "ram='65536' vram='131072'vram64='131072' vgamem='65536' heads='2' primary='yes'",
		],
		"H2.256M" => [
			"text" => "2 Displays 256Mb Memory",
			"qxlxml" => "ram='65536' vram='262144'vram64='262144' vgamem='65536' heads='2' primary='yes'",
		],
		"H4.64M" => [
			"text" => "4 Displays 64Mb Memory",
			"qxlxml" => "ram='65536' vram='65536' vram64='65535' vgamem='65536' heads='4' primary='yes'",
		],
		"H4.128M" => [
			"text" => "4 Displays 128Mb Memory",
			"qxlxml" => "ram='65536' vram='131072'vram64='131072' vgamem='65536' heads='4' primary='yes'",
		],
		"H4.256M" => [
			"text" => "4 Displays 256Mb Memory",
			"qxlxml"=> "ram='65536' vram='262144' vram64='262144' vgamem='65536' heads='4' primary='yes'",
		],
		];

	// Read configuration file (guaranteed to exist)
	$domain_cfgfile = "/boot/config/domain.cfg";
	$domain_cfg = parse_ini_file($domain_cfgfile);

	if ( ($domain_cfg['DEBUG'] ?? false) != "yes") {
		error_reporting(0);
	}

	if (empty($domain_cfg['VMSTORAGEMODE'])) {
		$domain_cfg['VMSTORAGEMODE'] = "auto";
	}
	if (!empty($domain_cfg['DOMAINDIR'])) {
		$domain_cfg['DOMAINDIR'] = rtrim($domain_cfg['DOMAINDIR'], '/').'/';
	}
	if (!empty($domain_cfg['MEDIADIR'])) {
		$domain_cfg['MEDIADIR'] = rtrim($domain_cfg['MEDIADIR'], '/').'/';
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
		$maxmem = number_format(($arrHostInfo['memory'] / 1048576), 1, '.', '');
	}

	function mk_dropdown_options($arrOptions, $strSelected) {
		foreach ($arrOptions as $key => $label) {
			echo mk_option($strSelected, $key, $label);
		}
	}

	function appendOrdinalSuffix($number) {
		$ends = ['th','st','nd','rd','th','th','th','th','th','th'];

		if (($number % 100) >= 11 && ($number % 100) <= 13) {
			$abbreviation = $number.'th';
		} else {
			$abbreviation = $number.$ends[$number % 10];
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
		$arrJSON = json_decode(shell_exec("qemu-img info --output json ".escapeshellarg($strImgPath)." 2>/dev/null"), true);
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

		# "System peripheral [0880]" "Global unichip corp. [1ac1]" "Coral Edge Tpu [089a]" -pff "Global unichip corp. [1ac1]" "Coral Edge Tpu [089a]"
		#                    typeid													productid
		# file is csv typeid:productid
		#
		if (is_file("/boot/config/VMPCIOverride.cfg")) {
			$arrWhiteListOverride = str_getcsv(file_get_contents("/boot/config/VMPCIOverride.cfg"));
		}
		$arrWhiteListOverride[] = "0880:089a";

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

				$overrideCheck = "{$arrMatch['typeid']}:{$arrMatch['productid']}";
				if (in_array($overrideCheck,$arrWhiteListOverride) ) $boolBlacklisted = false;

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

				if (!file_exists('/sys/bus/pci/devices/0000:'.$arrMatch['id'].'/iommu_group/')) {
					// No IOMMU support for device, skip device
					continue;
				}

				// Attempt to get the current kernel-bound driver for this pci device
				$strDriver = '';
				if (is_link('/sys/bus/pci/devices/0000:'.$arrMatch['id'].'/driver')) {
					$strLink = @readlink('/sys/bus/pci/devices/0000:'.$arrMatch['id'].'/driver');
					if (!empty($strLink)) {
						$strDriver = basename($strLink);
					}
				}

				// Attempt to get the boot_vga driver for this pci device
				$strBootVGA = '';
				if (is_file('/sys/bus/pci/devices/0000:'.$arrMatch['id'].'/boot_vga') && $strClass == 'vga') {
					$strFileVal = file_get_contents('/sys/bus/pci/devices/0000:'.$arrMatch['id'].'/boot_vga');
					if (!empty($strFileVal)) {
						$strBootVGA = trim($strFileVal);
					}
				}

				// Clean up the vendor and product name
				$arrMatch['vendorname'] = sanitizeVendor($arrMatch['vendorname']);
				$arrMatch['productname'] = sanitizeProduct($arrMatch['productname']);

				$arrValidPCIDevices[$arrMatch['id']] = [
					'id' => $arrMatch['id'],
					'type' => $arrMatch['type'],
					'typeid' => $arrMatch['typeid'],
					'vendorid' => $arrMatch['vendorid'],
					'vendorname' => $arrMatch['vendorname'],
					'productid' => $arrMatch['productid'],
					'productname' => $arrMatch['productname'],
					'class' => $strClass,
					'driver' => $strDriver,
					'bootvga' => $strBootVGA,
					'name' => $arrMatch['vendorname'].' '.$arrMatch['productname'],
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

	function getValidSoundCards() {
		$arrValidSoundCards = [
			'ich6'		=> ['name' => 'ich6','id' => 'virtual::ich6'],
			'ich7' 		=> ['name' => 'ich7','id' => 'virtual::ich7'],
			'ich9' 		=> ['name' => 'ich9','id' => 'virtual::ich9'],
			'ac97' 		=> ['name' => 'ac97','id' => 'virtual::ac97'],
			'es1370' 	=> ['name' => 'es1370','id' => 'virtual::es1370'],
			'pcspk' 	=> ['name' => 'pcspk','id' => 'virtual::pcspk'],
			'sb16' 		=> ['name' => 'sb16','id' => 'virtual::sb16'],
			'usb' 		=> ['name' => 'usb','id' => 'virtual::usb'],
			'virtio' 	=> ['name' => 'virtio','id' => 'virtual::virtio'],
		];
		return $arrValidSoundCards;
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

	function getValidevDev() {
		$inputevdev = array_merge(glob("/dev/input/by-id/*event-kbd"),glob("/dev/input/by-id/*event-mouse"));
		return $inputevdev;
	}

	function getevDev($res) {
		global $lv;
		$xml = $lv->domain_get_xml($res);
		$xmldoc = new SimpleXMLElement($xml);
		$xmlpath = $xmldoc->xpath('//devices/input[@type="evdev"] ');
		$evdevs = [];
		foreach ($xmlpath as $i => $evDev) {
		$evdevs[$i] = [
			'dev' => $evDev->source->attributes()->dev,
			'grab' => $evDev->source->attributes()->grab,
			'repeat' => $evDev->source->attributes()->repeat,
			'grabToggle' => $evDev->source->attributes()->grabToggle
			];
		}
		if (empty($evdevs)) $evdevs[0] = ['dev'=>"",'grab'=>"",'repeat'=>"",'grabToggle'=>""];
		return $evdevs;
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

				$arrValidUSBDevices[$arrMatch['id']] = [
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

		$strQEMUVersion = $arrQEMUInfo['hypervisor_major'].'.'.$arrQEMUInfo['hypervisor_minor'];

		foreach ($arrMachineTypes as $arrMachine) {
			if ($arrMachine['name'] == 'q35') {
				// Latest Q35
				$arrValidMachineTypes['pc-q35-'.$strQEMUVersion] = 'Q35-'.$strQEMUVersion;
			}
			if (strpos($arrMachine['name'], 'q35-') !== false) {
				// Prior releases of Q35
				$arrValidMachineTypes[$arrMachine['name']] = str_replace(['q35', 'pc-'], ['Q35', ''], $arrMachine['name']);
			}
			if ($arrMachine['name'] == 'pc') {
				// Latest i440fx
				$arrValidMachineTypes['pc-i440fx-'.$strQEMUVersion] = 'i440fx-'.$strQEMUVersion;
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

	function ValidateMachineType($machinetype) {
		$machinetypes=getValidMachineTypes();
		$type = substr($machinetype,0,strpos($machinetype,'-',3));
		foreach($machinetypes as $machinetypekey => $machinedetails) {
			$check_type = substr($machinetypekey,0,strlen($type));
			if ($check_type == $type) break;
		}
		return($machinetypekey);
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

	function getValidDiskDiscard() {
		$arrValidDiskDiscard = [
			'ignore' => 'Ignore(No Trim)',
			'unmap' => 'Unmap(Trim)',
		];
		return $arrValidDiskDiscard;
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
			'virtio' => 'Virtio(2d)',
			'virtio3d' => 'Virtio(3d)',
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
			'none' => 'No Keymap',
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
		[$strCPUModel] = my_explode('@', str_replace(["Processor","CPU","(C)","(R)","(TM)"], ["","","&#169;","&#174;","&#8482;"], $cpu[0]).'@', 1);
		return trim($strCPUModel);
	}

	function getValidNetworks() {
		global $lv;
		$arrValidNetworks = [];
		exec("ls --indicator-style=none /sys/class/net | grep -Po '^(br|bond|eth|wlan)[0-9]+(\.[0-9]+)?'",$arrBridges);
		// add 'virbr0' as default first choice
		array_unshift($arrBridges, 'virbr0');
		// remove redundant references of bridge and bond interfaces
		$remove = [];
		foreach ($arrBridges as $name) {
			if (substr($name,0,4) == 'bond') {
				$remove = array_merge($remove, (array)@file("/sys/class/net/$name/bonding/slaves",FILE_IGNORE_NEW_LINES));
			} elseif (substr($name,0,2) == 'br') {
				$remove = array_merge($remove, array_map(function($n){return end(explode('/',$n));}, glob("/sys/class/net/$name/brif/*")));
			} 
		}
		$arrValidNetworks['bridges'] = array_diff($arrBridges, $remove);

		// This breaks VMSettings.page if libvirt is not running
		/*	if ($libvirt_running == "yes") {
			$arrVirtual = $lv->libvirt_get_net_list($lv->get_connection());

			if (($key = array_search('default', $arrVirtual)) !== false) {
				unset($arrVirtual[$key]);
			}

			array_unshift($arrVirtual, 'default');

			$arrValidNetworks['libvirt'] = array_values($arrVirtual);
		}*/

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
		$arrSoundCards = $lv->domain_get_sound_cards($res);
		$getcopypaste=getcopypaste($res);

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
		if (empty($vmrcport) && $autoport == "yes") $vmrcport = -1;
		if (!empty($vmrcport)) {
			$arrGPUDevices[] = [
				'id' => 'virtual',
				'protocol' => $lv->domain_get_vmrc_protocol($res),
				'model' => $lv->domain_get_vnc_model($res),
				'keymap' => $lv->domain_get_vnc_keymap($res),
				'password' => $lv->domain_get_vnc_password($res),
				'port' => $vmrcport,
				'wsport' => $lv->domain_get_ws_port($res),
				'autoport' => $autoport,
				'copypaste' => $getcopypaste,
				'guest' => ['multi' => 'off' ],
				'render' => $lv->domain_get_vnc_render($res),
				'DisplayOptions' => $lv->domain_get_vnc_display_options($res),
			];
		}

		foreach ($arrHostDevs as $arrHostDev) {
			$arrFoundGPUDevices = array_filter($arrValidGPUDevices, function($arrDev) use ($arrHostDev) {return ($arrDev['id'] == $arrHostDev['id']);});
			if (!empty($arrFoundGPUDevices)) {
				$arrGPUDevices[] = ['id' => $arrHostDev['id'], 'rom' => $arrHostDev['rom'], 'guest' => $arrHostDev['guest']];
				continue;
			}

			$arrFoundAudioDevices = array_filter($arrValidAudioDevices, function($arrDev) use ($arrHostDev) {return ($arrDev['id'] == $arrHostDev['id']);});
			if (!empty($arrFoundAudioDevices)) {
				$arrAudioDevices[] = ['id' => $arrHostDev['id'], 'guest' => $arrHostDev['guest']];
				continue;
			}

			$arrFoundOtherDevices = array_filter($arrValidOtherDevices, function($arrDev) use ($arrHostDev) {return ($arrDev['id'] == $arrHostDev['id']);});
			if (!empty($arrFoundOtherDevices)) {
				$arrOtherDevices[] = ['id' => $arrHostDev['id'],'boot' => $arrHostDev['boot'], 'guest' => $arrHostDev['guest']];
				continue;
			}
		}
		if (empty($arrGPUDevices)) {
			$arrGPUDevices[] = [
				'id' => 'nogpu',
			];
		}

		if (!empty($arrSoundCards)) {
			foreach ($arrSoundCards as $sckey => $soundcard) $arrAudioDevices[] = ['id' => "virtual::".$soundcard['model']];
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
				basename($strPath) != 'vdisk'.($i+1).'.img' || basename($strPath) != 'vdisk'.($i+1).'.qcow2') {
				if (($disk['type'] == "qcow2" && (basename($strPath) == 'vdisk'.($i+1).'.qcow2')) || ($disk['type'] == "raw" && (basename($strPath) == 'vdisk'.($i+1).'.img'))) $default_option = "auto"; else $default_option = 'manual';
			}

			$arrDisks[] = [
				'new' => $strPath,
				'size' => '',
				'driver' => $disk['type'],
				'dev' => $disk['device'],
				'bus' => $disk['bus'],
				'discard' => $disk['discard'],
				'boot' => $disk['boot order'],
				'rotation' => $disk['rotation'],
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
				'bus' => 'virtio',
				'discard' => 'ignore',
				'rotation' => "0"
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

		if ($lv->domain_get_boot_devices($res)[0] == "fd") $osbootdev = "Yes"; else $osbootdev = "No";
		$vmname = $lv->domain_get_name($res);
		$cmdline = null;
		$QEMUCmdline = getQEMUCmdLine($strDOMXML);
		$QEMUOverride = getQEMUOverride($strDOMXML);
		if (isset($QEMUCmdline)) $cmdline = $QEMUCmdline;
		if (isset($QEMUOverride) && isset($QEMUCmdline)) $cmdline .= "\n".$QEMUOverride;
		if (isset($QEMUOverride) && !isset($QEMUCmdline)) $cmdline = $QEMUOverride;
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
				'cpumigrate' => $lv->domain_get_cpu_migrate($res),
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
			'shares' => $lv->domain_get_mount_filesystems($res),
			'evdev' => getevDev($res),
			'qemucmdline' => $cmdline,
			'clocks' => getClocks($strDOMXML),
			'xml' => [
				'machine' => $lv->domain_get_xml($vmname, "//domain/os/*"),
				'devices' => $lv->get_xpath($vmname, "//domain/os/*"),
			],
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
		// remove and rebuild usb + scsi controllers
		$devices = $old['devices']['controller'];
		foreach ($devices as $key => $controller) {
			if ($controller['@attributes']['type']=='usb') unset($old['devices']['controller'][$key]);
			if ($controller['@attributes']['type']=='scsi') unset($old['devices']['controller'][$key]);
		}
		// preserve existing disk driver settings
		foreach ($new['devices']['disk'] as $key => $disk) {
			$source = $disk['source']['@attributes']['file'];
			if (isset($disk['driver']['@attributes']['discard'])) $discard = $disk['driver']['@attributes']['discard']; else $discard = null;
			foreach ($old['devices']['disk'] as $k => $d)
				if ($source==$d['source']['@attributes']['file']) {
					if (isset($discard)) $d['driver']['@attributes']['discard'] = $discard;
					$new['devices']['disk'][$key]['driver']['@attributes'] = $d['driver']['@attributes'];
				}
		}
		// settings not in the GUI, but maybe customized
		unset($old['clock']);
		unset($old['devices']['input']);
		// preserve vnc/spice port settings
		// unset($new['devices']['graphics']['@attributes']['port'],$new['devices']['graphics']['@attributes']['autoport']);
		unset($old['devices']['sound']);
		unset($old['devices']['graphics']);
		if (!isset($new['devices']['graphics']['@attributes']['keymap']) && isset($old['devices']['graphics']['@attributes']['keymap'])) unset($old['devices']['graphics']['@attributes']['keymap']);
		// update parent arrays
		if (!$old['devices']['hostdev']) unset($old['devices']['hostdev']);
		if (!$new['devices']['hostdev']) unset($new['devices']['hostdev']);
		// preserve tpm
		if (!$new['devices']['tpm']) unset($old['devices']['tpm']);
		// remove existing auto-generated settings
		unset($old['cputune']['vcpupin'],$old['devices']['video'],$old['devices']['disk'],$old['devices']['interface'],$old['devices']['filesystem'],$old['cpu']['@attributes'],$old['os']['boot'],$old['os']['loader'],$old['os']['nvram']);
		// Remove old CPU cache and features
		unset($old['cpu']['cache'], $old['cpu']['feature']);
		unset($old['features']['hyperv'],$old['devices']['channel']);
		// set namespace
		$new['metadata']['vmtemplate']['@attributes']['xmlns'] = 'unraid';
	}

	function getVMUSBs($strXML){
		$arrValidUSBDevices = getValidUSBDevices();
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
			$VMUSB=$VMxml->xpath('//devices/hostdev[@type="usb"]');
			foreach($VMUSB as $USB){
				$vendor=$USB->source->vendor->attributes()->id;
				$product=$USB->source->product->attributes()->id;
				$startupPolicy=$USB->source->attributes()->startupPolicy;
				$usbboot= $USB->boot->attributes()->order ?? "";
				$id = str_replace('0x', '', $vendor.':'.$product);
				$found = false;
				foreach($arrValidUSBDevices as $key => $data) {
					if ($data['id'] == $id) {
						$array[$key]['checked'] = "checked";
						$array[$key]['startupPolicy'] = $startupPolicy;
						$array[$key]['usbboot'] = $usbboot;
						$found = true;
						break;
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
		return $array;
	}

	function sharesOnly($disk) {
		return strpos('Data,Cache',$disk['type'])!==false && $disk['exportable']=='yes';
	}

	function getUnraidShares(){
		$shares  = parse_ini_file('state/shares.ini',true);
		uksort($shares,'strnatcasecmp');
		$arrreturn[] = "Manual";
		foreach ($shares as $share) {
			$arrreturn[] = "User:".$share["name"];
		}
		$disks   = parse_ini_file('state/disks.ini',true);
		$disks = array_filter($disks,'sharesOnly');

		foreach ($disks as $name => $disk) {
			$arrreturn[] = "Disk:".$name;
		}
		return $arrreturn;
	}

	function getgastate($res) {
		global $lv;
		$xml = new SimpleXMLElement($lv->domain_get_xml($res));
		$data = $xml->xpath('//channel/target[@name="org.qemu.guest_agent.0"]/@state');
		$data = $data[0]->state ?? null;
		return $data;
	}

	function getmemoryBacking($res) {
		global $lv;
		$xml = $lv->domain_get_xml($res);
		$memoryBacking = new SimpleXMLElement($xml);
		$memorybacking = $memoryBacking->memoryBacking;
		return json_encode($memorybacking);;
	}

	function getClocks($xml) {
		$clocks = new SimpleXMLElement($xml);
		$clocks = json_decode(json_encode($clocks->clock),true);
		$arrClocks = [
			"hpet" => [
				"present" => "no",
				"tickpolicy" => "delay"
			],
			"hypervclock" => [
				"present" => "no",
				"tickpolicy" => "delay"
			],
			"pit" => [
				"present" => "no",
				"tickpolicy" => "delay"
			],
			"rtc" => [
				"present" => "no",
				"tickpolicy" => "delay"
			]
		];
		foreach ($clocks['timer'] as $timer) {
			$name = $timer["@attributes"]["name"];
			$tickpolicy = $timer["@attributes"]["tickpolicy"];
			$present = $timer["@attributes"]["present"];
			if (isset($present)) $arrClocks[$name]['present'] = $present;
			if (isset($tickpolicy)) { $arrClocks[$name]['tickpolicy'] = $tickpolicy; $arrClocks[$name]['present'] = 'yes'; }
		}
		return json_encode($arrClocks);
	}

	function getQEMUCmdLine($xml) {
		$x = strpos($xml,"<qemu:commandline>", 0);
		if ($x === false) return null;
		$y = strpos($xml,"</qemu:commandline>", 0) ;
		$z=$y;
		while ($y!=false) {
			$y = strpos($xml,"<qemu:commandline>", $z +19) ;
			if ($y != false) $z =$y ;
		}
		return substr($xml,$x, ($z + 19) -$x);
	}

	function getQEMUOverride($xml) {
		$x = strpos($xml,"<qemu:override>", 0);
		if ($x === false) return null;
		$y = strpos($xml,"</qemu:override>", 0) ;
		$z=$y;
		while ($y!=false) {
			$y = strpos($xml,"<qemu:override>", $z +16) ;
			if ($y != false) $z =$y ;
		}
		return substr($xml,$x, ($z + 16) -$x);
	}

	function getchannels($res) {
		global $lv;
				$xml = $lv->domain_get_xml($res);
		$x = strpos($xml,"<channel", 0);
		$y = strpos($xml,"</channel>", 0) ;
		$z=$y;
		while ($y!=false) {
			$y = strpos($xml,"</channel>", $z +10) ;
			if ($y != false) $z =$y ;
		}
		$channels = substr($xml,$x, ($z + 10) -$x);
		return $channels;
	}

	function getcopypaste($res) {
		$channels = getchannels($res);
		$spicevmc = $qemuvdaagent = $copypaste = false;
		if (strpos($channels,"spicevmc",0)) $spicevmc = true;
		if (strpos($channels,"qemu-vdagent",0)) $qemuvdaagent = true;
		if ($spicevmc || $qemuvdaagent) $copypaste = true; else $copypaste = false;
		return $copypaste;
	}

	function vm_clone($vm, $clone ,$overwrite,$start,$edit, $free, $waitID) {
		global $lv,$domain_cfg,$arrDisplayOptions;
		/*
			Clone.

			Stopped only.

			Get new VM Name
			Extract XML for VM to be cloned.
			Check if snapshots.
			Check if directory exists.
			Check for disk space

			Stop VM Starting until clone is finished or fails.

			Create new directory for Clone.
			Update paths with new directory

			Create new UUID
			Create new MAC Address for NICs

			Create VM Disks from source. Options full or Sparce. Method of copy?

			release orginal VM to start.

			If option to edit, show VMUpdate
		*/
		$snaps = getvmsnapshots($vm);
		if (is_array($snaps)) {
			if (count($snaps) ) {write("addLog\0".htmlspecialchars(_("Clone of VM not currently supported if it has snapshots"))); $arrResponse =  ['error' => _("Clone of VM not currently supported if it has snapshots")]; return false;}
		}
		$uuid = $lv->domain_get_uuid($clone);
		write("addLog\0".htmlspecialchars(_("Checking if clone exists")));
		if ($uuid) { $arrResponse =  ['error' => _("Clone VM name already inuse")]; return false;}
		#VM must be shutdown.
		$res = $lv->get_domain_by_name($vm);
		$dom = $lv->domain_get_info($res);
		$state = $lv->domain_state_translate($dom['state']);
		$vmxml = $lv->domain_get_xml($res);
		$storage =  $lv->_get_single_xpath_result($vm, '//domain/metadata/*[local-name()=\'vmtemplate\']/@storage');
		if (empty($storage)) $storage = "default";
		# if VM running shutdown. Record was running.
		if ($state != 'shutoff') {write("addLog\0".htmlspecialchars(_("Shuting down ").$vm._(" current ")._($state))); $arrResponse = $lv->domain_destroy($vm); }
		# Wait for shutdown?

		$disks =$lv->get_disk_stats($vm);

		$capacity = 0;

		foreach($disks as $disk)   {
			$file = $disk["file"];
			$pathinfo =  pathinfo($file);
			$capacity = $capacity + $disk["capacity"];
		}

		#Check free space.
		write("addLog\0".htmlspecialchars(_("Checking for free space")));
		$dirfree = disk_free_space($pathinfo["dirname"]);
		$sourcedir = trim(shell_exec("getfattr --absolute-names --only-values -n system.LOCATION ".escapeshellarg($pathinfo["dirname"])." 2>/dev/null"));
		if (!empty($sourcedir)) $repdir = str_replace('/mnt/user/', "/mnt/$sourcedir/", $pathinfo["dirname"]); else $repdir = $pathinfo["dirname"];
		$repdirfree = disk_free_space($repdir);
		$reflink = true;
		$capacity *=  1;

		if ($free == "yes" && $repdirfree < $capacity) { $reflink = false;}
		if ($free == "yes" && $dirfree < $capacity) { write("addLog\0".htmlspecialchars(_("Insufficent storage for clone")));  return false;}

		#Clone XML
		$uuid = $lv->domain_get_uuid($vm);
		$config=domain_to_config($uuid);

		$config["domain"]["name"] = $clone;
		$config["domain"]["uuid"]  = $lv->domain_generate_uuid();
		foreach($config["nic"] as $index => $detail) {
		$config["nic"][$index]["mac"] = $lv->generate_random_mac_addr();
		}
		$config["domain"]["type"] = "kvm";

		$usbs = getVMUSBs($vmxml);
		foreach($usbs as $i => $usb) {
			if ($usb["checked"] == "checked") continue;
			unset($usbs[$i]);
		}
		$config["usb"] = $usbs;

		$file_exists = false;
		$file_clone = array();
		if ($config['disk'][0]['new'] != "") {
		foreach ($config["disk"] as $diskid => $disk) {
			$file_clone[$diskid]["source"] = $config["disk"][$diskid]["new"];
			$config["disk"][$diskid]["new"] = str_replace($vm,$clone,$config["disk"][$diskid]["new"]);
			$pi = pathinfo($config["disk"][$diskid]["new"]);
			$isdir = is_dir($pi['dirname']);
			if (is_file($config["disk"][$diskid]["new"])) $file_exists = true;
			write("addLog\0".htmlspecialchars(_("Checking from file:").$file_clone[$diskid]["source"]));
			write("addLog\0".htmlspecialchars(_("Checking to file:").$config["disk"][$diskid]["new"]));
			write("addLog\0".htmlspecialchars(_("File exists value:"). ($file_exists ? "True" : "False")));
			$file_clone[$diskid]["target"] = $config["disk"][$diskid]["new"];
			}

		if ($storage == "default") $clonedir = $domain_cfg['DOMAINDIR'].$clone; else $clonedir = str_replace('/mnt/user/', "/mnt/$storage/", $domain_cfg['DOMAINDIR']).$clone;
		if (!is_dir($clonedir)) {
			my_mkdir($clonedir,0777,true);
		}
		write("addLog\0".htmlspecialchars("Checking for image files"));
		if ($file_exists && $overwrite != "yes") { write("addLog\0".htmlspecialchars(_("New image file names exist and Overwrite is not allowed")));  return( false); }

		#Create duplicate files.
		foreach($file_clone as $diskid => $disk)  {
			$reptgt = $target = $disk['target'];
			$repsrc = $source = $disk['source'];
			if ($target == $source) { write("addLog\0".htmlspecialchars(_("New image file is same as old")));  return( false); }
			if ($storage == "default") $sourcerealdisk = trim(shell_exec("getfattr --absolute-names --only-values -n system.LOCATION ".escapeshellarg($source)." 2>/dev/null")); else $sourcerealdisk = $storage;
			if (!empty($sourcerealdisk)) {
			$reptgt = str_replace('/mnt/user/', "/mnt/$sourcerealdisk/", $target);
			$repsrc = str_replace('/mnt/user/', "/mnt/$sourcerealdisk/", $source);
			}
			$cmdstr = "cp --reflink=always '$repsrc' '$reptgt'";
					if ($reflink == true) { $refcmd = $cmdstr; } else {$refcmd = false; }
			$cmdstr = "rsync -ahPIXS  --out-format=%f --info=flist0,misc0,stats0,name1,progress2 '$repsrc' '$reptgt'";
			$error = execCommand_nchan_clone($cmdstr,$target,$refcmd);
			if (!$error) { write("addLog\0".htmlspecialchars("Image copied failed."));  return( false); }
		}
	}

		write("<p class='logLine'></p>","addLog\0<fieldset class='docker'><legend>"._("Completing Clone").": </legend><p class='logLine'></p><span id='wait-$waitID'></span></fieldset>");
		write("addLog\0".htmlspecialchars(_("Creating new XML ").$clone));

		foreach($config['gpu'] as $ID => $arrGPU) {
			if ($arrGPU['id'] != 'virtual') continue;
			if ($arrGPU['model'] == 'qxl' && !empty($arrGPU['DisplayOptions'])) {
			  $config['gpu'][$ID]['DisplayOptions'] = $arrDisplayOptions[$arrGPU['DisplayOptions']]['qxlxml'];
			}
		}

		$xml = $lv->config_to_xml($config, true);
		$rtn = $lv->domain_define($xml);

		if (is_resource($rtn)) {
			$arrResponse  = ['success' => true]; 
			write("addLog\0".htmlspecialchars(_("Creating XML successful")));
		} else { 
			$lastvmerror = $lv->get_last_error();
			$arrResponse = ['xml' => $xml,'error' => $lastvmerror];
			write("addLog\0".htmlspecialchars(_("Creating XML Error:$lastvmerror")));
			file_put_contents("/tmp/vmclonertn.debug", json_encode($arrResponse,JSON_PRETTY_PRINT));
		}
	
		return($rtn);

	}

	function compare_creationtime($a, $b) 	{
		return strnatcmp($a['creationtime'], $b['creationtime']);
	}

	function compare_creationtimelt($a, $b) 	{
		return $a['creationtime'] < $b['creationtime'];
	}

	function getvmsnapshots($vm) {
		$snaps=array();
		$dbpath = "/etc/libvirt/qemu/snapshotdb/$vm";
		$snaps_json = file_get_contents($dbpath."/snapshots.db");
		$snaps = json_decode($snaps_json,true);
		if (is_array($snaps)) uasort($snaps,'compare_creationtime');
		return $snaps;
	}

	function write_snapshots_database($vm,$name,$state,$desc,$method="QEMU") {
		global $lv;
		$dbpath = "/etc/libvirt/qemu/snapshotdb/$vm";
		if (!is_dir($dbpath)) mkdir($dbpath);
		$noxml = "";
		$snaps_json = file_get_contents($dbpath."/snapshots.db");
		$snaps = json_decode($snaps_json,true);
		$snapshot_res=$lv->domain_snapshot_lookup_by_name($vm,$name);
		if (!$snapshot_res) {
			 # Manual Snap no XML
		if ($state == "shutoff" && ($method == "ZFS" || $method == "BTRFS")) {
			# Create Snapshot info
			$vmsnap = $name;
			$snaps[$vmsnap]["name"]= $name;
			$snaps[$vmsnap]["parent"]= "None";
			$snaps[$vmsnap]["state"]= "shutoff";
			$snaps[$vmsnap]["desc"]= $desc;
			$snaps[$vmsnap]["memory"]= ['@attributes' => ['snapshot' => 'no']];
			$snaps[$vmsnap]["creationtime"]= date("U");
			$snaps[$vmsnap]["method"]= $method;
			$snaps[$vmsnap]['xml'] = $lv->domain_get_xml($vm);
			$noxml = "noxml";
		}
		} else {
		$snapshot_xml=$lv->domain_snapshot_get_xml($snapshot_res);
		$a = simplexml_load_string($snapshot_xml);
		$a = json_encode($a);
		$b = json_decode($a, TRUE);
		$vmsnap = $b["name"];
		$snaps[$vmsnap]["name"]= $b["name"];
		$snaps[$vmsnap]["parent"]= $b["parent"];
		$snaps[$vmsnap]["state"]= $b["state"];
		$snaps[$vmsnap]["desc"]= $b["description"];
		$snaps[$vmsnap]["memory"]= $b["memory"];
		$snaps[$vmsnap]["creationtime"]= $b["creationTime"];
		$snaps[$vmsnap]["method"]= $method;
		}

		$disks =$lv->get_disk_stats($vm);
			foreach($disks as $disk)   {
				$file = $disk["file"];
				if ($disk['device'] == "hdc" ) $primarypath = dirname(transpose_user_path($file));
				$output = array();
				exec("qemu-img info --backing-chain -U '$file'  | grep image:",$output);
				foreach($output as $key => $line) {
					$line=str_replace("image: ","",$line);
					$output[$key] = $line;
				}

				$snaps[$vmsnap]['backing'][$disk["device"]] = $output;
				$rev = "r".$disk["device"];
				$reversed = array_reverse($output);
				$snaps[$vmsnap]['backing'][$rev] = $reversed;
			}
			$snaps[$vmsnap]["primarypath"]= $primarypath;
			$parentfind = $snaps[$vmsnap]['backing'][$disk["device"]];
			$parendfileinfo = pathinfo($parentfind[1]);
			$snaps[$vmsnap]["parent"]= $parendfileinfo["extension"];
			$snaps[$vmsnap]["parent"] = str_replace("qcow2",'',$snaps[$vmsnap]["parent"]);
			if (isset($parentfind[1]) && !isset($parentfind[2])) $snaps[$vmsnap]["parent"]="Base";

			if (isset($b)) if (array_key_exists(0 , $b["disks"]["disk"])) $snaps[$vmsnap]["disks"]= $b["disks"]["disk"]; else $snaps[$vmsnap]["disks"][0]= $b["disks"]["disk"];

			$value = json_encode($snaps,JSON_PRETTY_PRINT);
		file_put_contents($dbpath."/snapshots.db",$value);
		return $noxml;
	}

	function refresh_snapshots_database($vm) {
		global $lv;
		$dbpath = "/etc/libvirt/qemu/snapshotdb/$vm";
		if (!is_dir($dbpath)) mkdir($dbpath);
		$snaps_json = file_get_contents($dbpath."/snapshots.db");
		$snaps = json_decode($snaps_json,true);
		foreach($snaps as $vmsnap=>$snap)

			$disks =$lv->get_disk_stats($vm);
			foreach($disks as $disk)   {
				$file = $disk["file"];
				$output = array();
				exec("qemu-img info --backing-chain -U '$file'  | grep image:",$output);
				foreach($output as $key => $line) {
					$line=str_replace("image: ","",$line);
					$output[$key] = $line;
				}

				$snaps[$vmsnap]['backing'][$disk["device"]] = $output;
				$rev = "r".$disk["device"];
				$reversed = array_reverse($output);
				$snaps[$vmsnap]['backing'][$rev] = $reversed;
			}
			$parentfind = $snaps[$vmsnap]['backing'][$disk["device"]];
			$parendfileinfo = pathinfo($parentfind[1]);
			$snaps[$vmsnap]["parent"]= $parendfileinfo["extension"];
			$snaps[$vmsnap]["parent"] = str_replace("qcow2",'',$snaps[$vmsnap]["parent"]);
			if (isset($parentfind[1]) && !isset($parentfind[2])) $snaps[$vmsnap]["parent"]="Base";

			$value = json_encode($snaps,JSON_PRETTY_PRINT);
			$res = $lv->get_domain_by_name($vm);
			#if (!empty($lv->domain_get_ovmf($res))) $nvram = $lv->nvram_create_snapshot($lv->domain_get_uuid($vm),$name);

			#Remove any NVRAMs that are no longer valid.
			# Get uuid
			$vmuuid = $lv->domain_get_uuid($vm);
			#Get list of files
			$filepath = "/etc/libvirt/qemu/nvram/$vmuuid*"; #$snapshotname"
			$nvram_files=glob($filepath);
			foreach($nvram_files as $key => $nvram_file)  {
				if ($nvram_file == "/etc/libvirt/qemu/nvram/$vmuuid"."_VARS-pure-efi.fd" || $nvram_file == "/etc/libvirt/qemu/nvram/$vmuuid"."_VARS-pure-efi-tpm.fd" ) unset($nvram_files[$key]);
				foreach ($snaps as $snapshotname => $snap) {
					$tpmfilename = "/etc/libvirt/qemu/nvram/".$vmuuid.$snapshotname."_VARS-pure-efi-tpm.fd";
					$nontpmfilename = "/etc/libvirt/qemu/nvram/".$vmuuid.$snapshotname."_VARS-pure-efi.fd";
					if ($nvram_file == $tpmfilename || $nvram_file == $nontpmfilename ) {
						unset($nvram_files[$key]);}
				}
			}
			foreach ($nvram_files  as $nvram_file) unlink($nvram_file);

		file_put_contents($dbpath."/snapshots.db",$value);
	}

	function delete_snapshots_database($vm,$name) {
		global $lv;
		$dbpath = "/etc/libvirt/qemu/snapshotdb/$vm";
		$snaps_json = file_get_contents($dbpath."/snapshots.db");
		$snaps = json_decode($snaps_json,true);
		unset($snaps[$name]);
		$value = json_encode($snaps,JSON_PRETTY_PRINT);
		file_put_contents($dbpath."/snapshots.db",$value);
		return true;
	}

	function vm_snapshot($vm,$snapshotname, $snapshotdescinput, $free = "yes", $method = "QEMU",  $memorysnap = "yes") {
		global $lv;
		$logging = true;
		#Get State
		$res = $lv->get_domain_by_name($vm);
		$dom = $lv->domain_get_info($res);
		$state = $lv->domain_state_translate($dom['state']);
		$storage =  $lv->_get_single_xpath_result($vm, '//domain/metadata/*[local-name()=\'vmtemplate\']/@storage');
		if (empty($storage)) $storage = "default";

		if ($method == "ZFS" && $state == "running" && $memorysnap == "no") {$arrResponse =  ['error' => _("ZFS snapshot without memory dump not supported at this time.") ];return $arrResponse;}

		#Get disks for --diskspec
		$disks =$lv->get_disk_stats($vm);
		$diskspec = "";
		$capacity = 0;
		if ($snapshotname == "--generate") $name= "S".date("YmdHis"); else $name=$snapshotname;
		if ($snapshotdescinput != "") $snapshotdesc = " --description '$snapshotdescinput'";

		foreach($disks as $disk)   {
			$file = $disk["file"];
			$pathinfo =  pathinfo($file);
			$dirpath = $pathinfo["dirname"];
			if ($storage == "default") {
				$dirpath = $pathinfo["dirname"];
			} else {
				$storagelocation = trim(shell_exec("getfattr --absolute-names --only-values -n system.LOCATION ".escapeshellarg($file)." 2>/dev/null"));
				$dirpath= str_replace('/mnt/user/', "/mnt/$storagelocation/", $dirpath);
			}
			$filenew = $dirpath.'/'.$pathinfo["filename"].'.'.$name.'qcow2';
			switch ($method) {
			case "QEMU" :
				$diskspec .= " --diskspec '".$disk["device"]."',snapshot=external,file='".$filenew."'";
				break;
			case "ZFS":
			case "BTRFS":
				$diskspec .= " --diskspec '".$disk["device"]."',snapshot=manual ";
			}
			$capacity = $capacity + $disk["capacity"];
		}

		#get memory
		$mem = $lv->domain_get_memory_stats($vm);
		$memory = $mem[6];

		if ($memorysnap == "yes") $memspec = ' --memspec "'.$dirpath.'/memory'.$name.'.mem",snapshot=external'; else $memspec = "";
		$cmdstr = "virsh snapshot-create-as '$vm' --name '$name' $snapshotdesc  --atomic";

		if ($state == "running" & $memorysnap == "yes") {
			$cmdstr .= " --live ".$memspec.$diskspec;
			$capacity = $capacity + $memory;

		} else {
			$cmdstr .= "  --disk-only ".$diskspec;
		}

		#Check free space.
		$dirfree = disk_free_space($dirpath);

		$capacity *=  1;

		if ($free == "yes" && $dirfree < $capacity) { $arrResponse =  ['error' => _("Insufficent Storage for Snapshot")]; return $arrResponse;}

		#Copy nvram
		if ($logging) qemu_log($vm,"Copy NVRAM");
		if (!empty($lv->domain_get_ovmf($res))) $nvram = $lv->nvram_create_snapshot($lv->domain_get_uuid($vm),$name);

		$xmlfile = $dirpath."/".$name.".running";
		if ($logging) qemu_log($vm,"Save XML if state is running current $state");
		if ($state == "running") exec("virsh dumpxml '$vm' > ".escapeshellarg($xmlfile),$outxml,$rtnxml);

		$output= [];
		if ($logging) qemu_log($vm,"snap method $method");
		switch ($method) {
		case "ZFS":
			# Create ZFS Snapshot
			if ($state == "running") exec($cmdstr." 2>&1",$output,$return);
			$zfsdataset = trim(shell_exec("zfs list -H -o name -r $dirpath"));
			$fssnapcmd = " zfs snapshot $zfsdataset@$name";
			if ($logging) qemu_log($vm,"zfs snap: $fssnapcmd");
			shell_exec($fssnapcmd);
			# if running resume.
			if ($state == "running") $lv->domain_resume($vm);
			break;
		case "BTRFS":
			# Create BTRFS Snapshot
			break;
		default:
			# No Action
			if ($logging) qemu_log($vm,"Cmd: $cmdstr");
			exec($cmdstr." 2>&1",$output,$return);
		}

		if (strpos(" ".$output[0],"error") ) {
			$arrResponse =  ['error' => substr($output[0],6) ];
			if ($logging) qemu_log($vm,"Error");
		} else {
		$arrResponse = ['success' => true];
		if ($logging) qemu_log($vm,"Success write snap db");
		$ret = write_snapshots_database("$vm","$name",$state,$snapshotdescinput,$method);
		#remove meta data
		if ($ret != "noxml") $ret = $lv->domain_snapshot_delete($vm, "$name" ,2);
		}
		return $arrResponse;

	}

		function vm_revert($vm, $snap="--current",$action="no",$actionmeta = 'yes',$dryrun = false) {
		global $lv;
		$logging = true;
		$snapslist= getvmsnapshots($vm);
		#$disks =$lv->get_disk_stats($vm);
		$snapstate = $snapslist[$snap]['state'];
		$method = $snapslist[$snap]['method'];

		#VM must be shutdown.
		$res = $lv->get_domain_by_name($vm);
		$dom = $lv->domain_get_info($res);
		$state = $lv->domain_state_translate($dom['state']);
		# if VM running shutdown. Record was running.
		if ($state != 'shutdown') $arrResponse = $lv->domain_destroy($vm);
		# Wait for shutdown?
		# GetXML
		$strXML= $lv->domain_get_xml($res);
		$xmlobj = custom::createArray('domain',$strXML);

		# Process disks and update path for method QEMU.
		if ($method == "QEMU") {
			$disks=($snapslist[$snap]['disks']);
			foreach ($disks as $disk) {
				$diskname = $disk["@attributes"]["name"];
				if ($diskname == "hda" || $diskname == "hdb") continue;
				$path = $disk["source"]["@attributes"]["file"];
				if ($diskname == "hdc") {
					$primarypathinfo =  pathinfo($path);
					$primarypath = $primarypathinfo['dirname'];
				}
				if ($snapstate != "running") {
					$item = array_search($path,$snapslist[$snap]['backing'][$diskname]);
					$newpath =  $snapslist[$snap]['backing'][$diskname][$item + 1];
					$json_info = getDiskImageInfo($newpath);
					foreach($xmlobj['devices']['disk'] as $ddk => $dd){
						if ($dd['target']["@attributes"]['dev'] == $diskname) {
							$xmlobj['devices']['disk'][$ddk]['source']["@attributes"]['file'] = "$newpath";
							$xmlobj['devices']['disk'][$ddk]['driver']["@attributes"]['type'] = $json_info["format"];
						}
					}
				}
			}
		}

		# If Snapstate not running create new XML.
		if ($snapstate != "running") {
			if ($method == "ZFS") $xml = $snapslist[$snap]['xml']; else $xml = custom::createXML('domain',$xmlobj)->saveXML();
			if (!strpos($xml,'<vmtemplate xmlns="unraid"') && !strpos($xml,'<vmtemplate xmlns="http://unraid"') ) $xml=str_replace('<vmtemplate','<vmtemplate xmlns="http://unraid"',$xml);
			if (!$dryrun) $new = $lv->domain_define($xml);
			if ($new) $arrResponse  = ['success' => true]; else $arrResponse = ['error' => $lv->get_last_error()];
			if ($logging) qemu_log($vm,"Create XML $new");
		}

		# remove snapshot meta data, images, memory, runxml and NVRAM. for all snapshots.

		foreach ($disks as $disk) {
			$diskname = $disk["@attributes"]["name"];
			if ($diskname == "hda" || $diskname == "hdb") continue;
			$path = $disk["source"]["@attributes"]["file"];
			if (is_file($path) && $action == "yes") if (!$dryrun)  unlink("$path");else echo "unlink $path\n";
			if ($logging) qemu_log($vm,"unlink $path");
			$item = array_search($path,$snapslist[$snap]['backing']["r".$diskname]);
			$item++;
			while($item > 0)
			{
			if (!isset($snapslist[$snap]['backing']["r".$diskname][$item])) break;
			$newpath =  $snapslist[$snap]['backing']["r".$diskname][$item];
			if (is_file($newpath) && $action == "yes") if (!$dryrun) unlink("$newpath"); else echo "unlink $newpath\n";
			if ($logging) qemu_log($vm,"unlink $newpath");
			$item++;
			}
		}

		# Remove later snapshots
		if (!is_null($snapslist)) uasort($snapslist,'compare_creationtimelt');

		foreach($snapslist as $s) {
			if ($s['name'] == $snap) break;
			$name = $s['name'];
			$oldmethod = $s['method'];
			if ($dryrun) echo "$name $oldmethod\n";
			if ($logging) qemu_log($vm,"$name $oldmethod");
			if (!isset($primarypath)) $primarypath = $s['primarypath'];
			$xmlfile = $primarypath."/$name.running";
			$memoryfile = $primarypath."/memory$name.mem";
			$olddisks = $snapslist[$name]['disks'];

			if ($oldmethod == "QEMU") {
				foreach ($olddisks as $olddisk) {
				$olddiskname = $olddisk["@attributes"]["name"];
				if ($olddiskname == "hda" || $olddiskname == "hdb") continue;
				$oldpath = $olddisk["source"]["@attributes"]["file"];
				if (is_file($oldpath) && $action == "yes") if (!$dryrun) unlink("$oldpath"); else echo "$oldpath\n";
				if ($logging) qemu_log($vm,"unlink $oldpath");
				}
			}
			if ($oldmethod == "ZFS") {
			# Rollback ZFS Snapshot
			$zfsdataset = trim(shell_exec("zfs list -H -o name -r ".transpose_user_path($primarypath)));
			$fssnapcmd = " zfs destroy $zfsdataset@$name";
			if (!$dryrun) shell_exec($fssnapcmd); else echo "old $fssnapcmd\n";
			if ($logging) qemu_log($vm,"old $fssnapcmd");
			}

			#Delete Metadata
			#if ($actionmeta == "yes") if (!$dryrun) $ret = delete_snapshots_database("$vm","$name");

			if (is_file($memoryfile) && $action == "yes") if (!$dryrun) unlink($memoryfile); else echo ("$memoryfile \n");
			if (is_file($xmlfile) && $action == "yes") if (!$dryrun) unlink($xmlfile); else echo ("$xmlfile \n");
			if ($logging) qemu_log($vm,"mem $memoryfile xml $xmlfile");
			# Delete NVRAM
			if (!empty($lv->domain_get_ovmf($res)) && $action == "yes")  if (!$dryrun) if (!empty($lv->domain_get_ovmf($res))) $nvram = $lv->nvram_revert_snapshot($lv->domain_get_uuid($vm),$name); else echo "Remove old NV\n";
			if ($actionmeta == "yes") {
				if (!$dryrun)  $ret = delete_snapshots_database("$vm","$name"); else echo "Old Delete snapshot meta\n";
				if ($logging) qemu_log($vm,"Old Delete snapshot meta");
			}
		}

		if ($method == "ZFS") {
			if (!isset($primarypath)) $primarypath = $snapslist[$snap]['primarypath'];

			$zfsdataset = trim(shell_exec("zfs list -H -o name -r ".transpose_user_path($primarypath)));
			if ($dryrun) {
				var_dump(transpose_user_path($primarypath));
			}
			$fssnapcmd = " zfs rollback $zfsdataset@$snap";
			if (!$dryrun) shell_exec($fssnapcmd); else echo "$fssnapcmd\n";
			if ($logging) qemu_log($vm,"$fssnapcmd");
			$fssnapcmd = " zfs destroy $zfsdataset@$snap";
			if (!$dryrun) shell_exec($fssnapcmd); else echo "$fssnapcmd\n";
			if ($logging) qemu_log($vm,"$fssnapcmd");
		}

		if ($snapslist[$snap]['state'] == "running" || $snapslist[$snap]['state'] == "disk-snapshot") {
			$xmlfile = $primarypath."/$snap.running";
			$memoryfile = $primarypath."/memory$snap.mem";
			# Set XML to saved XML
			$xml = file_get_contents($xmlfile);
			$xmlobj = custom::createArray('domain',$xml);
			$xml = custom::createXML('domain',$xmlobj)->saveXML();
			if (!strpos($xml,'<vmtemplate xmlns="unraid"') && !strpos($xml,'<vmtemplate xmlns="http://unraid"') ) $xml=str_replace('<vmtemplate','<vmtemplate xmlns="http://unraid"',$xml);
			if (!$dryrun) $rtn = $lv->domain_define($xml);
			if ($logging) qemu_log($vm,"Define XML");

			# Restore Memory.
			if ($snapslist[$snap]['state'] == "running") {
				if (!$dryrun) $cmdrtn = exec("virsh restore --running ".escapeshellarg($memoryfile));
				if ($logging) qemu_log($vm,"Restore");
				if (!$dryrun && !$cmdrtn) unlink($xmlfile);
				if ($logging) qemu_log($vm,"Unlink XML");
				if (!$dryrun && !$cmdrtn) unlink($memoryfile);
				if ($logging) qemu_log($vm,"Unlink memoryfile");
			}
			if ($snapslist[$snap]['state'] == "disk-snapshot") if (!$dryrun) unlink($xmlfile);
		}

		#if VM was started restart.
		if ($state == 'running' && $snapslist[$snap]['state'] != "running") {
			if (!$dryrun) $arrResponse = $lv->domain_start($vm);
		}

		if ($actionmeta == "yes") {
			if (!$dryrun)  $ret = delete_snapshots_database("$vm","$snap"); else echo "Delete snapshot meta\n";
			if ($logging) qemu_log($vm,"Delete Snapshot DB entry");
		}

		if (!$dryrun) if (!empty($lv->domain_get_ovmf($res))) $nvram = $lv->nvram_revert_snapshot($lv->domain_get_uuid($vm),$snap); else echo "Delete NV $vm,$snap\n";

		$arrResponse  = ['success' => true];
		if ($dryrun) var_dump($arrResponse);
		if ($logging) qemu_log($vm, "Success");
		return($arrResponse);
		}

	function vm_snapimages($vm, $snap, $only) {
		global $lv;
		$snapslist= getvmsnapshots($vm);
		$data = "<br><br>Images and metadata to remove if tickbox checked.<br>";
			$capacity = 0;
		$diskspec = "";
		$disks =$lv->get_disk_stats($vm);
		foreach($disks as $disk)   {
			$file = $disk["file"];
			$output = array();
			exec("qemu-img info --backing-chain -U '$file'  | grep image:",$output);
			foreach($output as $key => $line) {
				$line=str_replace("image: ","",$line);
				$output[$key] = $line;
			}
			$snaps[$vm][$disk["device"]] = $output ;
			$rev = "r".$disk["device"];
			$reversed = array_reverse($output);
			$snaps[$vm][$rev] = $reversed;
			$pathinfo =  pathinfo($file);
			$capacity = $capacity + $disk["capacity"];
		}

		$snapdisks= $snapslist[$snap]['disks'];

		foreach ($snapdisks as $diskkey => $snapdisk) {
			$diskname = $snapdisk["@attributes"]["name"];
			if ($diskname == "hda" || $diskname == "hdb") continue;
			$path = $snapdisk["source"]["@attributes"]["file"];
			if (is_file($path)) $data .= "$path<br>";
			$item = array_search($path,$snaps[$vm]["r".$diskname]);
			$item++;
			if ($only == 0) $item = 0;
			while($item > 0)
			{
			if (!isset($snaps[$vm]["r".$diskname][$item])) break;
			$newpath =  $snaps[$vm]["r".$diskname][$item];
				if (is_file($path)) $data .= "$newpath<br>";
			$item++;

			}
		}
		$data .= "<br>Snapshots metadata to remove.";
		if ($only == 0) {
			$data .= "<br>$snap";
		} else {
		uasort($snapslist,'compare_creationtimelt');
		foreach($snapslist as $s) {
			$name = $s['name'];
			$data .= "<br>$name";
			if ($s['name'] == $snap) break;
			}
		}
		return($data);
	}

	function vm_snapremove($vm, $snap) {
		global $lv;
		$snapslist= getvmsnapshots($vm);
		$res = $lv->get_domain_by_name($vm);
		$dom = $lv->domain_get_info($res);

		$disks =$lv->get_disk_stats($vm);
		foreach($disks as $disk)   {
			$file = $disk["file"];
			$output = array();
			exec("qemu-img info --backing-chain -U \"$file\"  | grep image:",$output);
			foreach($output as $key => $line) {
				$line=str_replace("image: ","",$line);
				$output[$key] = $line;
			}

			$snaps[$vm][$disk["device"]] = $output;
			$rev = "r".$disk["device"];
			$reversed = array_reverse($output);
			$snaps[$vm][$rev] = $reversed;
			$pathinfo =  pathinfo($file);
		}

		# GetXML
		$strXML= $lv->domain_get_xml($res);
		$xmlobj = custom::createArray('domain',$strXML);

		# Process disks.
		$disks=($snapslist[$snap]['disks']);
		foreach ($disks as $disk) {
			$diskname = $disk["@attributes"]["name"];
			if ($diskname == "hda" || $diskname == "hdb") continue;
			$path = $disk["source"]["@attributes"]["file"];
			$item = array_search($path,$snaps[$vm][$diskname]);
			if ($item!==false) {
				$data = ["error" => "Image currently active for this domain."];
				return ($data);
				}
			}

		$disks=($snapslist[$snap]['disks']);
		foreach ($disks as $disk) {
			$diskname = $disk["@attributes"]["name"];
			if ($diskname == "hda" || $diskname == "hdb") continue;
			$path = $disk["source"]["@attributes"]["file"];
			if (is_file($path)) {
				if(!unlink("$path")) {
					$data = ["error" => "Unable to remove image file $path"];
					return ($data);
				}
			}
		}

		# Delete NVRAM
		if (!empty($lv->domain_get_ovmf($res))) $nvram = $lv->nvram_delete_snapshot($lv->domain_get_uuid($vm),$snap);

		$ret = delete_snapshots_database("$vm","$snap") ;

		if(!$ret)
			$data = ["error" => "Unable to remove snap metadata $snap"];
		else
			$data = ["success => 'true"];
		return($data);
	}

function vm_blockcommit($vm, $snap ,$path,$base,$top,$pivot,$action) {
	global $lv;
	/*
NAME
	blockcommit - Start a block commit operation.

SYNOPSIS
	blockcommit <domain> <path> [--bandwidth <number>] [--base <string>] [--shallow] [--top <string>] [--active] [--delete] [--wait] [--verbose] [--timeout <number>] [--pivot] [--keep-overlay] [--async] [--keep-relative] [--bytes]

DESCRIPTION
	Commit changes from a snapshot down to its backing image.

OPTIONS
	[--domain] <string>  domain name, id or uuid
	[--path] <string>  fully-qualified path of disk
	--bandwidth <number>  bandwidth limit in MiB/s
	--base <string>  path of base file to commit into (default bottom of chain)
	--shallow        use backing file of top as base
	--top <string>   path of top file to commit from (default top of chain)
	--active         trigger two-stage active commit of top file
	--delete         delete files that were successfully committed
	--wait           wait for job to complete (with --active, wait for job to sync)
	--verbose        with --wait, display the progress
	--timeout <number>  implies --wait, abort if copy exceeds timeout (in seconds)
	--pivot          implies --active --wait, pivot when commit is synced
	--keep-overlay   implies --active --wait, quit when commit is synced
	--async          with --wait, don't wait for cancel to finish
	--keep-relative  keep the backing chain relatively referenced
	--bytes          the bandwidth limit is in bytes/s rather than MiB/s

	blockcommit Debian --path /mnt/user/domains/Debian/vdisk1.S20230513120410qcow2 --verbose --pivot --delete
	*/
	#Get VM State. If shutdown start as paused.
	$res = $lv->get_domain_by_name($vm);
	$dom = $lv->domain_get_info($res);
	$state = $lv->domain_state_translate($dom['state']);
	if ($state == "shutoff") {
		$lv->domain_start($res);
		$lv->domain_suspend($res);
	}

		$snapslist= getvmsnapshots($vm);
		$disks =$lv->get_disk_stats($vm);

		foreach($disks as $disk)   {
			$path = $disk['file'];
			$cmdstr = "virsh blockcommit '$vm' --path '$path' --verbose ";
			if ($pivot == "yes") $cmdstr .= " --pivot ";
			if ($action == "yes") $cmdstr .= " --delete ";
			# Process disks and update path.
			$snapdisks=($snapslist[$snap]['disks']);
			if ($base != "--base" && $base != "") {
				#get file name from  snapshot.
				$snapdisks=($snapslist[$base]['disks']);
				$basepath = "";
				foreach ($snapdisks as $snapdisk) {
					$diskname = $snapdisk["@attributes"]["name"];
					if ($diskname != $disk['device']) continue;
					$basepath = $snapdisk["source"]["@attributes"]["file"];
					}
				if ($basepath != "") $cmdstr .= " --base '$basepath' ";
			}
			if ($top != "--top" && $top !="")  {
				#get file name from  snapshot.
				$snapdisks=($snapslist[$top]['disks']);
				$toppath = "";
				foreach ($snapdisks as $snapdisk) {
					$diskname = $snapdisk["@attributes"]["name"];
					if ($diskname != $disk['device']) continue;
					$toppath = $snapdisk["source"]["@attributes"]["file"];
					}
				if ($toppath != "") $cmdstr .= " --top '$toppath' ";
			}

			$error = execCommand_nchan($cmdstr,$path);
			if (!$error) {
				$arrResponse =  ['error' => "Process Failed"];
				return($arrResponse);
			} else {
				$arrResponse = ['success' => true];
			}

		}
		# Delete NVRAM
		#if (!empty($lv->domain_get_ovmf($res))) $nvram = $lv->nvram_delete_snapshot($lv->domain_get_uuid($vm),$snap);
		if ($state == "shutoff") {
		$lv->domain_destroy($res);
		}

		refresh_snapshots_database($vm);
		$ret = $ret = delete_snapshots_database("$vm","$snap");;
		if($ret)
			$data = ["error" => "Unable to remove snap metadata $snap"];
		else
			$data = ["success => 'true"];
		return $data;

}

function vm_blockpull($vm, $snap ,$path,$base,$top,$pivot,$action) {
	global $lv;
	/*
NAME
	blockpull - Populate a disk from its backing image.

SYNOPSIS
	blockpull <domain> <path> [--bandwidth <number>] [--base <string>] [--wait] [--verbose] [--timeout <number>] [--async] [--keep-relative] [--bytes]

DESCRIPTION
	Populate a disk from its backing image.

OPTIONS
	[--domain] <string>  domain name, id or uuid
	[--path] <string>  fully-qualified path of disk
	--bandwidth <number>  bandwidth limit in MiB/s
	--base <string>  path of backing file in chain for a partial pull
	--wait           wait for job to finish
	--verbose        with --wait, display the progress
	--timeout <number>  with --wait, abort if pull exceeds timeout (in seconds)
	--async          with --wait, don't wait for cancel to finish
	--keep-relative  keep the backing chain relatively referenced
	--bytes          the bandwidth limit is in bytes/s rather than MiB/s

	*/
	#Get VM State. If shutdown start as paused.
	$res = $lv->get_domain_by_name($vm);
	$dom = $lv->domain_get_info($res);
	$state = $lv->domain_state_translate($dom['state']);
	if ($state == "shutoff") {
	$lv->domain_start($res);
		$lv->domain_suspend($res);
	}

	$snapslist= getvmsnapshots($vm);
	$disks =$lv->get_disk_stats($vm);
	foreach($disks as $disk)   {
		$file = $disk["file"];
		$output = array();
		exec("qemu-img info --backing-chain -U '$file'  | grep image:",$output);
		foreach($output as $key => $line) {
			$line=str_replace("image: ","",$line);
			$output[$key] = $line;
		}
		$snaps[$vm][$disk["device"]] = $output ;
		$rev = "r".$disk["device"];
		$reversed = array_reverse($output);
		$snaps[$vm][$rev] = $reversed;
	}
	$snaps_json=json_encode($snaps,JSON_PRETTY_PRINT);
	$pathinfo =  pathinfo($file);
	$dirpath = $pathinfo["dirname"];
	#file_put_contents("$dirpath/image.tracker",$snaps_json);

	foreach($disks as $disk)   {
	$path = $disk['file'];
	$cmdstr = "virsh blockpull '$vm' --path '$path' --verbose --pivot --delete";
	$cmdstr = "virsh blockpull '$vm' --path '$path' --verbose --wait ";
	# Process disks and update path.
	$snapdisks=($snapslist[$snap]['disks']);
	if ($base != "--base" && $base != "") {
		#get file name from  snapshot.
		$snapdisks=($snapslist[$base]['disks']);
		$basepath = "";
		foreach ($snapdisks as $snapdisk) {
			$diskname = $snapdisk["@attributes"]["name"];
			if ($diskname != $disk['device']) continue;
			$basepath = $snapdisk["source"]["@attributes"]["file"];
			}
		if ($basepath != "") $cmdstr .= " --base '$basepath' ";
	}

	if ($action) $cmdstr .= " $action ";

	$error = execCommand_nchan($cmdstr,$path);

	if (!$error)  {
		$arrResponse =  ['error' => "Process Failed" ];
		return($arrResponse);
	} else {
		# Remove nvram snapshot
		$arrResponse = ['success' => true];
	}

}

	if ($state == "shutoff") {
	$lv->domain_destroy($res);
	}

	refresh_snapshots_database($vm);
	$ret = $ret = delete_snapshots_database("$vm","$snap");
	if($ret)
		$data = ["error" => "Unable to remove snap metadata $snap"];
	else
		$data = ["success => 'true"];

	return $data;

}

function vm_blockcopy($vm,$path,$base,$top,$pivot,$action) {
	/*
NAME
	blockcopy - Start a block copy operation.

SYNOPSIS
	blockcopy <domain> <path> [--dest <string>] [--bandwidth <number>] [--shallow] [--reuse-external] [--blockdev] [--wait] [--verbose] [--timeout <number>] [--pivot] [--finish] [--async] [--xml <string>] [--format <string>] [--granularity <number>] [--buf-size <number>] [--bytes] [--transient-job] [--synchronous-writes] [--print-xml]

DESCRIPTION
	Copy a disk backing image chain to dest.

OPTIONS
	[--domain] <string>  domain name, id or uuid
	[--path] <string>  fully-qualified path of source disk
	--dest <string>  path of the copy to create
	--bandwidth <number>  bandwidth limit in MiB/s
	--shallow        make the copy share a backing chain
	--reuse-external  reuse existing destination
	--blockdev       copy destination is block device instead of regular file
	--wait           wait for job to reach mirroring phase
	--verbose        with --wait, display the progress
	--timeout <number>  implies --wait, abort if copy exceeds timeout (in seconds)
	--pivot          implies --wait, pivot when mirroring starts
	--finish         implies --wait, quit when mirroring starts
	--async          with --wait, don't wait for cancel to finish
	--xml <string>   filename containing XML description of the copy destination
	--format <string>  format of the destination file
	--granularity <number>  power-of-two granularity to use during the copy
	--buf-size <number>  maximum amount of in-flight data during the copy
	--bytes          the bandwidth limit is in bytes/s rather than MiB/s
	--transient-job  the copy job is not persisted if VM is turned off
	--synchronous-writes  the copy job forces guest writes to be synchronously written to the destination
	--print-xml      print the XML used to start the copy job instead of starting the job
	*/
}

function addtemplatexml($post) {
	global $templateslocation,$lv,$config;
	$savedtemplates = json_decode(file_get_contents($templateslocation),true);
	if (isset($post['xmldesc'])) {
		$data = explode("\n",$post['xmldesc']);
		foreach ($data as $k => $line) {
			if (strpos($line,"uuid")) unset($data[$k]);
			if (strpos($line,"<nvram>")) unset($data[$k]);
			if (strpos($line,"<name>")) $data[$k] = "<name>#template123456</name>";
		}

		$data = implode("\n",$data);
		$new = $lv->domain_define($data);
		$dom = $lv->get_domain_by_name("#template123456");
		$uuid = $lv->domain_get_uuid("#template123456");
		$usertemplate = domain_to_config($uuid);
		$lv->domain_undefine($dom);
		$usertemplate['templatename'] = $post['templatename'];
		$usertemplate['template'] = $post['template'];
		unset($usertemplate['domain']['uuid']);
		unset($usertemplate['domain']['name']);

	} else {
		// form view

		$usertemplate = $post;
					// generate xml for this domain
		$strXML = $lv->config_to_xml($usertemplate);
		$qemucmdline = $config['qemucmdline'];
		$strXML = $lv->appendqemucmdline($strXML,$qemucmdline);
	}

	foreach($usertemplate['disk'] as $diskid => $diskdata) { unset($usertemplate['disk'][$diskid]['new']);}
	foreach($usertemplate['gpu'] as $gpuid => $gpudata) { $usertemplate['gpu'][$gpuid]['guest']['multi'] = $usertemplate['gpu'][$gpuid]['multi'];  unset($usertemplate['gpu'][$gpuid]['multi']);}
	unset($usertemplate['createvmtemplate']);
	unset($usertemplate['domain']['xmlstart']);
	unset($usertemplate['pci']);
	unset($usertemplate['usb']);
	unset($usertemplate['usbboot']);
	unset($usertemplate['nic']['mac']);

	$templatename=$usertemplate['templatename'];
	if ($templatename == "") $templatename=$usertemplate['template']['os'];
	unset($usertemplate['templatename']);
	if (strpos($templatename,"User-") === false) $templatename = "User-".$templatename;
	if (is_array($usertemplate['clock'])) $usertemplate['clocks'] = json_encode($usertemplate['clock']);
	unset($usertemplate['clock']);
	$savedtemplates[$templatename] = [
		'icon' => $usertemplate['template']['icon'],
		'form' => 'Custom.form.php',
		'os' => $usertemplate['template']['os'],
		'overrides' => $usertemplate
	];
	if (!is_dir(dirname($templateslocation))) mkdir(dirname($templateslocation));
	file_put_contents($templateslocation,json_encode($savedtemplates,JSON_PRETTY_PRINT));
	$reply = ['success' => true];
	return $reply;
}

function get_vm_usage_stats($collectcpustats = true,$collectdiskstats = true,$collectnicstats = true, $collectmemstats = true) {
	global $lv, $vmusagestats;

	$hostcpus = $lv->host_get_node_info();
	$timestamp = time();
	$allstats=$lv->domain_get_all_domain_stats();

	foreach ($allstats as $vm => $data) {
		$rd=$wr=$tx=$rx=null;
		$state = $data["state.state"];
		# CPU Metrics
		$cpuTime = 0;
		$cpuHostPercent = 0;
		$cpuGuestPercent = 0;
		$cpuTimeAbs = $data["cpu.time"];
		if ($state == 1 && $collectcpustats == true) {
			$guestcpus = $data["vcpu.current"];
			$cpuTime = $cpuTimeAbs - $vmusagestats[$vm]["cpuTimeAbs"];
			$pcentbase = ((($cpuTime) * 100.0) / ((($timestamp) - $vmusagestats[$vm]["timestamp"] ) * 1000.0 * 1000.0 * 1000.0));
			$cpuHostPercent = round($pcentbase / $hostcpus['cpus'],1);
			$cpuGuestPercent = round($pcentbase / $guestcpus, 1);
			$cpuHostPercent = max(0.0, min(100.0, $cpuHostPercent));
			$cpuGuestPercent = max(0.0, min(100.0, $cpuGuestPercent));
		}

		# Memory Metrics
		if ($state == 1 && $collectmemstats) {
		$currentmem = $data["balloon.current"];
		$maximummem = $data["balloon.maximum"];
		$meminuse = min($data["balloon.rss"],$data["balloon.current"]);
		} else $maximummem = $currentmem = $meminuse = 0;

		# Disk
		if ($state == 1 && $collectdiskstats) {
			$disknum = $data["block.count"];
			$rd=$wr=$i=0;
			for ($i = 0; $i < $disknum; $i++) {
				if ($data["block.$i.name"] == "hda" || $data["block.$i.name"] == "hdb") continue;
				$rd +=  $data["block.$i.rd.bytes"];
				$wr +=  $data["block.$i.wr.bytes"];
			}
			$rdrate = ($rd - $vmusagestats[$vm]['rdp']);
			$wrrate = ($wr - $vmusagestats[$vm]['wrp']);
		} else $rdrate=$wrrate=0;

		# Net
		if ($state == 1 && $collectnicstats) {
			$nicnum = $data["net.count"];
			$rx=$tx=$i=0;
			for ($i = 0; $i < $nicnum; $i++) {
				$rx +=  $data["net.$i.rx.bytes"];
				$tx +=  $data["net.$i.tx.bytes"];
			}
			$rxrate = round(($rx - $vmusagestats[$vm]['rxp']),0);
			$txrate = round(($tx - $vmusagestats[$vm]['txp']),0);
		} else {
			$rxrate=$txrate=0;
		}
		$vmusagestats[$vm] = [
			"cpuTime" => $cpuTime,
			"cpuTimeAbs" => $cpuTimeAbs,
			"cpuhost" => $cpuHostPercent,
			"cpuguest" => $cpuGuestPercent,
			"timestamp" => $timestamp,
			"mem" => $meminuse,
			"curmem" => $currentmem,
			"maxmem" => $maximummem,
			"rxrate" => $rxrate,
			"rxp" => $rx,
			"txrate" => $txrate,
			"txp" => $tx,
			"rdp" => $rd,
			"rdrate" => $rdrate,
			"wrp" => $wr,
			"wrrate" => $wrrate,
			"state" => $state,
		];
	}
}

function build_xml_templates($strXML) {
	global $arrValidPCIDevices,$arrValidUSBDevices;

	$xmldevsections = $xmlsections = [];
	$xml = new SimpleXMLElement($strXML);
	$x = $xml->children();
	foreach($x as $key=>$y) {
		$xmlsections[] = $key;
	}

	$ns= $xml->getNamespaces(true);
	foreach($ns as $namekey=>$namespace) foreach($xml->children($namespace) as $key=>$y)	$xmlsections[] = "$namekey:$key";

	$v = $xml->devices->children();
	$keys = [];
	foreach($v as $key=>$y) $keys[] = $key;
	foreach(array_count_values($keys) as $key=>$number) $xmldevsections[]= $key;

	$endpos = 0;
	foreach($xmlsections as $xmlsection) {
		$strpos = strpos($strXML,"<$xmlsection",$endpos);
		if ($strpos === false) continue ;
		$endcheck = "</$xmlsection>";
		$endpos = strpos($strXML,$endcheck,$strpos);
		$xml2[$xmlsection] = trim(substr($strXML,$strpos,$endpos-$strpos+strlen($endcheck)),'/0');
	}

	$xml = $xml2['devices'];
	$endpos = 0;
	foreach($xmldevsections as $xmlsection ) {
		 $strpos = $count = 0;
		while (true) {
			$strpos = strpos($xml,"<$xmlsection",$endpos);
			if ($strpos === false) continue  2;
			$endcheck = "</$xmlsection>";
			$endpos = strpos($xml,$endcheck,$strpos);
			#echo $xmlsection." ".$strpos." ".$endpos."\n";
			if ($endpos === false) {
				$endcheck = "/>";
				$endpos = strpos($xml,$endcheck,$strpos);
			}
			# echo substr($xml,$strpos,$endpos-$strpos+strlen($endcheck));
			if ($xmlsection == "disk") {
				$disk = substr($xml,$strpos,$endpos-$strpos+strlen($endcheck));
				$xmldiskdoc = new SimpleXMLElement($disk);
				$devxml[$xmlsection][$xmldiskdoc->target->attributes()->dev->__toString()] = $disk;
			} else {
				$devxml[$xmlsection][$count] = substr($xml,$strpos,$endpos-$strpos+strlen($endcheck));
			}
			$count++;
		}
	}
	$xml2["devices"] = $devxml;
	$xml2["devices"]["allusb"] = "";
	if(isset($xml2['devices']["hostdev"])) {
		foreach ($xml2['devices']["hostdev"] as $xmlhostdev) {
			$xmlhostdevdoc = new SimpleXMLElement($xmlhostdev);
			switch ($xmlhostdevdoc->attributes()->type) {
			case 'pci' :
				$pciaddr = $xmlhostdevdoc->source->address->attributes()->bus.":".$xmlhostdevdoc->source->address->attributes()->slot.".".$xmlhostdevdoc->source->address->attributes()->function;
				$pciaddr = str_replace("0x","",$pciaddr);
				$xml2["devices"][$arrValidPCIDevices[$pciaddr]["class"]][$pciaddr] = $xmlhostdev;
				break;
			case "usb":
				$usbaddr = $xmlhostdevdoc->source->vendor->attributes()->id.":".$xmlhostdevdoc->source->product->attributes()->id;
				$usbaddr = str_replace("0x","",$usbaddr);
				$xml2["devices"]["usb"][$usbaddr] = $xmlhostdev;
				$xml2["devices"]["allusb"] .= $xmlhostdev;
				break;
			}
		}
	}
	foreach($xml2["devices"]["input"] as $input) $xml2["devices"]["allinput"] .= "$input\n";
	return $xml2;
}

function qemu_log($vm,$m) {
	$m = print_r($m,true);
	$m = date("YmdHis")." ".$m;
	$m = str_replace("\n", " ", $m);
	$m = str_replace('"', "'", $m);
	file_put_contents("/var/log/libvirt/qemu/$vm.log",$m."\n",FILE_APPEND);
}

function get_vm_ip($dom) {
	global $lv;
	$myIP=null;
	$gastate = getgastate($dom);
	if ($gastate == "connected") {
		$ip  = $lv->domain_interface_addresses($dom, 1);
		$gastate = getgastate($dom);
		if ($gastate == "connected") {
			$ip  = $lv->domain_interface_addresses($dom, 1);
			if ($ip != false) {
				foreach ($ip as $arrIP) {
					$ipname = $arrIP["name"];
					if (preg_match('/^(lo|Loopback)/',$ipname)) continue; // omit loopback interface
					$iplist = $arrIP["addrs"];
						foreach ($iplist as $arraddr) {
							$myIP= $arraddr["addr"];
							if (preg_match('/^f[c-f]/',$myIP)) continue; // omit ipv6 private addresses
						break 2;
					}
				}
			}
		}
	}
	return $myIP;
}

function check_zfs_name($zfsname, $storage="default") {
	global $lv,$domain_cfg;
	if ($storage == "default") $storage = $domain_cfg['DOMAINDIR']; else $storage = "/mnt/$storage/";
	$storage=transpose_user_path($storage);
	$fstype = trim(shell_exec(" stat -f -c '%T' $storage"));
	#Check if ZFS.
	$allowed_chars = "/^[A-Za-z0-9][A-Za-z0-9\-_.: ]*$/";
	if ($fstype == "zfs" && !preg_match($allowed_chars, $zfsname)) {
		return false;
	} else {
		return true;
	}
}

function get_storage_fstype($storage="default") {
	global $domain_cfg;
	if ($storage == "default") $storage = $domain_cfg['DOMAINDIR']; else $storage = "/mnt/$storage/";
	$storage=transpose_user_path($storage);
	$fstype = trim(shell_exec(" stat -f -c '%T' $storage"));
	return $fstype;
}
?>

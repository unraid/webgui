<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * Modified for Unraid of Array2XML Published February 9, 2016 by Jeetendra Singh
 * Modified for Unraid of xmlToArray Published August 23, 2012 by Tamlyn Rhodes
 */
class custom {
  private static $xml = null;
  private static $encoding = 'UTF-8';

 /*
  * custom::init > initialize the root XML node [optional]
  * @param $version
  * @param $encoding
  * @param $format_output
  */
  public static function init($version = '1.0', $encoding = 'UTF-8', $format_output = true) {
    self::$xml = new DomDocument($version, $encoding);
    self::$xml->formatOutput = $format_output;
    self::$encoding = $encoding;
  }

 /*
  * custom::createXML > convert Array to XML
  * @param string $root - name of the root node
  * @param array $arr   - array object to be converterd
  * @return XML object
  */
  public static function &createXML($root, $arr) {
    $xml = self::getXMLRoot();
    $xml->appendChild(self::Array2XML($root, $arr));
    self::$xml = null; // clear the xml node in the class for 2nd time use
    return $xml;
  }

 /*
  * custom::createArray > convert XML to Array
  * @param string $root     - name of the root node
  * @param array $xmlstring - xml string to be converterd
  * @return Array object
  */
  public static function &createArray($root, $xmlstring) {
    $xml = simplexml_load_string($xmlstring);
    return self::XML2Array($xml)[$root];
  }

 /*
  * Recursive conversion Array to XML
  */
  private static function &Array2XML($node_name, $arr) {
    $xml = self::getXMLRoot();
    $node = $xml->createElement($node_name);
    if (is_array($arr)) {
      // get the attributes first
      if (isset($arr['@attributes'])) {
        foreach ($arr['@attributes'] as $key => $value) {
          if (!self::isValidTagName($key)) {
            throw new Exception("[custom] Illegal character in attribute name. Attribute: $key in node: $node_name");
          }
          $node->setAttribute($key, self::bool2str($value));
        }
        unset($arr['@attributes']); //remove the key from the array once done
      }
      // check if it has a value stored in @value, if yes store the value and return
      // else check if its directly stored as string
      if (isset($arr['@value'])) {
        $node->appendChild($xml->createTextNode(self::bool2str($arr['@value'])));
        unset($arr['@value']); //remove the key from the array once done
        // return from recursion, as a note with value cannot have child nodes
        return $node;
      } elseif (isset($arr['@cdata'])) {
        $node->appendChild($xml->createCDATASection(self::bool2str($arr['@cdata'])));
        unset($arr['@cdata']); //remove the key from the array once done.
        // return from recursion, as a note with cdata cannot have child nodes
        return $node;
      }
    }
    // create subnodes using recursion
    if (is_array($arr)) {
      // recurse to get the node for that key
      foreach ($arr as $key=>$value) {
        if (!self::isValidTagName($key)) {
          throw new Exception("[custom] Illegal character in tag name. Tag: $key in node: $node_name");
        }
        if (is_array($value) && is_numeric(key($value))) {
          // MORE THAN ONE NODE OF ITS KIND;
          // if the new array is numeric index, means it is array of nodes of the same kind
          // it should follow the parent key name
          foreach ($value as $k=>$v) {
            $node->appendChild(self::Array2XML($key, $v));
          }
        } else {
          // ONLY ONE NODE OF ITS KIND
          $node->appendChild(self::Array2XML($key, $value));
        }
        unset($arr[$key]); //remove the key from the array once done
      }
    }
    // after we are done with all the keys in the array (if it is one)
    // we check if it has any text value, if yes, append it
    if (!is_array($arr)) {
      $node->appendChild($xml->createTextNode(self::bool2str($arr)));
    }
    return $node;
  }

 /*
  * Recursive conversion XML to Array
  */
  private static function &XML2Array($xml) {
    // get the attributes first
    $attributes = [];
    foreach ($xml->attributes() as $attributeName => $attribute) {
      $attributes['@attributes'][$attributeName] = (string)$attribute;
    }
    $tags = [];
    // walk thru child nodes recursively
    foreach ($xml->children() as $child) {
      $arr = self::XML2Array($child);
      $node = key($arr);
      $data = current($arr);
      // store as single or multi array
      if (!isset($tags[$node])) {
        // specific for Unraid
        if (in_array($node,['hostdev','controller','disk','interface'])) $tags[$node][] = $data; else $tags[$node] = $data;
      } elseif (is_array($tags[$node]) && array_keys($tags[$node])===range(0, count($tags[$node])-1)) {
        $tags[$node][] = $data;
      } else {
        $tags[$node] = [$tags[$node], $data];
      }
    }
    $textContent = [];
    $plainText = trim((string)$xml);
    if ($plainText !== '') $textContent['@value'] = $plainText;
    // combine elements together
    $data = $attributes || $tags || ($plainText==='') ? array_merge($attributes, $tags, $textContent) : $plainText;
    return array($xml->getName() => $data);
  }

 /*
  * Get the root XML node, if there isn't one, create it
  */
  private static function getXMLRoot() {
    if (empty(self::$xml)) self::init();
    return self::$xml;
  }

 /*
  * Get string representation of boolean value
  */
  private static function bool2str($v) {
    //convert boolean to text value.
    $v = $v === true ? 'true' : $v;
    $v = $v === false ? 'false' : $v;
    return $v;
  }

 /*
  * Check if the tag name or attribute name contains illegal characters
  * Ref: http://www.w3.org/TR/xml/#sec-common-syn
  */
  private static function isValidTagName($tag) {
    $pattern = '/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i';
    return preg_match($pattern, $tag, $matches) && $matches[0] == $tag;
  }
}
?>
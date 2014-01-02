<?php
namespace PSIGate;

/**
 * Helper class which provides convenience methods
 * 
 * @author Pavel Kulbakin <p.kulbakin@gmail.com>
 */
class Helper
{
    /**
     * Convert XML to Array
     * 
     * @param mixed $xml
     * @return array
     */
    public static function xmlToArray($xml)
    {
        if ( ! ($xml instanceof \DOMNode)) {
            $doc = new \DOMDocument();
            if ( ! $doc->loadXML($xml)) {
                 throw new \DOMException('Error parsing XML string');
            }
            $xml = $doc;
        }
        
        $output = array();
        if (XML_DOCUMENT_NODE == $xml->nodeType) {
            $output[$xml->documentElement->tagName] = self::xmlToArray($xml->documentElement);
        } elseif (XML_ELEMENT_NODE == $xml->nodeType) {
            $output = array();
            
            // for each child node, call the covert function recursively
            for ($i = 0, $cnt = $xml->childNodes->length; $i < $cnt; $i++) {
                $child = $xml->childNodes->item($i);
                $v = self::xmlToArray($child);
                if (isset($child->tagName)) {
                    $t = $child->tagName;
                    
                    // assume multiple nodes with the same tag name
                    isset($output[$t]) or $output[$t] = array();
                    $output[$t][] = $v;
                } else {
                    if ('' !== $v) { // there is not empty text node
                        // assume multiple text nodes
                        isset($output['@text']) or $output['@text'] = array();
                        $output['@text'][] = $v;
                    }
                }
            }
            
            if (is_array($output)) {
                foreach ($output as $t => $v) {
                    if ('@text' == $t) { // text node
                        $output[$t] = implode("\n", $v);
                    } elseif (is_array($v) and 1 == count($v)) { // one node of its kind, assign it directly
                        $output[$t] = $v[0];
                    }
                }
            }
            
            // loop through the attributes and collect them
            if($xml->attributes->length) {
                $a = array();
                foreach($xml->attributes as $attrName => $attrNode) {
                    $a[$attrName] = (string) $attrNode->value;
                }
                $output['@attributes'] = $a;
            }
            
            if (1 == count($output) and isset($output['@text'])) {
                $output = $output['@text'];
            }
            
        } elseif (XML_CDATA_SECTION_NODE == $xml->nodeType) {
            $output = trim($xml->textContent);
        } elseif (XML_TEXT_NODE == $xml->nodeType) {
            $output = trim($xml->textContent);
        }
        
        return $output;
    }
    
    /**
     * Convert array to XML
     * 
     * @param array $array
     * @param string|DOMElement[optional] $node Root element name
     * @return DOMDocument
     */
    public static function arrayToXml($array, $node = 'root')
    {
        if ( ! ($node instanceof \DOMElement)) {
            $xml = new \DOMDocument('1.0', 'UTF-8');
            $xml->formatOutput = true;
            $node = $xml->appendChild($xml->createElement($node)); // create root element
        } else {
            $xml = $node->ownerDocument;
        }
        
        if (is_array($array)) {
            // process child elements
            foreach ($array as $key => $value) {
                if ('@attributes' == $key) { // process attributes
                    foreach($value as $attrName => $attrValue) {
                        $node->setAttribute($attrName, $attrValue);
                    }
                } elseif (isset($array['@text'])) { // process text
                    $v = is_array($array['@text']) ? implode("\n", $array['@text']) : $array['@text'];
                    $node->appendChild($xml->createTextNode($v));
                } elseif (is_array($value) && is_numeric(key($value))) { // multiple nodes with the same name
                    foreach ($value as $v){
                        self::arrayToXml($v, $node->appendChild($xml->createElement($key)));
                    }
                } else {
                    self::arrayToXml($value, $node->appendChild($xml->createElement($key)));
                }
            }
        } elseif ( ! is_null($array)) { // scalar value
            $node->appendChild($xml->createTextNode($array));
        }
        
        return $xml;
    }
}

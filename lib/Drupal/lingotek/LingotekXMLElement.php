<?php

/**
 * @file
 * Defines LingotekXMLElement
 */

/**
 * An extension of SimpleXMLElement to add CDATA
 */
class LingotekXMLElement extends SimpleXMLElement {
  public function addCData($text) {
    $xml = dom_import_simplexml($this);
    $doc = $xml->ownerDocument;
    $xml->appendChild($doc->createCDATASection($text));
  }
}

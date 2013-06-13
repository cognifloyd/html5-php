<?php
/**
 * @file
 * The rules for generating output in the serializer.
 *
 * These output rules are likely to generate output similar to the document that
 * was parsed. It is not intended to output exactly the document that was parsed.
 */
namespace HTML5\Serializer;

use \HTML5\Elements;

class OutputRules implements \HTML5\Serializer\RulesInterface {

  protected $traverser;
  protected $encode = FALSE;
  protected $out;
  protected $inSvg = FALSE;
  protected $inMathMl = FALSE;

  const DOCTYPE = '<!DOCTYPE html>';

  public function __construct($traverser, $output, $options = array()) {
    $this->traverser = $traverser;

    if (isset($options['encode_entities'])) {
      $this->encode = $options['encode_entities'];
    }

    $this->out = $output;
  }

  public function document($dom) {
    $this->doctype();
    $this->traverser->node($dom->documentElement);
    $this->nl();
  }

  protected function doctype() {
    $this->wr(self::DOCTYPE);
    $this->nl();
  }

  public function element($ele) {
    $name = $ele->tagName;

    // Per spec:
    // If the element has a declared namespace in the HTML, MathML or
    // SVG namespaces, we use the lname instead of the tagName.
    if ($this->traverser->isLocalElement($ele)) {
      $name = $ele->localName;
    }

    // If we are in SVG or MathML there is special handling. 
    switch($name) {
      case 'svg':
        $this->inSvg = TRUE;
        $name = Elements::normalizeSvgElement($name);
        break;
      case 'mathml':
        $this->inMathMl = TRUE;
        break;
    }

    $this->openTag($ele);

    // Handle children.
    if ($ele->hasChildNodes()) {
      $this->traverser->children($ele->childNodes);
    }

    // Close out the SVG or MathML special handling.
    switch($name) {
      case 'svg':
        $this->inSvg = FALSE;
        break;
      case 'mathml':
        $this->inMathMl = FALSE;
        break;
    }

    // If not unary, add a closing tag.
    if (!Elements::isA($name, Elements::VOID_TAG)) {
      $this->closeTag($ele);
    }
  }

  /**
   * Write a text node.
   *
   * @param \DOMText $ele 
   *   The text node to write.
   */
  public function text($ele) {
    if (isset($ele->parentNode) && Elements::isA($ele->parentNode->tagName, Elements::TEXT_RAW)) {
      $this->wr($ele->data);
      return;
    }

    // FIXME: This probably needs some flags set.
    $this->wr($this->enc($ele->data));

  }

  public function cdata($ele) {
    $this->wr('<![CDATA[')->wr($ele->data)->wr(']]>');
  }

  public function comment($ele) {
    $this->wr('<!--')->wr($ele->data)->wr('-->');
  }

  public function processorInstruction($ele) {
    $this->wr('<?')->wr($ele->target)->wr(' ')->wr($ele->data)->wr(' ?>');
  }

  /**
   * Write the opening tag.
   *
   * Tags for HTML, MathML, and SVG are in the local name. Otherwise, use the
   * qualified name (8.3).
   * 
   * @param \DOMNode $ele
   *   The element being written.
   */
  protected function openTag($ele) {
    // FIXME: Needs support for SVG, MathML, and namespaced XML.
    $this->wr('<')->wr($ele->tagName);
    $this->attrs($ele);
    $this->wr('>');
  }

  protected function attrs($ele) {
    // FIXME: Needs support for xml, xmlns, xlink, and namespaced elements.
    if (!$ele->hasAttributes()) {
      return $this;
    }

    // TODO: Currently, this always writes name="value", and does not do
    // value-less attributes.
    $map = $ele->attributes;
    $len = $map->length;
    for ($i = 0; $i < $len; ++$i) {
      $node = $map->item($i);
      $val = $this->enc($node->value);

      // XXX: The spec says that we need to ensure that anything in
      // the XML, XMLNS, or XLink NS's should use the canonical
      // prefix. It seems that DOM does this for us already, but there
      // may be exceptions.
      $name = $node->name;

      // Special handling for attributes in SVG and MathML.
      if ($this->inSvg) {
        $name = Elements::normalizeSvgAttribute($name);
      }
      elseif ($this->inMathMl) {
        $name = Elements::normalizeMathMlAttribute($name);
      }

      $this->wr(' ')->wr($name)->wr('="')->wr($val)->wr('"');
    }
  }

  /**
   * Write the closing tag.
   * 
   * Tags for HTML, MathML, and SVG are in the local name. Otherwise, use the
   * qualified name (8.3).
   *
   * @param \DOMNode $ele
   *   The element being written.
   */
  protected function closeTag($ele) {
    // FIXME: Needs support for SVG, MathML, and namespaced XML.
    $this->wr('</')->wr($ele->tagName)->wr('>');
  }

  /**
   * Write to the output.
   *
   * @param string $text
   *   The string to put into the output.
   *
   * @return HTML5\Serializer\Traverser
   *   $this so it can be used in chaining.
   */
  protected function wr($text) {
    fwrite($this->out, $text);
    return $this;
  }

  /**
   * Write a new line character.
   *
   * @return HTML5\Serializer\Traverser
   *   $this so it can be used in chaining.
   */
  protected function nl() {
    fwrite($this->out, PHP_EOL);
    return $this;
  }

  /**
   * Encode text.
   *
   * True encoding will turn all named character references into their entities.
   * This includes such characters as +.# and many other common ones. By default
   * encoding here will just escape &'<>".
   *
   * Note, PHP 5.4+ has better html5 encoding.
   *
   * @todo Use the Entities class in php 5.3 to have html5 entities.
   *
   * @param string $text
   *   text to encode.
   *
   * @return string
   *   The encoded text.
   */
  protected function enc($text) {
    $flags = ENT_QUOTES;

    // Escape rather than encode all entities.
    if (!$this->encode) {
      return htmlspecialchars($text, $flags, 'UTF-8');
    }

    // If we are in PHP 5.4+ we can use the native html5 entity functionality.
    if (defined('ENT_HTML5')) {
      $flags = ENT_HTML5 | ENT_SUBSTITUTE | ENT_QUOTES;
      $ret = htmlentities($text, $flags, 'UTF-8', FALSE);
    }
    // If a version earlier than 5.4 html5 entities are not entirely handled.
    // This manually handles them.
    else {
      $ret = strtr($text, \HTML5\Serializer\HTML5Entities::$map);
    }
    return $ret;
  }

}
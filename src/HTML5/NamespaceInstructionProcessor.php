<?php
/**
 * A handler for processor instructions.
 */
namespace HTML5;

/*
This is how this could be used:

function my_parser(\HTML5\Parser\InputStream $input) {

  // Create this first so that the InstructionProcessor can have access to it.
  $events = new DOMTreeBuilder();

  // Create an instance of the processing instruction.
  $nsProcessor = new NamespaceInstructionProcessor($events);

  // Attach it to the event based DOM tree builder.
  $events->setInstructionProcessor($nsProcessor);

  $scanner = new Scanner($input);
  $parser = new Tokenizer($scanner, $events);
  $parser->parse();

  return $events->document();
}

Or I suppose I could do some kind of pre-processing. Use Fluid to extract the namespace definitions.
TYPO3/Fluid/Core/Parser/TemplateParser->getNamespaces(); //This would require generating the xmlns uri
TYPO3/Fluid/Core/Parser/TemplateParser->extractNamespaceDefinitions($templateString);
I just rewrote extractNamespaceDefinitions to add a parseNamespaceInfo public function that returns
$namespaces[$nsPrefix] = array('phpNamespace' => $phpNamespace, 'xmlNamespace' => $xmlNamespace)


*/

/**
 * Provide an processor to handle embedded instructions.
 *
 * XML defines a mechanism for inserting instructions (like PHP) into a 
 * document. These are called "Processor Instructions." The HTML5 parser 
 * provides an opportunity to handle these processor instructions during 
 * the tree-building phase (before the DOM is constructed), which makes 
 * it possible to alter the document as it is being created.
 *
 * One could, for example, use this mechanism to execute well-formed PHP
 * code embedded inside of an HTML5 document.
 */
class NamespaceInstructionProcessor {

  /**
   * @var DomTreeBuilder
   */
  $events;

  /**
   * 
   */
  public function __construct(DomTreeBuilder &$events) {
    $this->events = $events;
  }


  /**
   * Process an individual processing instruction.
   *
   * The process() function is responsible for doing the following:
   * - Determining whether $name is an instruction type it can handle.
   * - Determining what to do with the data passed in.
   * - Making any subsequent modifications to the DOM by modifying the 
   * DOMElement or its attached DOM tree.
   *
   * @param DOMElement $element
   *   The parent element for the current processing instruction.
   * @param string $name
   *   The instruction's name. E.g. `&lt;?php` has the name `php`.
   * @param string $data
   *   All of the data between the opening and closing PI marks.
   * @return DOMElement
   *   The element that should be considered "Current". This may just be
   *   the element passed in, but if the processor added more elements,
   *   it may choose to reset the current element to one of the elements
   *   it created. (When in doubt, return the element passed in.)
   */
  public function process(\DOMElement $element, $name, $data) {
    if($name !== 'namespace') return $element;
    
    list($nsPrefix, $nsUri) = $this->processNamespaceFromData($data);
    
    //how do I call this:
    $this->events->registerNamespace($nsPrefix, $nsUri);
    
    //Done processing. Tell the events loop to drop the processing instructino from the tree by returning Null.
    return NULL;
  }
  
  protected function processNamespaceFromData($data) {
    //maybe check for new lines or semicolons to support multiple namespace declarations per call
    //Right now, this only supports one namespace per processor instruction.
    $dataParts = explode('=',$data,2);
    $nsPrefix = trim($dataParts[0]);
    $nsUri = trim($dataParts[1]);
    
    return array($nsPrefix, $nsUri);
  }
}

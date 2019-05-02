<?php
/******************************************************************************
 * domo.php
 * --------
 * Loads the entire library. The library can also be loaded class-by-class
 * if necessary. Individual dependencies are still included by each page,
 * so use of this file is not strictly necessary.
 ******************************************************************************/

require_once('utilities.php');
require_once('linked_list.php');
require_once('whatwg.php');
require_once('Node.php');
require_once('DOMImplementation.php');
require_once('Attr.php');
require_once('ChildNode.php');
require_once('HTMLCollection.php');
require_once('NonDocumentTypeChildNode.php');
require_once('DocumentType.php');
require_once('DocumentFragment.php');
require_once('NodeList.php');
require_once('NamedNodeMap.php');
require_once('Document.php');
require_once('Element.php');
require_once('CharacterData.php');
require_once('Comment.php');
require_once('Text.php');
require_once('ProcessingInstruction.php');


?>

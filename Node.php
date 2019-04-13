<?php
/******************************************************************************
 * Node.php
 * ````````
 * Defines a "Node", the primary datatype of the W3C Document Object Model.
 *
 * Conforms to W3C Document Object Model (DOM) Level 1 Recommendation
 * (see: https://www.w3.org/TR/2000/WD-DOM-Level-1-20000929)
 *
 *
 * PORT NOTES
 *****************************************************************************/
use \DOM\EventTarget
use \DOM\LinkedList
use \DOM\NodeUtils
use \DOM\utils

/*
 * A "Node" is an abstract interface implemented by objects which also
 * implement the more specific interfaces:
 *
 *      Document,
 *      DocumentFragment,
 *      DocumentType,
 *      Element,
 *      Text,
 *      ProcessingInstruction,
 *      Comment.
 *
 * These objects are collectively referred to as "nodes."
 *
 * The name "node" refers to the fact that these are the objects which
 * participate in the document tree (as nodes, in the graph theoretic sense).
 */
interface NodeInterface {

        /*********************************************************************
         * DOM PROPERTIES
         *********************************************************************/

        //public $baseURI;      // TODO: not implemented
        //public $childNodes;   // TODO: not implemented (list stuff?)
        public $firstChild;
        //public $isConnected;  // TODO: not implemented
        public $lastChild;
        public $nextSibling;
        public $nodeName;
        public $nodeType;
        public $nodeValue;
        public $ownerDocument;
        //public $parentNode;   // TODO: not implemented?
        public $parentElement;
        public $previousSibling
        public $textContent;
        //public $namespaceURI; // TODO: Obsolete in DOM spec
        //public $localName;    // TODO: Obsolete in DOM spec

        /*********************************************************************
         * DOM METHODS
         *********************************************************************/

        public function appendChild(Node $node);
        public function cloneNode(boolean $deep);
        public function compareDocumentPosition(Node $node);
        public function contains(Node $node);
        // public function getRootNode(); /* TODO: Not implemented */
        public function hasChildNodes();
        public function insertBefore();
        public function isDefaultNamespace($namespace);
        public function isEqualNode(Node $node);
        public function isSameNode(Node $node);
        public function lookupPrefix($namespace);
        public function lookupNamespaceURI($prefix);
        public function normalize();
        public function removeChild(Node $node);
        public function replaceChild(Node $node);

        /*********************************************************************
         * DOMINO METHODS (extensions, not part of the DOM)
         *********************************************************************/

        public function index();
        public function isAncestor(Node $node);
        public function ensureSameDoc(Node $node);
        public function removeChildren();
        public function lastModTime();
        public function modify();
        public function doc();
        public function rooted();
        public function serialize();
        public function outerHTML();
}

abstract class Node {



?>

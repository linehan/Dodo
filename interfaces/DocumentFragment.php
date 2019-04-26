<?php

namespace domo;

class DocumentFragment extends Node 
{
        public $_nodeType = DOCUMENT_FRAGMENT_NODE;
        public $_nodeName = '#document-fragment';
        public $_nodeValue = NULL;

        public function __construct(Document $doc)
        {
                parent::__construct($doc);
        
                $this->_ownerDocument = $doc;
        }

        /* TODO: Same as Element's. Factor? */
        public function textContent(?string $value = NULL)
        {
                /* GET */
                if ($value === NULL) {
                        $text = array();
                        \domo\algorithm\descendant_text_content($this, $text);
                        return implode("", $text);
                /* SET */
                } else {
                        $this->__remove_children();
                        if ($value !== "") {
                                /* Equivalent to Node:: appendChild without checks! */
                                \domo\whatwg\insert_before_or_replace($node, $this->_ownerDocument->createTextNode($value), NULL);
                        }
                }
        }
                
        public function querySelector($selector)
        {
                // implement in terms of querySelectorAll
                /* TODO stub */
                $nodes = $this->querySelectorAll($selector);
                return count($nodes) ? $nodes[0] : NULL;
        }
  
        public function querySelectorAll($selector)
        {
                /* TODO: Stub */
                //// create a context
                //var context = Object.create(this);
                //// add some methods to the context for zest implementation, without
                //// adding them to the public DocumentFragment API
                //context.isHTML = true; // in HTML namespace (case-insensitive match)
                //context.getElementsByTagName = Element.prototype.getElementsByTagName;
                //context.nextElement =
                        //Object.getOwnPropertyDescriptor(Element.prototype, 'firstElementChild').get;
                //// invoke zest
                //var nodes = select(selector, context);
                //return nodes.item ? nodes : new NodeList(nodes);
        }

        /* TODO DELEGATED FROM NODE */
        public function _subclass_cloneNodeShallow(): ?Node
        {
                return new DocumentFragment($this->_ownerDocument);
        }
        public function _subclass_isEqualNode(Node $node): bool
        {
                // Any two document fragments are shallowly equal.
                // Node.isEqualNode() will test their children for equality
                return true;
        }

        // Non-standard, but useful (github issue #73)
        public function innerHTML()
        {
                return $this->__serialize();
        }
  
        public function outerHTML(?string $value = NULL)
        {
                return $this->__serialize(); 
        }
}

?>

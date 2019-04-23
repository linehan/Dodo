<?php
/******************************************************************************
 * Attr.php
 * ````````
 * Defines an "Attr", a class representing an Attribute Node.
 ******************************************************************************/
/*
PORT NOTES
----------
CHANGED:
        - This was once part of Element.js
        - Incorporated _setOwnerDocument into constructor
        - The usual read-only attribute changes for
                localName
                namespaceURI
                prefix
                value (renamed 'data' to '_value')
                ownerElement
                (but not name, which is not stored)

REMOVED:
        - there were notes indicating that prefix was mutable,
          but this is not in the DOM-LS spec, so I removed it.
        - obsolete interfaces
                specified()
                cloneNode()
                nodeType()
                nodeName()
                nodeValue()
                textContent()

*/

/*
 * The fact that
 *      Element::setAttributeNode()
 *      Element::removeAttributeNode()
 *      ...
 *      exist, but property values like ownerDocument are treated
 *      as read-only means that there needs to be some kind of
 *      mechanism to update things, presents us a problem.
 *
 *      If Attr should be immutable (except value), then we should
 *      create a new Attr with the same properties but NULL
 *      ownerDocument when we remove, etc., and pass around the
 *      new copy? That is what the w3c spec seems to imply, but
 *      it is not explicit.
 *
 *      Should we bother? Is this spec? I don't know.
 */

/*
 * The Attr class represents a single attribute node.
 *
 * Element stores Attr objects in its internal storage arrays that cache
 * which attributes are present on the element.
 *
 * DOM3: Attr extended Node, with namespaceURI, localName, and prefix on
 *       the Node class.
 * DOM4: This was abandoned.
 */

class Attr extends Node
{
        /* TODO: Re-order these arguments because you can make an Attr with
         * just an lname, and the defaults are spec'd as
         *      ownerElement = NULL
         *      namespaceURI = NULL
         *      prefix = NULL
         *      value = ""
         */
        public function __construct($elt, string $lname, string $prefix, string $namespace, string $value)
        {
                /* DOM4: (readonly) non-empty string */
                $this->_localName = $lname;

                /* DOM4: (readonly) NULL or non-empty string */
                $this->_namespaceURI = ($namespace === "") ? NULL : $namespace;

                /* DOM4: (readonly) NULL or non-empty string */
                $this->_prefix = ($prefix==="") ? NULL : $prefix;

                /* DOM4: string */
                $this->_value = ($value === NULL) ? "" : $value;

                /* DOM4: (readonly) NULL or Element */
                $this->_ownerElement = $elt;
        }

        public function _subclass_cloneNodeShallow(void): ?Attr
        {
                return new Attr(
                        NULL, 
                        $this->_localName, 
                        $this->_prefix, 
                        $this->_namespaceURI, 
                        $this->_value
                )
        }


        public function ownerElement()
        {
                return $this->_ownerElement;
        }

        public function localName()
        {
                return $this->_localName;
        }
        public function prefix()
        {
                return $this->_prefix;
        }

        /* Must return qualified name */
        public function name()
        {
                if ($this->_prefix) {
                        return "$this->_prefix:$this->_localName";
                } else {
                        return $this->_localName;
                }
        }

        public function specified()
        {
                /* DOM spec: always returns true */
                return true;
        }

        /* NOTE: You can unset an attribute by calling Attr::value(""); */
        public function value(string $v = NULL)
        {
                if ($v === NULL) {
                        /* GET */
                        return $this->_value;
                } else {
                        /* SET */
                        $old = $this->_value;
                        $new = $v;

                        if ($new === $old) {
                                return;
                        }

                        $this->_value = $new;

                        /* Run the onchange hook for the attribute */
                        /* TODO: Get rid of this and just look for mutationevent as below? */
                        /* TODO: Changed this to look up each time; will this impact performance?
                           makes it easier to treat Attrs as value-like objects
                         */
                        /*
                         * Register change handlers for attributes.
                         * In practice this is used almost exclusively to track
                         * updates to the 'id' attribute, to allow Document objects
                         * to do their book-keeping with their internal id<->Node
                         * lookup tables used by e.g. Document::getElementById()
                         */
                        if ($this->_ownerElement) {
                                if (isset($this->_ownerElement->_attributeChangeHandlers[$this->_localName])) {
                                        $this->_ownerElement->_attributeChangeHandlers[$this->_localName](
                                                $this->_ownerElement,
                                                $this->_localName,
                                                $old,
                                                $new
                                        );
                                }
                        }

                        /* Generate a mutation event if the element is rooted */
                        if ($this->_ownerElement->rooted()) {
                                $this->_ownerElement->ownerDocument()->mutateAttr($this, $old);
                        }
                }
        }
}

?>

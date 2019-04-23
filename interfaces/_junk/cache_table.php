<?php
/*
   Many of these follow conventions of HTMLCollection:

   HTMLCollection::length
   HTMLCollection::item()
   HTMLCollection::namedItem()

   Especially the ones that are able to be exposed
*/

/*
 * FROM THE SPEC:
 * Note: DOM Level 1 methods are namespace ignorant. Therefore, while it is
 * safe to use these methods when not dealing with namespaces, using them and
 * the new ones at the same time should be avoided. DOM Level 1 methods solely
 * identify attribute nodes by their nodeName. On the contrary, the DOM Level 2
 * methods related to namespaces, identify attribute nodes by their
 * namespaceURI and localName. Because of this fundamental difference, mixing
 * both sets of methods can lead to unpredictable results. In particular, using
 * setAttributeNS, an element may have two attributes (or more) that have the
 * same nodeName, but different namespaceURIs. Calling getAttribute with that
 * nodeName could then return any of those attributes. The result depends on
 * the implementation. Similarly, using setAttributeNode, one can set two
 * attributes (or more) that have different nodeNames but the same prefix and
 * namespaceURI. In this case getAttributeNodeNS will return either attribute,
 * in an implementation dependent manner. The only guarantee in such cases is
 * that all methods that access a named item by its nodeName will access the
 * same item, and all methods which access a node by its URI and local name
 * will access the same node. For instance, setAttribute and setAttributeNS
 * affect the node that getAttribute and getAttributeNS, respectively, return.
 *
 * TODO: THIS IS BATSHIT INSANE. FIX THIS.
 */


/*
 * Add a qname->Attr mapping to the _attrsByQName object, taking into
 * account that there may be more than one attr object with the
 * same qname
 */
public function _addQName($attr)
{
        $qname = $attr->name();
        $existing = $this->_attrsByQName[$qname] ?? NULL;

        if (!$existing === NULL) {
                $this->_attrsByQName[$qname] = $attr;
        } else if (is_array($existing)) {
                $existing[] = $attr;
        } else {
                $this->_attrsByQName[$qname] = array($existing, $attr);
        }

        if ($this->_attributes) {
                $this->_attributes[$qname] = $attr;
        }
}

/*
 * Remove a qname->Attr mapping to the _attrsByQName object,
 * taking into account that there may be more than one attr
 * object with the same qname
 */
public function _removeQName($attr)
{
        $qname = $attr->name();
        $target = $this->_attrsByQName[$qname] ?? NULL;

        if (is_array($target)) {
                $idx = array_search($attr, $target);

                utils\assert($idx !== -1); // It must be here somewhere

                if (count($target) === 2) {
                        $this->_attrsByQName[$qname] = $target[1-$idx];
                        if ($this->_attributes) {
                                $this->_attributes[$qname] = $this->_attrsByQName[$qname];
                        }
                } else {
                        array_splice($target, $idx, 1);
                        if ($this->_attributes && $this->_attributes[$qname] === $attr) {
                                $this->_attributes[$qname] = $target[0];
                        }
                }
        } else {
                utils\assert($target === $attr);  // If only one, it must match
                unset($this->_attrsByQName[$qname]);
                if ($this->_attributes) {
                        unset($this->_attributes[$qname]);
                }
        }
}



// The children property of an Element will be an instance of this class.
// It defines length, item() and namedItem() and will be wrapped by an
// HTMLCollection when exposed through the DOM.
/*
 THIS IS AN HTMLCollection -- an array-like collection of Elements
 (not to be confused with a NodeList
*/
class ChildrenCollection
{
        public $length = 0;

        public $childrenByNumber;
        public $childrenByName;
        public $lastModTime;

        public function __construct(Element $element)
        {
                $this->element = $element;
                $this->updateCache();
        }

        /* TODO: Try and implement this as a normal property?? */
        //public function length
        //{
                //this.updateCache();
                //return this.childrenByNumber.length;
        //}

        public function item($n)
        {
                $this->updateCache();
                return $this->childrenByNumber[$n] ?? NULL;
        }

        public function namedItem($name)
        {
                $this->updateCache();
                return $this->childrenByName[$name] ?? NULL;
        }

        /*
         * This attribute returns the entire name->element map.
         * It is not part of the HTMLCollection API, but we need it in
         * src/HTMLCollectionProxy
         */
        public function namedItems()
        {
                $this->updateCache();
                return $this->childrenByName;
        }

        public function updateCache()
        {
                /* TODO: Fix this */
                $namedElts = /^(a|applet|area|embed|form|frame|frameset|iframe|img|object)$/;

                if ($this->lastModTime !== $this->element->lastModTime) {
                        $this->lastModTime = $this->element->lastModTime;

                        $n = $this->childrenByNumber ? count($this->childrenByNumber) : 0;

                        for ($i=0; $i<$n; $i++) {
                                unset($this[$i]);
                        }

                        $this->childrenByNumber = array();
                        $this->childrenByName = array();

                        for ($c=$this->element->firstChild; $c!==NULL; $c=$c->nextSibling) {
                                if ($c->nodeType === Node\ELEMENT_NODE) {

                                        $this[count($this->childrenByNumber)] = $c;
                                        $this->childrenByNumber[] = $c;

                                        // XXX Are there any requirements about the namespace
                                        // of the id property?
                                        $id = $c->getAttribute("id");

                                        // If there is an id that is not already in use...
                                        if ($id && !$this->childrenByName[$id]) {
                                                $this->childrenByName[$id] = $c;
                                        }

                                        // For certain HTML elements we check the name attribute
                                        $name = $c->getAttribute("name");
                                        if ($name
                                        && $this->element->namespaceURI === NAMESPACE_HTML
                                        && $namedElts->test($this->element->localName)
                                        && !$this->childrenByName[$name]) {
                                                $this->childrenByName[$id] = $c;
                                        }
                                }
                        }
                }
        }
}


/*
 * The attributes property of an Element will be an instance of this class.
 * This class is really just a dummy, though. It only defines a length
 * property and an item() method. The AttrArrayProxy that
 * defines the public API just uses the Element object itself.
 */
/*
   This is a NamedNodeMap. Calling it an AttributesArray is stupid!
*/
class AttributesArray extends NamedNodeMap
{
        public function __construct(?Element $element=NULL)
        {
                NamedNodeMap.call(this, elt);

                for ($name in $elt->_attrsByQName) {
                        $this[$name] = $elt->_attrsByQName[$name];
                }

                for ($i=0; $i<count($elt->_attrKeys); $i++) {
                        $this[$i] = $elt->_attrsByLName[$elt->_attrKeys[$i]];
                }
        }

        public function length()
        {
                return count($this->element->_attrKeys);
        }

        public function item($n)
        {
                /* jshint bitwise: false */
                $n = $n >>> 0;
                if ($n >= $this->length()) {
                        return NULL;
                }
                return $this->element->_attrsByLName[$this->element->_attrKeys[$n]];
                /* jshint bitwise: true */
        }
}

//// We can't make direct array access work (without Proxies, node >=6)
//// but we can make `Array.from(node.attributes)` and for-of loops work.
//if (global.Symbol && global.Symbol.iterator) {
    //AttributesArray.prototype[global.Symbol.iterator] = function() {
        //var i=0, n=this.length, self=this;
        //return {
            //next: function() {
                //if (i<n) return { value: self.item(i++) };
                //return { done: true };
            //}
        //};
    //};
//}


// A class for storing multiple nodes with the same ID
/* TODO: ALL THIS IS DOING IS GIVING THE ABILITY TO RETURN THE FIRST
   ITEM IN DOCUMENT ORDER.
*/
class MultiId
{
        public function __construct(Node $node)
        {
                $this->nodes = array();
                $this->nodes[$node->_nid] = $node;
                $this->length = 1;
                $this->firstNode = NULL;
        }

        // Add a node to the list, with O(1) time
        public function add(Node $node)
        {
                if (!isset($this->nodes[$node->_nid])) {
                        $this->nodes[$node->_nid] = $node;
                        $this->length++;
                        $this->firstNode = NULL;
                }
        }

        // Remove a node from the list, with O(1) time
        public function del(Node $node)
        {
                if ($this->nodes[$node->_nid]) {
                        unset($this->nodes[$node->_nid]);
                        $this->length--;
                        $this->firstNode = NULL;
                }
        }

        // Get the first node from the list, in the document order
        // Takes O(N) time in the size of the list, with a cache that is invalidated
        // when the list is modified.
        public function getFirst()
        {
                /* jshint bitwise: false */
                if (!$this->firstNode) {
                        foreach ($this->nodes as $nid) {
                                if ($this->firstNode === NULL || $this->firstNode->compareDocumentPosition($this->nodes[$nid]) & Node\DOCUMENT_POSITION_PRECEDING) {
                                        $this->firstNode = $this->nodes[$nid];
                                }
                        }
                }
                return $this->firstNode;
        }

        // If there is only one node left, return it. Otherwise return "this".
        public function downgrade()
        {
                if ($this->length === 1) {
                        foreach ($this->nodes as $nid) {
                                return $this->nodes[$nid];
                        }
                }
                return $this;
        }
}

?>

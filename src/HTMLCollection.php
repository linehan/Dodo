<?php
/* DO NOT IMPLEMENT -- NOT BEING CALLED, SLOW!! */
/******************************************************************************
 * HTMLCollection.php
 * ------------------
 * FROM THE SPEC:
 *
 *      "HTMLCollection is a historical artifact we cannot rid the web of. 
 *      While developers are of course welcome to keep using it, new API 
 *      standard designers ought not to use it (use sequence<T> in IDL 
 *      instead)."
 *
 * USAGE 
 *
 * Returned by:
 *      Document::getElementsByTagName()
 *      Document::getElementsByTagNameNS()
 *      Document::getElementsByClassNames()
 *      Element::getElementsByTagName()
 *      Element::getElementsByTagNameNS()
 *      Element::getElementsByClassNames()
 *
 * Type of:
 *      Element::children
 *      
 * An HTMLCollection is always "in the DOM," that is, it is
 * always associated with some kind of Document root.
 *
 * A NodeList, on the other hand, may or may not be in the DOM.
 *
 * If a collection contains objects in the DOM, it is assumed
 * to be "live", that is, if the underlying Document changes,
 * then the collection must also update.
 *
 * Live-ness is not really a problem, EXCEPT for 
 * the namedItems() method. That needs to look up
 * an element by its 'id', or as a fallback, for
 * certain kinds of elements, its 'name' attribute. 
 * 
 * What a real pain in the ass.
 *
 * PORT NOTE
 * In domino.js, this was done with all this modtime,
 * lastModTime, update, blah blah blah, but didn't even
 * capture the key thing of the 'id' or 'name' changing
 * on the underlying nodes. Those changes did not change
 * the Node's lastModTime, and hence would not trigger the 
 * cache here to update, breaking live-ness. Great.
 ******************************************************************************/
namespace domo;

/* DOM-LS:
 * 4.2.10. Old-style collections: NodeList and HTMLCollection
 * A collection is an object that represents a list of nodes. 
 * A collection can be either live or static. Unless otherwise stated, 
 * a collection must be live.
 *
 * If a collection is live, then the attributes and methods on that object 
 * must operate on the actual underlying data, not a snapshot of the data.
 *
 * When a collection is created, a filter and a root are associated with it.
 *
 * The collection then represents a view of the subtree rooted at the 
 * collection’s root, containing only nodes that match the given filter. 
 * The view is linear. In the absence of specific requirements to the contrary, 
 * the nodes within the collection must be sorted in tree order.
 */

/*
 * Element::children will be an instance of this class.
 * It defines length, item() and namedItem().
 *
 * Compare the similar NamedNodeMap, used to implement
 * Element::attributes.
 *
 * TODO: Clean up to bring in line with style guidelines used elsewhere.
 */
/* THE SINGLE REASON THAT Node::__mod_time EXISTS IS THIS THING */

/* 
 * TODO: WHY would you use this, and not the other children representations? 
 * is it because of its live-ness guarantee? 
 */
class HTMLCollection extends \ArrayObject
{
        private $name_to_item = array();
        private $subtree_last_mod_time;
        private $root;
        private $filter;

        public function __construct(Element $root, ?string $filter)
        {
                $this->root   = $root;
                $this->filter = $filter;
                $this->updateCache();
        }

        public function length()
        {
                $this->updateCache();
                return count($this);
        }

        public function item(int $i): ?Element
        {
                $this->updateCache();
                return $this[$i] ?? NULL;
        }

        public function namedItem(string $name): ?Element
        {
                $this->updateCache();
                return $this->name_to_item[$name] ?? NULL;
        }

        /* TODO: Why is this here? */
        public function namedItems()
        {
                $this->updateCache();
                return $this->name_to_item;
        }

        public function updateCache()
        {
                if ($this->subtree_last_mod_time !== $this->root->__mod_time) {
                        $this->subtree_last_mod_time = $this->root->__mod_time;

                        foreach ($this as $i => $v) {
                                unset($this[$i]);
                        }

                        $this->name_to_item = array();

                        for ($n=$this->root->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                if ($n->_nodeType === ELEMENT_NODE) {
                                        $this[] = $n;

                                        /* XXX Are there any requirements about the namespace of the id property? */
                                        $id = $n->getAttribute("id");

                                        /* If there is an id that is not already in use... */
                                        if ($id && !$this->name_to_item[$id]) {
                                                $this->name_to_item[$id] = $n;
                                        }

                                        /* For certain HTML elements we check the name attribute */
                                        $name = $n->getAttribute("name");
                                        if ($name
                                        && $this->root->namespaceURI() === NAMESPACE_HTML
                                        && preg_match('/^(a|applet|area|embed|form|frame|frameset|iframe|img|object)$/', $this->root->localName())
                                        && !$this->name_to_item[$name]) {
                                                $this->name_to_item[$id] = $n;
                                        }
                                }
                        }
                }
        }
}

?>

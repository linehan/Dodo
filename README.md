# DOMOperator (DOMO)

DOMOperator is an effort to port [Domino.js](https://github.com/fgnass/domino) to PHP, in order to provide a more performant and spec-compliant DOM library than the DOMDocument PHP extension, which is built on [libxml2](www.xmlsoft.org).

## Technical reference
### Auxiliary data structures

These are data structures that are not part of the DOM, but which
participate in its implementation.

#### Document
* `Document::__nid_to_node`
.. A simple hash table that indexes all of a Document's child Nodes
   using their (Document-assigned) unique `Node::__nid` values.

* `Document::__id_to_element`
.. Hash table which indexes a document's child nodes by their "id" content
   attribute. In the event of a collision, the hash bucket is converted
   to an object of class `MultiId`. Used by `Document::getElementById()`.

* `MultiId` (defined in `/src/Document.php`).
.. Maintains an array of `Node`s, indexed by their (internal-use)
   "node identifier" (NID) value (`Node::__nid`, assigned by `Document`
   on adoption), which uniquely identify each node in a document.

   (The DOM-LS spec does not require the "id" content attribute
   to be a unique identifier.)

   Implements the method `MultiId::get_first()`, which will return the
   first `Node` in the list, in document order. This is the behavior expected
   of `Document::getElementById($id)` in the event of a collision.

#### Node
* `Node::__nid`
* `Node::childNodes`
.. `NodeList`, or `LinkedList`
* `LinkedList`
* `Node::_previousSibling`
* `Node::_nextSibling`
* `Node::_firstChild`

#### Element
* `Element::children` (`HTMLCollection`)

* `Element::attributes` (`NamedNodeMap`)
.. This extends the PHP `ArrayObject` class, meaning that we can iterate
   over an element's attributes using
        `foreach ($element->attributes as $attr) {`
                `...`
        `}`
* `NamedNodeMap::__qname_to_attr`
* `NamedNodeMap::__lname_to_attr`
* `NamedNodeMap::__lname_to_index`
.. We require three tables to performantly implement this data structure,
   because we must be able to perform 3 kinds of lookups:
   1. Qualified name to `Attr` (an non-namespace-aware attribute) (`NamedNodeMap::getNamedItem(string)`)
   2. Namespace, Local name to `Attr` (a namespace-aware attribute) (`NamedNodeMap::getNamedItemNS(string, string)`)
   3. Integer lookup (`NamedNodeMap::item(integer)`)

   In the event of a collision (possible on `NamedNodeMap::{get,set}NamedItem()`,
   the `NamedNodeMap::__qname_to_attr` table converts the bucket to an array.
   Lookups will return the first element of the array.

   TODO: Is this DOM-LS compliant behavior?


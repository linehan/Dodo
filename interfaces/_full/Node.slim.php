<?php
/******************************************************************************
 * Node.php
 * ````````
 * Defines a "Node", the primary datatype of the W3C Document Object Model.
 *
 * Conforms to W3C Document Object Model (DOM) Level 1 Recommendation
 * (see: https://www.w3.org/TR/2000/WD-DOM-Level-1-20000929)
 *
 *****************************************************************************/
namespace domo;

require_once("EventTarget.php");
require_once("LinkedList.php");
require_once("NodeUtils.php");
require_once("NodeList.php");
require_once("utils.php");
require_once("algorithms.php");

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

abstract class Node /* extends EventTarget // try factoring events out? */ 
{
        abstract public function textContent(string $value=NULL);       /* Should override for DocumentFragment/Element/Attr/Text/ProcessingInstruction/Comment */

        /* Delegated subclass method called by Node::isEqualNode() */
        abstract protected function _subclass_isEqualNode();

        /* Delegated subclass method called by Node::cloneNode() */
        abstract protected function _subclass_cloneNodeShallow();

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about its ancestors 
         **********************************************************************/

        /* DOMO: Top-level Document object of the Node */
        public $_ownerDocument;

        /* DOMO: Parent node (NULL if no parent) */
        public $_parentNode;

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about the childNodes of its parent 
         **********************************************************************/

        /* DOMO: Node's index in childNodes of parent (NULL if no parent) */
        public $_siblingIndex;

        /* DOMO: Next sibling in childNodes of parent ($this if none) */
        public $_nextSibling;

        /* DOMO: Prev sibling in childNodes of parent ($this if none) */
        public $_previousSibling;

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about its own childNodes 
         **********************************************************************/

        /* DOMO: Reference to first child Node (NULL if no children) */
        public $_firstChild;

        /* DOMO: Array form of childNodes (NULL if no children or using LL) */
        public $_childNodes;

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about itself 
         **********************************************************************/

        /* DOMO: For HTMLElement, will contain name of the tag */
        public $_nodeName;
        /* DOMO: Integer enumerating node type. See constants. */
        public $_nodeType;

        public function __construct()
        {
                /* Our ancestors */
		$this->_ownerDocument = NULL;
                $this->_parentNode = NULL;

                /* Our children */
                $this->_firstChild = NULL;
                $this->_childNodes = NULL;

                /* Our siblings */
                $this->_nextSibling = $this; // for LL
                $this->_previousSibling = $this; // for LL
                $this->_siblingIndex = NULL;
        }

        /**********************************************************************
         * UNSUPPORTED
         **********************************************************************/

        /* Not implemented */
        public function baseURI(){}
        public function baseURIObject(){}
        /* DOM-LS: Obsolete */
        public function localName(){}
        public function prefix(){}              // then how do you do this??
        public function namespaceURI(){}        // then how do you do this??
        public function nodePrincipal(){}
        public function rootNode() }

        /**********************************************************************
         * ACCESSORS 
         **********************************************************************/

        /**
         * Document that this node belongs to, or NULL if node is a Document
         *
         * @return Document or NULL
         * @spec DOM-LS
         */
        public function ownerDocument(void): ?Document
        {
                if ($this->_ownerDocument === NULL
                ||  $this->_nodeType === DOCUMENT_NODE) {
                        return NULL;
                }
                return $this->_ownerDocument;
        }

        /**
         * Node that is the parent of this node, or NULL if DNE.
         *
         * @return Node or NULL
         * @spec DOM-LS
         *
         * NOTE
         * Nodes may not have a parentNode if they are at the top of the
         * tree (e.g. Document Nodes), or if they don't participate in a
         * tree.
         */
        public function parentNode(void): ?Node
        {
                return $this->_parentNode ?? NULL;
        }

        /**
         * Element that is the parent of this node, or NULL if DNE.
         *
         * @return Element or NULL
         * @spec DOM-LS
         *
         * NOTE
         * Computed from _parentNode, has no state of its own to mutate. 
         */
        public function parentElement(void): ?Element
        {
                if ($this->_parentNode === NULL 
                || $this->_parentNode->_nodeType !== ELEMENT_NODE) {
                        return NULL;
                }
                return $this->_parentNode;
        }

        /**
         * Node representing previous sibling, or NULL if DNE. 
         *
         * @return Node or NULL
         * @spec DOM-LS
         *
         * CAUTION 
         * Directly accessing _previousSibling is NOT a substitute 
         * for this method. When Node is an only child, _previousSibling 
         * is set to $this, but DOM-LS needs it to be NULL. 
         */
        public function previousSibling(void): ?Node
        {
                if ($this->_parentNode === NULL 
                || $this === $this->_parentNode->firstChild()) {
                        return NULL;
                }
                return $this->_previousSibling;
        }

        /**
         * Node representing next sibling, or NULL if DNE. 
         *
         * @return Node or NULL
         * @spec DOM-LS
         *
         * CAUTION 
         * Directly accessing _nextSibling is NOT a substitute 
         * for this method. When Node is an only child, _nextSibling 
         * is set to $this, but DOM-LS needs it to be NULL. 
         */
        public function nextSibling(void): ?Node
        {
                if ($this->_parentNode === NULL 
                || $this->_nextSibling === $this->_parentNode->_firstChild) {
                        return NULL;
                }
                return $this->_nextSibling;
        }

        /**
         * Live NodeList containing this Node's children (NULL if no children)
         * 
         * @return NodeList or NULL
         * @spec DOM-LS
         *
         * NOTE
         * For performance, the creation of this NodeList is done lazily,
         * and only triggered the first time this method is called. Until
         * then, other functions rely on the LinkedList representation of
         * a Node's child nodes.
         *
         * So when we test if ($this->_childNodes), we are testing to see
         * if we have to mutate or work with a live NodeList.
         */
        public function childNodes(void): ?NodeList
        {
                if ($this->_childNodes !== NULL) {
                        return $this->_childNodes; /* memoization */
                }

                /* Lazy evaluation to build the child nodes */ 
                $this->_childNodes = new NodeList();

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $this->_childNodes[] = $n;
                }

                $this->_firstChild = NULL; /* Signals we are not using LL */
                return $this->_childNodes;
        }

        /**
         * Determine if the Node has any children 
         *
         * @return boolean
         * @spec DOM-LS
         *
         * CAUTION 
         * Testing _firstChild or _childNodes alone is *not* a shortcut
         * for this method. Depending on whether we are in NodeList or
         * LinkedList mode, one or the other or both may be NULL.
         */
        public function hasChildNodes(): boolean
        {
                if ($this->_childNodes !== NULL) {
                        return !empty($this->_childNodes); /* NodeList */
                } else {
                        return $this->_firstChild !== NULL; /* LinkedList */
                }
        }

        /**
         * Node representing the Node's first child node, or NULL if DNE
         * 
         * @return Node or NULL
         * @spec DOM-LS
         *
         * CAUTION 
         * Directly accessing _firstChild is *not* a substitute for this
         * method. Where to find the first child depends on whether we
         * are in NodeList or LinkedList mode.
         */
        public function firstChild(): ?Node
        {
                if ($this->_childNodes !== NULL) {
                        if (isset($this->_childNodes[0])) {
                                return $this->_childNodes[0]; /* NodeList */
                        } else {
                                return NULL; /* NodeList */
                        }
                }
                return $this->_firstChild; /* LinkedList */
        }

        /**
         * Node representing the Node's last child node, or NULL if DNE
         *
         * @return Node or NULL
         * @spec DOM-LS
         *
         * NOTE
         * See note for firstChild()
         */
        public function lastChild(): ?Node
        {
                if ($this->_childNodes !== NULL) {
                        if (!empty($this->_childNodes)) {
                                return end($this->_childNodes); /* NodeList */
                        } else {
                                return NULL; /* NodeList */
                        }
                }
                if ($this->_firstChild !== NULL) {
                        return $this->_firstChild->previousSibling(); /* LinkedList */
                } else {
                        return NULL; /* LinkedList */
                }
        }

	/**********************************************************************
	 * MUTATION ALGORITHMS
	 *********************************************************************/

        /**
         * Insert a Node as a child of this one, before a given reference node
         *
         * @param Node $node To be inserted
         * @param Node $refChild Child of this node before which to insert $node
         * @return Newly inserted Node or empty DocumentFragment
         * @throw DOMException "HierarchyRequestError" or "NotFoundError"
         * @spec DOM-LS
         *
         * NOTES
         * DOM-LS: If $node already exists in this Document, this function 
         * moves it from its current position to its new position ('move' 
         * means 'remove' followed by 're-insert').
         *
         * DOM-LS: If $refNode is NULL, then $node is added to the end of the 
         * list of children of $this. In other words, insertBefore($node, NULL) 
         * is equivalent to appendChild($node).
         *
         * DOM-LS: If $node is a DocumentFragment, the children of the 
         * DocumentFragment are moved into the child list of $this, and the 
         * empty DocumentFragment is returned.
         *
         * SORRY 
         * Despite its weird syntax (blame the spec) this is a real workhorse, 
         * used to implement all of the insertion mutations.
         */
        public function insertBefore(Node $node, ?Node $refChild): Node 
        {
                /* DOM-LS #1. Ensure pre-insertion validity */
                \domo\algorithm\_DOM_ensureInsertValid($node, $this, $refChild);

                /* DOM-LS #3. If $refChild is node, set to $node next sibling */
                if ($refChild === $node) {
                        $refChild = $node->nextSibling();
                }

                /* DOM-LS #4. Adopt $node into parent's node document. */
                $this->doc()->adoptNode($node);

                /* DOM-LS #5. Insert $node into parent before $refChild . */
                \domo\algorithm\_DOM_insertBeforeOrReplace($node, $this, $refChild, false);

                /* DOM-LS #6. Return $node */
                return $node;
        }

        /**
         * Append the given Node to the child list of this one 
         *
         * @param Node $node
         * @return Newly inserted Node or empty DocumentFragment
         * @spec DOM-LS
         * @throw DOMException "HierarchyRequestError", "NotFoundError"
         */
        public function appendChild(Node $node)
        {
                return $this->insertBefore($node, NULL);
        }

        /**
         * Replaces a given child Node with another Node
         *
         * @param Node $newChild to be inserted 
         * @param Node $oldChild to be replaced
         * @return Reference to $oldChild
         * @throw DOMException "HierarchyRequestError", "NotFoundError"
         * @spec DOM-LS
         */
        public function replaceChild(Node $newChild, ?Node $oldChild)
        {
                \domo\algorithm\_DOM_ensureReplaceValid($newChild, $this, $oldChild);

                /* Adopt node into parent's node document. */
                if ($newChild->doc() !== $this->doc()) {
                        /*
                         * XXX adoptNode has side-effect of removing node from
                         * its parent and generating a mutation event, causing 
                         * _insertOrReplace to generate 2 deletes and 1 insert 
                         * instead of a 'move' event.  It looks like the new 
                         * MutationObserver stuff avoids this problem, but for 
                         * now let's only adopt (ie, remove 'node' from its 
                         * parent) here if we need to.
                         */
                        $this->doc()->adoptNode($newChild);
                }

                \domo\algorithm\_DOM_insertBeforeOrReplace($newChild, $this, $oldChild, true);

                return $oldChild;
        }

        /**
         * Removes a child node from the DOM and returns it.
         *
         * @param ChildNode $node
         * @return ChildNode 
         * @throw DOMException "NotFoundError"
         * @spec DOM-LS
         *
         * NOTE
         * It must be the case that the returned value === $node
         */
        public function removeChild(ChildNode $node): Node
        {
                if ($this !== $node->_parentNode) {
                        \domo\error("NotFoundError");
                }
                $node->remove(); /* ChildNode method */
                return $node;
        }

	/**********************************************************************
	 * COMPARISONS AND PREDICATES
	 *********************************************************************/

        /**
         * Indicates whether a node is a descendant of this one 
         *
         * @param Node $node
         * @returns boolean
         * @spec DOM-LS
         * 
         * NOTE: see http://ejohn.org/blog/comparing-document-position/
         */
        public function contains(Node $node): boolean
        {
                if ($node === NULL) {
                        return false;
                }
                if ($this === $node) {
                        return true; /* inclusive descendant, see spec */
                }

                /* NOTE: bitwise operation */
                return ($this->compareDocumentPosition($node) & DOCUMENT_POSITION_CONTAINED_BY) !== 0;
        }

        /**
         * Compares position of this Node against another (in any Document)
         * 
         * @param Node $that
         * @return One of the document position constants
         * @spec DOM-LS
         */
        public function compareDocumentPosition(Node $that): integer
        {
                if ($this === $that) {
                        return 0;
                }

                /*
                 * If they're not owned by the same document or if one is 
                 * rooted and one is not, then they're disconnected.
                 */
                if ($this->doc() !== $that->doc() || $this->rooted() !== $that->rooted()) {
                        return (DOCUMENT_POSITION_DISCONNECTED + DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC);
                }

                /* #1 Make a list of the ancestors of each node */
                $these = array();
                $those = array();

                for ($n = $this; $n !== NULL; $n = $n->_parentNode) {
                        $these[] = $n;
                }
                for ($n = $that; $n !== NULL; $n = $n->_parentNode) {
                        $those[] = $n;
                }

                /* #2 Reverse them, so they start from the Document element */
                $these = array_reverse($these);
                $those = array_reverse($those);

                /* 
                 * #3 
                 * The order of the first elements that differ gives the
                 * order of the descendant nodes. 
                 */

                if ($these[0] !== $those[0]) {
                        return (DOCUMENT_POSITION_DISCONNECTED + DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC);
                }

                $len = min(count($these), count($those));

                for ($i = 1; $i < $len; $i++) {
                        if ($these[$i] !== $those[$i]) {
                                if ($these[$i]->index() < $those[$i]->index()) {
                                        return DOCUMENT_POSITION_FOLLOWING;
                                } else {
                                        return DOCUMENT_POSITION_PRECEDING;
                                }
                        }
                }

                /*
                 * #4 If we get here, then one list is a prefix of the other,
                 * and so the one node (the one with the shorter ancestor list)
                 * contains the other one. 
                 */
                if (count($these) < count($those)) {
                        return (DOCUMENT_POSITION_FOLLOWING + DOCUMENT_POSITION_CONTAINED_BY);
                } else {
                        return (DOCUMENT_POSITION_PRECEDING + DOCUMENT_POSITION_CONTAINS);
                }
        }

        /**
         * Whether this node and another are the same one in the same DOM
         *
         * @param Node $node to compare to this one 
         * @return boolean
         * @spec DOM-LS
         */
        public function isSameNode(Node $node): boolean
        {
                return $this === $node;
        }

        /**
         * Determine whether this node and $other are equal
         *
         * @param Node $other - will be compared to $this
         * @return boolean
         * @spec: DOM-LS
         *
         * NOTE:
         * Each subclass of Node has its own criteria for equality.
         * Rather than extend   Node::isEqualNode(),  subclasses
         * must implement   _subclass_isEqualNode(),  which is called
         * from   Node::isEqualNode()  and handles all of the equality
         * testing specific to the subclass.
         *
         * This allows the recursion and other fast checks to be
         * handled here and written just once.
         *
         * Yes, we realize it's a bit weird.
         */
        public function isEqualNode(?Node $node = NULL): boolean
        {
                if ($node === NULL) {
                        /* We're not equal to NULL */
                        return false;
                }
                if ($node->_nodeType !== $this->_nodeType) {
                        /* If we're not the same nodeType, we can stop */
                        return false;
                }

                if (!$this->_subclass_isEqualNode($node)) {
                        /* Run subclass-specific equality comparison */
                        return false;
                }

                /* Call this method on the children of both nodes */
                for (
                        $a=$this->firstChild(), $b=$node->firstChild();
                        $a!==NULL && $b!==NULL;
                        $a=$a->nextSibling(), $b=$b->nextSibling()
                ) {
                        if (!$a->isEqualNode($b)) {
                                return false;
                        }
                }

                /* If we got through all of the children (why wouldn't we?) */
                return $a === NULL && $b === NULL;
        }


        /**
         * Clone this Node 
         *
         * @param Boolean $deep - if true, clone entire subtree
         * @return Node (clone of $this)
         * @spec DOM-LS
         *
         * NOTE:
         * 1. If $deep is false, then no child nodes are cloned, including
         *    any text the node contains (since these are Text nodes).
         * 2. The duplicate returned by this method is not part of any
         *    document until it is added using ::appendChild() or similar.
         * 3. Initially (DOM4)   , $deep was optional with default of 'true'.
         *    Currently (DOM4-LS), $deep is optional with default of 'false'.
         * 4. Shallow cloning is delegated to   _subclass_cloneNodeShallow(),
         *    which needs to be implemented by the subclass.
         *    For a similar pattern, see Node::isEqualNode().
         * 5. All "deep clones" are a shallow clone followed by recursion on
         *    the tree structure, so this suffices to capture subclass-specific
         *    behavior.
         */
        public function cloneNode(boolean $deep = false): Node
        {
                /* Make a shallow clone using the delegated method */
                $clone = $this->_subclass_cloneNodeShallow();

                /* If the shallow clone is all we wanted, we're done. */
                if ($deep === false) {
                        return $clone;
                }

                /* Otherwise, recurse on the children */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        /* APPEND DIRECTLY; NO CHECKINSERTVALID */
                        \domo\algorithm\_DOM_insertBeforeOrReplace($clone, $n->cloneNode(true), NULL, false);
                }

                return $clone;
        }

        /**
         * Return DOMString containing prefix for given namespace URI.
         *
         * @param string $ns
         * @return string or NULL
         * @spec DOM-LS
         *
         * NOTE 
         * Think this function looks weird? It's actually spec:
         * https://dom.spec.whatwg.org/#locate-a-namespace
         */
        public function lookupPrefix(?string $ns): ?string
        {
                return \domo\algorithm\_DOM_locate_prefix($this, $ns);
        }

        /**
         * Return DOMString containing namespace URI for a given prefix
         *
         * @param string $prefix
         * @return string or NULL
         *
         * NOTE
         * Inverse of Node::lookupPrefix
         */
        public function lookupNamespaceURI(?string $prefix): ?string
        {
                return \domo\algorithm\_DOM_locate_namespace($this, $prefix);
        }

        /**
         * Determine whether this is the default namespace
         *
         * @param string $ns
         * @return boolean
         */
        public function isDefaultNamespace(?string $ns): boolean
        {
                return ($ns ?? NULL) === $this->lookupNamespaceURI(NULL);
        }


	/**********************************************************************
	 * UTILITY METHODS AND DOMO EXTENSIONS 
	 *********************************************************************/

        /*
         * Return the index of this node in its parent.
         * Throw if no parent, or if this node is not a child of its parent
         */
        public function index()
        {
                if ($this->_parentNode === NULL) {
                        return; /* ??? throw ??? */
                }

                if ($this === $this->_parentNode->_firstChild) {
                        return 0; // fast case
                }

                $childNodes = $this->_parentNode->childNodes();

                /*
                 * TODO: So if we fuck up the indexing, we just re-index
                 * everything. Great way to not catch errors.
                 */
                if ($this->_index === NULL || $childNodes[$this->_index] !== $this) {
                        /*
                         * Ensure that we don't have an O(N^2) blowup
                         * if none of the kids have defined indices yet
                         * and we're traversing via nextSibling or
                         * previousSibling
                         */
                        foreach ($childNodes as $i => $child) {
                                $child->_index = $i;
                        }

                        \domo\assert($childNodes[$this->_index] === $this);
                }
                return $this->_index;
        }

        /*
         * Return true if this node is equal to or is an ancestor of that node
         * Note that nodes are considered to be ancestors of themselves
         */
        public function isAncestor($that)
        {
                /*
                 * If they belong to different documents,
                 * then they're unrelated.
                 */
                if ($this->doc() !== $that->doc()) {
                        return false;
                }
                /*
                 * If one is rooted and one isn't then they're not related
                 */
                if ($this->rooted() !== $that->rooted()) {
                        return false;
                }

                /* Otherwise check by traversing the parentNode chain */
                for ($e = $that; $e; $e = $e->parentNode()) {
                        if ($e === $this) {
                                return true;
                        }
                }

                return false;
        }

        /*
         * DOMINO Changed the behavior to conform with the specs. See:
         * https://groups.google.com/d/topic/mozilla.dev.platform/77sIYcpdDmc/discussion
         *
         * PORT TODO: Hey! this has a side-effect and should be re-named,
         * it's confusing.
         */
        public function ensureSameDoc(?Node $that)
        {
                if ($that->ownerDocument === NULL) {
                        $that->ownerDocument = $this->doc();
                } else {
                        if ($that->ownerDocument !== $this->doc()) {
                                error("WrongDocumentError");
                        }
                }
        }

        /*
         * Remove all of this node's children.
         * This provides a minor optimization over iterative calls
         * to removeChild(), since it calls modify() only once.
         */
        public function removeChildren()
        {
                if ($this->rooted()) {
                        $root = $this->ownerDocument;
                } else {
                        $root = NULL;
                }

                /* Go through all the children and remove me as their parent */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($root !== NULL) {
                                /* If we're rooted, mutate */
                                $root->mutateRemove($n);
                        }
                        $n->parentNode = NULL;
                }

                /* Remove the child node memory or references on this node */
                if ($this->_childNodes !== NULL) {
                        /* BRANCH: NodeList (array-like) */
                        $this->_childNodes = new NodeList();
                } else {
                        /* BRANCH: circular linked list */
                        $this->_firstChild = NULL;
                }

                /* Update last modified type once only (minor optimization) */
                $this->modify();
        }

        /*
         * This attribute is not part of the DOM but is quite helpful.
         * It returns the document with which a node is associated.
         * Usually this is the ownerDocument. But ownerDocument is null
         * for the document object itself, so this is a handy way to get
         * the document regardless of the node type
         *
         * TODO PORT: This was changed from a property to a method
         * because PHP doesn't do getters and setters like JavaScript
         *
         * This is also silly, whether or not it's handy.
         */
        public function doc()
        {
                return $this->_ownerDocument || $this;
        }


        /*
         * If the node has a nid (node id), then it is rooted in a document
         * TODO PORT: This was changed from a property to a method
         * because PHP doesn't do getters and setters like JavaScript
         */
        public function rooted()
        {
                return !!$this->_nid;
        }

        /*
         * Return the lastModTime value for this node. (For use as a
         * cache invalidation mechanism. If the node does not already
         * have one, initialize it from the owner document's modclock
         * property. (Note that modclock does not return the actual
         * time; it is simply a counter incremented on each document
         * modification)
         */
        public function lastModTime()
        {
                if (!$this->_lastModTime) {
                        $this->_lastModTime = $this->doc()->modclock;
                }
                return $this->_lastModTime;
        }

        /*
         * Increment the owner document's modclock and use the new
         * value to update the lastModTime value for this node and
         * all of its ancestors. Nodes that have never had their
         * lastModTime value queried do not need to have a
         * lastModTime property set on them since there is no
         * previously queried value to ever compare the new value
         * against, so only update nodes that already have a
         * _lastModTime property.
         */
        public function modify()
        {
                /* Skip while doc.modclock == 0 */
                if ($this->doc()->modclock) {
                        $time = ++$this->doc()->modclock;

                        for ($n = $this; $n; $n = $n->parentElement()) {
                                if ($n->_lastModTime) {
                                        $n->_lastModTime = $time;
                                }
                        }
                }
        }

        public function normalize()
        {
                $next=NULL;

                for ($child=$this->firstChild; $child !== NULL; $child=$next) {
                        $next = $child->nextSibling;

                        /* TODO: HOW TO FIX THIS IN PHP? */
                        if ($child->normalize) {
                                $child->normalize();
                        }

                        if ($child->nodeType !== TEXT_NODE) {
                                continue;
                        }

                        if ($child->nodeValue === "") {
                                $this->removeChild($child);
                                continue;
                        }

                        $prevChild = $child->previousSibling;

                        if ($prevChild === NULL) {
                                continue;
                        } else {
                                if ($prevChild->nodeType === TEXT_NODE) {
                                        /*
                                         * merge this with previous and
                                         * remove the child
                                         */
                                        $prevChild->appendData($child->nodeValue);
                                        $this->removeChild($child);
                                }
                        }
                }
        }

        public function serialize(){}
        public function outerHTML(string $value = NULL){}
}


?>

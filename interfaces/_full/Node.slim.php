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

abstract class Node /* extends EventTarget // try factoring events out? */ {

        abstract public function textContent(string $value=NULL);       /* Should override for DocumentFragment/Element/Attr/Text/ProcessingInstruction/Comment */
        abstract public function hasChildNodes();                       /* Should override for ContainerNode or non-leaf node? */
        abstract public function firstChild();                          /* Should override for ContainerNode or non-leaf node? */
        abstract public function lastChild();                           /* Should override for ContainerNode or non-leaf node? */

        /* Delegated subclass method called by Node::isEqualNode() */
        abstract protected function _subclass_isEqualNode();
        /* Delegated subclass method called by Node::cloneNode() */
        abstract protected function _subclass_cloneNodeShallow();

        /**
         * NOTE: NODE REFERENCES
         * ---------------------
         * Because parentNode, nextSibling, and previousSibling are
         * read-only attributes in the spec, we implement them as
         * getter methods in PHP, with the actual node references
         * stored in protected properties _parentNode, _nextSibling,
         * and _previousSibling, respectively.
         *
         * Nodes implement a circular linked list of siblings using
         * nextSibling() and previousSibling(), so with only a ref.
         * to one child (e.g. firstChild), we can traverse all the
         * children by calling these methods on each in turn.
         *
         * The list is circular to ensure all child nodes can be
         * visited regardless of what node we start from.
         *
         * TODO PORT NOTE: We are not actually ensuring this.
         * nextSibling() and previousSibling() assume that you begin
         * from firstChild if you want a traversal.
         *
         * TODO PORT NOTE: Also, these cannot be protected, they
         * must be public, because LinkedList accesses them.
         */

        /* Index in childNodes (NULL if no parent) */
        public $_index
        /* Next sibling in childNodes ($this if none) */
        public $_nextSibling;
        /* Prev sibling in childNodes ($this if none) */
        public $_previousSibling;
        /* First child Node (NULL if no children) */
        public $_firstChild;
        /* Child nodes array (NULL if no children) */
        public $_childNodes;

        /* DOM-LS: Read only top-level Document object of the node */
        public $ownerDocument;

        /* DOM-LS: Parent node (NULL if no parent) */
        public $_parentNode;

        /* DOM-LS Subclasses are responsible for setting these */
        public $nodeName;
        public $nodeType;

        public function __construct()
        {
                $this->parentNode = NULL;
		$this->ownerDocument = NULL;

                /* Used by _childNodes; similar to _nid */
                $this->_index = NULL;
                $this->_childNodes = NULL;

                /* Internal references */
                $this->_nextSibling = $this;
                $this->_previousSibling = $this;
                $this->_firstChild = NULL;
        }

        public function baseURI(){}

        public function parentNode(): ?Node
        {
                return $this->_parentNode ?? NULL;
        }

        public function parentElement(): ?Element
        {
                if ($this->_parentNode === NULL 
                || $this->_parentNode->nodeType !== ELEMENT_NODE) {
                        return NULL;
                }
                return $this->parentNode;
        }

        public function previousSibling(): ?Node
        {
                if ($this->_parentNode === NULL 
                || $this === $this->_parentNode->firstChild) {
                        return NULL;
                }
                return $this->_previousSibling;
        }
        public function nextSibling(): ?Node
        {
                if ($this->_parentNode === NULL 
                || $this->_nextSibling === $this->_parentNode->_firstChild) {
                        return NULL;
                }
                return $this->_nextSibling;
        }

        public function childNodes()
        {
                /* Memoized fast path */
                if ($this->_childNodes !== NULL) {
                        return $this->_childNodes;
                }

                /* Build child nodes array by traversing the linked list */
                $childNodes = new NodeList();

                for ($n=$this->_firstChild; $n!==NULL; $n=$n->_nextSibling) {
                        $childNodes[] = $n;
                }

                /*
                 * SIDE EFFECT:
                 * Switch from circular linked list branch to array-like
                 * branch.
                 *
                 * (Which branch we are on is detected by looking for which
                 * of these two members is NULL, see methods below.)
                 */
                $this->_childNodes = $childNodes;
                $this->_firstChild = NULL;

                return $this->_childNodes;
        }

        public function hasChildNodes()
        {
                /* BRANCH: NodeList (array-like) */
                if ($this->_childNodes !== NULL) {
                        return !empty($this->_childNodes);
                }
                /* BRANCH: circular linked list */
                return $this->_firstChild !== NULL;
        }


        public function firstChild()
        {
                /* BRANCH: NodeList (array-like) */
                if ($this->_childNodes !== NULL) {
                        if (!empty($this->_childNodes)) {
                                return $this->_childNodes[0];
                        } else {
                                return NULL;
                        }
                }

                /* BRANCH: circular linked list */
                return $this->_firstChild;
        }

        public function lastChild()
        {
                /* BRANCH: NodeList (array-like) */
                if ($this->_childNodes !== NULL) {
                        if (!empty($this->_childNodes)) {
                                return end($this->_childNodes);
                        } else {
                                return NULL;
                        }
                }

                /* BRANCH: circular linked list */
                if ($this->_firstChild === NULL) {
                        return NULL;
                } else {
                        return $this->_firstChild->_previousSibling;
                }
        }

	/**********************************************************************
	 * MUTATION ALGORITHMS
	 *********************************************************************/

        public function insertBefore(Node $node, ?Node $child)
        {
                /* 1. Ensure pre-insertion validity */
                _DOM_ensureInsertValid($this, $node, $child);

                /* 2. Let reference child be child. */
                $refChild = $child;

                /* 3. If reference child is node, set it to node's next sibling */
                if ($refChild === $node) {
                        $refChild = $node->nextSibling();
                }

                /* 4. Adopt node into parent's node document. */
                $parent->doc()->adoptNode($node);

                /* 5. Insert node into parent before reference child. */
                $node->_insertOrReplace($parent, $refChild, false);

                /* 6. Return node */
                return $node;
        }

        public function removeChild(ChildNode $node)
        {
                if ($this !== $node->_parentNode) {
                        error("NotFoundError");
                }
                $node->remove();

                return $node;
        }

        public function appendChild($node)
        {
		/*
		 * DOM-LS: "To append a node to parent, pre-insert node
		 * into parent before NULL."
		 */
                return $this->insertBefore($child, NULL);
        }

        protected function _appendChild($child)
        {
                $child->_insertOrReplace($this, NULL, false);
        }

        /* To replace a `child` with `node` within a `parent` (this) */
        public function replaceChild($node, $child)
        {
                $parent = $this;
                /* Ensure validity (slight differences from pre-insertion check) */
                _DOM_ensureReplaceValid($this, $node, $child);
                /* Adopt node into parent's node document. */
                if ($node->doc() !== $parent->doc()) {
                        /*
                         * XXX adoptNode has side-effect of removing node from
                         * its parent and generating a mutation event, thus
                         * causing the _insertOrReplace to generate two deletes
                         * and an insert instead of a 'move' event.  It looks
                         * like the new MutationObserver stuff avoids this
                         * problem, but for now let's only adopt (ie, remove
                         * `node` from its parent) here if we need to.
                         */
                        $parent->doc()->adoptNode($node);
                }
                /* Do the replace. */
                $node->_insertOrReplace($parent, $child, true);
                return $child;
        }


        // See: http://ejohn.org/blog/comparing-document-position/
        public function contains($node)
        {
                if ($node === NULL) {
                        return false;
                }
                if ($this === $node) {
                        return true; /* inclusive descendant */
                }

                /* NOTE: bitwise operation */
                return ($this->compareDocumentPosition($node) & DOCUMENT_POSITION_CONTAINED_BY) !== 0;
        }

        /*
         * Basic algorithm for finding the relative position of two nodes.
         * Make a list the ancestors of each node, starting with the
         * document element and proceeding down to the nodes themselves.
         * Then, loop through the lists, looking for the first element
         * that differs.  The order of those two elements give the
         * order of their descendant nodes.  Or, if one list is a prefix
         * of the other one, then that node contains the other.
         */
        public function compareDocumentPosition(Node $that)
        {
                if ($this === $that) {
                        return 0;
                }

                /*
                 * If they're not owned by the same document
                 * or if one is rooted and one is not, then
                 * they're disconnected.
                 */
                if ($this->doc() !== $that->doc() || $this->rooted() !== $that->rooted()) {
                        return (DOCUMENT_POSITION_DISCONNECTED + DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC);
                }

                /* Get arrays of ancestors for this and that */
                /* TODO PORT NOTE: Oh jeez... */
                $these = array();
                $those = array();

                for ($n = $this; $n !== NULL; $n = $n->_parentNode) {
                        $these[] = $n;
                }
                for ($n = $that; $n !== NULL; $n = $n->_parentNode) {
                        $those[] = $n;
                }

                /* So we start with the outermost */
                $these = array_reverse($these);
                $those = array_reverse($those);

                /* No common ancestor */
                if ($these[0] !== $those[0]) {
                        return (DOCUMENT_POSITION_DISCONNECTED + DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC);
                }

                /* TODO PORT: Gross, $n was for node before, now it's for number */
                $n = min(count($these), count(those));

                for ($i = 1; $i < $n; $i++) {
                        if ($these[$i] !== $those[$i]) {
                                /*
                                 * We found two different ancestors,
                                 * so compare their positions
                                 */
                                if ($these[$i]->index < $those[$i]->index) {
                                        return DOCUMENT_POSITION_FOLLOWING;
                                } else {
                                        return DOCUMENT_POSITION_PRECEDING;
                                }
                        }
                }

                /*
                 * If we get to here, then one of the nodes (the one with the
                 * shorter list of ancestors) contains the other one.
                 */
                if (count($these) < count(those)) {
                        return (DOCUMENT_POSITION_FOLLOWING + DOCUMENT_POSITION_CONTAINED_BY);
                } else {
                        return (DOCUMENT_POSITION_PRECEDING + DOCUMENT_POSITION_CONTAINS);
                }
        }

        public function isSameNode($node)
        {
                return $this === $node;
        }

        /**
         * isEqualNode()
         * `````````````
         * Determine whether this node and $other are equal
         *
         * @other: Node to compare to $this
         * Return: true if equal, or else false
         * PartOf: DOM4
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
        public function isEqualNode(Node $other = NULL)
        {
                if ($other === NULL) {
                        /* We're not equal to NULL */
                        return false;
                }
                if ($other->_nodeType !== $this->_nodeType) {
                        /* If we're not the same nodeType, we can stop */
                        return false;
                }

                if (!$this->_subclass_isEqualNode($other)) {
                        /* Run subclass-specific equality comparison */
                        return false;
                }

                /* Call this method on the children of both nodes */
                for (
                        $a=$this->firstChild(), $b=$other->firstChild();
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


        /*
         * This method delegates shallow cloning to a clone() method
         * that each concrete subclass must implement
         * TODO PORT: Is this a good idea?
         */
        /**
         * cloneNode()
         * ```````````
         * Clone this Document (as a Node?)
         *
         * @deep  : if true, clone entire subtree
         * Returns: Clone of $this.
         * Part_Of: DOM4-LS
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
        public function cloneNode(boolean $deep = false){}
        public function lookupPrefix($ns){}
        public function lookupNamespaceURI($prefix){}
        public function isDefaultNamespace($ns){}


        /************** UTILITY METHODS; NOT PART OF THE DOM **************/

        /*
         * Return the index of this node in its parent.
         * Throw if no parent, or if this node is not a child of its parent
         */
        public function index()
        {
                $parent = $this->parentNode();

                if ($this === $parent->firstChild) {
                        return 0; // fast case
                }

                $kids = $parent->childNodes;

                /*
                 * TODO: So if we fuck up the indexing, we just re-index
                 * everything. Great way to not catch errors.
                 */

                if ($this->_index === NULL || $kids[$this->_index] !== $this) {
                        /*
                         * Ensure that we don't have an O(N^2) blowup
                         * if none of the kids have defined indices yet
                         * and we're traversing via nextSibling or
                         * previousSibling
                         */
                        for ($i=0; $i<count($kids); $i++) {
                                $kids[$i]->_index = $i;
                        }
                        utils\assert($kids[$this->_index] === $this);
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
         * Insert this node as a child of parent before the specified child,
         * or insert as the last child of parent if specified child is null,
         * or replace the specified child with this node, firing mutation events as
         * necessary
         */
        public function _insertOrReplace($parent, $before, $isReplace)
        {
                $child = $this
                $before_index;
                $i;

                if ($child->nodeType === DOCUMENT_FRAGMENT_NODE && $child->rooted()) {
                        error("HierarchyRequestError");
                }

                /*
                 * Ensure index of `before` is cached before we (possibly)
                 * remove it.
                 */
                if ($parent->_childNodes) {
                        if ($before === NULL) {
                                $before_index = count($parent->_childNodes);
                        } else {
                                $before_index = $before->_index;
                        }

                        /*
                         * If we are already a child of the specified parent,
                         * then the index may have to be adjusted.
                         */
                        if ($child->_parentNode === $parent) {
                                $child_index = $child->_index;
                                /*
                                 * If the child is before the spot it is to be
                                 * inserted at, then when it is removed, the
                                 * index of that spot will be reduced.
                                 */
                                if ($child_index < $before_index) {
                                        $before_index--;
                                }
                        }
                }

                /* Delete the old child */
                if ($isReplace) {
                        if ($before->rooted()) {
                                $before->doc()->mutateRemove($before);
                        }
                        $before->_parentNode = null;
                }

                if ($before !== NULL) {
                        $n = $before;
                } else {
                        $n = $parent->firstChild;
                }

                /*
                 * If both the child and the parent are rooted,
                 * then we want to transplant the child without
                 * uprooting and rerooting it.
                 */
                $bothRooted = $child->rooted() && $parent->rooted();

                if ($child->_nodeType === DOCUMENT_FRAGMENT_NODE) {
                        $spliceArgs = array(0, $isReplace ? 1 : 0);
                        $nodes = array();

                        for ($kid = $child->firstChild(); $kid !== NULL; $kid = $next) {
                                $next = $kid->nextSibling();
                                //$spliceArgs[] = $kid;
                                $kid->_parentNode = $parent;
                        }

                        $len = count($nodes) + count($spliceArgs);

                        /*
                         * Add all nodes to the new parent,
                         * overwriting the old child
                         */
                        if ($isReplace) {
                                LinkedList\replace($n, $len > 2 ? $nodes[0] : NULL);
                        } else {
                                if ($len > 2 && $n !== NULL) {
                                        LinkedList\insertBefore($nodes[0], $n);
                                }
                        }

                        if ($parent->_childNodes) {
                                if ($before === NULL) {
                                        $spliceArgs[0] = count($parent->_childNodes);
                                } else {
                                        $spliceargs[0] = $before->_index;
                                }

                                //parent._childNodes.splice.apply(parent._childNodes, spliceArgs);
                                array_splice($parent->_childNodes, $spliceArgs[0], $spliceArgs[1], $nodes);

                                for ($i=0; $i<($len-2); $i++) {
                                        $nodes[$i]->_index = $spliceArgs[0] + $i;
                                }
                                /* TODO: We don't re-index the shifted nodes? Ofc not,
                                   it catches in the index() function when it finds
                                   out that $this->_childNodes[$n] !== $this or whatever.
                                   */
                        } else {
                                if ($parent->_firstChild === $before) {
                                        if ($len > 2) {
                                                $parent->_firstChild = $nodes[0];
                                        } else {
                                                if ($isReplace) {
                                                        $parent->_firstChild = NULL;
                                                }
                                        }
                                }
                        }

                        /* Remove all nodes from the document fragment */
                        if ($child->_childNodes) {
                                /* TODO PORT: This is the easiest way to do this in PHP and preserves references */
                                $child->_childNodes = array();
                        } else {
                                $child->_firstChild = NULL;
                        }

                        /*
                         * Call the mutation handlers
                         * Use spliceArgs since the original array has been
                         * destroyed. The liveness guarantee requires us to
                         * clone the array so that references to the childNodes
                         * of the DocumentFragment will be empty when the
                         * insertion handlers are called.
                         */
                        if ($parent->rooted()) {
                                $parent->modify();
                                for ($i = 2; $i < $len; $i++) {
                                        $parent->doc()->mutateInsert($spliceArgs[$i]);
                                }
                        }
                } else {
                        if ($before === $child) {
                                return;
                        }
                        if ($bothRooted) {
                                /*
                                 * Remove the child from its current position
                                 * in the tree without calling remove(), since
                                 * we don't want to uproot it.
                                 */
                                $child->_remove();
                        } else {
                                if ($child->_parentNode) {
                                        $child->remove();
                                }
                        }

                        /* Insert it as a child of its new parent */
                        $child->_parentNode = $parent;

                        if ($isReplace) {
                                LinkedList\replace($n, $child);
                                if ($parent->_childNodes) {
                                        $child->_index = $before_index;
                                        $parent->_childNodes[$before_index] = $child;
                                } else {
                                        if ($parent->_firstChild === $before) {
                                                $parent->_firstChild = $child;
                                        }
                                }
                        } else {
                                if ($n !== NULL) {
                                        LinkedList\insertBefore($child, $n);
                                }
                                if ($parent->_childNodes) {
                                        $child->_index = $before_index;
                                        // parent._childNodes.splice(before_index, 0, child);
                                        array_splice($parent->_childNodes, $before_index, 0, $child);
                                } else {
                                        if ($parent->_firstChild === $before) {
                                                $parent->_firstChild = $child;
                                        }
                                }
                        }

                        if ($bothRooted) {
                                $parent->modify();
                                /* Generate a move mutation event */
                                $parent->doc()->mutateMove($child);
                        } else {
                                if ($parent->rooted()) {
                                        $parent->modify();
                                        $parent->doc()->mutateInsert($child);
                                }
                        }
                }
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
                return $this->ownerDocument || $this;
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


public function _DOM_insert(Node $node, Node $parent, ?Node $child)
{
        /*
         * 1. Let count be the number of children of node if it is a
         * DocumentFragment, and 1 otherwise.

         * 2. Let nodes be node's children if node is a DocumentFragment
         * node, and a list containing solely node otherwise.
         */
        if ($node->nodeType === DOCUMENT_FRAGMENT_NODE) {
                $nodes = array();
                for ($n=$node->firstChild; $n!==NULL; $n=$n->nextSibling) {
                        $nodes[] = $n;
                }
        } else {
                $nodes = array($node);
        }
        $count = count($nodes);

        /* 7. For each node in nodes, in tree order: */
        foreach ($nodes as $n) {
                /* 7.1 If child is NULL, append node to parent's children */
                if ($child === NULL) {
                        /*
                         * NOTE: Inserting before firstChild will append,
                         * since this is a circular linked list.
                         *
                         * TODO: Perhaps make this more explicit, with
                         * firstChild, nextSibling, previousSibling being
                         * more clearly involved in the LL ops.
                         */
                        LinkedList\insertBefore($n, $parent->firstChild);

                        if ($parent->_childNodes) {
                                array_push($parent->_childNodes, $n);
                                $n->_index = end($parent->_childNodes)->_index + 1;
                        }

                        /* Set this node's parent */
                        $n->parentNode = $parent;

                        /* If we're the first child, set the reference */
                        if (!$parent->hasChildNodes()) {
                                $parent->firstChild = $n;
                        }

                        /* We are appending, so we're the last child */
                        $parent->lastChild = $n;
                /*
                 * 7.2 Otherwise, insert node into parent’s children before
                 * child’s index.
                 */
                } else {
                        LinkedList\insertBefore($n, $child);

                        if ($parent->_childNodes) {
                                array_splice($parent->_childNodes, $child->_index, 0, $n);
                                $n->_index = -1; // will be re-indexed in index()
                        }

                        $n->parentNode = $parent;

                        /* We just inserted before the first child */
                        if ($parent->firstChild === $child) {
                                $parent->firstChild = $n;
                        }
                }
        }
}




/*
 * TODO PORT NOTE:
 * A rather long and complicated set of cases to determine whether
 * it is valid to insert a particular node at a particular location.
 *
 * Will throw an exception if an invalid condition is detected,
 * otherwise will do nothing.
 */

/*
TODO: Look at the way these were implemented in the original;
there are some speedups esp in the way that you implement
things like "node has a doctype child that is not child
*/
static function _DOM_ensureInsertValid(Node $node, Node $parent, ?Node $child)
{
        /*
         * DOM-LS: #1: If parent is not a Document, DocumentFragment,
         * or Element node, throw a HierarchyRequestError.
         */
        switch ($parent->nodeType) {
        case DOCUMENT_NODE:
        case DOCUMENT_FRAGMENT_NODE:
        case ELEMENT_NODE:
                break;
        default:
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #2: If node is a host-including inclusive ancestor
         * of parent, throw a HierarchyRequestError.
         */
        if ($node->isAncestor($parent)) {
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #3: If child is not null and its parent is not $parent, then
         * throw a NotFoundError
         */
        if ($child !== NULL && $child->parentNode !== $parent) {
                error("NotFoundError");
        }

        /*
         * DOM-LS #4: If node is not a DocumentFragment, DocumentType,
         * Element, Text, ProcessingInstruction, or Comment Node,
         * throw a HierarchyRequestError.
         */
        switch ($node->nodeType) {
        case DOCUMENT_FRAGMENT_NODE:
        case DOCUMENT_TYPE_NODE:
        case ELEMENT_NODE:
        case TEXT_NODE:
        case PROCESSING_INSTRUCTION_NODE:
        case COMMENT_NODE:
                break;
        default:
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #5. If either:
         *      -node is a Text and parent is a Document
         *      -node is a DocumentType and parent is not a Document
         * throw a HierarchyRequestError
         */
        if (($node->nodeType === TEXT_NODE          && $parent->nodeType === DOCUMENT_NODE)
        ||  ($node->nodeType === DOCUMENT_TYPE_NODE && $parent->nodeType !== DOCUMENT_NODE)) {
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #6: If parent is a Document, and any of the
         * statements below, switched on node, are true, throw a
         * HierarchyRequestError.
         */
        if ($parent->nodeType !== DOCUMENT_NODE) {
                return;
        }

        switch ($node->nodeType) {
        case DOCUMENT_FRAGMENT_NODE:
                /*
                 * DOM-LS #6a-1: If node has more than one
                 * Element child or has a Text child.
                 */
                $count_text = 0;
                $count_element = 0;

                for ($n=$node->firstChild; $n!==NULL; $n=$n->nextSibling) {
                        if ($n->nodeType === TEXT_NODE) {
                                $count_text++;
                        }
                        if ($n->nodeType === ELEMENT_NODE) {
                                $count_element++;
                        }
                        if ($count_text > 0 && $count_element > 1) {
                                error("HierarchyRequestError");
                                // TODO: break ? return ?
                        }
                }
                /*
                 * DOM-LS #6a-2: If node has one Element
                 * child and either:
                 */
                if ($count_element === 1) {
                        /* DOM-LS #6a-2a: child is a DocumentType */
                        if ($child !== NULL && $child->nodeType === DOCUMENT_TYPE_NODE) {
                               error("HierarchyRequestError");
                        }
                        /*
                         * DOM-LS #6a-2b: child is not NULL and a
                         * DocumentType is following child.
                         */
                        if ($child !== NULL) {
                                for ($n=$child->nextSibling; $n!==NULL; $n=$n->nextSibling) {
                                        if ($n->nodeType === DOCUMENT_TYPE_NODE) {
                                                error("HierarchyRequestError");
                                        }
                                }
                        }
                        /* DOM-LS #6a-2c: parent has an Element child */
                        for ($n=$parent->firstChild; $n!==NULL; $n=$n->nextSibling) {
                                if ($n->nodeType === ELEMENT_NODE) {
                                        error("HierarchyRequestError");
                                }
                        }
                }
                break;
        case ELEMENT_NODE:
                /* DOM-LS #6b-1: child is a DocumentType */
                if ($child !== NULL && $child->nodeType === DOCUMENT_TYPE_NODE) {
                       error("HierarchyRequestError");
                }
                /* DOM-LS #6b-2: child not NULL and DocumentType is following child. */
                if ($child !== NULL) {
                        for ($n=$child->nextSibling; $n!==NULL; $n=$n->nextSibling) {
                                if ($n->nodeType === DOCUMENT_TYPE_NODE) {
                                        error("HierarchyRequestError");
                                }
                        }
                }
                /* DOM-LS #6b-3: parent has an Element child */
                for ($n=$parent->firstChild; $n!==NULL; $n=$n->nextSibling) {
                        if ($n->nodeType === ELEMENT_NODE) {
                                error("HierarchyRequestError");
                        }
                }
                break;
        case DOCUMENT_TYPE_NODE:
                /* DOM-LS #6c-1: parent has a DocumentType child */
                for ($n=$parent->firstChild; $n!==NULL; $n=$n->nextSibling) {
                        if ($n->nodeType === DOCUMENT_TYPE_NODE) {
                                error("HierarchyRequestError");
                        }
                }
                /*
                 * DOM-LS #6c-2: child is not NULL and an Element
                 * is preceding child,
                 */
                if ($child !== NULL) {
                        for ($n=$child->previousSibling; $n!==NULL; $n=$n->previousSibling) {
                                if ($n->nodeType === ELEMENT_NODE) {
                                        error("HierarchyRequestError");
                                }
                        }
                }
                /*
                 * DOM-LS #6c-3: child is NULL and parent has
                 * an Element child.
                 */
                if ($child === NULL) {
                        for ($n=$parent->firstChild; $n!==NULL; $n=$n->nextSibling) {
                                if ($n->nodeType === ELEMENT_NODE) {
                                        error("HierarchyRequestError");
                                }
                        }
                }

                break;
        }
}

function _DOM_ensureReplaceValid(Node $node, Node $parent, Node $child)
{
        /*
         * DOM-LS: #1: If parent is not a Document, DocumentFragment,
         * or Element node, throw a HierarchyRequestError.
         */
        switch ($parent->nodeType) {
        case DOCUMENT_NODE:
        case DOCUMENT_FRAGMENT_NODE:
        case ELEMENT_NODE:
                break;
        default:
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #2: If node is a host-including inclusive ancestor
         * of parent, throw a HierarchyRequestError.
         */
        if ($node->isAncestor($parent)) {
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #3: If child's parentNode is not parent
         * throw a NotFoundError
         */
        if ($child->parentNode !== $parent) {
                error("NotFoundError");
        }

        /*
         * DOM-LS #4: If node is not a DocumentFragment, DocumentType,
         * Element, Text, ProcessingInstruction, or Comment Node,
         * throw a HierarchyRequestError.
         */
        switch ($node->nodeType) {
        case DOCUMENT_FRAGMENT_NODE:
        case DOCUMENT_TYPE_NODE:
        case ELEMENT_NODE:
        case TEXT_NODE:
        case PROCESSING_INSTRUCTION_NODE:
        case COMMENT_NODE:
                break;
        default:
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #5. If either:
         *      -node is a Text and parent is a Document
         *      -node is a DocumentType and parent is not a Document
         * throw a HierarchyRequestError
         */
        if (($node->nodeType === TEXT_NODE          && $parent->nodeType === DOCUMENT_NODE)
        ||  ($node->nodeType === DOCUMENT_TYPE_NODE && $parent->nodeType !== DOCUMENT_NODE)) {
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #6: If parent is a Document, and any of the
         * statements below, switched on node, are true, throw a
         * HierarchyRequestError.
         */
        if ($parent->nodeType !== DOCUMENT_NODE) {
                return;
        }

        switch ($node->nodeType) {
        case DOCUMENT_FRAGMENT_NODE:
                /*
                 * #6a-1: If node has more than one Element child
                 * or has a Text child.
                 */
                $count_text = 0;
                $count_element = 0;

                for ($n=$node->firstChild; $n!==NULL; $n=$n->nextSibling) {
                        if ($n->nodeType === TEXT_NODE) {
                                $count_text++;
                        }
                        if ($n->nodeType === ELEMENT_NODE) {
                                $count_element++;
                        }
                        if ($count_text > 0 && $count_element > 1) {
                                error("HierarchyRequestError");
                        }
                }
                /* #6a-2: If node has one Element child and either: */
                if ($count_element === 1) {
                        /* #6a-2a: parent has an Element child that is not child */
                        for ($n=$parent->firstChild; $n!==NULL; $n=$n->nextSibling) {
                                if ($n->nodeType === ELEMENT_NODE && $n !== $child) {
                                        error("HierarchyRequestError");
                                }
                        }
                        /* #6a-2b: a DocumentType is following child. */
                        for ($n=$child->nextSibling; $n!==NULL; $n=$n->nextSibling) {
                                if ($n->nodeType === DOCUMENT_TYPE_NODE) {
                                        error("HierarchyRequestError");
                                }
                        }
                }
                break;
        case ELEMENT_NODE:
                /* #6b-1: parent has an Element child that is not child */
                for ($n=$parent->firstChild; $n!==NULL; $n=$n->nextSibling) {
                        if ($n->nodeType === ELEMENT_NODE && $n !== $child) {
                                error("HierarchyRequestError");
                        }
                }
                /* #6b-2: DocumentType is following child. */
                for ($n=$child->nextSibling; $n!==NULL; $n=$n->nextSibling) {
                        if ($n->nodeType === DOCUMENT_TYPE_NODE) {
                                error("HierarchyRequestError");
                        }
                }
                break;
        case DOCUMENT_TYPE_NODE:
                /* #6c-1: parent has a DocumentType child */
                for ($n=$parent->firstChild; $n!==NULL; $n=$n->nextSibling) {
                        if ($n->nodeType === DOCUMENT_TYPE_NODE) {
                                error("HierarchyRequestError");
                        }
                }
                /* #6c-2: an Element is preceding child */
                for ($n=$child->previousSibling; $n!==NULL; $n=$n->previousSibling) {
                        if ($n->nodeType === ELEMENT_NODE) {
                                error("HierarchyRequestError");
                        }
                }
                break;
        }
}




?>

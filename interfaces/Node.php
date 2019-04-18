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
 * CHANGED:
 *      Attributes had to become functions because PHP does not
 *      support getters and setters for property access.
 *      Node.baseURI            => Node->baseURI()
 *      Node.rooted             => Node->rooted()
 *      Node.outerHTML          => Node->outerHTML()
 *      Node.doc                => Node->doc()
 *      Node.previousSibling    => Node->previousSibling()
 *      Node.nextSibling        => Node->nextSibling()
 *
 *      TODO: The array splicing that happens needs to be cleaned up
 *      TODO: The "private" methods can be made private and static in PHP
 *
 *      Merged ContainerNode with Node to better match spec
 *      Moved _ensureChildNodes() into childNode() as a memoization branch
 *      and re-write do-while loop as for loop to match other traversals.
 *
 *      clone()                 => _subclass_cloneNode()
 *      isEqual()               => _subclass_isEqualNode()
 *      Changed the names of these delegated subclass things to be more
 *      clear about their purpose and role.
 *
 * NOT CHANGED:
 *      Node.parentNode         => Node->parentNode
 *      this one is kept as an attribute. Is this wise?
 *
 * TODO
 *      Find a way to signal that methods and properties are part of
 *      our extension, and not the DOM. E.g. removeChildren(), doc(),
 *      and so on.
 *
 *      Perhaps name them x_removeChildren(), or have them be in some
 *      kind of sub-object, like $node->domo->removeChildren() or something.
 *
 *****************************************************************************/
//use domo\EventTarget
use domo\LinkedList
use domo\NodeUtils
use domo\utils

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

/*
 * All Nodes have a nodeType and an ownerDocument.
 * Once inserted, they also have a parentNode.
 *
 * This is an abstract class; all Nodes in a Document are instances
 * of a subtype, so all the properties are defined by more specific
 * constructors.
 */
abstract class Node /* extends EventTarget // try factoring events out? */ {

        /**
         * Node types
         * ``````````
         * Integers enumerating the various specialized node types
         */
        const ELEMENT_NODE = 1;
        const ATTRIBUTE_NODE = 2;
        const TEXT_NODE = 3;
        const CDATA_SECTION_NODE = 4;
        const ENTITY_REFERENCE_NODE = 5;
        const ENTITY_NODE = 6;
        const PROCESSING_INSTRUCTION_NODE = 7;
        const COMMENT_NODE = 8;
        const DOCUMENT_NODE = 9;
        const DOCUMENT_TYPE_NODE = 10;
        const DOCUMENT_FRAGMENT_NODE = 11;
        const NOTATION_NODE = 12;

        /**
         * DOCUMENT_POSITION_*
         * ```````````````````
         * Bitmasks indicating position of a node x relative to a node y.
         * Returned from x->compareDocumentPosition(y)
         */

        /* x and y are not part of the same tree */
        const DOCUMENT_POSITION_DISCONNECTED = 1;
        /* y precedes x */
        const DOCUMENT_POSITION_PRECEDING = 2;
        /* y follows x */
        const DOCUMENT_POSITION_FOLLOWING = 4;
        /* y is an ancestor of x */
        const DOCUMENT_POSITION_CONTAINS = 8;
        /* y is a descendant of x */
        const DOCUMENT_POSITION_CONTAINED_BY = 16;
        /* whatever you need it to be */
        const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

        /*
         * Abstract functions that need to be implemented
         * by the child classes
         */

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

        /* Parent node (NULL if no parent) */
        public $_parentNode;
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

        /* DOM4-LS: Read only top-level Document object of the node */
        public $_ownerDocument;

        /* Other read-only properties */
        public $_nodeName;
        public $_nodeType;

        public function __construct()
        {
                $this->_parentNode = NULL;
                $this->_index = NULL;
                $this->_nextSibling = $this;
                $this->_previousSibling = $this;
                $this->_firstChild = NULL;
                $this->_childNodes = NULL;
        }

        public function baseURI()
        {
                /*
                 * TODO
                 * This is not implemented by Domino, so it is not
                 * implemented in this port.
                 *
                 * Apparently, implementing it correctly requires
                 * HTML features, so the domino developers held off.
                 */
        }

        public function nodeType()
        {
                return $this->_nodeType;
        }

        public function nodeName()
        {
                return $this->_nodeName;
        }

        public function ownerDocument()
        {
                if ($this->_ownerDocument) {
                        return $this->_ownerDocument;
                }
                return NULL;
        }

        /**
         * parentNode()
         * ````````````
         * Get a node's parent Node, if it has one.
         * Returns: Parent Node, or NULL
         */
        public function parentNode()
        {
                if ($this->_parentNode) {
                        return $this->_parentNode;
                }
                return NULL;
        }

        /**
         * parentElement()
         * ```````````````
         * Get a node's parent Element, if it has one.
         * Return: Parent Element node, or NULL
         */
        public function parentElement()
        {
                $p = $this->parentNode();

                if ($p != NULL && $p->nodeType === ELEMENT_NODE) {
                        return $p;
                }

                return NULL;
        }

        /**
         * previousSibling()
         * `````````````````
         * Get node preceding this one in parentNode's childNodes list
         * Return: Node, or NULL if no previous sibling exists
         */
        public function previousSibling()
        {
                $p = $this->parentNode();

                if ($p === NULL || $this === $p->firstChild()) {
                        return NULL;
                }

                return $this->_previousSibling;
        }

        /**
         * nextSibling()
         * `````````````
         * Get node succeeding this one in parentNode's childNodes list
         * Return: Node, or NULL if no succeeding sibling exists
         */
        public function nextSibling()
        {
                $p = $this->parentNode();

                if ($p === NULL || $this->_nextSibling === $p->firstChild()) {
                        return NULL;
                }

                return $this->_nextSibling;
        }

        /* TODO PORT NOTE: The first time this accessor is used, it will
         * switch the node into the array-like access branch by generating
         * the _childNodes NodeList and removing the _firstChild property.
         *
         * This is all internal however, since it will just switch e.g.
         * firstChild(), lastChild(), and hasChildNodes() to fetch
         * elements from the NodeList rather than references to the
         * the properties _firstChild.
         *
         * It has performance implications though, so it's good to be
         * aware of it and how it is working
         */
        public function childNodes()
        {
                /* Memoized fast path */
                if ($this->_childNodes !== NULL) {
                        return $this->_childNodes;
                }

                /* Build child nodes array by traversing the linked list */
                $childNodes = new NodeList();

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
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
                                return end($this->childNodes);
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

        public function removeChild(ChildNode $node)
        {
                if (!$node->nodeType) {
                        throw new TypeError("not a node");
                }
                if ($node->parentNode() !== $this) {
                        utils\NotFoundError();
                }
                $node->remove();

                return $node;
        }


        public function insertBefore($node, $child)
        {
                $parent = $this;

                /* 1. Ensure pre-insertion validity */
                $parent->_ensureInsertValid($node, $child, true);
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

        public function appendChild($child)
        {
                /* This invokes _appendChild after doing validity checks. */
                /* PORT NOTE TODO: It does no such thing */
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
                $parent->_ensureInsertValid($node, $child, false);
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

        /* TODO: Called exclusively within _ensureInsertValid */
        protected function _countChildrenOfType(int $type) : int
        {
                $count = 0;

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->nodeType === $type) {
                                $count++;
                        }
                }

                return $count;
        }

        /*
         * TODO PORT NOTE:
         * A rather long and complicated set of cases to determine whether
         * it is valid to insert a particular node at a particular location.
         *
         * Will throw an exception if an invalid condition is detected,
         * otherwise will do nothing.
         */
        protected function _ensureInsertValid($node, $child, boolean $isPreinsert)
        {
                /* TODO PORT: What */
                /* $parent = $this; */

                if (!$node->nodeType) {
                        /* TODO: PORT: That's it huh? */
                        throw new TypeError("not a node");
                }

                /*
                 * 1. If parent is not a Document, DocumentFragment, or Element
                 * node, throw a HierarchyRequestError.
                 */
                switch ($this->nodeType) {
                case DOCUMENT_NODE:
                case DOCUMENT_FRAGMENT_NODE:
                case ELEMENT_NODE:
                        break;
                default:
                        utils\HierarchyRequestError();
                }

                /*
                 * 2. If node is a host-including inclusive ancestor of parent,
                 * throw a HierarchyRequestError.
                 */
                if ($node->isAncestor($this)) {
                        utils\HierarchyRequestError();
                }

                /*
                 * 3. If child is not null and its parent is not $parent, then
                 * throw a NotFoundError. (replaceChild omits the "child is not
                 * null' and throws a TypeError here if child is null.)
                 */
                if ($child !== NULL || !$isPreinsert) {
                        if ($child->parentNode() !== $this) {
                                utils\NotFoundError();
                        }
                }
                /*
                 * 4. If node is not a DocumentFragment, DocumentType, Element,
                 * Text, ProcessingInstruction, or Comment node, throw a
                 * HierarchyRequestError.
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
                        utils\HierarchyRequestError();
                }

                /*
                 * 5. If either node is a Text node, and parent is a
                 * document, or node is a doctype and parent is not a
                 * document, throw a HierarchyRequestError
                 *
                 * 6. If parent is a document, and any of the statements
                 * below, switched on node, are true, throw a
                 * HierarchyRequestError.
                 */
                if ($this->nodeType === DOCUMENT_NODE) {
                        switch ($node->nodeType) {
                        case TEXT_NODE:
                                utils\HierarchyRequestError();
                                break;
                        case DOCUMENT_FRAGMENT_NODE:
                                /*
                                 * 6a1. If node has more than one element
                                 * child or has a Text node child.
                                 */
                                if ($node->_countChildrenOfType(TEXT_NODE) > 0) {
                                        utils\HierarchyRequestError();
                                }
                                switch ($node->_countChildrenOfType(ELEMENT_NODE)) {
                                case 0:
                                        break;
                                case 1:
                                        /*
                                         * 6a2. Otherwise, if node has one
                                         * element child and either parent has
                                         * an element child, child is a doctype,
                                         * or child is not null and a doctype
                                         * is following child. [preinsert]
                                         */
                                        /*
                                         * 6a2. Otherwise, if node has one
                                         * element child and either parent has
                                         * an element child that is not child
                                         * or a doctype is following child.
                                         * [replaceWith]
                                         */
                                        if ($child !== NULL /* always true here for replaceWith */) {
                                                if ($isPreinsert && $child->nodeType === DOCUMENT_TYPE_NODE) {
                                                        utils\HierarchyRequestError();
                                                }
                                                for ($kid=$child->nextSibling(); $kid !== NULL; $kid = $kid->nextSibling()) {
                                                        if ($kid->nodeType === DOCUMENT_TYPE_NODE) {
                                                                utils\HierarchyRequestError();
                                                        }
                                                }
                                        }
                                        $i = $this->_countChildrenOfType(ELEMENT_NODE);
                                        if ($isPreinsert) {
                                                // "parent has an element child"
                                                if ($i > 0) {
                                                        utils\HierarchyRequestError();
                                                } else {
                                                // "parent has an element child that is not child"
                                                        if ($i > 1 || ($i === 1 && $child->nodeType !== ELEMENT_NODE)) {
                                                                utils\HierarchyRequestError();
                                                        }
                                                }
                                        }
                                        break;
                                default:
                                        /*
                                         * 6a1, continued.
                                         * (more than one Element child)
                                         */
                                        utils\HierarchyRequestError();
                                }
                                break;

                        case ELEMENT_NODE:
                                /*
                                 * 6b. parent has an element child, child is a
                                 * doctype, or child is not null and a doctype
                                 * is following child. [preinsert]
                                 */
                                /*
                                 * 6b. parent has an element child that is not
                                 * child or a doctype is following child.
                                 * [replaceWith]
                                 */
                                if ($child !== NULL /* always true here for replaceWith */) {
                                        if ($isPreinsert && $child->nodeType === DOCUMENT_TYPE_NODE) {
                                                utils\HierarchyRequestError();
                                        }
                                        for ($kid = $child->nextSibling(); $kid !== NULL; $kid = $kid->nextSibling()) {
                                                if ($kid->nodeType === DOCUMENT_TYPE_NODE) {
                                                        utils\HierarchyRequestError();
                                                }
                                        }
                                }
                                $i = $this->_countChildrenOfType(ELEMENT_NODE);
                                if ($isPreinsert) {
                                        /* "parent has an element child" */
                                        if ($i > 0) {
                                                utils\HierarchyRequestError();
                                        }
                                } else {
                                        /* "parent has an element child that is not child" */
                                        if ($i > 1 || ($i === 1 && $child->nodeType !== ELEMENT_NODE)) {
                                                utils\HierarchyRequestError();
                                        }
                                }
                                break;

                        case DOCUMENT_TYPE_NODE:
                                /*
                                 * 6c. parent has a doctype child, child is
                                 * non-null and an element is preceding child,
                                 * or child is null and parent has an element
                                 * child. [preinsert]
                                 */
                                /*
                                 * 6c. parent has a doctype child that is not
                                 * child, or an element is preceding child.
                                 * [replaceWith]
                                 */
                                if ($child === NULL) {
                                        if ($this->_countChildrenOfType(ELEMENT_NODE)) {
                                                utils\HierarchyRequestError();
                                        }
                                } else {
                                        /* child is always non-null for [replaceWith] case */
                                        for ($kid = $this->firstChild(); $kid !== NULL; $kid = $kid->nextSibling()) {
                                                if ($kid === $child) {
                                                        break;
                                                }
                                                if ($kid->nodeType === ELEMENT_NODE) {
                                                        utils\HierarchyRequestError();
                                                }
                                        }
                                }
                                $i = $this->_countChildrenOfType(DOCUMENT_TYPE_NODE);
                                if ($isPreinsert) {
                                        /* "parent has an doctype child" */
                                        if ($i > 0) {
                                                utils\HierarchyRequestError();
                                        }
                                } else {
                                        /* "parent has an doctype child that is not child" */
                                        if ($i > 1 || ($i === 1 && $child->nodeType !== DOCUMENT_TYPE_NODE)) {
                                                utils\HierarchyRequestError();
                                        }
                                }
                                break;
                        }
                } else {
                        /*
                         * 5, continued: (parent is not a document)
                         */
                        if ($node->nodeType === DOCUMENT_TYPE_NODE) {
                                utils\HierarchyRequestError();
                        }
                }

                /*
                 * If you made it here without throwing any exceptions,
                 * then you're valid!
                 */
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
                if ($other->nodeType !== $this->nodeType) {
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
        public function cloneNode(boolean $deep = false)
        {
                /* Make a shallow clone using the delegated method */
                $clone = $this->_subclass_cloneNodeShallow();

                /* If the shallow clone is all we wanted, we're done. */
                if ($deep === false) {
                        return $clone;
                }

                /* Otherwise, recurse on the children */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                $clone->_appendChild($n->cloneNode(true));
                        }
                }

                return $clone;
        }

        public function lookupPrefix($ns)
        {
                if ($ns === "" || $ns === NULL) {
                        return NULL;
                }

                switch ($this->nodeType) {
                case ELEMENT_NODE:
                        /* TODO PORT : What the heck function is this? */
                        return $this->_lookupNamespacePrefix($ns, $this);
                case DOCUMENT_NODE:
                        if ($this->documentElement) {
                                return $this->documentElement->lookupPrefix($ns);
                        }
                        break;
                case ENTITY_NODE:
                case NOTATION_NODE:
                case DOCUMENT_FRAGMENT_NODE:
                case DOCUMENT_TYPE_NODE:
                        break;
                case ATTRIBUTE_NODE:
                        if ($this->ownerElement) {
                                return $this->ownerElement->lookupPrefix($ns);
                        }
                        break;
                default:
                        if ($this->parentElement()) {
                                return $this->parentElement()->lookupPrefix($ns);
                        }
                        break;
                }

                return NULL;
        }

        public function lookupNamespaceURI($prefix)
        {
                if ($prefix === "") {
                        $prefix = NULL;
                }

                switch ($this->nodeType) {
                case ELEMENT_NODE:
                        return utils.shouldOverride();
                case DOCUMENT_NODE:
                        if ($this->documentElement) {
                                return $this->documentElement->lookupNamespaceURI($prefix);
                        }
                        break;
                case ENTITY_NODE:
                case NOTATION_NODE:
                case DOCUMENT_TYPE_NODE:
                case DOCUMENT_FRAGMENT_NODE:
                        break;
                case ATTRIBUTE_NODE:
                        if ($this->ownerElement) {
                                return $this->ownerElement->lookupNamespaceURI($prefix);
                        }
                       break;
                default:
                        if ($this->parentElement()) {
                                return $this->parentElement()->lookupNamespaceURI($prefix);
                        }
                        break;
                }

                return NULL;
        }

        public function isDefaultNamespace($ns)
        {
                if ($ns === "") {
                        $ns = NULL;
                }

                $defaultNamespace = $this->lookupNamespaceURI(NULL);

                return $defaultNamespace === $ns;
        }

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
        public function ensureSameDoc($that)
        {
                if ($that->ownerDocument === NULL) {
                        $that->ownerDocument = $this->doc();
                } else {
                        if ($that->ownerDocument !== $this->doc()) {
                                utils\WrongDocumentError();
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
                        $n->_parentNode = NULL;
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
                        utils\HierarchyRequestError();
                }

                /*
                 * Ensure index of `before` is cached before we (possibly)
                 * remove it.
                 */
                if ($parent->_childNodes) {
                        if ($before === NULL) {
                                $before_index = count($parent->_childNodes);
                        } else {
                                $before_index = $before->index;
                        }

                        /*
                         * If we are already a child of the specified parent,
                         * then the index may have to be adjusted.
                         */
                        if ($child->parentNode() === $parent) {
                                $child_index = $child->index;
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

                if ($child->nodeType === DOCUMENT_FRAGMENT_NODE) {
                        $spliceArgs = [0, $isReplace ? 1 : 0];
                        $next;

                        for ($kid = $child->firstChild; $kid !== NULL; $kid = $next) {
                                $next = $kid->nextSibling;
                                $spliceArgs[] = $kid;
                                $kid->_parentNode = $parent;
                        }

                        $len = count(spliceArgs);

                        /*
                         * Add all nodes to the new parent,
                         * overwriting the old child
                         */
                        if ($isReplace) {
                                LinkedList\replace($n, $len > 2 ? $spliceArgs[2] : NULL);
                        } else {
                                if ($len > 2 && $n !== NULL) {
                                        LinkedList\insertBefore($spliceArgs[2], $n);
                                }
                        }

                        if ($parent->_childNodes) {
                                if ($before === NULL) {
                                        $spliceArgs[0] = count($parent->_childNodes);
                                } else {
                                        $spliceargs[0] = $before->_index;
                                }

                                //parent._childNodes.splice.apply(parent._childNodes, spliceArgs);
                                /* TODO PORT: This will not work, we need to untangle this mess */
                                array_splice($parent->_childNodes, $spliceArgs);

                                for ($i=2; $i<$len; $i++) {
                                        $spliceArgs[$i]->_index = $spliceArgs[0] + ($i - 2);
                                }
                        } else {
                                if ($parent->_firstChild === $before) {
                                        if ($len > 2) {
                                                $parent->_firstChild = $spliceArgs[2];
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
                                        array_splice($parent->_childNodes($before_index, 0, $child));
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
         * TODO PORT: This was changed from a property to a method
         * because PHP doesn't do getters and setters like JavaScript
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

        /*
         * Convert the children of a node to an HTML string.
         * This is used by the innerHTML getter
         * The serialization spec is at:
         * http://www.whatwg.org/specs/web-apps/current-work/multipage/the-end.html#serializing-html-fragments
         *
         * The serialization logic is intentionally implemented in a separate
         * `NodeUtils` helper instead of the more obvious choice of a private
         * `_serializeOne()` method on the `Node.prototype` in order to avoid
         * the megamorphic `this._serializeOne` property access, which reduces
         * performance unnecessarily. If you need specialized behavior for a
         * certain subclass, you'll need to implement that in `NodeUtils`.
         * See https://github.com/fgnass/domino/pull/142 for more information.
         */
        public function serialize()
        {
                $s = "";

                for ($kid = $this->firstChild; $kid !== NULL; $kid = $kid->nextSibling) {
                        $s += NodeUtils\serializeOne($kid, $this);
                }

                return $s;
        }

        /* Non-standard, but often useful for debugging. */
        public function outerHTML(string $value = NULL)
        {
                if ($value == NULL) {
                        return NodeUtils\serializeOne($this, { $nodeType: 0 });
                } else {
                        /* not yet implemented */
                }
        }
}


?>

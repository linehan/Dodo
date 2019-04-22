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

abstract class Node /* extends EventTarget // try factoring events out? */ 
{
        abstract public function textContent(string $value=NULL);       /* Should override for DocumentFragment/Element/Attr/Text/ProcessingInstruction/Comment */

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

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about its ancestors 
         **********************************************************************/

        /* DOM-LS: Read only top-level Document object of the node */
        public $_ownerDocument;

        /* DOM-LS: Parent node (NULL if no parent) */
        public $_parentNode;

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about the childNodes of its parent 
         **********************************************************************/

        /* [DOMO] Node's index in childNodes of parent (NULL if no parent) */
        public $_siblingIndex;

        /* Next sibling in childNodes of parent ($this if none) */
        public $_nextSibling;

        /* Prev sibling in childNodes of parent ($this if none) */
        public $_previousSibling;

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about its own childNodes 
         **********************************************************************/

        /* First child Node (NULL if no children) */
        public $_firstChild;

        /* ARRAY FORM of childNodes list (NULL if no children or using LL) */
        /* 
         * TODO It's hard to understand if you aren't familiar with the
         * code, that 'if ($this->_childNodes)' is testing whether we are
         * using the array representation or a LL representation.
         * How can we fix this?
         */
        public $_childNodes;

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about itself 
         **********************************************************************/

        /* DOM-LS Subclasses are responsible for setting these */
        public $_nodeName;
        public $_nodeType;

        public function __construct()
        {
                $this->_parentNode = NULL;
		$this->_ownerDocument = NULL;

                /* Our children */
                $this->_firstChild = NULL;
                $this->_childNodes = NULL;

                /* Our siblings */
                $this->_nextSibling = $this;
                $this->_previousSibling = $this;
                $this->_siblingIndex = NULL;
        }

        public function baseURI(){}

        public function parentNode(void): ?Node
        {
                return $this->_parentNode ?? NULL;
        }

        public function parentElement(void): ?Element
        {
                if ($this->_parentNode === NULL 
                || $this->_parentNode->nodeType !== ELEMENT_NODE) {
                        return NULL;
                }
                return $this->parentNode;
        }

        public function previousSibling(void): ?Node
        {
                /* LL loops depend on this returning NULL on these conds. */
                if ($this->_parentNode === NULL 
                || $this === $this->_parentNode->_firstChild) {
                        return NULL;
                }
                return $this->_previousSibling;
        }
        public function nextSibling(void): ?Node
        {
                /* LL loops depend on this returning NULL on these conds. */
                if ($this->_parentNode === NULL 
                || $this->_nextSibling === $this->_parentNode->_firstChild) {
                        return NULL;
                }
                return $this->_nextSibling;
        }

        public function childNodes(void): ?NodeList
        {
                /* Memoized fast path */
                if ($this->_childNodes !== NULL) {
                        return $this->_childNodes;
                }

                /* Build child nodes array by traversing the linked list */
                $this->_childNodes = new NodeList();

                for ($n=$this->_firstChild; $n!==NULL; $n=$n->_nextSibling) {
                        $this->_childNodes[] = $n;
                }

                /*
                 * SIDE EFFECT:
                 * Switch from circular linked list branch to array-like
                 * branch.
                 *
                 * (Which branch we are on is detected by looking for which
                 * of these two members is NULL, see methods below.)
                 */
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
                _DOM_ensureInsertValid($node, $this, $child);

                /* 2. Let reference child be child. */
                $refChild = $child;

                /* 3. If reference child is node, set it to node's next sibling */
                if ($refChild === $node) {
                        $refChild = $node->nextSibling();
                }

                /* 4. Adopt node into parent's node document. */
                $this->doc()->adoptNode($node);

                /* 5. Insert node into parent before reference child. */
                _DOM_insertBeforeOrReplace($node, $this, $refChild, false);

                /* 6. Return node */
                return $node;
        }

        public function removeChild(ChildNode $node)
        {
                if ($this !== $node->_parentNode) {
                        \domo\error("NotFoundError");
                }
                $node->remove();

                return $node;
        }

        public function appendChild(Node $node)
        {
		/*
		 * [DOM-LS]: "To append a node to parent, pre-insert node
		 * into parent before NULL."
		 */
                return $this->insertBefore($node, NULL);
        }

        /* To replace a `child` with `node` within a `parent` (this) */
        public function replaceChild(Node $node, ?Node $child)
        {
                /* Ensure validity (slight differences from pre-insertion check) */
                _DOM_ensureReplaceValid($node, $this, $child);

                /* Adopt node into parent's node document. */
                if ($node->doc() !== $this->doc()) {
                        /*
                         * XXX adoptNode has side-effect of removing node from
                         * its parent and generating a mutation event, thus
                         * causing the _insertOrReplace to generate two deletes
                         * and an insert instead of a 'move' event.  It looks
                         * like the new MutationObserver stuff avoids this
                         * problem, but for now let's only adopt (ie, remove
                         * `node` from its parent) here if we need to.
                         */
                        $this->doc()->adoptNode($node);
                }

                /* Do the replace. */
                _DOM_insertBeforeOrReplace($node, $this, $child, true);

                return $child;
        }

	/**********************************************************************
	 * COMPARISONS AND PREDICATES
	 *********************************************************************/

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



        public function _insertOrReplace_FIN(Node $parent, ?Node $before, boolean $isReplace)
        {
                $child = $this

                if ($child->_nodeType === DOCUMENT_FRAGMENT_NODE && $child->rooted()) {
                        \domo\error("HierarchyRequestError");
                }
                if ($before === $child) {
                        return;
                }

                /* ARRAY MODE */
                if ($parent->_childNodes) {
                        /* 
                         * Compute what index to assign to the inserted Node 
                         * (If you care, it might help to draw a picture)
                         */
                        if ($before === NULL) {
                                /* 
                                 * When we don't specify Node to insert 
                                 * relative to, [DOM-LS] says we assume
                                 * that we're appending.
                                 */
                                $index_to_insert_at = count($parent->_childNodes);
                        } else {
                                /* 
                                 * Otherwise, one of two things is true:
                                 *      - we are going to be inserted before
                                 *        $before, in which case we take over 
                                 *        its index ($before and all subsequent 
                                 *        siblings will shift down by 1)
                                 *      - we are going to replace $before,
                                 *        in which case we are going to take
                                 *        over its index and all subsequent
                                 *        siblings will not shift down by 1.
                                 * 
                                 * Either way, our index is going to be the
                                 * index of $before. 
                                 */
                                $index_to_insert_at = $before->index();
                        }
                        if ($child->_parentNode === $parent) {
                                /*
                                 * If we are already a child of the parent
                                 * we are going to insert into, that means
                                 * we can view this operation as a move.
                                 *
                                 * Note that this will never happen if we
                                 * are a DocumentFragment, since those can
                                 * never have a parent.
                                 */
                                if ($child->index() < $index_to_insert_at) {
                                        /* 
                                         * But then, if our current position
                                         * is prior to the one we wish to 
                                         * insert into, the removal of our
                                         * node during this move will have the
                                         * effect of shifting all the nodes
                                         * after us down by 1. That includes
                                         * the place we wish to insert into.
                                         * Therefore we must reduce it by 1.
                                         *
                                         * Notice that in an append (see above),
                                         * this ensures that $item_to_insert_at
                                         * will always be the last index in the
                                         * sibling list.
                                         */
                                        $index_to_insert_at--;
                                }
                        }
                }

                /* LINKED LIST MODE 
                 *
                 * TODO: This one is harder to understand because it's used
                 * whether or not ARRAY MODE is active, so it's not like they
                 * are mutually exclusive. 
                 * Basically, $n is the node to work with for the LL stuff.
                 *
                 * I say it's LL-specific because these LL's are circular and
                 * have the quirk that if we insert before _firstChild, we
                 * are effectively appending to the circular LL. That is quite
                 * confusing, right?
                 */
                $reference_node = $before ?? $parent->firstChild();

                /* Delete the old child */
                if ($isReplace) {
                        if ($before->rooted()) {
                                $before->doc()->mutateRemove($before);
                        }
                        $before->_parentNode = NULL;
                }

                /*
                 * If both the child and the parent are rooted,
                 * then we want to transplant the child without
                 * uprooting and rerooting it.
                 *
                 * (will never be true if $child instanceof DocumentFragment)
                 */
                $bothWereRooted = $child->rooted() && $parent->rooted();
                if ($bothWereRooted) {
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


                $insert = array();

                if ($child instanceof DocumentFragment) {
                        for ($n=$child->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                $insert[] = $n; /* TODO: Needs to clone? */
                                $n->_parentNode = $parent;
                        }
                } else {
                        $insert[0] = $child; /* TODO: Needs to clone? */
                        $insert[0]->_parentNode = $parent;
                }

                if (empty($insert)) {
                        /* 
                         * TODO: I think that $reference_node is always 
                         * non-NULL if $isReplace is true. This is shaky, and
                         * we should factor this condition out.
                         */
                        if ($isReplace) {
                                if ($reference_node !== NULL /* If you work it out, you'll find that this condition is equivalent to 'if $parent has children' */) {
                                        LinkedList\replace($reference_node, NULL);
                                }
                                if ($parent->_childNodes === NULL && $parent->_firstChild === $before) {
                                        $parent->_firstChild = NULL;
                                }
                        }
                } else {
                        if ($reference_node !== NULL) {
                                if ($isReplace) {
                                        LinkedList\replace($reference_node, $insert[0]);
                                } else {
                                        LinkedList\insertBefore($insert[0], $reference_node);
                                }
                        }
                        if ($parent->_childNodes !== NULL) {
                                if ($isReplace) {
                                        array_splice($parent->_childNodes, $index_to_insert_at, 1, $insert);
                                } else {
                                        array_splice($parent->_childNodes, $index_to_insert_at, 0, $insert);
                                }
                                foreach ($insert as $i => $n) {
                                        $n->_index = $index_to_insert_at + $i;
                                }
                        } else if ($parent->_firstChild === $before) {
                                $parent->_firstChild = $insert[0];
                        }
                }


                if ($child->_nodeType === DOCUMENT_FRAGMENT_NODE) {
                        /* 
                         * Remove these references on the DocumentFragment,
                         * so that it now stands empty.
                         * TODO: Why? SPEC SAYS SO!
                         */
                        if ($child->_childNodes) {
                                /* TODO PORT: This is the easiest way to do this in PHP and preserves references */
                                $child->_childNodes = array();
                        } else {
                                $child->_firstChild = NULL;
                        }
                }

                if ($bothWereRooted) {
                        /* Generate a move mutation event */
                        $parent->modify();
                        $parent->doc()->mutateMove($insert[0]);
                } else {
                        if ($parent->rooted()) {
                                $parent->modify();
                                foreach ($insert as $n) {
                                        $parent->doc()->mutateInsert($n);
                                }
                        }
                }


                if ($child->_nodeType === DOCUMENT_FRAGMENT_NODE) {

                        $insert = array();

                        for ($n=$child->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                /* 
                                 * Keep a reference to these in an array
                                 * because we're going to destroy the refs 
                                 * on the DocumentFragment and will need 
                                 * them later. 
                                 * TODO: I think this is supposed to be a
                                 * clone, but in PHP these are references.
                                 * Need to fix this.
                                 */
                                $insert[] = $n;

                                /* Re-assign each Node's parent as well */
                                $n->_parentNode = $parent;
                        }

                        if (empty($insert)) {
                                /* TODO: I think that $reference_node is always non-NULL if $isReplace is true. This is shaky, and
                                 * we should factor this condition out.
                                 */
                                if ($isReplace) {
                                        if ($reference_node !== NULL /* If you work it out, you'll find that this condition is equivalent to 'if $parent has children' */) {
                                                LinkedList\replace($reference_node, NULL);
                                        }
                                        if ($parent->_childNodes === NULL && $parent->_firstChild === $before) {
                                                $parent->_firstChild = NULL;
                                        }
                                }
                        } else {
                                if ($reference_node !== NULL) {
                                        if ($isReplace) {
                                                LinkedList\replace($reference_node, $insert[0]);
                                        } else {
                                                LinkedList\insertBefore($insert[0], $reference_node);
                                        }
                                }
                                if ($parent->_childNodes !== NULL) {
                                        if ($isReplace) {
                                                array_splice($parent->_childNodes, $index_to_insert_at, 1, $insert);
                                        } else {
                                                array_splice($parent->_childNodes, $index_to_insert_at, 0, $insert);
                                        }
                                        foreach ($insert as $i => $n) {
                                                $n->_index = $index_to_insert_at + $i;
                                        }
                                } else if ($parent->_firstChild === $before) {
                                        $parent->_firstChild = $insert[0];
                                }
                        }

                        /* 
                         * Remove these references on the DocumentFragment,
                         * so that it now stands empty.
                         * TODO: Why? SPEC SAYS SO!
                         */
                        if ($child->_childNodes) {
                                /* TODO PORT: This is the easiest way to do this in PHP and preserves references */
                                $child->_childNodes = array();
                        } else {
                                $child->_firstChild = NULL;
                        }

                        /*
                         * Call the mutation handlers
                         * Use spliceArgs since the original array has been
                         * destroyed. 
                         * TODO: The liveness guarantee requires us to
                         * clone the array so that references to the childNodes
                         * of the DocumentFragment will be empty when the
                         * insertion handlers are called.
                         * THIS IS NOT BEING DONE RIGHT NOW I THINK?
                         */
                        if ($parent->rooted()) {
                                $parent->modify();
                                foreach ($insert as $n) {
                                        $parent->doc()->mutateInsert($n);
                                }
                        }
                /* NOT A DocumentFragment */ 
                } else {
                        /*
                         * If both the child and the parent are rooted,
                         * then we want to transplant the child without
                         * uprooting and rerooting it.
                         */
                        $bothWereRooted = $child->rooted() && $parent->rooted();
                        if ($bothWereRooted) {
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

                        if ($reference_node !== NULL) {
                                if ($isReplace) {
                                        LinkedList\replace($reference_node, $child);
                                } else {
                                        LinkedList\insertBefore($child, $reference_node);
                                }
                        }
                        if ($parent->_childNodes !== NULL) {
                                if ($isReplace) {
                                        $parent->_childNodes[$index_to_insert_at] = $child;
                                } else {
                                        array_splice($parent->_childNodes, $index_to_insert_at, 0, $child);
                                }
                                $child->_index = $index_to_insert_at;
                        } else if ($parent->_firstChild === $before) {
                                $parent->_firstChild = $child;
                        }

                        if ($bothWereRooted) {
                                /* Generate a move mutation event */
                                $parent->modify();
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
         * Insert this node as a child of parent before the specified child,
         * or insert as the last child of parent if specified child is null,
         * or replace the specified child with this node, firing mutation events as
         * necessary
         */
        public function _insertOrReplace($parent, $before, $isReplace)
        {
                $child = $this
                $index_to_insert_at;
                $i;

                if ($child->_nodeType === DOCUMENT_FRAGMENT_NODE && $child->rooted()) {
                        \domo\error("HierarchyRequestError");
                }

                /* ARRAY MODE */
                if ($parent->_childNodes) {
                        /* 
                         * Compute what index to assign to the inserted Node 
                         * (If you care, it might help to draw a picture)
                         */
                        if ($before === NULL) {
                                /* 
                                 * When we don't specify Node to insert 
                                 * relative to, [DOM-LS] says we assume
                                 * that we're appending.
                                 */
                                $index_to_insert_at = count($parent->_childNodes);
                        } else {
                                /* 
                                 * Otherwise, one of two things is true:
                                 *      - we are going to be inserted before
                                 *        $before, in which case we take over 
                                 *        its index ($before and all subsequent 
                                 *        siblings will shift down by 1)
                                 *      - we are going to replace $before,
                                 *        in which case we are going to take
                                 *        over its index and all subsequent
                                 *        siblings will not shift down by 1.
                                 * 
                                 * Either way, our index is going to be the
                                 * index of $before. 
                                 */
                                $index_to_insert_at = $before->index();
                        }

                        if ($child->_parentNode === $parent) {
                                /*
                                 * If we are already a child of the parent
                                 * we are going to insert into, that means
                                 * we can view this operation as a move.
                                 */
                                if ($child->index() < $index_to_insert_at) {
                                        /* 
                                         * But then, if our current position
                                         * is prior to the one we wish to 
                                         * insert into, the removal of our
                                         * node during this move will have the
                                         * effect of shifting all the nodes
                                         * after us down by 1. That includes
                                         * the place we wish to insert into.
                                         * Therefore we must reduce it by 1.
                                         *
                                         * Notice that in an append (see above),
                                         * this ensures that $item_to_insert_at
                                         * will always be the last index in the
                                         * sibling list.
                                         */
                                        $index_to_insert_at--;
                                }
                        }
                }

                /* LINKED LIST MODE 
                 *
                 * TODO: This one is harder to understand because it's used
                 * whether or not ARRAY MODE is active, so it's not like they
                 * are mutually exclusive. 
                 * Basically, $n is the node to work with for the LL stuff.
                 *
                 * I say it's LL-specific because these LL's are circular and
                 * have the quirk that if we insert before _firstChild, we
                 * are effectively appending to the circular LL. That is quite
                 * confusing, right?
                 */
                if ($before !== NULL) {
                        $n = $before;
                } else {
                        $n = $parent->_firstChild;
                }

                /* Delete the old child */
                if ($isReplace) {
                        if ($before->rooted()) {
                                $before->doc()->mutateRemove($before);
                        }
                        $before->_parentNode = null;
                }


                /*
                 * If both the child and the parent are rooted,
                 * then we want to transplant the child without
                 * uprooting and rerooting it.
                 */
                $bothRooted = $child->rooted() && $parent->rooted();

                if ($child->_nodeType === DOCUMENT_FRAGMENT_NODE) {
                        
                        /* 
                         * If it's a DocumentFragment, then essentially it
                         * means we are inserting not one Node, but 
                         * potentially a bunch of Nodes (the children of the
                         * DocumentFragment). 
                         *
                         * Therefore this branch does similar things to the
                         * else branch, only it needs to handle inserting
                         * more than one Node. 
                         */

                        $spliceArgs = array(0, $isReplace ? 1 : 0);
                        $nodes_to_insert = array();

                        for ($c=$child->firstChild(); $c!==NULL; $c=$c->nextSibling()) {
                                /* Keep a reference to these in an array
                                   [We are going to destroy the refs on the
                                   DocumentFragment and will need them later] 
                                 */
                                $nodes_to_insert[] = $c;
                                /* While you're at it, set the node's parents;
                                 * This should probably be done some other
                                 * time... */
                                $c->_parentNode = $parent;
                        }

                        $len = count($nodes_to_insert) + count($spliceArgs);

                        /*
                         * Add all nodes to the new parent,
                         * overwriting the old child
                         */
                        if ($isReplace) {
                                LinkedList\replace($n, $len > 2 ? $nodes_to_insert[0] : NULL);
                        } else {
                                if ($len > 2 && $n !== NULL) {
                                        LinkedList\insertBefore($nodes_to_insert[0], $n);
                                }
                        }

                        if ($parent->_childNodes) {
                                /* 
                                   TODO TODO TODO TODO TODO TODO TODO TODO

                                   TODO: HEY WTF, THIS IS THE SAME AS WE'RE
                                   DOING ABOVE, DETERMINING THE INSERT POSITION.
                                   WHY ARE WE DUPLICATING IT? HERE?

                                   AND WHY ARE WE NOT DOING THE DECREMENT THING
                                   WE DID UP THERE?

                                   IF WE REALLY DO IT THIS WAY, THEN THE BIG
                                   'IF' STATEMENT THAT STARTS THIS FN ONLY 
                                   REALLY NEEDS TO BE COMPUTED FOR THE ELSE
                                   BRANCH (WHERE WE AREN'T A DOCUMENT FRAGMENT)

                                   OHHHHH -- BECAUSE WE'RE A 
                                   DOCUMENTFRAGMENT, WE DON'T *HAVE* A PARENT!

                                   SO THEN YES! FACTOR IT SO THAT THIS IS DONE
                                   FOR EITHER BRANCH, AND THEN DO THE BIT WITH
                                   THE ADJUSTMENT ON THE ELSE BRANCH, WHERE WE
                                   COULD CONCEIVABLY BE A CHILD OF THE PARENT
                                   ALREADY.

                                   TODO TODO TODO TODO TODO TODO TODO TODO
                                 */
                                if ($before === NULL) {
                                        $spliceArgs[0] = count($parent->_childNodes);
                                } else {
                                        $spliceargs[0] = $before->_index;
                                }

                                //parent._childNodes.splice.apply(parent._childNodes, spliceArgs);
                                array_splice($parent->_childNodes, $spliceArgs[0], $spliceArgs[1], $nodes_to_insert);

                                /*
                                 * TODO: We aren't just re-indexing one, we have
                                 * to re-index all of the things we append. 
                                 */
                                foreach ($nodes_to_insert as $i => $inserted_node) {
                                        $inserted_node->_index = $spliceArgs[0] + $i;
                                }

                                /* TODO: We don't re-index the shifted nodes? 
                                 * Ofc not, it catches in the index() function 
                                 * when it finds out that 
                                 * $this->_childNodes[$n] !== $this or whatever.
                                 */
                        } else {
                                if ($parent->_firstChild === $before) {
                                        if ($len > 2) {
                                                $parent->_firstChild = $nodes_to_insert[0];
                                        } else {
                                                if ($isReplace) {
                                                        // huh? Why would it be empty? Insert NULL is spec valid? */
                                                        $parent->_firstChild = NULL;
                                                }
                                        }
                                }
                        }

                        /* 
                         * Remove these references on the DocumentFragment 
                         * TODO: Why? 
                         */
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
                                foreach ($nodes_to_insert as $inserted_node) {
                                        $parent->doc()->mutateInsert($inserted_node);
                                }
                        }
                /* NOT A DocumentFragment */ 
                } else {

                        if ($before === $child) {
                                /* 
                                 * TODO: Easy street; this could be moved up to
                                 * the main part of the function, way up to
                                 * the top, for an even faster quit.
                                 */ 
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
                                        $child->_index = $index_to_insert_at;
                                        $parent->_childNodes[$index_to_insert_at] = $child;
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
                                        $child->_index = $index_to_insert_at;
                                        // parent._childNodes.splice(before_index, 0, child);
                                        array_splice($parent->_childNodes, $index_to_insert_at, 0, $child);
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

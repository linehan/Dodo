<?php
/******************************************************************************
 * Node.php
 * --------
 * Defines a "Node", the primary datatype of the W3C Document Object Model.
 *
 * Conforms to W3C Document Object Model (DOM) Level 1 Recommendation
 * (see: https://www.w3.org/TR/2000/WD-DOM-Level-1-20000929)
 *
 *****************************************************************************/
namespace Dodo;

require_once("NodeList.php");
require_once('utilities.php');
require_once("whatwg.php");

abstract class Node
{
        /*
         * NOTE: We do not include all of the constant node type enums
         * see: https://dom.spec.whatwg.org/#node.
         */

        /**********************************************************************
         * Abstract methods that must be defined in subclasses
         **********************************************************************/

        /* Delegated subclass method called by Node::isEqualNode() */
        abstract protected function _subclass_isEqualNode(Node $node): bool;

        /* Delegated subclass method called by Node::cloneNode() */
        abstract protected function _subclass_cloneNodeShallow(): ?Node;

        /**********************************************************************
         * Properties that appear in DOM-LS
         **********************************************************************/

        /*
         * SET IN SUBCLASS CONSTRUCTOR
         */
        public $_nodeType;     /* readonly unsigned short */
        public $_nodeName;     /* readonly DOMString */
        public $_nodeValue;    /* readonly DOMString or NULL */

        /*
         * SET WHEN SOMETHING APPENDS NODE
         */
        public $_ownerDocument; /* readonly Document or NULL */
        public $_parentNode;    /* readonly Node or NULL */
        /*
         * DEVIATION FROM SPEC
         * PURPOSE: SIBLING TRAVERSAL OPTIMIZATION
         *
         * If a Node has no siblings,
         * i.e. it is the 'only child'
         * of $_parentNode, then the
         * properties $_nextSibling
         * and $_previousSibling are
         * set equal to $this.
         *
         * This is an optimization for
         * traversing siblings, but in
         * DOM-LS, these properties
         * should be NULL in this
         * scenario.
         *
         * The relevant accessors are
         * spec-compliant, returning
         * NULL in this situation.
         */
        public $_nextSibling;     /* readonly Node or NULL */
        public $_previousSibling; /* readonly Node or NULL */

        /*
         * SET WHEN NODE APPENDS SOMETHING
         */
        public $_firstChild;      /* readonly Node or NULL */
        /*
         * DEVIATION FROM SPEC
         * PURPOSE: APPEND OPTIMIZATION
         *
         * The $_childNodes property
         * holds an array-like object
         * (a NodeList) referencing
         * each of a Node's children
         * as a live representation of
         * the DOM.
         *
         * This 'liveness' is somewhat
         * unperformant, and the
         * upkeep of this object has
         * a significant impact on
         * append performance.
         *
         * So, this implementation
         * chooses to defer its
         * construction until a value
         * is requested by calling
         * Node::childNodes().
         *
         * Until that time, it will
         * have the value NULL.
         */
        public $_childNodes;      /* readonly NodeList or NULL */

        /**********************************************************************
         * Properties that are for internal use by this library
         **********************************************************************/

        /*
         * DEVELOPERS NOTE:
         * An index is assigned on
         * ADOPTION. It uniquely
         * identifies the Node
         * within its owner Document.
         *
         * This index makes it
         * simple to represent a
         * Node as an integer.
         *
         * It exists for a single
         * optimization. If two
         * Elements have the same
         * id, they will be stored
         * in an array under their
         * $document_index. This
         * means we don't have to
         * search the array for a
         * matching Node, we can
         * look it up in O(1). Yep.
         */
        protected $__document_index;

        /*
         * DEVELOPERS NOTE:
         * An index is assigned on
         * INSERTION. It uniquely
         * identifies the Node among
         * its siblings.
         *
         * It is used to help compute
         * document position and to
         * mark where insertion should
         * occur.
         *
         * Its existence is, frankly,
         * mostly for convenience due
         * to the fact that the most
         * common representation of
         * child nodes is a linked list
         * that doesn't have numeric
         * indices otherwise.
         *
         * FIXME
         * It is public because it
         * gets used by the whatwg
         * algorithms page.
         */
        public $__sibling_index;

        /* TODO: Unused */
        public $__roothook;

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
        }

        /**********************************************************************
         * ACCESSORS
         **********************************************************************/

        /*
         * Sometimes, subclasses will override
         * nodeValue and textContent, so these
         * accessors should be seen as "defaults,"
         * which in some cases are extended.
         */
        public function nodeValue(?string $value = NULL)
        {
                return $this->_nodeValue;
        }

        public function textContent(?string $value=NULL)
        {
                /*
                 * This is spec. Relevent classes
                 * should override. For more, see
                 * https://dom.spec.whatwg.org/#dom-node-textcontent
                 */
                return NULL;
        }

        final public function nodeType(): int
        {
                return $this->_nodeType;
        }

        final public function nodeName(): ?string
        {
                return $this->_nodeName;
        }

        /*
         * Nodes might not have an ownerDocument.
         * Perhaps they have not been inserted
         * into a DOM, or are themselves a
         * Document. In those cases, the value of
         * ownerDocument will be NULL.
         */
        final public function ownerDocument(): ?Document
        {
                return $this->_ownerDocument;
        }

        /*
         * Nodes might not have a parentNode.
         * Perhaps they have not been inserted
         * into a DOM, or are a Document node,
         * which is the root of a DOM tree and
         * thus has no parent. In those cases,
         * the value of parentNode is NULL.
         */
        final public function parentNode(): ?Node
        {
                return $this->_parentNode;
        }

        /*
         * This value is the same as parentNode,
         * except it puts an extra condition --
         * that the parentNode must be an Element.
         *
         * Accordingly, it requires no additional
         * backing property, and can exist only
         * as an accessor.
         */
        final public function parentElement(): ?Element
        {
                if ($this->_parentNode === NULL) {
                        return NULL;
                }
                if ($this->_parentNode->_nodeType === ELEMENT_NODE) {
                        return $this->_parentNode;
                }
                return NULL;
        }

        final public function previousSibling(): ?Node
        {
                if ($this->_parentNode === NULL) {
                        return NULL;
                }
                if ($this->_parentNode->_firstChild === $this) {
                        /*
                         * TODO: Why not check
                         * $this->_nextSibling === $this
                         *
                         * Is it because firstChild will be
                         * set to NULL if we should be using
                         * NodeList???
                         */
                        return NULL;
                }
                return $this->_previousSibling;
        }

        final public function nextSibling(): ?Node
        {
                if ($this->_parentNode === NULL) {
                        return NULL;
                }
                if ($this->_nextSibling === $this->_parentNode->_firstChild) {
                        /*
                         * TODO: Why not check
                         * $this->_nextSibling === $this
                         *
                         * Is it because firstChild will be
                         * set to NULL if we should be using
                         * NodeList???
                         */
                        return NULL;
                }
                return $this->_nextSibling;
        }

        /*
         * When, in other place of the code,
         * you observe folks testing for
         * $this->_childNodes, it is to see
         * whether we should use the NodeList
         * or the linked list traversal methods.
         *
         * FIXME:
         * Wait, doesn't this need to be live?
         * I mean, don't we need to re-compute
         * this thing when things are appended
         * or removed...? Or is it not live?
         */
        public function childNodes(): ?NodeList
        {
                if ($this->_childNodes === NULL) {

                        /*
                         * If childNodes has never been
                         * created, we've now created it.
                         */
                        $this->_childNodes = new NodeList();

                        for ($c=$this->firstChild(); $c!==NULL; $c=$c->nextSibling()) {
                                $this->_childNodes[] = $c;
                        }

                        /*
                         * TODO: Must we?
                         * Setting this to NULL is a
                         * signal that we are not to
                         * use the Linked List, but
                         * it is stupid and I think we
                         * don't actually need it.
                         */
                        $this->_firstChild = NULL;
                }
                return $this->_childNodes;
        }

        /*
         * CAUTION
         * Directly accessing _firstChild
         * alone is *not* a shortcut for this
         * method. Depending on whether we are
         * in NodeList or LinkedList mode, one
         * or the other or both may be NULL.
         *
         * I'm trying to factor it out, but
         * it will take some time.
         */
        public function firstChild(): ?Node
        {
                if ($this->_childNodes === NULL) {
                        /*
                         * If we are using the Linked List
                         * representation, then just return
                         * the backing property (may still
                         * be NULL).
                         */
                        return $this->_firstChild;
                }
                if (isset($this->_childNodes[0])) {
                        /*
                         * If we are using the NodeList
                         * representation, and the
                         * NodeList is not empty, then
                         * return the first item in the
                         * NodeList.
                         */
                        return $this->_childNodes[0];
                }
                /*
                 * Otherwise, the NodeList is
                 * empty, so return NULL.
                 */
                return NULL;
        }

        /*
         * FIXME
         * HEY HEY HEY!! THIS IS NOT PART OF THE NORMAL SPEC,
         * BUT IS USED HEAVILY IN OUR TARGET RUN.
         * IT SHOULD BE NAMED DIFFERENTLY, MAYBE.
         */
        public function lastChild(): ?Node
        {
                if ($this->_childNodes === NULL) {
                        /*
                         * If we are using the Linked List
                         * representation.
                         */
                        if ($this->_firstChild !== NULL) {
                                /*
                                 * If we have a firstChild,
                                 * its previousSibling is
                                 * the last child.
                                 */
                                return $this->_firstChild->previousSibling();
                        } else {
                                /*
                                 * Otherwise there are
                                 * no children, and so
                                 * last child is NULL.
                                 */
                                return NULL;
                        }
                } else {
                        /*
                         * If we are using the NodeList
                         * representation.
                         */
                        if (isset($this->_childNodes[0])) {
                                /*
                                 * If there is at least
                                 * one element in the
                                 * NodeList, return the
                                 * last element in the
                                 * NodeList.
                                 */
                                return end($this->_childNodes);
                        } else {
                                /*
                                 * Otherwise, there are
                                 * no children, and so
                                 * last child is NULL.
                                 */
                                return NULL;
                        }
                }
        }

        /*
         * CAUTION
         * Testing _firstChild or _childNodes
         * alone is *not* a shortcut for this
         * method. Depending on whether we are
         * in NodeList or LinkedList mode, one
         * or the other or both may be NULL.
         *
         * I'm trying to factor it out, but
         * it will take some time.
         */
        public function hasChildNodes(): bool
        {
                if ($this->_childNodes === NULL) {
                        /*
                         * If we are using the Linked List
                         * representation, then the NULL-ity
                         * of firstChild is diagnostic.
                         */
                        return $this->_firstChild !== NULL;
                } else {
                        /*
                         * If we are using the NodeList
                         * representation, then the
                         * non-emptiness of childNodes
                         * is diagnostic.
                         */
                        return !empty($this->_childNodes);
                }
        }

	/**********************************************************************
	 * MUTATION ALGORITHMS
	 *********************************************************************/

        /**
         * Insert $node as a child of $this, and insert it before $refChild
         * in the document order.
         *
         * @param Node $node To be inserted
         * @param Node $refChild Child of this node before which to insert $node
         * @return Newly inserted Node or empty DocumentFragment
         * @throw DOMException "HierarchyRequestError" or "NotFoundError"
         * @spec DOM-LS
         *
         * THINGS TO KNOW FROM THE SPEC:
         *
         * 1. If $node already exists in
         *    this Document, this function
         *    moves it from its current
         *    position to its new position
         *    ('move' means 'remove' followed
         *    by 're-insert').
         *
         * 2. If $refNode is NULL, then $node
         *    is added to the end of the list
         *    of children of $this. In other
         *    words, insertBefore($node, NULL)
         *    is equivalent to appendChild($node).
         *
         * 3. If $node is a DocumentFragment,
         *    the children of the DocumentFragment
         *    are moved into the child list of
         *    $this, and the empty DocumentFragment
         *    is returned.
         *
         * THINGS TO KNOW IN LIFE:
         *
         * Despite its weird syntax (blame the spec),
         * this is a real workhorse, used to implement
         * all of the non-replacing insertion mutations.
         */
        public function insertBefore(Node $node, ?Node $refNode): ?Node
        {
                /*
                 * [1]
                 * Ensure pre-insertion validity.
                 * Validation failure will throw
                 * DOMException "HierarchyRequestError" or
                 * DOMException "NotFoundError".
                 */
                \Dodo\whatwg\ensure_insert_valid($node, $this, $refNode);

                /*
                 * [2]
                 * If $refNode is $node, re-assign
                 * $refNode to the next sibling of
                 * $node. This may well be NULL.
                 */
                if ($refNode === $node) {
                        $refNode = $node->nextSibling();
                }

                /*
                 * [3]
                 * Adopt $node into the Document
                 * to which $this is rooted.
                 */
                $this->__node_document()->adoptNode($node);

                /*
                 * [4]
                 * Run the complicated algorithm
                 * to Insert $node into $this at
                 * a position before $refNode.
                 */
                \Dodo\whatwg\insert_before_or_replace($node, $this, $refNode, false);

                /*
                 * [5]
                 * Return $node
                 */
                return $node;
        }

        public function appendChild(Node $node): ?Node
        {
                return $this->insertBefore($node, NULL);
        }

        /*
         * Does not check for insertion validity.
         * This out-performs PHP DOMDocument by
         * over 2x.
         */
        final public function __unsafe_appendChild(Node $node): Node
        {
                \Dodo\whatwg\insert_before_or_replace($node, $this, NULL, false);
                return $node;
        }

        public function replaceChild(Node $new, ?Node $old): ?Node
        {
                /*
                 * [1]
                 * Ensure pre-replacement validity.
                 * Validation failure will throw
                 * DOMException "HierarchyRequestError" or
                 * DOMException "NotFoundError".
                 */
                \Dodo\whatwg\ensure_replace_valid($new, $this, $old);

                /*
                 * [2]
                 * Adopt $node into the Document
                 * to which $this is rooted.
                 */
                if ($new->__node_document() !== $this->__node_document()) {
                        /*
                         * FIXME
                         * adoptNode has a side-effect
                         * of removing the adopted node
                         * from its parent, which
                         * generates a mutation event,
                         * causing _insertOrReplace to
                         * generate 2 deletes and 1 insert
                         * instead of a 'move' event.
                         *
                         * It looks like the MutationObserver
                         * stuff avoids this problem, but for
                         * now let's only adopt (ie, remove
                         * 'node' from its parent) here if we
                         * need to.
                         */
                        $this->__node_document()->adoptNode($new);
                }

                /*
                 * [4]
                 * Run the complicated algorithm
                 * to replace $old with $new.
                 */
                \Dodo\whatwg\insert_before_or_replace($new, $this, $old, true);

                /*
                 * [5]
                 * Return $old
                 */
                return $old;
        }

        public function removeChild(ChildNode $node): ?Node
        {
                if ($this === $node->_parentNode) {
                        /* Defined on ChildNode class */
                        $node->remove();
                } else {
                        /* That's not my child! */
                        \Dodo\error("NotFoundError");
                }
                /*
                 * The spec requires that
                 * the return value always
                 * be equal to $node.
                 */
                return $node;
        }

        /**
         * Puts $this and the entire subtree
         * rooted at $this into "normalized"
         * form.
         *
         * In a normalized sub-tree, no text
         * nodes in the sub-tree are empty,
         * and there are no adjacent text nodes.
         *
         * See: https://dom.spec.whatwg.org/#dom-node-normalize
         */
        final public function normalize()
        {
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        /*
                         * [0]
                         * Proceed to traverse the
                         * subtree in a depth-first
                         * fashion.
                         */
                        $n->normalize();

                        if ($n->_nodeType === TEXT_NODE) {
                                if ($n->_nodeValue === '') {
                                        /*
                                         * [1]
                                         * If you are a text node,
                                         * and you are empty, then
                                         * you get pruned.
                                         */
                                        $this->removeChild($n);
                                } else {
                                        $p = $n->previousSibling();
                                        if ($p && $p->_nodeType === TEXT_NODE) {
                                                /*
                                                 * [2]
                                                 * If you are a text node,
                                                 * and you are not empty,
                                                 * and you follow a
                                                 * non-empty text node
                                                 * (if it were empty, it
                                                 * would have been pruned
                                                 * in the depth-first
                                                 * traversal), then you
                                                 * get merged into that
                                                 * previous non-empty text
                                                 * node.
                                                 */
                                                $p->appendData($n->_nodeValue);
                                                $this->removeChild($n);
                                        }
                                }
                        }
                }
        }

	/**********************************************************************
	 * COMPARISONS AND PREDICATES
	 *********************************************************************/

        final public function compareDocumentPosition(Node $that): int
        {
                /*
                 * CAUTION
                 * The order of these args matters
                 */
                return \Dodo\whatwg\compare_document_position($that, $this);
        }

        final public function contains(?Node $node): bool
        {
                if ($node === NULL) {
                        return false;
                }
                if ($this === $node) {
                        /*
                         * As per the DOM-LS,
                         * containment is
                         * inclusive.
                         */
                        return true;
                }

                return ($this->compareDocumentPosition($node) & DOCUMENT_POSITION_CONTAINED_BY) !== 0;
        }

        final public function isSameNode(Node $node): bool
        {
                return $this === $node;
        }

        /**
         * Determine whether this node and $other are equal
         *
         * @param Node $other - will be compared to $this
         * @return bool
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
        public function isEqualNode(?Node $node = NULL): bool
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
         * @param bool $deep - if true, clone entire subtree
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
        public function cloneNode(bool $deep = false): ?Node
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
                        \Dodo\whatwg\insert_before_or_replace($clone, $n->cloneNode(true), NULL, false);
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
                return \Dodo\whatwg\locate_prefix($this, $ns);
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
                return \Dodo\whatwg\locate_namespace($this, $prefix);
        }

        /**
         * Determine whether this is the default namespace
         *
         * @param string $ns
         * @return bool
         */
        public function isDefaultNamespace(?string $ns): bool
        {
                return ($ns ?? NULL) === $this->lookupNamespaceURI(NULL);
        }


	/**********************************************************************
	 * UTILITY METHODS AND DODO EXTENSIONS
	 *********************************************************************/
        /*
         * You were sorting out ROOTEDNESS AND STUFF
         * At the same time, you were unravelling the
         * crucial function ChildNode::remove.
         *
         *
         * There are three distinct phases in which a Node
         * can exist, and the state diagram works like
         * this:
         *
         *                      [1] Unowned, Unrooted
         *                      7|
         *                     / Document::adoptNode()
         *                    /  v
         *      Node::remove()  [2] Owned, Unrooted
         *                    \  |
         *                     \ Document:;insertBefore()
         *                      \v
         *                      [3] Owned, Rooted
         *
         *      [1]->[2] (adoption)
         *              Sets:
         *                      ownerDocument    on Nodes of subtree rooted at Node
         *                      __document_index on Nodes of subtree rooted at Node
         *
         *      [2]->[3] (insertion)
         *              Sets:
         *                      parentNode      on Node
         *                      nextSibling     on Node
         *                      previousSibling on Node
         *                      __sibling_index on Node
         *
         *              Possibly sets:
         *                      firstChild      on parent of Node, if Node is
         *                                      the first child.
         *
         *      [3]->[1] (removal)
         *              Unsets:
         *                      parentNode
         *                      nextSibling
         *                      previousSibling
         *                      __sibling_index
         *                      parentNode->firstChild, if we were last
         *              ???
         *                      Does it unset ownerDocument?
         *                      Does it unset __document_index?
         *                        (remove_from_node_table does this)
         *
         * __document_index is being set by add_to_node_table. ugh
         * __document_index is being set by add_to_node_table. ugh
         *
         * TODO
         * Centralize all of this.
         * For instance, node->removeChild(node)
         * should just call node->remove()?
         *
         *      Document::importNode($node)
         *              $this->adoptNode($node->clone())
         *      Document::insertBefore()
         *              Node::insertBefore()
         *              update_document_stuff;
         *      Document::replaceChild()
         *              Node::replaceChild()
         *              update_document_stuff;
         *      Document::removeChild()
         *              Node::removeChild()
         *              update_document_stuff;
         *      Document::cloneNode()
         *              Node::cloneNode();
         *              (clone children)
         *              update_document_stuff
         *
         * FIXME: This is an antipattern right here.
         * These don't need to be re-defined on the
         * Document.
         *
         * Already, insert_before_or_replace is calling
         *      node->__root()
         *              node->mutate
         *
         * and FIXME update_document_state is just
         * setting whether the document has a doctype
         * node or a document element. it's horrible.
         *
         * And where is __document_index being set?
         *
         */

        /*
         * Set the ownerDocument reference
         * on a subtree rooted at $this.
         *
         * When a Node becomes part of a
         * Document, even if it is not yet
         * inserted.
         *
         * Called by Document::adoptNode()
         */
        public function __set_owner(Document $doc)
        {
                $this->_ownerDocument = $doc;

                /* FIXME: Wat ? */
                if (method_exists($this, "tagName")) {
                        /* Element subclasses might need to change case */
                        $this->tagName = NULL;
                }

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $n->__set_owner($n, $owner);
                }
        }

        /**
         * Determine whether this Node is rooted (belongs to a tree)
         *
         * return: bool
         *
         * NOTE
         * A Node is rooted if it belongs to a tree, in which case it will
         * have an ownerDocument. Document nodes maintain a list of all the
         * nodes inside their tree, assigning each an index,
         * Node::__document_index.
         *
         * Therefore if we are currently rooted, we can tell by checking that
         * we have one of these.
         *
         * TODO: This should be Node::isConnected(), see spec.
         */
        public function __is_rooted(): bool
        {
                return !!$this->__document_index;
        }

        /* Called by \Dodo\whatwg\insert_before_or_replace */
        /*
         * TODO
         * This is the only place where
         *      __add_to_node_table
         *      __add_from_id_table
         * is called.
         *
         * FIXME
         * The *REASON* that this, and __uproot(),
         * and __set_owner() exist, is fundamentally
         * that they need to operate recursively on
         * the subtree, which means it needs to be
         * down here on Node.
         *
         * All of this extra stuff in here just
         * crept in here over time.
         */
        public function __root(): void
        {
                $doc = $this->ownerDocument();

                if ($this->_nodeType === ELEMENT_NODE) {
                        /* getElementById table */
                        if (NULL !== ($id = $this->getAttribute('id'))) {
                                $doc->__add_to_id_table($id, $this);
                        }
                        /* <SCRIPT> elements use this hook */
                        /* TODO This hook */
                        if ($this->__roothook) {
                                $this->__roothook();
                        }

                        /*
                         * TODO: Why do we only do this for Element?
                         * This is how it was written in Domino. Is this
                         * a bug?
                         *
                         * Oh, I see, it doesn't recurse if the first
                         * thing isn't an ELEMENT? Well, maybe then
                         * it can't have children? I dunno.
                         */

                        /* RECURSE ON CHILDREN */
                        /*
                         * TODO
                         * What if we didn't use recursion to do this?
                         * What if we used some other way? Wouldn't that
                         * make it even faster?
                         *
                         * What if we somehow had a list of indices in
                         * documentorder that would give us the subtree.
                         */
                        for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                $n->__root();
                        }
                }
        }

        /*
         * TODO
         * This is the only place where
         *      __remove_from_id_table
         *      __remove_from_node_table
         * is called.
         */
        public function __uproot(): void
        {
                $doc = $this->ownerDocument();

                /* Manage id to element mapping */
                if ($this->_nodeType === ELEMENT_NODE) {
                        if (NULL !== ($id = $this->getAttribute('id'))) {
                                $doc->__remove_from_id_table($id, $this);
                        }
                }

                /*
                 * TODO: And here we don't restrict to ELEMENT_NODE.
                 * Why not? I think this is the intended behavior, no?
                 * Then does that make the behavior in root() a bug?
                 * Go over with Scott.
                 */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $n->__uproot();
                }
        }

        /**
         * The document this node is associated to.
         *
         * @return Document
         * @spec DOM-LS
         *
         * NOTE
         * How is this different from ownerDocument? According to DOM-LS,
         * Document::ownerDocument() must equal NULL, even though it's often
         * more convenient if a document is its own owner.
         *
         * What we're looking for is the "node document" concept, as laid
         * out in the DOM-LS spec:
         *
         *      -"Each node has an associated node document, set upon creation,
         *       that is a document."
         *
         *      -"A node's node document can be changed by the 'adopt'
         *       algorithm."
         *
         *      -"The node document of a document is that document itself."
         *
         *      -"All nodes have a node document at all times."
         *
         * TODO
         * Does the DOM-LS method Node::getRootNode (not implemented here)
         * in its non-shadow-tree branch, do the same thing?
         *
         * TODO
         * Wouldn't it fit better with all the __root* junk if it were
         * called __root_node?
         */
        public function __node_document(): Document
        {
                return $this->_ownerDocument ?? $this;
        }

        /**
         * The index of this Node in its parent's childNodes list
         *
         * @return int index
         * @throw Something if we have no parent
         *
         * NOTE
         * Calling Node::__sibling_index() will automatically trigger a switch
         * to the NodeList representation (see Node::childNodes()).
         */
        public function __sibling_index(): int
        {
                if ($this->_parentNode === NULL) {
                        return 0; /* ??? TODO: throw or make an error ??? */
                }

                if ($this === $this->_parentNode->firstChild()) {
                        return 0;
                }

                /* We fire up the NodeList mode */
                $childNodes = $this->_parentNode->childNodes();

                /* We end up re-indexing here if we ever run into trouble */
                if ($this->___sibling_index === NULL || $childNodes[$this->___sibling_index] !== $this) {
                        /*
                         * Ensure that we don't have an O(N^2) blowup
                         * if none of the kids have defined indices yet
                         * and we're traversing via nextSibling or
                         * previousSibling
                         */
                        foreach ($childNodes as $i => $child) {
                                $child->___sibling_index = $i;
                        }

                        \Dodo\assert($childNodes[$this->___sibling_index] === $this);
                }
                return $this->___sibling_index;
        }

        /**
         * Remove all of the Node's children.
         *
         * NOTE
         * Provides minor optimization over iterative calls to
         * Node::removeChild(), since it calls Node::modify() once.
         * TODO: Node::modify() no longer exists. Does this optimization?
         */
        public function __remove_children()
        {
                if ($this->__is_rooted()) {
                        $root = $this->_ownerDocument;
                } else {
                        $root = NULL;
                }

                /* Go through all the children and remove me as their parent */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($root !== NULL) {
                                /* If we're rooted, mutate */
                                $root->__mutate_remove($n);
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
        }


        /*
         * Convert the children of a node to an HTML string.
         * This is used by the innerHTML getter
         */
        public function __serialize()
        {
                $s = "";

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $s .= \Dodo\whatwg\serialize_node($n, $this);
                }

                return $s;
        }
}

?>

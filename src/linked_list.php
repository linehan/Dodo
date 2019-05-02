<?php
/******************************************************************************
 * linked_list.php
 * ---------------
 *
 * Methods to operate on nodes of a circular linked list, where the
 * nodes are linked by references called _previousSibling and _nextSibling.
 *
 * That means our Node object is a node in a linked list! Yes, in reality
 * this is just rather tightly coupled to Node.
 ******************************************************************************/
namespace domo;

/**
 * Determine if the object we want to treat as a (circular) linked list
 * has the necessary data elements and that the elements aren't NULL.
 *
 * @param Node $a: "circular ll node"
 * @return: true if all assertions pass
 * @throws: The exception in \domo\assert(), see 'utilities.php'
 */
function ll_is_valid($a)
{
        \domo\assert(NULL!==$a, "list is falsy");
        \domo\assert(NULL!==$a->_previousSibling, "previous is falsy");
        \domo\assert(NULL!==$a->_nextSibling, "next is falsy");

        /* TODO: Check that list is actually circular? */

        return true;
}

/**
 * Insert $a before $b
 *
 * @param Node $a: "circular ll node" (THING TO BE INSERTED BEFORE $b)
 * @param Node $b: "circular ll node" (THING BEFORE WHICH WE INSERT $a)
 * @return void
 * @throws see ll_is_valid()
 *
 * NOTE
 * Given what this is actually doing (if you draw it out), this could
 * probably be renamed to 'link', where we are linking $a to $b.
 */
function ll_insert_before($a, $b)
{
        \domo\assert(ll_is_valid($a) && ll_is_valid($b));

        $a_first = $a;
        $a_last  = $a->_previousSibling;
        $b_first = $b;
        $b_last  = $b->_previousSibling;

        $a_first->_previousSibling = $b_last;
        $a_last->_nextSibling      = $b_first;
        $b_last->_nextSibling      = $a_first;
        $b_first->_previousSibling = $a_last;

        \domo\assert(ll_is_valid($a) && ll_is_valid($b));
}

/**
 * Remove a single node $a from its list
 *
 * @param Node $a: "circular ll node" to be removed
 * @return void
 * @throws see ll_is_valid()
 *
 * NOTE
 * Again, given what this is doing, could probably re-name
 * to 'unlink'.
 */
function ll_remove($a)
{
        \domo\assert(ll_is_valid($a));

        $prev = $a->_previousSibling;

        if ($prev === $a) {
                return;
        }

        $next = $a->_nextSibling;
        $prev->_nextSibling = $next;
        $next->_previousSibling = $prev;
        $a->_previousSibling = $a->_nextSibling = $a;

        \domo\assert(ll_is_valid($a));
}

/**
 * Replace a single node $a with a list $b (which could be null)
 *
 * @param $a "circular ll node"
 * @param $b "circular ll node" (or NULL)
 * @return void
 * @throws see ll_is_valid()
 *
 * NOTE
 * I don't like this method. It's confusing.
 */
function ll_replace($a, $b)
{
        \domo\assert(ll_is_valid($a) && ($b==NULL || ll_is_valid($b)));

        if ($b !== NULL) {
                ll_is_valid($b);
        }
        if ($b !== NULL) {
                ll_insert_before($b, $a);
        }
        ll_remove($a);

        \domo\assert(ll_is_valid($a) && ($b==NULL || ll_is_valid($b)));
}

?>

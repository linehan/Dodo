<?php
/*
 * PORT NOTES
 * ----------
 *
 * LinkedList.js                        LinkedList.php
 *
 * module.exports = LinkedList          namespace DOM\LinkedList
 *
 * utils.assert                         \DOM\util\assertion
 *
 ******************************************************************************
 *
 * 04/09/2019
 * ----------
 * Confusingly, LinkedList.js does not actually implement a
 * LinkedList, but rather operates on a data structure with
 * the elements
 *
 *      _previousSibling
 *      _nextSibling
 *
 * which are implemented only in the DOM Node object (Node.js).
 *
 * Why LinkedList is broken into a separate module/file is a
 * little mystery to me right now.
 *
 * UPDATE:
 * Ah, it has to do with what C. Scott mentioned -- sometimes
 * we treat a list of nodes as an array, and sometimes as a
 * circular linked list, for performance. The code here is
 * only triggered when we want to treat it like a linked list.
 * See Node._insertOrReplace()
 *
 * QUESTIONS FROM PORT:
 *      + Why nest our assertions? (Calls to valid() wrapped in asserts)
 *      + Why is this such a separate unit of code?
 *      + Why are we so loose defining the linked list-ness?
 *      + Why don't we check for circularity?
 *      + Why is it not called prevSibling? I imagine it's due to the DOM spec
 */

namespace domo\LinkedList;


/**
 * valid()
 * -------
 * Determine if the object we want to treat as a (circular) linked list
 * has the necessary data elements and that said elements aren't falsy.
 *
 * @a    : "circular linked list" (obj. w/ _previousSibling and _nextSibling)
 * Return: True if all assertions pass; otherwise throws an Exception.
 *
 * PORT NOTE:
 * This is basically acting as a duck-typing check for a DOM Node.
 */
function valid($a)
{
        \domo\assert(NULL!==$a, "list is falsy");
        \domo\assert(NULL!==$a->_previousSibling, "previous is falsy");
        \domo\assert(NULL!==$a->_nextSibling, "next is falsy");

        /* TODO: Check that list is actually circular? */

        return true;
}

/* TODO: Rename 'link' */
/**
 * insertBefore()
 * --------------
 * Insert $a before $b
 *
 * @a: "circular linked list" THING TO BE INSERTED BEFORE @b
 * @b: "circular linked list" THING BEFORE WHICH WE INSERT @a
 * Return: None
 * Throws: Exception if lists aren't valid or become invalid.
 */
function insertBefore($a, $b)
{
        \domo\assert(valid($a) && valid($b));

        $a_first = $a;
        $a_last  = $a->_previousSibling;
        $b_first = $b;
        $b_last  = $b->_previousSibling;

        $a_first->_previousSibling = $b_last;
        $a_last->_nextSibling      = $b_first;
        $b_last->_nextSibling      = $a_first;
        $b_first->_previousSibling = $a_last;

        \domo\assert(valid($a) && valid($b));
}

/* TODO: Rename 'unlink' */
/**
 * remove()
 * --------
 * Remove a single node $a from its list
 *
 * @a: "circular linked list node" to be removed
 * Return: None
 * Throws: Exception if node is not valid or becomes invalid.
 */
function remove($a)
{
        \domo\assert(valid($a));

        $prev = $a->_previousSibling;

        if ($prev === $a) {
                return;
        }

        $next = $a->_nextSibling;
        $prev->_nextSibling = $next;
        $next->_previousSibling = $prev;
        $a->_previousSibling = $a->_nextSibling = $a;

        \domo\assert(valid($a));
}

/**
 * replace()
 * ---------
 * Replace a single node $a with a list $b (which could be null)
 *
 * @a: "circular linked list node"
 * @b: "circular linked list" (or NULL)
 * Return: None
 * Throws: Exception if lists aren't valid or become invalid.
 */
function replace($a, $b)
{
        \domo\assert(valid($a) && ($b==NULL || valid($b)));

        if ($b !== NULL) {
                valid($b);
        }

        if ($b !== NULL) {
                insertBefore($b, $a);
        }
        remove($a);

        \domo\assert(valid($a) && ($b==NULL || valid($b)));
}

?>

<?php
use \DOM\Event
use \DOM\MouseEvent
use \DOM\util

/*
 * PORT NOTES
 *
 * this._listeners = Object.create(null)        $this->_listeners = array()
 * if (typeof listener === 'function')          if (is_callable($listener))
 * function.call(x,y,...)                       call_user_func($a, $b, $c)
 *
 * Beware of pass by reference in here?
 *
 * This is a simple page but has a bit of weird dispatching code
 */

/* PORT: ABSTRACT CLASS because we cannot instantiate these */
abstract class EventTarget {
        /*
         * XXX
         * See WebIDL §4.8 for details on object event handlers
         * and how they should behave.  We actually have to accept
         * any object to addEventListener... Can't type check it.
         * on registration.
         */

        /*
         * XXX:
         * Capturing event listeners are sort of rare.  I think I can optimize
         * them so that dispatchEvent can skip the capturing phase (or much of
         * it).  Each time a capturing listener is added, increment a flag on
         * the target node and each of its ancestors.  Decrement when removed.
         * And update the counter when nodes are added and removed from the
         * tree as well.  Then, in dispatch event, the capturing phase can
         * abort if it sees any node with a zero count.
         */

        function addEventListener($type, $listener, $capture=NULL)
        {
                if (!$listener) {
                        return;
                }

                if ($capture === NULL) {
                        $capture = false;
                }

                if (!$this->_listeners) {
                        /* TODO: PORT: Object.create(null) => array() */
                        $this->_listeners = array();
                }

                if (!isset($this->_listeners[$type])) {
                        $this->_listeners[$type] = array();
                }

                $list = $this->_listeners[$type];

                /* If this listener has already been registered, just return */
                for ($i=0, $n=count($list); $i<$n; $i++) {
                        $l = $list[$i];
                        if ($l->listener === $listener && $l->capture === $capture) {
                                return;
                        }
                }

                /* Add an object to the list of listeners */
                $obj = array("listener" => $listener, "capture" => $capture);

                if (is_callable($listener)) {
                        $obj->f = $listener;
                }

                $list[] = $obj;
        }

        function removeEventListener($type, $listener, $capture=NULL)
        {
                if ($capture === NULL) {
                        $capture = false;
                }

                if ($this->_listeners && isset($this->_listeners[$type])) {
                        $list = $this->_listeners[$type];
                }

                if ($list) {
                        // Find the listener in the list and remove it
                        for ($i=0, $n=count($list); $i<$n; $i++) {
                                $l = $list[$i];
                                if ($l->listener === $listener && $l->capture === $capture) {
                                        if (count($list) === 1) {
                                                unset($this->_listeners[$type]);
                                        } else {
                                                array_splice($list, $i, 1);
                                        }
                                        return;
                                }
                        }
                }
        }

        // This is the public API for dispatching untrusted public events.
        // See _dispatchEvent for the implementation
        function dispatchEvent($event)
        {
                // Dispatch an untrusted event
                return $this->_dispatchEvent($event, false);
        }

        //
        // See DOMCore §4.4
        // XXX: I'll probably need another version of this method for
        // internal use, one that does not set isTrusted to false.
        // XXX: see Document._dispatchEvent: perhaps that and this could
        // call a common internal function with different settings of
        // a trusted boolean argument
        //
        // XXX:
        // The spec has changed in how to deal with handlers registered
        // on idl or content attributes rather than with addEventListener.
        // Used to say that they always ran first.  That's how webkit does it
        // Spec now says that they run in a position determined by
        // when they were first set.  FF does it that way.  See:
        // http://www.whatwg.org/specs/web-apps/current-work/multipage/webappapis.html#event-handlers
        //
        function _dispatchEvent($event, $trusted)
        {
                /* PORT:TODO: Why are we not just using type hinting? */
                if (gettype($trusted) !== "boolean") {
                        $trusted = false;
                }

                /* PORT: TODO: Nested function alert */
                function invoke(target, event)
                {
                        $type = $event->type;
                        $phase = $event->eventPhase;
                        $event->currentTarget = $target;

                        // If there was an individual handler defined, invoke it first
                        // XXX: see comment above: this shouldn't always be first.
                        if ($phase !== \DOM\Event\EVENT_CAPTURING_PHASE && $target->_handlers && isset($target->_handlers[$type])) {
                                $handler = $target->_handlers[$type];
                                $rv;
                                if (is_callable($handler)) {
                                        /* PORT: TODO: The params are not passed by reference; is this okay? See call_user_func manual */
                                        $rv = call_user_func($handler, $event->currentTarget, $event);
                                } else {
                                        $f = $handler->handleEvent;

                                        if (!is_callable($f)) {
                                                throw new TypeError('handleEvent property of event handler object is not a function.');
                                        }
                                        $rv=call_user_func($f, $handler, $event);
                                }

                                switch ($event->type) {
                                case "mouseover":
                                        if ($rv === true) { // Historical baggage
                                                $event->preventDefault();
                                        }
                                        break;
                                case "beforeunload":
                                        // XXX: eventually we need a special case here
                                        /* falls through */
                                default:
                                        if ($rv === false) {
                                                $event->preventDefault();
                                        }
                                        break;
                                }
                        }

                        // Now invoke list list of listeners for this target and type
                        /* ECMAScript uses && for a null coalescing operator? reee */
                        //$list = $target->_listeners && $target._listeners[type];

                        /* PORT: TODO: Fix this return logic */
                        if ($target->_listeners) {
                                if (isset($target->_listeners[$type])) {
                                        $list = $target->_listeners[$type];
                                } else {
                                        return;
                                }
                        }

                        if (!$list) {
                                return;
                        }

                        /*
                         * PORT:TODO: This makes a shallow copy in JS. How does
                         * PHP deal with this? It's already made a shallow copy
                         * on assignment, above.
                         *
                         * This means we will have to figure out how to pass by
                         * reference in certain places. Sigh, okay!
                         */
                        $list = list.slice();

                        for ($i=0, $n=count($list); $i<$n; $i++) {
                                if ($event->_immediatePropagationStopped) {
                                        return;
                                }

                                $l = $list[$i];

                                if (($phase === \DOM\Event\EVENT_CAPTURING_PHASE && !$l->capture) ||
                                    ($phase === \DOM\Event\EVENT_BUBBLING_PHASE && $l->capture)) {
                                        continue;
                                }

                                if ($l->f) {
                                        call_user_func($l->f, $event->currentTarget, $event);
                                } else {
                                        $fn = $l->listener->handleEvent;
                                        if (!is_callable($fn)) {
                                                throw new TypeError('handleEvent property of event listener object is not a function.');
                                        }
                                        call_user_func($fn, $l->listener, $event);
                                }
                        }
                } /* End invoke() */

                if (!$event->_initialized || $event->_dispatching) {
                        \DOM\util\InvalidStateError();
                }

                $event->isTrusted = $trusted;

                // Begin dispatching the event now
                $event->_dispatching = true;
                $event->target = $this;

                // Build the list of targets for the capturing and bubbling phases
                // XXX: we'll eventually have to add Window to this list.
                $ancestors = array();

                for ($n=$this->parentNode; $n; $n=$n->parentNode) {
                        $ancestors[] = $n;
                }

                // Capturing phase
                $event->eventPhase = \DOM\Event\EVENT_CAPTURING_PHASE;

                for ($i=count($ancestors)-1; $i>=0; $i--) {
                        invoke($ancestors[$i], $event);
                        if ($event._propagationStopped) {
                                break;
                        }
                }

                // At target phase
                if (!$event->_propagationStopped) {
                        $event->eventPhase = \DOM\Event\EVENT_AT_TARGET;
                        invoke($this, $event);
                }

                // Bubbling phase
                if ($event->bubbles && !$event->_propagationStopped) {
                        $event->eventPhase = \DOM\Event\EVENT_BUBBLING_PHASE;

                        for ($ii=0, $nn=count($ancestors); $ii<$nn; $ii++) {
                                invoke($ancestors[$ii], $event);
                                if ($event->_propagationStopped) {
                                        break;
                                }
                        }
                }

                $event->_dispatching = false;
                $event->eventPhase = \DOM\Event\EVENT_AT_TARGET;
                $event->currentTarget = NULL;

                // Deal with mouse events and figure out when
                // a click has happened
                if ($trusted && !$event->defaultPrevented && $event instanceof MouseEvent) {
                        switch ($event->type) {
                        case 'mousedown':
                                $this._armed = array(
                                        "x" => $event->clientX,
                                        "y" => $event->clientY,
                                        "t" => $event->timeStamp
                                );
                                break;
                        case 'mouseout':
                        case 'mouseover':
                                $this->_armed = NULL;
                                break;
                        case 'mouseup':
                                if ($this->_isClick($event)) {
                                        $this->_doClick($event);
                                }
                                $this->_armed = NULL;
                                break;
                        }
                }

                return !$event->defaultPrevented;
        }

        // Determine whether a click occurred
        // XXX We don't support double clicks for now
        function _isClick($event)
        {
                return ($this->_armed !== NULL &&
                        $event->type === 'mouseup' &&
                        $event->isTrusted &&
                        $event->button === 0 &&
                        $event->timeStamp - $this._armed->t < 1000 &&
                        abs($event->clientX - $this->_armed->x) < 10 &&
                        /*
                         * PORT:NOTE: In Domino upstream, this reads
                         * this._armed.Y, (a bug). It should be y, not Y.
                         */
                        abs($event->clientY - $this->_armed->y) < 10);
        }

        // Clicks are handled like this:
        // http://www.whatwg.org/specs/web-apps/current-work/multipage/elements.html#interactive-content-0
        //
        // Note that this method is similar to the HTMLElement.click() method
        // The event argument must be the trusted mouseup event
        function _doClick($event)
        {
                if ($this->_click_in_progress) {
                        return;
                }
                $this->_click_in_progress = true;

                // Find the nearest enclosing element that is activatable
                // An element is activatable if it has a
                // _post_click_activation_steps hook
                $activated = $this;

                while ($activated && !$activated->_post_click_activation_steps) {
                        $activated = $activated->parentNode;
                }

                if ($activated && $activated->_pre_click_activation_steps) {
                        $activated->_pre_click_activation_steps();
                }

                $click = $this->ownerDocument->createEvent('MouseEvent');

                $click->initMouseEvent("click", true, true,
                        $this->ownerDocument->defaultView, 1,
                        $event->screenX, $event->screenY,
                        $event->clientX, $event->clientY,
                        $event->ctrlKey, $event->altKey,
                        $event->shiftKey, $event->metaKey,
                        $event->button, NULL);

                $result = $this->_dispatchEvent($click, true);

                if ($activated) {
                        if ($result) {
                                // This is where hyperlinks get followed, for example.
                                if ($activated->_post_click_activation_steps)
                                        $activated->_post_click_activation_steps($click);
                                } else {
                                        if ($activated->_cancelled_activation_steps) {
                                                $activated->_cancelled_activation_steps();
                                        }
                                }
                        }
                }
        }

        //
        // An event handler is like an event listener, but it registered
        // by setting an IDL or content attribute like onload or onclick.
        // There can only be one of these at a time for any event type.
        // This is an internal method for the attribute accessors and
        // content attribute handlers that need to register events handlers.
        // The type argument is the same as in addEventListener().
        // The handler argument is the same as listeners in addEventListener:
        // it can be a function or an object. Pass null to remove any existing
        // handler.  Handlers are always invoked before any listeners of
        // the same type.  They are not invoked during the capturing phase
        // of event dispatch.
        //
        function _setEventHandler($type, $handler)
        {
                if (!$this->_handlers) {
                        $this->_handlers = array();
                }
                $this->_handlers[$type] = $handler;
        }

        function _getEventHandler($type)
        {
                return ($this->_handlers && $this->_handlers[$type]) || NULL;
        }
}

?>

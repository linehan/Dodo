<?php
use \DOM\Event

class UIEvent extends Event {

        public
        function __construct()
        {
                parent::__construct();

                $this->view = NULL; /* Firefox uses the current window */
                $this->detail = 0;
        }

        public
        function initUIEvent($type, $bubbles, $cancelable, $view, $detail)
        {
                $this->initEvent($type, $bubbles, $cancelable);
                $this->view = $view;
                $this->detail = $detail;
        }
}
?>
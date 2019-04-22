<?php

/*
 *  PORT TODO: This should be a thing with a bunch of
 * constant DOMStrings on it. How to implement? A static class?
 *
 * TODO: Right now this is mutable!
 */
// https://html.spec.whatwg.org/multipage/webappapis.html#navigatorid
class NavigatorID
{
        $appCodeName = "Mozilla";
        $appName = "Netscape";
        $appVersion = "4.0";
        $platform = "";
        $product = "Gecko";
        $productSub = "20100101";
        $userAgent = "";
        $vendorSub = "";

        function taintEnabled() {
                return false;
        }
}

?>

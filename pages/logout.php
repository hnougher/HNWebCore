<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
 * This page is run on logout.
 * Just after this script is run the session is distroyed and all data goes with it.
 * If this page returns ture then the code will attempt to display the page
 * being requested or give login screen if that is required by the page.
 * (eg: If the logout is called with /main_menu?section=2&logout=1 then it will
 * require login after logout and the section=2 will be passed on to that script).
 * 
 * @return TRUE if this page is never shown. String to redirect to somewhere else.
 * 			Otherwise it will be left up to you what to do.
 */
return true;

<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
 * This file is designed to be included quickly to get a page to be cached for 7 days.
 * at the start it was to get PHP'ed CSS files to stay cached.
 */

header('Cache-Control: public; max-age=' .CSS_CACHETIME);
header('Expires: ' .gmdate('D, d M Y H:i:s', time() + CSS_CACHETIME) .' GMT');
header('Pragma: ');

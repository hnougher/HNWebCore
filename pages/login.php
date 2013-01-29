<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
 * This page is run when login is requested.
 *  Use it to display a page with contains at least POST vars of
 * login_user, login_pass and login=1.
 * 
 * Extra Params to Normal:
 * @param $postToPath This is the path which the login should POST to.
 */

// Create the output
$form = $HNTPL->new_form('Login', $postToPath);

$form->new_hidden('login', '1');
$form->new_text('Username', 'login_user', isset($_POST['login_user']) ? $_POST['login_user'] : '');
	$form->attrib('tooltip', 'Enter your Username Here');
$form->new_password('Password', 'login_pass');
	$form->attrib('tooltip', 'Enter your Password Here');

<?php

/**
 * SquirrelMail Plugin Hook Registration File
 * Auto-generated using the configure script, conf.pl
 */

global $squirrelmail_plugin_hooks;

$squirrelmail_plugin_hooks['left_main_before']['check_quota'] 
    = 'check_quota_graph_before_do';
$squirrelmail_plugin_hooks['left_main_after']['check_quota'] 
    = 'check_quota_graph_after_do';
$squirrelmail_plugin_hooks['right_main_after_header']['check_quota'] 
    = 'check_quota_motd_do';
$squirrelmail_plugin_hooks['template_construct_left_main.tpl']['check_quota'] 
    = 'check_quota_graph_do';
$squirrelmail_plugin_hooks['template_construct_motd.tpl']['check_quota'] 
    = 'check_quota_motd_do';
$squirrelmail_plugin_hooks['optpage_register_block']['check_quota'] 
    = 'check_quota_optpage_register_block_do';
$squirrelmail_plugin_hooks['configtest']['check_quota'] 
    = 'check_quota_check_configuration_do';



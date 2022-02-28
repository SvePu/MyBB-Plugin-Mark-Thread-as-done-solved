<?php
/*
Plugin "Thread solved" 2.1
2008 (c) MyBBoard.de
2019 (c) MyBB.de - Plugin changed and modified by itsmeJAY
Version tested: 1.8.20 by itsmeJAY
*/

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (defined('IN_ADMINCP'))
{
    $plugins->add_hook('admin_config_settings_begin', 'threadsolved_settings');
}
else
{
    // $plugins->add_hook('forumdisplay_thread', 'threadsolved');
    // $plugins->add_hook('search_results_thread', 'threadsolved');
    // //$plugins->add_hook('search_results_post', 'threadsolved');
    // $plugins->add_hook('showthread_linear', 'threadsolved');
    $plugins->add_hook('showthread_end', 'threadsolved_showthread');
}

function threadsolved_info()
{
    global $db, $lang;
    $lang->load('threadsolved', true);

    return array(
        'name'          => $db->escape_string($lang->threadsolved),
        'description'   => $db->escape_string($lang->threadsolved_desc),
        'website'       => 'http://www.mybb.de',
        'author'        => 'MyBB.de - Changed and modified by itsmeJAY',
        'authorsite'    => 'http://www.mybb.de',
        'version'       => '2.2.2',
        'codename'      => 'threadsolved',
        'compatibility' => '18*'
    );
}

function threadsolved_install()
{
    global $db, $mybb, $lang;
    $lang->load('threadsolved', true);

    /** Add db column if not exists */
    if (!$db->field_exists('threadsolved', 'threads'))
    {
        $db->add_column("threads", "threadsolved", "tinyint(1) NOT NULL default '0'");
    }

    /** Add templates */
    $templatearray = array(
        'showthread_thread_solved_button' => '<a href="showthread.php?marksolved=1&amp;my_post_key={$mybb->post_code}" class="button thread_solved_button"><span>{$lang->setting_threadsolved_solved_text_value}</span></a>&nbsp;',
        'showthread_thread_notsolved_button' => '<a href="showthread.php?marksolved=0&amp;my_post_key={$mybb->post_code}" class="button thread_notsolved_button"><span>{$lang->setting_threadsolved_notsolved_text_value}</span></a>&nbsp;',
        'threadsolved_icon' => '<img src="images/solved.png" border="0" alt="{$lang->setting_threadsolved_solved_text_value}" style="vertical-align: middle;" />'
    );

    foreach ($templatearray as $name => $template)
    {
        $template = array(
            'title' => $db->escape_string($name),
            'template' => $db->escape_string($template),
            'version' => $mybb->version_code,
            'sid' => -2,
            'dateline' => TIME_NOW
        );

        $db->insert_query('templates', $template);
    }

    /** Add Settings */
    $query = $db->simple_select('settinggroups', 'MAX(disporder) AS disporder');
    $disporder = (int)$db->fetch_field($query, 'disporder');

    $setting_group = array(
        'name' => 'threadsolved',
        'title' => $db->escape_string($lang->setting_group_threadsolved),
        'description' => $db->escape_string($lang->setting_group_threadsolved_desc),
        'isdefault' => 0
    );

    $setting_group['disporder'] = ++$disporder;

    $gid = (int)$db->insert_query('settinggroups', $setting_group);

    $settings = array(
        'groups' => array(
            'optionscode' => 'groupselect',
            'value' => '3,4'
        ),
        'threadowner' => array(
            'optionscode' => 'yesno',
            'value' => 1
        ),
        'solved_text' => array(
            'optionscode' => 'text',
            'value' => $db->escape_string($lang->setting_threadsolved_solved_text_value)
        ),
        'notsolved_text' => array(
            'optionscode' => 'text',
            'value' => $db->escape_string($lang->setting_threadsolved_notsolved_text_value)
        ),
        'forums' => array(
            'optionscode' => 'forumselect',
            'value' => '-1'
        )
    );

    $disporder = 0;

    foreach ($settings as $name => $setting)
    {
        $name = "threadsolved_{$name}";

        $setting['name'] = $db->escape_string($name);

        $lang_var_title = "setting_{$name}";
        $lang_var_description = "setting_{$name}_desc";

        $setting['title'] = $db->escape_string($lang->{$lang_var_title});
        $setting['description'] = $db->escape_string($lang->{$lang_var_description});
        $setting['disporder'] = $disporder;
        $setting['gid'] = $gid;

        $db->insert_query('settings', $setting);
        ++$disporder;
    }

    rebuild_settings();
}

function threadsolved_is_installed()
{
    global $db;
    if ($db->field_exists('threadsolved', "threads"))
    {
        return true;
    }
    return false;
}

function threadsolved_uninstall()
{
    global $db, $mybb;

    if ($mybb->request_method != 'post')
    {
        global $page, $lang;
        $lang->load('threadsolved', true);

        $page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=threadsolved', $lang->threadsolved_uninstall_message, $lang->threadsolved_uninstall);
    }

    /** Remove settings */
    $db->delete_query("settinggroups", "name='threadsolved'");
    $db->delete_query("settings", "name LIKE 'threadsolved_%'");

    rebuild_settings();

    /** Remove templates */
    $db->delete_query('templates', "title IN ('showthread_thread_solved_button', 'showthread_thread_notsolved_button', 'threadsolved_icon')");

    if (!isset($mybb->input['no']))
    {
        /** Remove db column*/
        $db->drop_column("threads", "threadsolved");
    }
}

function threadsolved_activate()
{
    require MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("forumdisplay_thread", '#{\$gotounread}#', "{\$gotounread} {\$threadsolved} ");
    find_replace_templatesets("search_results_threads_thread", '#{\$gotounread}#', "{\$gotounread} {\$threadsolved} ");
    find_replace_templatesets("search_results_posts_post", '#{\$lang->post_thread}#', "{\$lang->post_thread} {\$threadsolved}");
    find_replace_templatesets("showthread", '#<strong>{\$thread#', "{\$threadsolved} <strong>{\$thread");
    find_replace_templatesets("showthread", '#{\$newreply}#', "{\$threadsolved_button}{\$newreply}");
}

function threadsolved_deactivate()
{
    require MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("forumdisplay_thread", '# {\$threadsolved} #', "", 0);
    find_replace_templatesets("search_results_threads_thread", '# {\$threadsolved} #', "", 0);
    find_replace_templatesets("search_results_posts_post", '# {\$threadsolved}#', "", 0);
    find_replace_templatesets("showthread", '#{\$threadsolved} #', "", 0);
    find_replace_templatesets("showthread", '#{\$threadsolved_button}#', "", 0);
}

function threadsolved_settings()
{
    global $lang;
    $lang->load('threadsolved', true);
}

function threadsolved_showthread()
{
    global $mybb, $lang, $thread, $templates, $threadsolved, $threadsolved_button;
    $lang->load('threadsolved');

    if ((!is_member($mybb->settings['threadsolved_groups']) && ($mybb->user['uid'] == $thread['uid'] && $mybb->settings['threadsolved_threadowner'] != 1)) || ($mybb->settings['threadsolved_forums'] != "-1" && !in_array($thread['fid'], explode(',', $mybb->settings['threadsolved_forums']))))
    {
        return;
    }

    if ($marksolved = $mybb->get_input('marksolved', MyBB::INPUT_INT))
    {
        verify_post_check($mybb->get_input('my_post_key'));

        if ($marksolved == 1)
        {
            $db->update_query("threads", "array('threadsolved' => 1)", "tid = '{$thread['tid']}'");
            $thread['threadsolved'] = 1;
        }
        elseif ($marksolved == 0)
        {
            $db->update_query("threads", "array('threadsolved' => 0)", "tid = '{$thread['tid']}'");
            $thread['threadsolved'] = 0;
        }
    }

    $threadsolved = $threadsolved_button = "";

    if ($thread['threadsolved'] == "1")
    {
        eval("\$threadsolved = \"" . $templates->get("threadsolved_icon") . "\";");

        if(!empty($mybb->settings['threadsolved_solved_text']))
        {
            $lang->setting_threadsolved_notsolved_text_value = htmlspecialchars_uni($mybb->settings['threadsolved_notsolved_text']);
        }
        eval("\$threadsolved_button = \"" . $templates->get("showthread_thread_notsolved_button") . "\";");
    }
    else
    {
        if(!empty($mybb->settings['threadsolved_solved_text']))
        {
            $lang->setting_threadsolved_solved_text_value = htmlspecialchars_uni($mybb->settings['threadsolved_solved_text']);
        }
        eval("\$threadsolved_button = \"" . $templates->get("showthread_thread_solved_button") . "\";");
    }
}

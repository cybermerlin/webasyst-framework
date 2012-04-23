<?php

/*
 * This file is part of Webasyst framework.
 *
 * Licensed under the terms of the GNU Lesser General Public License (LGPL).
 * http://www.webasyst.com/framework/license/
 *
 * @link http://www.webasyst.com/
 * @author Webasyst LLC
 * @copyright 2011 Webasyst LLC
 * @package wa-system
 * @subpackage config
 */

/**
 * An interface between application and contacts app to allow access rights management.
 *
 * To allow custom access configuration for an app, add a 'rights' => true to /lib/confid/app.php
 * Then create /lib/appnameRightsConfig.class.php with appnameRightConfig class that extends waRightConfig.
 * Instance of this class will be used to get HTML for access control form for current app.
 *
 * Though the HTML form can be thoroughly generated by app (by overriding getHTML), normally it is not needed.
 * Class has a templating system to add controls commonly used in access control forms. To use this feature,
 * override init() and use addItem() to add controls to the form. The default implementation if getHTML()
 * will build the form for you. If you need some custom controls, you can override getItemHTML()
 * to generate them.
 *
 * Normally system keeps access_key => value pairs in a centralized storage. Application may choose
 * to implement its own storage for all or some of the access keys. getRights() and setRights() are hooks
 * that get called when admin manages application access for contact or group. This allows application
 * to control whether given key => value pair to be stored by system or by application.
 */
class waRightConfig
{
    protected $app;
    protected $items = array();

    public function __construct()
    {
        $this->app = substr(get_class($this), 0, -11);
        $this->init();
    }

    /**
     * Override in subclass to initialize access keys. See addItem()
     */
    public function init()
    {
        // Override me!
    }

    /**
     * Adds one control to the form that the default implementation of getHTML will return.
     *
     * Type: checkbox
     * - cssclass: CSS class for <tr>
     *
     * Type: list - list of checkboxes with $label being a header above them.
     * - cssclass: CSS class for <tr>
     * - items: array(access_key => human readable name) - checkboxes to show in the list.
     * - hint1: string to show above left checkbox col;
     *          'all_checkbox' will show a checkbox to check everything at once, and its status
     *          will be saved with access_key $name.all
     * - hint2: string to show above right checkbox col, if it's present
     *
     * @param string $name access_key to store in DB
     * @param string $label human readable name for a field
     * @param string $type control type; currently checkbox|list
     * @param array $params parameters passed to getItemHTML
     */
    public function addItem($name, $label, $type = 'checkbox', $params = array())
    {
        $this->items[] = array(
            'name' => $name,
            'label' => _wd($this->app, $label),
            'type' => $type,
            'params' => $params
        );
    }

    /**
     * Return custom access rights managed by app for contact id (not considering group he's in) or a set of group ids.
     * Application must override this if it uses custom access rights storage.
     *
     * @param int|array $contact_id contact_id (positive) or a list of group_ids (positive)
     * @return array access_key => value; for group_ids aggregate status is returned, as if for a member of all groups.
     */
    public function getRights($contact_id)
    {
        return array();
    }

    /**
     * Update custom rights storage for given contact and access_key setting given value.
     *
     * @param int $contact_id contact_id (if positive) or group id (if negative)
     * @param string $right access_key to set value for
     * @param mixed $value value to save
     * @return boolean false to write this key and value to system storage; true if application chooses to keep it in its own place.
     */
    public function setRights($contact_id, $right, $value = null)
    {
        return false;
    }

    /**
     * Remove all access control data for given contact or group id.
     *
     * @param int $contact_id contact id (if positive) or group id (if negative)
     */
    public function clearRights($contact_id)
    {
        // Nothing to do
    }

    /**
     * Set default access for given contact and return access rights to set up in system access storage.
     *
     * @param int $contact_id
     * @return array access key => value
     */
    public function setDefaultRights($contact_id)
    {
        return array();
    }

    /**
     * Return HTML to include into page to customize user access for application.
     *
     * @param array $rights access_key => value for both system-managed and app-managed rights
     * @param array $inherited access_key => value for rights inherited from groups member is in. Default is null: do not show group UI at all (e.g. when  managing group access)
     * @return string - generated HTML
     */
    public function getHTML($rights = array(), $inherited=null)
    {
        if ($inherited !== null) {
            $html = '<table class="zebra c-access-app"><tr><th></th>'.
                '<th width="1%">'._ws('Effective<br/>rights').'</th>'.
                '<th width="1%">'._ws('Granted<br/>personally').'</th>'.
                '<th width="1%">'._ws('Inherited<br/>from groups').'</th></tr>';
        } else {
            $html = '<table class="zebra c-access-app c-access-app-group">';
            //$html .= '<tr><th width="50%"></th><th width="50%">'._ws('Group').'</th></tr>';
        }
        $addScriptForCB = FALSE;
        $addScriptForSelect = FALSE;
        foreach ($this->items as $item) {
            $html .= $this->getItemHTML($item['name'], $item['label'], $item['type'], $item['params'], $rights, $inherited);
            if ($item['type'] == 'list' && isset($item['params']['hint1']) && $item['params']['hint1'] == 'all_checkbox') {
                $addScriptForCB = TRUE;
            }

            if ($inherited !== null && ($item['type'] == 'select' || $item['type'] == 'selectlist')) {
                $addScriptForSelect = TRUE;
            }
        }
        $html .= '</table>';

        $html .= '
            <script>(function() {
                // Make indicators change when user changes personal access
                var updateIndicator = function() {
                    var self = $(this);
                    var tr = self.parents("table.c-access-app tr");
                    if (tr.find("input[type=\"checkbox\"]:checked").size() > 0) {
                        tr.find("i.icon10.no").removeClass("no").addClass("yes");
                    } else {
                        tr.find("i.icon10.yes").removeClass("yes").addClass("no");
                    }
                };
                $("table.c-access-app input[type=\"checkbox\"]:enabled").click(updateIndicator);';

        if ($addScriptForSelect) {
            $html .= '
                // Change resulting column for selects
                $("table.c-access-app select").change(function() {
                    var self = $(this);
                    var tr = self.parents("table.c-access-app tr");
                    var result = Math.max(self.val()-0, tr.find("input.g-value").val()-0);
                    var name = self.find("option[value=\""+result+"\"]").text();
                    tr.find("strong").text(name);
                });';
        }

        if ($addScriptForCB) {
            $html .= '
                // Logic for "all" checkboxes
                /** if $(this) is checked, then check and disable all checkboxes starting with the same name (minus `.all`)
                  * if not checked, then enable all those checkboxes. */
                var handler = function() {
                    var cb = $(this);
                    cb.parents("table.c-access-app")
                        .find("input[type=\"checkbox\"][name^=\""+cb.attr("name").replace(/\.all]/,"")+"\"]")
                        .each(function(k,cb2) {
                            cb2 = $(cb2);
                            if (cb.attr("checked")) {
                                cb2.attr("checked", true).attr("disabled", true);
                                updateIndicator.call(cb2[0]);
                            } else {
                                cb2.attr("checked", false).attr("disabled", false);
                                updateIndicator.call(cb2[0]);
                            }
                        });
                    cb.attr("disabled", false);
                };
                /* For each enabled "all" checkbox in a table.c-access-app:
                   - Add an onclick handler
                   - Call the handler initially, if `all` is checked. */
                $("table.c-access-app .c-access-cb-all input:enabled").each(function(k,cb) {
                    cb = $(cb).click(handler);
                    if (cb.is(":checked")) {
                        handler.call(cb[0]);
                    }
                });';
        }

        $html .= '
            }).call({});</script>';

        return $html;
    }

    /**
     * Generate HTML for one field that was previously added by addItem().
     * Used by the default implementation of getHTML() to build a form.
     * See addItem() for details
     *
     * @param string $name access_key to store in DB
     * @param string $label human readable name for a field
     * @param string $type control type; currently checkbox|list
     * @param array $params parameters
     * @param array $rights
     * @param array $inherited
     * @return string HTML
     * @throws waException
     */
    protected function getItemHTML($name, $label, $type, $params, $rights, $inherited=null) {
        $own = isset($rights[$name]) ? $rights[$name] : '';
        $group = $inherited && isset($inherited[$name]) ? $inherited[$name] : null;
        if (!isset($params['cssclass'])) {
            $params['cssclass'] = '';
        }
        switch ($type) {
            case 'select':
                if (!isset($params['options']) || !$params['options']) {
                    return '';
                }
                if (!$group) {
                    $group = 0;
                }
                if (!$own) {
                    $own = 0;
                }

                $o = $params['options'];
                $oHTML = array();
                foreach($o as $val => $opt) {
                    $oHTML[] = '<option value="'.$val.'"'.($own==$val ? ' selected="selected"' : '').'>'.htmlspecialchars($opt).'</option>';
                }
                $oHTML = implode('', $oHTML);
                return '<tr'.($params['cssclass'] ? ' class="'.$params['cssclass'].'"' : '').'>'.
                            '<td><div>'.$label.'</div></td>'.
                            ($inherited !== null ? '<td><strong>'.$o[max($own, $group)].'</strong></td>' : '').
                            '<td><input type="hidden" name="app['.$name.']" value="0">'.
                                '<select name="app['.$name.']">'.$oHTML.'</select>'.
                            '</td>'.
                            ($inherited !== null ? '<td>'.($inherited && isset($inherited['backend']) ? $o[$group] : '').'<input type="hidden" class="g-value" value="'.$group.'"></td>' : '').
                        '</tr>';
            case 'checkbox':
                return '<tr'.($params['cssclass'] ? ' class="'.$params['cssclass'].'"' : '').'>'.
                            '<td><div>'.$label.'</div></td>'.
                            ($inherited !== null ? '<td><i class="icon10 '.($own || $group ? 'yes' : 'no').'"></i></td>' : '').
                            '<td><input type="hidden" name="app['.$name.']" value="0"><input type="checkbox" name="app['.$name.']" value="'.(isset($params['value']) ? $params['value'] : 1).'"'.($own ? ' checked="checked"' : '').'></td>'.
                            ($inherited !== null ? '<td><input type="checkbox"'.($group ? ' checked="checked"' : '').' disabled="disabled"></td>' : '').
                        '</tr>';
            case 'list':
                $indicator = '';
                if (isset($params['hint1']) && $params['hint1'] == 'all_checkbox') {
                    $own = isset($rights[$name.'.all']) ? $rights[$name.'.all'] : '';
                    $group = $inherited && isset($inherited[$name.'.all']) ? $inherited[$name.'.all'] : null;
                    $params['hint1'] = '<input type="hidden" name="app['.$name.'.all]" value="0"><span class="c-access-cb-all"><label><input type="checkbox" name="app['.$name.'.all]" value="1"'.($own ? ' checked="checked"' : '').'>'._ws('all').'</label></span>';
                    if($inherited !== null) {
                        $params['hint2'] = '<span class="c-access-cb-all"><input type="checkbox"'.($group ? ' checked="checked"' : '').' disabled="disabled">'._ws('all').'</span>';
                        //no indicator on this line anymore
                        //$indicator = '<span class="float-right"><i class="icon10 '.($own || $group ? 'yes' : 'no').'"></i></span>';
                    }
                }

                $html = '<tr class="c-access-subcontrol-header'.($params['cssclass'] ? ' '.$params['cssclass'] : '').'">'.
                                '<td><div>'.$indicator.$label.'</div></td>'.
                                ($inherited !== null ? '<td></td>' : '').
                                '<td><div class="hint">'.(isset($params['hint1']) ? $params['hint1'] : '').'</td>'.
                                ($inherited !== null ? '<td><div class="hint">'.(isset($params['hint2']) ? $params['hint2'] : '').'</td>' : '').
                        '</tr>';
                $item_params = array('cssclass' => 'c-access-subcontrol-item');
                if (isset($params['value'])) {
                    $item_params['value'] = $params['value'];
                }
                foreach ($params['items'] as $id => $item_name) {
                    if ($group) {
                        $inherited[$name.'.'.$id] = 1;
                    }
                    $html .= $this->getItemHtml($name.'.'.$id, htmlspecialchars($item_name), 'checkbox', $item_params, $rights, $inherited);
                }
                return $html;
            case 'selectlist':
                if (!isset($params['options']) || !$params['options']) {
                    return '';
                }
                $html = '<tr class="c-access-subcontrol-header'.($params['cssclass'] ? ' '.$params['cssclass'] : '').'">'.
                                '<td><div>'.$label.'</div></td>'.
                                ($inherited !== null ? '<td></td>' : '').
                                '<td><div class="hint">'.(isset($params['hint1']) ? $params['hint1'] : '').'</td>'.
                                ($inherited !== null ? '<td><div class="hint">'.(isset($params['hint2']) ? $params['hint2'] : '').'</td>' : '').
                        '</tr>';
                foreach ($params['items'] as $id => $item_name) {
                    $html .= $this->getItemHtml($name.'.'.$id, htmlspecialchars($item_name), 'select', array('cssclass' => 'c-access-subcontrol-item', 'options' => $params['options']), $rights, $inherited);
                }
                return $html;
            case 'header':
                if(!isset($params['tag'])) {
                    $params['tag'] = 'h2';
                }
                return '<tr'.($params['cssclass'] ? ' class="'.$params['cssclass'].'"' : '').'>'.
                            '<td colspan="'.($inherited !== null ? '4' : '2').'"><div><'.$params['tag'].'>'.$label.'</'.$params['tag'].'></div></td>'.
                        '</tr>';
            default:
                throw new waException('Unknown control: '.$type);
        }
    }
}

// EOF
<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {html_options} function plugin
 *
 * Type:     function<br>
 * Name:     html_options<br>
 * Input:<br>
 *           - name       (optional) - string default "select"
 *           - values     (required if no options supplied) - array
 *           - options    (required if no values supplied) - associative array
 *           - selected   (optional) - string default not set
 *           - output     (required if not options supplied) - array
 * Purpose:  Prints the list of <option> tags generated from
 *           the passed parameters
 * @link http://smarty.php.net/manual/en/language.function.html.options.php {html_image}
 *      (Smarty online manual)
 * @author Monte Ohrt <monte at ohrt dot com>
 * @param array
 * @param Smarty
 * @return string
 * @uses smarty_function_escape_special_chars()
 */
function smarty_function_responsible_employees($params, &$smarty)
{
    require_once $smarty->_get_plugin_filepath('function', 'imageurl');

    $global_employee_usernames = $smarty->get_template_vars ('global_employee_usernames');

    $options = isset($params['options']['']) ? [] : ['' => '---'];
    $employees = [];

    $statuses = $smarty->get_template_vars ('global_users_status');

    foreach ($params['options'] as $_key => $_value)
    {
        $options[$_key] = $_value . (isset($statuses[$_key]) ? ' <small>(' . $statuses[$_key] . ')</small>' : '');

        $emp_id = $global_employee_usernames[$_key];
        if ($emp_id)
        {
            $params4image = ['src' => 'employee', 'picid' => $emp_id, 'x' => 200];
            $employees[$_key] = smarty_function_imageurl($params4image, $smarty);
        }
    }

    $params['id'] = isset($params['id']) ? $params['id'] : $params['name'];
    $params['id'] = str_replace('[', '-', $params['id']);
    $params['id'] = str_replace(']', '', $params['id']);
    $params['employees'] = $employees;
    $params['style'] = 'width:220px';
    $params['options'] = $options;
    $params['colored'] = $smarty->get_template_vars ('global_users_colors');

    $_html_output ='<div class="select_wrapper">'. smarty_function_html_options($params, $smarty) .'</div>';

    $_html_output .= '<style type="text/css">'
            . '#select2-' . $params['id'] . '-results .select2-results__option {padding:1px 0!important}'
            . '#select2-' . $params['id'] . '-results .select2-results__option span {display:block!important;padding:3px 2px!important;white-space: nowrap;}'
            . '#select2-' . $params['id'] . '-container span {background:transparent!important;display:inline!important}'
            . '#select2-' . $params['id'] . '-container img {display:none!important}'
            . '#select2-' . $params['id'] . '-container.select2-selection__rendered small {display:none!important}'
            . '</style>';
    $_html_output .= '<script type="text/javascript">';
    $_html_output .= "\n";
    $_html_output .= '$(document).ready(function () {';
    $_html_output .= '$(".select_wrapper select").select2({templateResult: addEmpPic, templateSelection: addEmpPic}).on("select2:select",function(){hideTip()});';
    $_html_output .= '$(".select2-selection__rendered").attr("title","");';
    $_html_output .= '$(".select_wrapper select").on("change", function (evt) {$(".select2-selection__rendered").attr("title","")});';
    $_html_output .= '});';
    $_html_output .= '</script>';

    return $_html_output;
}

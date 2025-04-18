<?php
namespace label\Handler;

use label\Config;
use label\Handler;

class HandlerPDF extends HandlerAbstract
{

    public function action()
    {

        usort($this->request_params['list'], 'sortitems');
        foreach($this->request_params['list'] as $k=>$item) {
            if (!($this->request_params['print']=='Print full list' && $this->request_params['loggedUser']->get("see_all_employee"))) {
                $this->request_params['list'][$k]->street = $item->street_hide ? '' : $item->street;
                $this->request_params['list'][$k]->zip = $item->zip_hide ? '' : $item->zip;
                $this->request_params['list'][$k]->town = $item->town_hide ? '' : $item->town;
                $this->request_params['list'][$k]->country_name = $item->country_code_hide ? '' : $item->country_name;
                $this->request_params['list'][$k]->birthday = $item->birthday_hide ? '' : $item->birthday;
            }
        }
        global $smarty;
        global $siteURL;
        $smarty->assign('list', $this->request_params['list']);
        $smarty->assign('siteURL', $siteURL);

        if ($_POST['print']=='Print short list') {
            $html = $smarty->fetch("employees_short_prn.tpl");

        } else {
            $html = $smarty->fetch("employees_prn.tpl");
        }

        file_put_contents("/home/prologistics.net/public_html/tmp/employees.html", $html);
        $comand = "wkhtmltopdf \"http://www.prologistics.net/tmp/employees.html\" /home/prologistics.net/public_html/tmp/employees.pdf";
        exec($comand);
        if (file_exists("/home/prologistics.net/public_html/tmp/employees.pdf")) {
            $res = file_get_contents('/home/prologistics.net/public_html/tmp/employees.pdf');
            unlink('/home/prologistics.net/public_html/tmp/employees.pdf');
			unlink('/home/prologistics.net/public_html/tmp/employees.html');
        }

        header("Content-type: application/pdf; name=list.pdf");
        header("Content-disposition: attachment; filename=list.pdf");

        echo $res;
        exit;

    }
}

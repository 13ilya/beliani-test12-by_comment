<?php
namespace label\Handler\API;

use label\Config;
use label\Handler\HandlerAbstract;

class HOMTRANS extends HandlerAbstract
{
    private $ftp_server = 'ftp.ids-zas.com';
    private $ftp_user_name = 'guenstiger_ftp';
    private $ftp_user_pass = 'N1p%#3giMX';

    public function action($continue = false)
    {
        $date = date("d-m-Y");
//        $time = date("H-i");
//        $remote_file_name = "201120-{$date}-{$time}.csv";
        $remote_file_name = "201120-{$date}.csv";

        // check file already exists
        $conn_id = ftp_connect($this->ftp_server);
        $login_result = ftp_login($conn_id, $this->ftp_user_name, $this->ftp_user_pass);
        $result = ftp_size($conn_id, $remote_file_name);
        ftp_close($conn_id);
        $header = ($result != -1) ? false : true;
        
        $csv = $this->getCSV($header);

//        header("Content-type: application/excel; charset=utf-8");
//        header("Content-disposition: inline; filename=label.csv");
//        header("Content-length: " . strlen($csv));
//        die($csv);

        $link = "ftp://" . $this->ftp_user_name . ":" . $this->ftp_user_pass . "@" . $this->ftp_server . "/" . $remote_file_name;

        if (file_put_contents($link, $csv, FILE_APPEND)) {
            echo "$remote_file_name successfully uploaded to Homtrans";
        } else {
            echo "There was a problem while uploading $remote_file_name";
        }
        exit;
    }

    private function getCSV($header = false)
    {
        $offer_name = iconv('UTF-8', 'ASCII//TRANSLIT', $this->auction_params->get('offer_name'));
        
        $fields = array(
            'ENAME1' => $this->auction_params->get('firstname_shipping'),
            'ENAME2' => $this->auction_params->get('name_shipping'),
            'ESTRASSE' => $this->auction_params->get('street_shipping') . ' ' . $this->auction_params->get('house_shipping'),
            'EPLZ' => $this->auction_params->get('zip_shipping'),
            'EORT' => $this->auction_params->get('city_shipping'), // consignee e-post place
            'ELAND' => countryToCountryCode($this->auction_params->get('country_shipping')),
            'ETELNR' => $this->auction_params->get('tel_shipping'), // consignee phone number
            'SP1ANZAHL' => 1, // number of packages
            'SP1INHALT' => $offer_name, // contents (position1)
            'SP1VERP' => 'EP', // package key
            'SP1GEWICHT' => '5', // gross weight in kg
            'SP1MARK' => $this->auction_params->get('auction_number'), // recognition mark (position 1)
            'ABHOLDATUM' => date("d.m.Y H:i"), // pick up date till
            'FRANKATUR' => '2', // prepayment instructions
            'NACHNAHME' => '',
        );

        // COD
        $invoice = $this->auction_params->getMyInvoice();
        $openAmount = number_format($invoice->get("open_amount"), 2, '.', '');
        if ($this->auction_params->get('payment_method') == '2' && $openAmount > 0) {
            $fields['NACHNAHME'] = $openAmount;
        }

        $csv = '';
//        $fields = array_filter($fields);

        foreach ($fields as $k => $v) {
            $fields[$k] = trim($v);
        }

        // trim to maxlength
        $max_length = array(
            'ENAME1' => 35,
            'ENAME2' => 35,
            'ESTRASSE' => 35,
            'SP1INHALT' => 20,
            'SP1MARK' => 20,
        );

        foreach ($max_length as $index => $ml) {
            if (strlen($fields[$index]) > $ml) {
                $offset = ($ml - 3) - strlen($fields[$index]);
                $fields[$index] = substr($fields[$index], 0, $offset) . '...';
            }
        }

        if ($header == true) {
            $csv .= implode(';', array_keys($fields)) . "\r\n";
        }
        $csv .= implode(';', array_values($fields)) . "\r\n";

        return $csv;
    }


}

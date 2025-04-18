<?php
/**
 * @author  Dmytroped
 * @version 1.0.0
 */
namespace label\Handler\API;

use label\Handler\HandlerAbstract;

/**
 * Class GLS used in class HandlerFabric
 *
 * @package label\Handler\API
 */
class GLS extends HandlerAbstract
{
    private $ftp_server = 'emea-b2b-ftp1.hellmann.net';
    private $ftp_user_name = '';
    private $ftp_user_pass = '';
    private $gls_customer_id = '2760000001';
    private $gls_contact_id = '2761234567'; //GLS Contact ID
    private $shipping_number;
    private $timestamp;
    private $sender;
    private $isCOD = false;

    public function __construct()
    {
        $this->timestamp = time();
        $this->sender = array(
            'company_name' => 'Beliani DE GmbH',
            'street_name' => 'Seeweg',
            'street_number' => '1',
            'zip' => '17291',
            'country' => 'D',
            'city' => 'Grünow',
        );
    }

    public function action($continue = false)
    {
        /*$this->shipping_number = $this->getNVE();
        while ($this->request_params['DB']->getOne(
            "select id from auction_label where tracking_number='$this->shipping_number'"
        )) {
            $this->shipping_number = $this->getNVE();
        }*/
//        $this->shipping_number = $this->request_params['DB']->getOne("select number from tracking_numbers where auction_number = '{$this->auction_params->get('auction_number')}'");
        $this->shipping_number = 555;
        
        $content = $this->getLabelContent();
        $pdf = $this->getPDF($content);
        $this->saveLabel($this->shipping_number, $pdf);

        header("Content-type: application/pdf");
        header("Content-disposition: inline; filename=label.pdf");
        die($pdf);

        /*        $link = "ftp://" . $this->ftp_user_name . ":" . $this->ftp_user_pass . "@" . $this->ftp_server . "/" . $remote_file_name;
        
                if (file_put_contents($link, $csv, FILE_APPEND)) {
                    echo "$remote_file_name successfully uploaded";
                } else {
                    echo "There was a problem while uploading $remote_file_name";
                }
                exit;*/
    }

    /**
     * Save pdf label in database
     *
     * @param string $PackNumber Shipping number
     * @param string $pdf        PDF file source
     *
     * @return mixed ID of the inserted row
     */
    private function saveLabel($PackNumber, $pdf)
    {
        $md5 = md5($pdf);
        $filename = set_file_path($md5);
        if (!is_file($filename)) {
            file_put_contents($filename, $pdf);
        }

        $q = "INSERT INTO auction_label (`auction_number`,`txnid`,`tracking_number`,`doc`, `shipping_method_id`) 
                VALUES (" . $this->auction_params->get('auction_number') . ", " . $this->auction_params->get('txnid') . ", '$PackNumber', '$md5',  " . $this->request_params['method']->get('shipping_method_id') . ")";
        $this->request_params['DB']->query($q);

        return $this->request_params['DB']->queryOne('SELECT LAST_INSERT_ID()');
    }

    private function getLabelContent()
    {
        $allorder = \Order::listAll($this->request_params['DB'], $this->request_params['DB'],
            $this->auction_params->get('auction_number'), $this->auction_params->get('txnid'), 1,
            $this->auction_params->getMyLang(), '0,1', 1);

        $weightTotal = 0;
        foreach ($allorder as $item) {
            if ($item->article_id != 0 && $item->admin_id == 0) {
                $realOrderItems[] = $item;
                $article = new \Article($this->request_params['DB'], $this->request_params['DB'], $item->article_id);
                if(count($article->parcels)){
                    foreach ($article->parcels as $parcel) {
                        $weightTotal += $parcel->weight_parcel;
                    }
                }
            }
        }

        $shipping_method_id = $this->request_params['method']->get('shipping_method_id');
        $LabelsCount = $this->auction_params->getLabelsCount($shipping_method_id);

        if ($this->auction_params->get('payment_method') == '2' && !$LabelsCount) {
            $this->isCOD = true;
            $invoice = $this->auction_params->getMyInvoice();
            $openAmount = number_format($invoice->get("open_amount"), 2, '.', '');
            $currency = siteToSymbol($this->auction_params->get('siteid'));
        }
            
        $date = date("dmy", $this->timestamp);
        $time = date("H:i:s", $this->timestamp) . date("P", $this->timestamp);

        $content = [];
        $content[1] = 'A';
        $content[2] = $this->gls_customer_id;
        $content[3] = $this->gls_contact_id;
        $content[4] = 'AA'; //Product
        $content[5] = CountryToISOCode($this->auction_params->get('country_shipping')); //Country ISO code
        $content[6] = $this->auction_params->get('zip_shipping'); //Zip code of Destination
        $content[7] = count($realOrderItems); //Total Unit of consignment
        $content[8] = '1'; //Unit sequence number in consignment
        $content[9] = ' '; //Customers consignment reference
        $content[10] = substr($this->auction_params->get('firstname_shipping'), 0, 20); //Recipient Name 1
        $content[11] = substr($this->auction_params->get('name_shipping'), 0, 20); //Recipient Name 2
        $content[12] = ' '; //Recipient Name 3 (Can be used as Street 2)
        $content[13] = substr($this->auction_params->get('street_shipping'), 0, 20); //Street of recipient
        $content[14] = substr($this->auction_params->get('house_shipping'), 0, 5); //Street/House Number
        $content[15] = substr($this->auction_params->get('city_shipping'), 0, 20); //City/Village
        $content[16] = !empty($this->auction_params->get('tel_shipping')) ? $this->auction_params->get('tel_shipping') : $this->auction_params->get('cel_shipping'); //Phone number
        $content[17] = $this->auction_params->get('auction_number') . "/" . $this->auction_params->get('txnid'); //Reference number of customer
        $content[18] = ''; //GLS reference Number
        $content[19] = $weightTotal * 100; //Weight
         if($this->isCOD) {
            $content[20] = 'D^' . $openAmount . '^' . $currency . '^Invoice No: ' . $invoice->get('invoice_number') . '|'; //Services and additional service information
         } else {
             $content[20] = ' |';
         }

        return $content;
    }

    private function getPDF($barcode_text)
    {
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle('UNI-SHIP');
        $pdf->SetSubject('UNI-SHIP');
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);

        $lineStyle = array('width' => 0.1, 'color' => array(0, 0, 0));
        $bold = \TCPDF_FONTS::addTTFfont(ROOT_DIR . '/css/fonts/Swis721/swis721_cn_bt_b.ttf', 'Swis-cn-bold', '', 32);
        $roman = \TCPDF_FONTS::addTTFfont(ROOT_DIR . '/css/fonts/Swis721/swis721-cn-bt-roman.ttf', 'Swis-cn-roman', '',
            32);

        // add a page
        $pdf->AddPage();
        $pdf->SetFont($bold);
        // border 100x148,5 mm
        $pdf->Line(0, 0, 100, 0, $lineStyle);
        $pdf->Line(0, 0, 0, 148.5, $lineStyle);
        $pdf->Line(100, 0, 100, 148.5, $lineStyle);
        $pdf->Line(0, 148.5, 100, 148.5, $lineStyle);

        $pdf->SetY(0);
        $pdf->SetX(0);
        $pdf->writeHTMLCell(100, 10, 0, 0, '<div style="display: table;vertical-align: middle;background: black;color: #000;width: 100%; height: 100%;position: relative;text-align: center">
<span style="display: table-cell;vertical-align: middle;color: white;font-size: 7.5mm;margin-top: 2mm">UNI-SHIP</span>
</div>', 1, 0, true);

        $pdf->SetXY(30, 12);
        // set style for barcode
        $style = array(
            'border' => 1,
            //    'vpadding' => 'auto',
            //    'hpadding' => 'auto',
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => false, //array(255,255,255)
            //    'module_width' => 1, // width of a single module in points
            //    'module_height' => 1 // height of a single module in points
        );

        //write2DBarcode
        $pdf->serializeTCPDFtagParameters(array('UNI-SHIP test test test', 'QRCODE,H', '', '', 42, 42, $style, 'N'));
        $text = implode('|', $barcode_text);
        if (strlen($text) < 304) {
            $text .= str_repeat(' ', 304 - strlen($text));
        }
        $pdf->write2DBarcode($text, 'DATAMATRIX', '', '', 42, 42, $style, 'N');
        /**
         *
         * line 1 - 1 58 99 58 0,1
         * line 2 - 1 58 1 133 0,1
         * line 3 - 1 87 83 87 0,1
         * line 4 - 83 58 83 133 0,1
         * line 5 - 1 116 83 116 0,1
         * line 6 - 1 133 99 133 0,1
         * line 7 - 99 58 99 133 0,1
         */
        $pdf->Line(1, 58, 99, 58, $lineStyle);
        $pdf->Line(1, 58, 1, 133, $lineStyle);
        $pdf->Line(1, 87, 83, 87, $lineStyle);
        $pdf->Line(83, 58, 83, 133, $lineStyle);
        $pdf->Line(1, 116, 83, 116, $lineStyle);
        $pdf->Line(1, 133, 99, 133, $lineStyle);
        $pdf->Line(99, 58, 99, 133, $lineStyle);
        $pdf->SetFont($roman, '', 9);
        $pdf->Text(86, 54, 'U2.00.0');

        //sender
        $shiftX = 12;
        $shiftY = 3;
        $symbol_len = 1.10;
        $pdf->SetFont($roman, '', 7);
        $pdf->StartTransform();
        $pdf->Rotate(-90, 99, 58);
        $text = 'Sender: ';
        $x = 99;
        $y = 58;
        $pdf->Text($x, $y, $text);

        $pdf->Text($x += $symbol_len * strlen($text), 58,
            $text = 'Customer ID: ' . $barcode_text[2] . ' ');// Customer ID
        $pdf->Text($x += $symbol_len * strlen($text), 58, $text = 'Contract ID: ' . $barcode_text[3]);// Contract ID

        $pdf->Text(99, $y += 2.5, $this->sender['company_name']);
        $pdf->Text(99, $y += 2.5, $this->sender['street_name'] . ' ' . $this->sender['street_number']);
        $pdf->Text(99, $y += 2.5,
            $this->sender['country'] . '     ' . $this->sender['zip'] . '      ' . $this->sender['city']);
        $pdf->StopTransform();

        //consignee
        $pdf->SetFont($roman, '', 12);
        $x = 1;
        $y = 87;
        $pdf->Text($x, $y, $this->request_params['labels']);
        if (isset($barcode_text[9])) {
            $pdf->Text($x + 50, $y, 'Cust-Ref:' . $barcode_text[9]);
        }
        $pdf->SetFont($bold, '', 15);
        $pdf->Text($x, $y += 4, $barcode_text[10]);
        $pdf->SetFont($roman, '', 12);
        if (isset($barcode_text[11])) {
            $pdf->Text($x, $y += 5.5, $barcode_text[11]);
        } else {
            $pdf->Text($x, $y += 5, 'Caaa');
        }
        $pdf->Text($x, $y += 5, 'Caaa');
        $pdf->SetFont($bold, '', 15);
        $pdf->Text($x, $y += 4, $barcode_text[13] . (isset($barcode_text[14]) ? $barcode_text[14] : ''));
        $pdf->SetFont($bold, '', 13);
        $pdf->Text($x, $y += 5.5, $barcode_text[5] . '     ' . $barcode_text[6] . '    ' . $barcode_text[15]);

        $x = 1;
        $y = 116;
        $pdf->SetFont($roman, '', 8);
        $pdf->Text($x, $y, 'Contact: ' . $barcode_text[10]);
        $pdf->Text($x, $y += 3.3, 'Phone: ' . $barcode_text[16]);
        $pdf->Text($x, $y += 3.3, 'Note: ');
        $pdf->Text($x, $y += 3.3, 'Note: ');
        $pdf->Text($x, $y += 3.3, 'Ref-No: ' . $barcode_text[17]);

         if($this->isCOD) {
            $x = 1;
            $y = 58;
            $code = explode('^', $barcode_text[20]);
            $pdf->SetFont($bold, '', 18);
            $pdf->Text($x, $y, 'CASH-SERVICE');
            $pdf->SetFont($roman, '', 9);
            $pdf->Text($x, $y += 10, 'Verwendungszweck:');
            $pdf->Text($x, $y += 4, $code[3]);
        }

//        $pdf->Output('label.pdf', 'I');
        return $pdf->Output('', 'S');
    }

}
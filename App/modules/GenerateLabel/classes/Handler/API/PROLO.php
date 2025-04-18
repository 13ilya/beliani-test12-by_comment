<?php
/**
 * @copyright innerfly
 * @date      09.03.2017 14:56
 */

namespace label\Handler\API;

use label\Config;
use label\Handler\HandlerAbstract;

require_once 'lib/SellerInfo.php';

/**
 * Class PROLO used in class HandlerFabric
 * @package label\Handler\API
 */
class PROLO extends HandlerAbstract
{
    private $shipping_number;

    public function action($continue = false)
    {
        $this->getNVE();
        $pdf = $this->getPDF();
        $this->saveLabel($this->shipping_number, $pdf);

        if($continue) return $pdf;

        header("Content-type: application/pdf");
        header("Content-disposition: inline; filename=label.pdf");
        die($pdf);
    }
    
    private function getNVE(){
        $auction_number = $this->auction_params->data->auction_number;
        $txnid = $this->auction_params->data->txnid;
        
        $user_ware = $this->request_params['loggedUser']->data->timestamped_warehouse_id;
        if ($user_ware) {
            $q = "SELECT ware_char FROM warehouse WHERE warehouse_id=$user_ware";
            $ware_char = $this->request_params['DB']->getOne($q);
        } else {
            $ware_char = '-';
        }
        $q = "SELECT tracking_number FROM auction_label WHERE auction_number={$auction_number} AND txnid={$txnid}";
        $tns = $this->request_params['DB']->getAll($q);
        $counter = 0;
        foreach ($tns as $tn) {
            if ($tn->tracking_number[0] == $ware_char) {
                $counter++;
            }
        }
        $counter += 1;
        $this->shipping_number = $ware_char . '/' . $auction_number . '/' . $txnid . '-' . $counter;
        
        return $this->shipping_number;
    }

    private function getPDF()
    {
        require_once ROOT_DIR . '/plugins/function.barcodeurl.php';
        
        $smarty = new \Smarty();
        
        $auction_number = $this->auction_params->data->auction_number;
        $txnid = $this->auction_params->data->txnid;
        $seller = $this->getSeller();
        $url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}";
        
        $barcode1 = $url . smarty_function_barcodeurll([
            'number' => $auction_number . "/" . $txnid, 
            'fontsize' => 12], $smarty);
                
        $barcode2 = $url . smarty_function_barcodeurll([
            'number' => $this->shipping_number, 
            'fontsize' => 20,
            'barwidth' => 2,
            'height' => 80], $smarty);

        $pdf = new \TCPDF('p', 'mm', [106.6, 152.4], true, 'UTF-8', false);
        $pdf->SetMargins(1, 2, 2);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->AddPage();

        $lineStyle = [
            'width' => 0.1, 
            'color' => [0, 0, 0]
        ];
        $pdf->Line(2, 2, 105, 2, $lineStyle);
        $pdf->Line(105, 2, 105, 150, $lineStyle);
        $pdf->Line(2, 150, 105, 150, $lineStyle);
        $pdf->Line(2, 2, 2, 150, $lineStyle);

        $pdf->Line(2, 33, 105, 33, $lineStyle);
        $pdf->Line(2, 66, 105, 66, $lineStyle);
        $pdf->Line(2, 96, 105, 96, $lineStyle);

        $pdf->Image($seller->logo_url, 58, 4, 40);
        $pdf->Image($barcode1, 4, 77, 46);
        $pdf->Image($barcode2, 4, 115, 92);

        $bold = \TCPDF_FONTS::addTTFfont(ROOT_DIR . '/css/fonts/Swis721/swis721_cn_bt_b.ttf', 'Swis-cn-bold', '', 32);
        $roman = \TCPDF_FONTS::addTTFfont(ROOT_DIR . '/css/fonts/Swis721/swis721-cn-bt-roman.ttf', 'Swis-cn-roman', '', 32);

        $x = 4;
        $y = 4;
        $space = 4;
        // sender
        $pdf->SetFont($bold, '', 9);
        $pdf->Text($x, $y, 'Verkäufer:');
        $pdf->SetFont($roman, '', 9);
        $pdf->Text($x, $y += $space, $seller->seller_name);
        $pdf->Text($x, $y += $space, $seller->street);
        $pdf->Text($x, $y += $space, $seller->zip . " " . $seller->town);
        $pdf->Text($x, $y += $space, $seller->country_name);
        
        // recipient
        $y = 35;
        $space = 3.6;
        $pdf->SetFont($bold, '', 9);
        $pdf->Text($x, $y, 'Lieferadresse:');
        $pdf->SetFont($roman, '', 9);
        $auction = $this->auction_params->data;
        if(strlen(trim($auction->company_shipping))){
            $pdf->Text($x, $y += $space, $auction->company_shipping);
        }
        $pdf->Text($x, $y += $space, $auction->firstname_shipping . ' ' . $auction->name_shipping);
        $pdf->Text($x, $y += $space, $auction->street_shipping . " " . $auction->house_shipping);
        $pdf->Text($x, $y += $space, $auction->zip_shipping . " " . $auction->city_shipping);
        $pdf->Text($x, $y += $space, $auction->country_shipping);
        if(strlen(trim($auction->tel_shipping))){
            $pdf->Text($x, $y += $space, "Telefonnummer (festnetz) " . $auction->tel_shipping);
        }
        if(strlen(trim($auction->cel_shipping))){
            $pdf->Text($x, $y += $space, "Telefonnummer (mobile) " . $auction->cel_shipping);
        }

        // Auction number
        $pdf->SetFont($bold, '', 9);
        $pdf->Text($x, 70, "Auftrag " . $auction_number . " / " . $txnid);

        $pdf->Text(4, 100, 'Tracking number');

        // add route name and label
        $route_name = $this->getRouteName($auction->route_id);
        $x=58;
        $y=36;
        $pdf->SetFont($bold, '', 9);
        $pdf->Text($x, $y, $route_name);
        $y += $space;
        $pdf->Text($x, $y, 'Sequence');
        $pdf->SetFont($bold, '', 72);
        $pdf->Text($x, $y, $auction->route_label);

        //        $pdf->Output('label.pdf', 'I');
        return $pdf->Output('', 'S');
    }

    private function getSeller()
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $shipseller = $dbr->getOne("select shipseller from source_seller where id=" . $this->auction_params->get('source_seller_id'));
        $sideSeller = (strpos($shipseller, 'Beliani') !== false) ? 0 : 1;
        if ($sideSeller) {
            $seller = new \SellerInfo($db, $dbr, $shipseller, $this->auction_params->getMyLang());
        } else {
            $seller = $this->request_params['seller'];
        }

        return $seller->data;
    }

    private function getRouteName($route_id)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $route_name = $dbr->getOne("select name from route where id = {$route_id}");
        return $route_name;
    }

}
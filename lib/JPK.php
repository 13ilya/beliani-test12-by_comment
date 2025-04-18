<?php
class JPK {
    /**
     *
     */
    public $_auctions = [];
    /**
     *
     */
    public $_insurances = [];
    /**
     *
     */
    public $_methods = [];
    /**
     *
     */
    public $payments;
    /**
     *
     */
    public $output = '';
    /**
     *
     */
    public function __construct($payments) {
        $this->payments = $payments;
    }
    /**
     *
     */
    public function export() {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $this->output = '"Sales no.";"Recipent No. (VAT number)";"Company name";"Person name";"Recipient address";"Auftrag";"Invoice no.";"Date of issue";"Sale date";"Net value";"VAT value"' . "\r\n";
        
        $auction_numbers = [];
        foreach ($this->payments as $key => $payment) {
            if (!isset($this->_auctions[$payment->number])) {
                $this->_auctions[$payment->number] = new Auction($db, $dbr, $payment->auction_number, $payment->txnid);
            }
            
            if (stripos($payment->number, 'INS') !== false) {
                $parts = explode(' ', $payment->number);
                $ins_id = (int)$parts[1];
                
                if (!isset($this->_insurances[$ins_id])) {
                    $this->_insurances[$ins_id] = new Insurance($db, $dbr, $ins_id);
                }
                
                $shipping_method_id = $this->_insurances[$ins_id]->get('shipping_method');
                if (!isset($this->_methods[$shipping_method_id])) {
                    $this->_methods[$shipping_method_id] = new ShippingMethod($db, $dbr, $shipping_method_id);
                }
                
                $this->payments[$key]->insurance_id = $ins_id;
                $this->payments[$key]->shipping_method_id = $shipping_method_id;
            }

            $auction = $this->_auctions[$payment->number];
            
            $vat_percent = Vat::get_vat_percent($db, $dbr, $auction);
            $vat = ($payment->amount / (100+$vat_percent)) * $vat_percent;
            
            $this->payments[$key]->vat = round($vat, 2);
            $this->payments[$key]->net = round($payment->amount - $vat, 2);
            
            $auction_numbers[] = $payment->auction_number;
        }
        
        $r = $dbr->getAll("SELECT 
            auction.auction_number, 
            company_invoice.value company_invoice,
            CONCAT(firstname_shipping.value, ' ', name_shipping.value) name,
            CONCAT(street_shipping.value, ' ', house_shipping.value, ' ', zip_shipping.value, ' ', city_shipping.value) address
            FROM auction 
            LEFT JOIN auction_par_varchar company_invoice on auction.auction_number = company_invoice.auction_number 
                and auction.txnid=company_invoice.txnid
                and company_invoice.key = 'company_invoice'
            LEFT JOIN auction_par_varchar firstname_shipping on auction.auction_number = firstname_shipping.auction_number 
                and auction.txnid=firstname_shipping.txnid
                and firstname_shipping.key = 'firstname_shipping'
            LEFT JOIN auction_par_varchar name_shipping on auction.auction_number = name_shipping.auction_number 
                and auction.txnid=name_shipping.txnid
                and name_shipping.key = 'name_shipping'
            LEFT JOIN auction_par_varchar street_shipping on auction.auction_number = street_shipping.auction_number 
                and auction.txnid=street_shipping.txnid
                and street_shipping.key = 'street_shipping'
            LEFT JOIN auction_par_varchar house_shipping on auction.auction_number = house_shipping.auction_number 
                and auction.txnid=house_shipping.txnid
                and house_shipping.key = 'house_shipping'
            LEFT JOIN auction_par_varchar zip_shipping on auction.auction_number = zip_shipping.auction_number 
                and auction.txnid=zip_shipping.txnid
                and zip_shipping.key = 'zip_shipping'
            LEFT JOIN auction_par_varchar city_shipping on auction.auction_number = city_shipping.auction_number 
                and auction.txnid=city_shipping.txnid
                and city_shipping.key = 'city_shipping'
            WHERE auction.auction_number IN (" . implode(',', $auction_numbers) . ")");

        foreach ($r as $auction) {
            foreach ($this->payments as $key => $payment) {
                if ($payment->auction_number == $auction->auction_number) {
                    $this->payments[$key]->auction = $auction;
                }
            }
        }

        foreach ($this->payments as $key => $payment) {
            $original_auction = $this->_auctions[$payment->number]->data;
            
            if ($payment->payment_type == 'refund' || $payment->rma_spec_sol_id) {
                $minus = '-';
            } else {
               $minus = '';
            }
        
            $this->output .= '"'.($key + 1).'";';
            
            
            if (!$original_auction->company_invoice) {
                $this->output .= '"BRAK";';    
            } else {
                $this->output .= '"' . $original_auction->vat . '";';
            }
            
            if (stripos($payment->number, 'CREDIT') !== false || stripos($payment->number, 'INS') !== false) {
                $number = $payment->number;
            } else {
                $number = $payment->invoice_number;
            }
            
            if ($payment->shipping_method_id) {
                $address = $this->_methods[$payment->shipping_method_id]->data->street 
                    . ' ' 
                    . $this->_methods[$payment->shipping_method_id]->data->house 
                    . ' ' 
                    . $this->_methods[$payment->shipping_method_id]->data->zip 
                    . ' ' 
                    . $this->_methods[$payment->shipping_method_id]->data->city; 
            } else {
                $address = $payment->auction->address;
            }
            
            $this->output .= '"' . $payment->auction->company_invoice . '";';
            $this->output .= '"' . $payment->auction->name . '";';
            $this->output .= '"' . $address . '";';
            $this->output .= '"' . $payment->auction_number . '";';
            $this->output .= '"' . $number . '";';
            $this->output .= '"' . $payment->invoice_date . '";';
            $this->output .= '"' . $payment->invoice_date . '";';
            $this->output .= '"' . $minus . $payment->net . '";';
            $this->output .= '"' . $minus . $payment->vat . '";';
            
            $this->output .= "\r\n";
        }
        
        header("Content-type: application/excel; name=import.csv");
        header("Content-disposition: attachment; filename=import.csv");
        
        echo $this->output;
        die;
    }
}
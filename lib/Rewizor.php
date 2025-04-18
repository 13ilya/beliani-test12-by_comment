<?php
require_once 'lib/CurrencyRate.php';

class Rewizor {
    /**
     *
     */
    public $_auctions = [];
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
        
        $auction_numbers = [];
        foreach ($this->payments as $key => $payment) {
            $auction_numbers[$payment->auction_number] = $payment->txnid;
            $this->_auctions[$payment->number] = new Auction($db, $dbr, $payment->auction_number, $payment->txnid);
        }
        
        $search_string = implode(',', array_keys($auction_numbers));
        $q = "SELECT `auction_number`,min(`payment_date`) 
            FROM `payment` 
            WHERE `auction_number` IN ($search_string) 
            GROUP BY `auction_number`";
        $auction_first_payment = $dbr->getAssoc($q);
        
        // calculating vat and net
        foreach ($this->payments as $key => $payment) {
            $amount = $payment->sign_amount;
            
            $auction = $this->_auctions[$payment->number];
            
            $vat_percent = Vat::get_vat_percent($db, $dbr, $auction);
            $vat = ($amount / (100+$vat_percent)) * $vat_percent;
            
            $this->payments[$key]->vat_EUR = round($vat, 2);
            $this->payments[$key]->netto_sales_price_EUR = round($amount - $vat, 2);
        }
        
        $this->output .= '[INFO]
"1.05",0,1250,"Subiekt GT","BELIANI (AT)","Beliani (AT)","BELIANI (AT) Spó³ka z ograniczon¹ odpowiedzialnoœci¹","Szczecin","70-440","Ksiêcia Bogus³awa X 48/8","8513194539","MAG","G³ówny","Magazyn g³ówny",,1,20160101000000,20161231000000,"Szef",20161208222517,"Polska","PL",,0';

        foreach ($this->payments as $key => $payment) {
            $number = $this->_getNumber($payment);
            
            $used_date = $auction_first_payment[$payment->auction_number];
            $date = date('Ymd', strtotime($payment->payment_date));
            $payment_date = date('Y-m-d', strtotime('-1 day', strtotime($used_date)));
            $rate = CurrencyRate::getRate($payment_date);
            
            $vat = number_format($payment->vat_EUR, 2, '.', '');
            $vat = $payment->rma_spec_sol_id ? '-' . $vat : $vat;
            
            $net = number_format($payment->netto_sales_price_EUR , 2, '.', '');
            $net = $payment->rma_spec_sol_id ? '-' . $net : $net;
            
            $amount = number_format($payment->sign_amount, 2, '.', '');
            $amount = $payment->rma_spec_sol_id ? '-' . $amount : $amount;
            
            $this->output .= '

[NAGLOWEK]
"FS",1,0,' . ($key + 1) . ',,,"' . $number . '",,,,,"KLIENT","Klient","Klient",,,,,"Sprzeda¿","Sprzeda¿ dla klienta","Szczecin",' .  $date . '000000,' .  $date . '000000,,1,1,"Hurtowa",' . $net . '00,' . number_format($vat, 2, '.', '') . '00,' . $amount . '00,0.0000,,0.0000,,' .  $date . '000000,' . $amount . '00,' . $amount . '00,0,0,1,0,";Szef",,,0.0000,0.0000,"EUR",' . $rate . ',,,,,0,0,0,,0.0000,,0.0000,"Polska","PL",0

[ZAWARTOSC]
"' . number_format($payment->vat_percent, 0) . '",' . $payment->vat_percent . '00,' . $net . '00,' . number_format($vat, 2, '.', '') . '00,' . $amount . '00';
        }
        
        $this->output .= '

[NAGLOWEK]
"KONTRAHENCI"

[ZAWARTOSC]
2,"KLIENT","Klient","Klient",,,,,,,,,,,,,,,,,,,,,,,,"Polska","PL",0

[NAGLOWEK]
"GRUPYKONTRAHENTOW"

[ZAWARTOSC]
"KLIENT","Podstawowa"

[NAGLOWEK]
"CECHYKONTRAHENTOW"

[ZAWARTOSC]

[NAGLOWEK]
"DODATKOWEKONTRAHENTOW"

[ZAWARTOSC]
"KLIENT",0,1,0,0

[NAGLOWEK]
"DATYZAKONCZENIA"

[ZAWARTOSC]
';

        foreach ($this->payments as $key => $payment) {
            $number = $this->_getNumber($payment);
            $date = date('Ymd', strtotime($payment->payment_date));            
            $this->output .= '"' . $number . '",' .  $date . '000000
';
        }
        
        $this->output .= '
[NAGLOWEK]
"NUMERYIDENTYFIKACYJNENABYWCOW"

[ZAWARTOSC]';
        
        header("Content-type: text/plain; name=belianiat.epp");
        header("Content-disposition: attachment; filename=belianiat.epp");
        echo $this->output;
        die;
    }
    
    private function _getNumber($payment) {
        if ($payment->rma_spec_sol_id) {
            $parts = explode(' TICKET ', $payment->number);
            $number = $parts[1];
        } elseif ($payment->ins_id) {
            $number = $payment->number;
        } else {
            $number = $payment->invoice_number;
        }
        return $number;
    }
}
<?PHP
require_once 'Services/Ebay.php';

require_once 'config.php';


$page = 1;
while (true) {
    $eBay = new Services_Ebay( $devId, $appId, $certId );
    $eBay->setAuth( $testUser, $testPass );
    $items = $eBay->GetSellerList (array('StartTimeFrom'=>'2004-06-09 00:00:00', 'StartTimeTo'=>'2004-06-09 23:59:59', 'ItemsPerPage'=>10, 'PageNumber'=>$page), 2);
    if (PEAR::isError($items)) {
        echo 'Error: '.$items->getMessage();
        exit;
    }
    if ($page == 1) {
        $item = $items;
    } elseif (isset($items['Item'][0])) {
        $item['Item'] = array_merge($item['Item'], $items['Item']);
    } else {
        $item['Item'][] = $items['Item'];
    }
    $page++;
    if ($items['PageNumber'] >= $items['TotalNumberOfPages']) {
        break;
    }
}



    print_r( $item);
?>
<?php
require_once 'connect.php';
require_once 'lib/Config.php';

$loggedUser = new \User($db, $dbr, 'Justyna J', 1);
$GLOBALS['loggedUser'] = $loggedUser;
$db = dblogin(str_replace(' ', '', 'Justyna J'), $db_pass, $db_host, $db_name);
\label\DB::setInstance(\label\DB::USAGE_WRITE, $db);

$smarty->assign("loggedUser", $loggedUser->data);

//$devId = 'S288299ERP418N34ID1IL8QJWD3HJD';

//$appId = 'MICHAELWDMC871RTWDX986CKZD6291';

//$certId = 'H57MEX5CTMS$G63RT1B39-T99R87C3';

//$testUser = 'demenevtest';
//$testPass = 'reality';

$devId = 'S288299ERP418N34ID1IL8QJWD3HJD';

$appId = 'MICHAELWDMC871RTWDX986CKZD6291';

$certId = 'H57MEX5CTMS$G63RT1B39-T99R87C3';

$testUser = 'widmer0815';
$testPass = '1234567q';

if (APPLICATION_ENV === 'develop') {
    $siteURL = 'http://prolodev.prologistics.info/';
} else if (APPLICATION_ENV === 'heap') {
    $siteURL = 'http://proloheap.prologistics.info/';
} else if (APPLICATION_ENV === 'docker') {
    $siteURL = 'http://prologistics.dev/';
} else {
	$siteURL = 'https://www.prologistics.info/';
}
if (isset($smarty)) {
	$smarty->assign('siteURL', $siteURL);
}
$aaToken = 'AgAAAA**AQAAAA**aAAAAA**UGCxRw**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6wFloulCZSKpASdj6x9nY+seQ**rggAAA**AAMAAA**CN5aSwZcT0J3/63U76/i2oMIqV0bHvCOq0BtDEcsAU4/wY/hUHSLkoEn1IZ/ToYffpQuTQDhIqSA/Jl5nqfAnvm8cMS5P2Q22WFzPY8SiwCtdu08OH/fy4wGVo26ErPOAa0vR1s1bsqe03RqevvMvqvP7IqdoBUHu9Y1n5Omzvkz642jqDg5tXSKOH9Wiu7ML7aZmnUSpI6D3D8mT45XuHwjHQ9Bvsc8kH1+QljYqx2GTF3CtMo+fsSp2o5e0YI6g7EMREEeEM7ZQvfpma00svj+7zplHPpEQl+0+Cq0c4sAG90ivdl2RIidHenlnYBH+OPut583i1k3yHlcQTQ1T6cajJICRV0RyyN8yLONjhLgd/mk7VIMUMC3oHJ3+UaN7hFUokCBNR7lwM3sHhcMyRJcnFaUWmbkqchOhR0WcRmsWFig9GamLGcZBOl0SNGnxlN+BLo+DUOxIQ3xu9IcKPcI+faOAUcixuH7YO6HtyehlyIfh41huenK8yC171TDphuJzn5WMf3szztOFiQ9A17EG8sW0RUfXpvbdl7CjTU6i9nRrE+/BKWuDD5klF8K1SOw6A5Po/pE0eKOgDyXmii6sot6pqhNKTm6H+7hTzOatWGNGjwopFyQE3zzJtGsSgTBYo/qiVYPI4LgiYuRK4ZmebfZgQOAsoyEd+naBvluWLaky3BQsIQuUAQpxkzr/USuWscJKkbtF/vRlcUNdYZOQyZf4VZH8lAgcMcj8GxHrSwg97B0KWsEa18woKW1'; // bettenfachhandel
// $aaToken = 'AgAAAA**AQAAAA**aAAAAA**QH91RQ**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6wJnY+lCpSEqQ6dj6x9nY+seQ**RJkAAA**AAMAAA**5jjVmp8N2OAc+GFNWy0IK4JlyKf+LEvaXsVkxrNg6oqgi4B43oD9nmCksl+0lIIsaqabMWcsu5SU3dV5eQhlYDm9UUmLEbj2/FSUC980TIfY2NjPFQLHeA9DWEiMr9/EjzfM79HRFDiGFgIDG8Gioyzq3PXWTWUNAUcVLv8RmrMaNsGYLgvfCj2rIprzinKES6FlTgKVuAFU58X6VB7xZK17FG5QuifAnp1SxDycBmcwWt9CtlL+K7bHs4ln6BJsfVvzJxXsULAejimtDTcc/ipNonV5Y8yM6AnhlaPl/wFnfXyoeocICdUN5cIJ+6i1n2hy+ZUnSZBihRoIGWMNQKQ46kchKO9Tc3emqXCaPdqiegloc+U00bUDNH2C9tPiuW+3znKaF0beWW5F4/jTzUK+oa41xh3iqQhCTJiPMqzVt2LD7enll/AhYLjEMBhaSfr1Id/y9tks8EFErrnJmzsg40Rgm7fHTmId2LHx7INaJomVHXatxfvM+DU1K/dox1gtk4WxAFVbiDZVfQ5p4r3kpX4JQyqVhV0KUW1Rsssg7oh6QBjIbWRFv1ArSmaoXJOwMx8o2sgziQBuPQak2a2jtIFYDHo7V22krFJSiBqCP4LLdoNyO2ttNiXODYAXgb4LEbM5kEiNEpBqm4JFm6jh5cMH9S+RMSYcm6CRMo/a2R9Gn70869fVwwW3iBioKSUS/KszZU//qgSW/uermHYE0Hi3PuVRn62DFQ/pkQpi83e8wX4JbYRZWy8ecvWK'; // basertest
//	 Config::get($db, $dbr, 'aatoken');



$sites = array(
//                             0=>'United States',
//                             2=>'Canada',
                             3=>'United Kingdom',
//                             15=>'Australia',
                             16=>'Austria',
//                             23=>'Belgium (French)',
//                             71=>'France',
                             77=>'Germany',
//                             101=>'Italy',
//                             123=>'Belgium (Dutch)',
//                             146=>'Netherlands',
//                             186=>'Spain',
//                             193=>'Switzerland',
//                             100=>'eBay Motors',
							 );

?>
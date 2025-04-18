<?php
namespace label;

use label\Handler\API\PROLO;
use label\Handler\API\KEX;
use label\Handler\API\DHL_DE;
use label\Handler\API\HOMTRANS;
use label\Handler\API\ETL;
use label\Handler\API\DPDWS;
use label\Handler\API\DPDDEPOT119;
use label\Handler\API\DPDDEPOT130;
use label\Handler\API\DHL_CH_NEW;
use label\Handler\API\KN;
use label\Handler\API\HELLMANN;
use label\Handler\API\GLS190;
use label\Handler\API\GLS300;
use label\Handler\API\GLS360;
use label\Handler\API\PL_DPD;
use label\Handler\HandlerPDF;
use label\Handler\HandlerRTF;

use label\Handler\API;


class HandlerFabric
{

    /**
     * @param string $command
     * @param Config $config
     * @return Handler_Abstract
     */
    public static function handle($command = null, $auction, $config)
    {
        switch ($command) {

            case "PROLO":
                $Handler = new PROLO();
                break;
            case "KEX":
                $Handler = new KEX();
                break;
            case "DHL_DE":
                $Handler = new DHL_DE();
                break;
            case "HOMTRANS":
                $Handler = new HOMTRANS();
                break;
            case "ETL":
                $Handler = new ETL();
                break;
            case "DPDWS":
                $Handler = new DPDWS();
                break;
            case "DPDDEPOT119":
                $Handler = new DPDDEPOT119();
                break;
            case "DPDDEPOT130":
                $Handler = new DPDDEPOT130();
                break;
            case "PL_DPD":
                $Handler = new PL_DPD();
                break;
            case "DHL_CH_NEW":
                $Handler = new DHL_CH_NEW();
                break;
            case "KN":
                $Handler = new KN();
                break;
            case "HELLMANN":
                $Handler = new HELLMANN();
                break;
            case "GLS190":
                $Handler = new GLS190();
                break;
            case "GLS300":
                $Handler = new GLS300();
                break;
            case "GLS360":
                $Handler = new GLS360();
                break;
            case "CreatePDF":
                $Handler = new HandlerPDF();
                break;
            case "ToRTF":
                $Handler = new HandlerRTF();
                break;
            default:
                return new HandlerPDF();

        }

        return $Handler
            ->setLogger($config)
            ->setRequestParams(self::getRequest())
            ->setAuctionParams($auction)
            ->setConfig($config);
    }

    /**
     * @return array
     */
    private static function getRequest()
    {
        $return = array();
        if (isset($_POST) and count($_POST) > 0) {
            $return = $_POST;
        } else if (isset($_GET) and count($_GET) > 0) {
            $return = $_GET;
        }

        return $return;
    }
}

<?php
/*
 * Export to Excel format
 */
class ExcelExport {

    public function export_payments($payments){

        require_once 'PHPExcel.php';
        $xls = new PHPExcel();
        $xls->setActiveSheetIndex(0);
        $sheet = $xls->getActiveSheet();
        $sheet->setTitle('Payments');

        $row_num = 1;
        $sheet->setCellValue( 'A'.$row_num, 'Paid status' );
        $sheet->setCellValue( 'B'.$row_num, 'Payment Date' );
        $sheet->setCellValue( 'C'.$row_num, 'Invoice Date' );
        $sheet->setCellValue( 'D'.$row_num, 'Auftrag number' );
        $sheet->setCellValue( 'E'.$row_num, 'Name' );
        $sheet->setCellValue( 'F'.$row_num, 'VAT Account' );
        $sheet->setCellValue( 'G'.$row_num, 'Debit' );
        $sheet->setCellValue( 'H'.$row_num, 'Credit' );
        $sheet->setCellValue( 'I'.$row_num, 'Amount' );
        $sheet->setCellValue( 'J'.$row_num, 'Exported' );
        $sheet->setCellValue( 'K'.$row_num, 'Comment' );

        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(25);
        $sheet->getColumnDimension('G')->setWidth(25);
        $sheet->getColumnDimension('H')->setWidth(25);
        $sheet->getColumnDimension('I')->setWidth(25);
        $sheet->getColumnDimension('J')->setWidth(25);
        $sheet->getColumnDimension('K')->setWidth(25);

        foreach($payments as $payment){
            $row_num++;
            $sheet->setCellValue( 'A'.$row_num, $payment->paid_status );
            $sheet->setCellValue( 'B'.$row_num, $payment->payment_date );
            $sheet->setCellValue( 'C'.$row_num, $payment->invoice_date );

            if($payment->ins_id){
                $auftrag_number =  "INS {$payment->ins_id}";
            }elseif ($payment->rma_spec_sol_id){
                $auftrag_number = $payment->number;
            }elseif ($payment->rating_case_id) {
                $auftrag_number = "RATED {$payment->rating_case_id}";
            }elseif ($payment->number=='Voucher') {
                $auftrag_number = "VOUCHER {$payment->auction_number}";
            }else {
                $auftrag_number = "{$payment->auction_number} / {$payment->txnid}";
            }

            $sheet->setCellValue( 'D'.$row_num, $auftrag_number );
            $sheet->setCellValue( 'E'.$row_num, $payment->name_invoice );
            $sheet->setCellValue( 'F'.$row_num, $payment->vat_account );
            $sheet->setCellValue( 'G'.$row_num, $payment->account );
            $sheet->setCellValue( 'H'.$row_num, $payment->selling_account?$payment->selling_account:$payment->vat_selling_account_number );
            $sheet->setCellValue( 'I'.$row_num, $payment->amount );
            $sheet->setCellValue( 'J'.$row_num, $payment->exported?'Yes':'No' );
            $sheet->setCellValue( 'K'.$row_num, $payment->transaction_id . ($payment->comment?' Comment: ' . $payment->comment:''));
        }

        // Output http headers
        header ( "Expires: Mon, 1 Apr 1974 05:00:00 GMT" );
        header ( "Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT" );
        header ( "Cache-Control: no-cache, must-revalidate" );
        header ( "Pragma: no-cache" );
        header ( "Content-type: application/vnd.ms-excel" );
        header ( "Content-Disposition: attachment; filename=payments_export.xls" );

        // Output file
        $objWriter = new PHPExcel_Writer_Excel5($xls);
        $objWriter->save('php://output');

    }

}
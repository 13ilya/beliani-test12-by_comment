<?php
/**
 * Wrapper for library wkhtmltopdf
 */
class WKHtmlToPDF {

    /**
     * @param $content_html - html for convert to pdf
     */
    public static function render($content_html){

        $hash = uniqid('wkhtmltopdf');
        file_put_contents("tmp/$hash.html", $content_html);
        $comand = "/usr/local/bin/wkhtmltopdf \"tmp/$hash.html\" tmp/$hash.pdf";
        $r = exec($comand);
        if (file_exists("tmp/$hash.pdf")) {
            $result=file_get_contents("tmp/$hash.pdf");
            unlink("tmp/$hash.pdf");
            unlink("tmp/$hash.html");
        }

        return $result;

    }

}
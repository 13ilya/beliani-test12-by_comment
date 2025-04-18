<?php

function smarty_modifier_number_format($string, $dec=2, $point=".", $sep=",")
{
    return number_format($string, $dec, $point, $sep);
}

/* vim: set expandtab: */

?>

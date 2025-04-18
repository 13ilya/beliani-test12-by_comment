#!/usr/bin/perl
use strict;


open(F,"cat /CACHE/nginx_cache_list*.txt|grep \"$ARGV[0]\"|sort|uniq|" );
my $c=0;
while(<F>){

 my ($f, undef, $k) = split(/\:/,$_);
  print $_;
 if(-e $f){
  $c++;
  if (uc $ARGV[1] eq '-F'){
    unlink($f);
  }
 }



}

close(F);

  if (uc $ARGV[1] eq '-F'){
     print "$c cache files deleted\n";
  }else{
     print "$c cache files matched\n\nUse: $0 key_string -f\nto force deletion\n\n";
  }
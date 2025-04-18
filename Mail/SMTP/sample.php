<? 
// include the class file 
include_once "smtp.class.php";


// create the class object and predefine the function 
$mail=new smtp_class;


# email setting
$mail->Host			= "mail.qualitradegmbh.com";  // SMTP server. 
$mail->Port			= 25;  // SMTP port. Default: 25;
$mail->Timeout		= 10;  // SMTP connection timeout. Default: 10;
$mail->Timezone		= "+0800";  // Time Zone. Default: "+0000";
$mail->Priority		= 3;  // Email priority Default: 3 (1=High,3=Medium or 5=Low);
$mail->CharSet		= "big-5";  // Character set.
$mail->WordWrap		= 0;  // characters per line. 0 for umlimit characterc per line
$mail->IsHTML		= true;  // HTML email. Default: false (true or false);
$mail->MailerDebug	= false;  // Display SMTP error. Default: false (true or false);
$mail->AuthLogin	= false;  // using SMTP authorization. Default: false (true or false);
$mail->AuthUser		= "widmer@qualitradegmbh.com";  // SMTP username
$mail->AuthPass		= "poland";  // SMTP password


# email address and content start here
$mail->From("widmer@qualitradegmbh.com","Michael Widmer");
$mail->AddTo("baserzas@gmail.com","Name");
$mail->AddCc("Email","Name");
$mail->AddBcc("Email","Name");
$mail->AddReplyTo("Email","Name");
$mail->Subject="Email Subject";
$mail->Body="Email Message";


# add attachment
//$mail->AddAttachment("filename.ext");


# define attachment content 
//$mail->AddAttachmentContent("attachment content","attachment_filename.ext");


# check email success or fail with your own program
if(!$mail->Send()){
	echo "Email Fail";
	print_r($mail);
}else{
	echo "Email Success";
}


?> 

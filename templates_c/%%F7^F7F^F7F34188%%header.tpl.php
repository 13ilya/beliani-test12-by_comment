<?php /* Smarty version 2.6.18, created on 2017-08-07 11:37:20
         compiled from header.tpl */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('modifier', 'default', 'header.tpl', 5, false),array('modifier', 'strip_tags', 'header.tpl', 5, false),array('modifier', 'nl2br', 'header.tpl', 519, false),array('modifier', 'escape', 'header.tpl', 519, false),array('function', 'checkPermission', 'header.tpl', 892, false),array('function', 'html_options', 'header.tpl', 914, false),)), $this); ?>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo ((is_array($_tmp=((is_array($_tmp=@$this->_tpl_vars['title'])) ? $this->_run_mod_handler('default', true, $_tmp, @$this->_tpl_vars['ttitle']) : smarty_modifier_default($_tmp, @$this->_tpl_vars['ttitle'])))) ? $this->_run_mod_handler('strip_tags', true, $_tmp) : smarty_modifier_strip_tags($_tmp)); ?>
</title>
<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
<script src="/js/jquery-browser-plugin-master/dist/jquery.browser.min.js"></script>

<!-- Unload action for LIMITER (<?php echo $this->_tpl_vars['page_uuid']; ?>
 && <?php echo $this->_tpl_vars['page_pid']; ?>
) -->
<?php if ($this->_tpl_vars['page_uuid'] && $this->_tpl_vars['page_pid']): ?>
<script>
    var __PAGE_UUID = "<?php echo $this->_tpl_vars['page_uuid']; ?>
";
    var __PAGE_PID = "<?php echo $this->_tpl_vars['page_pid']; ?>
";
</script>
<script type="text/javascript" src="/js/limiter.js"></script>
<?php endif; ?>

<?php echo '
<script type="text/javascript" src="/soundmanager/script/soundmanager2.js"></script>
<script type="text/javascript" src="/soundmanager/script/mp3-player-button.js"></script>
<script>
soundManager.setup({
  // required: path to directory containing SM2 SWF files
  url: \'/soundmanager/swf/\',
  debugMode: false
});
function getCookie(name) {     //get cookies from browser
  var matches = document.cookie.match(new RegExp(
    \'(?:^|; )\' + name.replace(/([\\.$?*|{}\\(\\)\\[\\]\\\\\\/\\+^])/g, \'\\\\$1\') + \'=([^;]*)\'
  ));
  return matches ? decodeURIComponent(matches[1]) : undefined;
}

function setCookie(name,value,time,path) {
    value = encodeURIComponent(value);
    var exp = time?time:\'Session\';
    var cookie = name + \'=\' + value + \';expires=\'+exp;
    if(path) cookie += \';path=\'+path;
    document.cookie = cookie;
}
</script>
<link rel="stylesheet" href="/css/alertify.min.css" />
<link rel="stylesheet" href="/css/themes/default.css" />
<link rel="stylesheet" href="/css/switch/switch.css" />

<script type="text/javascript" src="/js/jquery-ui.min.js"></script>
<script type="text/javascript" src="/js/jquery.form.min.js"></script>
<script type="text/javascript" src="/js/main_be.js?v=6"></script>
<link rel="stylesheet" type="text/css" href="/css/themes/jquery-ui.css">

<!-- Editor -->
<script type="text/javascript" src="/tinymce/tinymce.min.js"></script>
<script type="text/javascript">
function getSettingsForTinyMCE(shortPage){
    var selector = \'.tinimce_editor\',
        additional = \' fullpage\';
    if(shortPage){
        selector = \'.tinimce_short_editor\'
        additional = \'\';
    }
    return {
          convert_urls:false,
          selector: selector,
          setup: function(editor) {
              editor.on(\'keyup\', function(e) {
                  PageChanged = true;
                  console.log(PageChanged);
              });
          },
          plugins: [
                "advlist autolink lists link image charmap print preview anchor",
                "searchreplace visualblocks code fullscreen",
                "insertdatetime media table contextmenu paste "+additional
            ],
          paste_data_images: true,
          toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image"
    }
}
try{
    $(document).ready(function(){
        if($(\'.tinimce_editor\').length){
            tinymce.init(getSettingsForTinyMCE());
        }
        if($(\'.tinimce_short_editor\').length){
            tinymce.init(getSettingsForTinyMCE(true));
        }
    })
}catch (err){
  console.log(err);
}
</script>

<style>
    .select_wrapper{position:relative;display:inline-block}
    .emploee_img {position:absolute;z-index: 700;}
  *:focus{
    outline: none!important;
    outline: 0!important;
  }
  :focus{
    outline: none!important;
    outline: 0!important;
  }
  .issueDialog .select2{
      width: 275px!important;
  }
  html{
    width: 100%;
  }
    body{
    background-color: #F0F0F0;
    font-family: verdana, arial, helvetica, sans-serif;
    font-size: 11px;
    max-width: 1890px;
    }
    h1{
      margin-top: 20px;
    }
    td{
    font-family: verdana, arial, helvetica, sans-serif;
    font-size: 11px;
    }
    a,
    a:active,
    a:visited
    {
      color: blue;
      text-decoration: none;
    }
    a:hover{
      text-decoration: underline;
      color: blue;
      cursor: pointer;
    }
    .subheader{
      font-size: 15px;
      font-weight: bold;
    }
    input,select{
      font-family: Arial;
      font-size: 11px;
    }
    textarea{
      font-family: monospace;
    }
    .today{
      background-color: #DDDDDD;
    }
    .weekend{
      border: dotted 1px red;
    }
    .today-weekend{
      background-color: #DDDDDD;
      border: dotted 1px red;
    }
    a.small{
        font-size: 9px;
    }
  #fulltable{
    width: 100%;
    max-width: 500px;
  }
  .employees tr:hover{
    background-color: #cccccc;
  }
  #digit_table{
      float: left;
    max-width: 1700px;
  }
  #digit_table a{
    padding:0;
    margin: 0;
  }
  #digit_table a:before{
    content:"| ";
  }
  #div_comments{
    width: 1000px;
  }
  #errortext_div{
    padding: 10px;
    border: 1px solid #FF008A;
    background: #ddd;
  }
  .info_finish {
    position: absolute;
    right: 3px;
    top: 1px;
  }
    .new-op-order {
        background: #0080ff ! important;
        color: #ffffff ! important;
        padding: 1px 2px ! important;
    }
</style>
'; ?>

<?php 
  if (\label\DebugToolbar\DebugToolbar::isEnabled()) {
    echo \label\DebugToolbar\DebugToolbar::renderHead();
  }
 ?>
</head>

<body
    data-user='<?php echo $this->_tpl_vars['loggedUser']->username; ?>
'
<?php 
  if (strpos($_SERVER['PHP_SELF'], "shipping_scan.php")>0){
    print("onload='document.all.tracking_number.focus()'");
  }
  if (strpos($_SERVER['PHP_SELF'], "packed_scan.php")>0){
    print("onload='document.all.number.focus()'");
  }
  if ((strpos($_SERVER['PHP_SELF'], "op_order.php")>0)
  || (strpos($_SERVER['PHP_SELF'], "rma_pics.php")>0)
  || (strpos($_SERVER['PHP_SELF'], "search.php")>0)
  || (strpos($_SERVER['PHP_SELF'], "shop_bonuses.php")>0)
  || (strpos($_SERVER['PHP_SELF'], "offer.php")>0)){
    print("onLoad='onLoad();'");
  }
 ?>
>
<script>
    <?php echo '
    var PageChanged = false;
    function showTip(el){
        var url = $(el).data("url");
        var parent = $(el).parents(".select_wrapper");
        if(!parent.length) parent = $(\'.select2-container--open\').parents(".select_wrapper");
        var template = \'<div class="emploee_img"><img src="\'+url+\'" width="200"></div>\';
        $(parent).append(template);
        $(\'.emploee_img\').css({left:"100%", top:"28px"});
    }
    function hideTip(){
        $(\'.emploee_img\').remove();
    }
    function addEmpPic (opt) {
        if (!opt.id) {
            return opt.text;
        }
        var optimage = $(opt.element).data("image");
        var optcolor = $(opt.element).data("color");

        if(!optimage && !optcolor){
            return opt.text;
        }
        var option = "";
        if(!optimage)
        {
            option = $(
                \'<span style="background:\' + optcolor + \'">\' + $(opt.element).text() + \'</span>\'
            );
        }
        else
        {
            option = $(
                \'<span style="background:\' + optcolor + \'" data-url="\'+optimage+\'" onmouseover = "showTip(this)" onmouseout = "hideTip()">\' + $(opt.element).text() + \'</span>\'
            );
        }
        return option;
    }
    $(document).ready(function(){
        $(\'.under_watch input[type="checkbox"],.under_watch input[type="radio"],.under_watch select\').on(\'change\',function(){
                PageChanged = true;
                console.log(PageChanged);
            });
        $(\'.under_watch input,.under_watch textarea\').on(\'keyup\',function(){
                PageChanged = true;
                console.log(PageChanged);
            });
            $(\'.goTo\').click(function(){
                console.log(\'PageChanged\',PageChanged);
                var param = $(this).data(\'param\');
                var form = $(this).data(\'form\');
                var page = $(this).data(\'page\');
                if(PageChanged){
                    document.getElementById(\'go\').value=page;
                    document.getElementById(form).submit();
                }
                else {
                    console.log(\'page\',page+param);
                    location.href = page+param;
                }
        })
        var main_be_lib_instance = main_be_lib();
        main_be_lib_instance.issueInit();//initial function for buttons with class = issueLog
        main_be_lib_instance.generateSelects();//initial function for buttons with class = issueLog
        main_be_lib_instance.copyToClipboardInit();//initial function for buttons with class = copyParam
        main_be_modal_wnd = main_be_lib_instance.ModalWndInstance({
            html:\'\',
            autoOpen:false
        })
    })
    var main_be_modal_wnd;
    '; ?>

</script>
<?php if ($this->_tpl_vars['order_checkout']): ?>
<div style="<?php if (! $this->_tpl_vars['admin']): ?>width: 800px;<?php endif; ?> margin: 0 auto; position: relative;">
<div style="position: absolute; top: 0; right: 0;">
<?php $_from = $this->_tpl_vars['langs']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['lang_id'] => $this->_tpl_vars['lang']):
?>
<a href="javascript:document.getElementById('stayhere').value = 1;document.getElementById('lang').value='<?php echo $this->_tpl_vars['lang_id']; ?>
';document.forms[0].submit();">
<?php if ($this->_tpl_vars['lang_id'] == $this->_tpl_vars['def_lang']): ?><b><?php echo $this->_tpl_vars['lang']; ?>
</b><?php else: ?><?php echo $this->_tpl_vars['lang']; ?>
<?php endif; ?>
</a>
<?php endforeach; endif; unset($_from); ?>
</div>
<?php endif; ?>


<!-- LOADER -->
<script>
<?php echo '
(function() {
  this.Loader = function() {
    // Define option defaults
    var defaults = {
      className: \'loader\',
      to_element: \'body\',
      img_src: \'/css/images/imgLoader.gif\'
    }
    // Create options by extending defaults with the passed in arugments
    if (arguments[0] && typeof arguments[0] === "object") {
      this.options = extendDefaults(defaults, arguments[0]);
    }
  }
  // Public Methods
  Loader.prototype.addLoader = function() {
    if(this.Loader === undefined){
      this.Loader = document.createElement("img");
      this.Loader.src = this.options.img_src;
      this.Loader.className = "k_loader " + this.options.className;
        this.Loader.style.position = \'fixed\';
        this.Loader.style.top = 0;
        this.Loader.style.right = 0;
        this.Loader.style.bottom = 0;
        this.Loader.style.left = 0;
        this.Loader.style.margin = \'auto\';
        this.Loader.style.border = \'1px solid #000\';
        this.Loader.style.borderRadius = \'4px\';
        this.Loader.style.boxShadow = \'0 0 15px 2px #555\';
        this.Loader.style.zIndex = \'999\';

        // Overlay
        this.overlay = document.createElement("div");
        var ov_style = this.overlay.style;
        ov_style.position = \'fixed\';
        ov_style.top = 0;
        ov_style.bottom = 0;
        ov_style.width = \'100%\';
        ov_style.height = \'100%\';
        ov_style.background = \'#999\';
        ov_style.opacity = \'0.5\';
        //console.log(ov_style);

       // var re_id = /^#/;
       // var re_class = /^[.]/;
      // var str = this.options.to_element;
      // var to_el = this.options.to_element.substring(1);
      // if (re_id.test(str)){
      // 	var parent = document.getElementById(to_el);
       //    	parent.style.position = \'relative\';
       //    	parent.appendChild(this.Loader);
      // } else if(re_class.test(str)) {
      //     var parents = document.getElementsByClassName(to_el);
      //     for (var i = parents.length - 1; i >= 0; i--) {
      //     	parents[i].style.position = \'relative\';
      //     	parents[i].appendChild(this.Loader);
      //     };
      // } else {
        document.body.appendChild(this.Loader);
        document.body.appendChild(this.overlay);
      //}
    }
  }
  Loader.prototype.removeLoader = function() {
    if(this.Loader != undefined ){
      this.Loader.parentNode.removeChild(this.Loader);
      this.Loader = undefined;
      this.overlay.parentNode.removeChild(this.overlay);
      this.overlay = undefined;
    }
  }
  // Utility method to extend defaults with user options
  function extendDefaults(source, properties) {
    var property;
    for (property in properties) {
      if (properties.hasOwnProperty(property)) {
        source[property] = properties[property];
      }
    }
    return source;
  }
}());
'; ?>

</script>
<!-- END LOADER -->

<div class="alertify" id="alertify_mockup" style="display:none;">
  <div class="ajs-dimmer"></div>
  <div class="ajs-modal" tabindex="0">
    <div class="ajs-dialog" tabindex="0">
      <a class="ajs-reset" href="/#"></a>
      <div class="ajs-commands">
        <!-- <button class="ajs-pin"></button>
        <button class="ajs-maximize"></button>
        <button class="ajs-close"></button> -->
      </div>
      <div class="ajs-header"></div>
      <div class="ajs-body">
        <div class="ajs-content"></div>
      </div>
      <div class="ajs-footer">
        <div class="ajs-auxiliary ajs-buttons"></div>
        <div class="ajs-primary ajs-buttons">
          <button class="ajs-button ajs-ok"></button>
          <button class="ajs-button ajs-cancel"></button>
        </div>
        <div class="ajs-handle"></div>
      </div>
      <a class="ajs-reset" href="/#"></a>
    </div>
  </div>
</div>


<script type="text/javascript">
<?php echo '
var show_custom_alertify;
jQuery(document).ready(function($){
    function formatSelect (el) {
        var $element = $(el.element);
        if ($element.data(\'color\')) {
            return $(\'<span style="color:\' + $element.data(\'color\') + \'">\' + el.text + \'</span>\');
        } else {
            return el.text;
        }
        console.log(\'formatSelect triggered\');
    }

    if($(".select2").length){
        $(".select2").select2({
          templateResult: formatSelect
        });
    }

    show_custom_alertify = function (args){
        console.log(\'show_custom_alertify\');
        var alertify_clone = $(\'#alertify_mockup\').clone().removeAttr(\'id\');

        var defaults = {
          type: args.type!==undefined?args.type:\'alert\',
          title: args.title!==undefined?args.title:defaults.type,
          content: args.content!==undefined?args.content:\'args.content is empty\',
          buttons_text: {
            ok: args.buttons_text!==undefined&&args.buttons_text.ok!==undefined?args.buttons_text.ok:\'YES\',
            cancel: args.buttons_text!==undefined&&args.buttons_text.cancel!==undefined?args.buttons_text.cancel:\'NO\'
          },
          on_ok: function(){
            if(args.on_ok !== undefined){
              args.on_ok(alertify_clone);
            }
          },
          on_cancel: function(){
            if(args.on_cancel !== undefined){
              args.on_cancel(alertify_clone);
            }
          },
          on_load: function(){
            console.log(\'on_load\');
            if(args.on_load !== undefined){
              args.on_load(alertify_clone);
            }
          }
        }

        if(defaults.type == \'alert\'){
          alertify_clone.find(\'.ajs-ok\').text(defaults.buttons_text.ok);
          alertify_clone.find(\'.ajs-cancel\').hide();
        } else {
          alertify_clone.find(\'.ajs-ok\').text(defaults.buttons_text.ok);
          alertify_clone.find(\'.ajs-cancel\').show().text(defaults.buttons_text.cancel);
        }

        alertify_clone.on(\'click\', \'.ajs-ok\',function(){
          defaults.on_ok();
          alertify_clone.hide();
          setTimeout(function(){
            alertify_clone.remove();
          },500);
          return false;
        });
        alertify_clone.on(\'click\', \'.ajs-cancel\',function(){
          defaults.on_cancel();
          alertify_clone.hide();
          setTimeout(function(){
            alertify_clone.remove();
          },500);
          return false;
        });

        alertify_clone.find(\'.ajs-header\').html(defaults.title);
        alertify_clone.find(\'.ajs-content\').html(defaults.content);
        alertify_clone.show().appendTo(\'body\');

        defaults.on_load();
  }
});
'; ?>

</script>
<script>
<?php echo '
jQuery(document).ready(function($){
'; ?>


<?php $_from = $this->_tpl_vars['emp_messages']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['_message']):
?>
    show_custom_alertify({
        type: 'alert',
        title: "<?php echo $this->_tpl_vars['_message']->subj; ?>
",
        content: "<?php echo ((is_array($_tmp=((is_array($_tmp=$this->_tpl_vars['_message']->body)) ? $this->_run_mod_handler('nl2br', true, $_tmp) : smarty_modifier_nl2br($_tmp)))) ? $this->_run_mod_handler('escape', true, $_tmp, 'javascript') : smarty_modifier_escape($_tmp, 'javascript')); ?>
",
        buttons_text: {
            ok: 'READ and UNDERSTOOD'
        },
        on_ok: function() {
            read_msg(<?php echo $this->_tpl_vars['_message']->id; ?>
);
        }
    });
<?php endforeach; endif; unset($_from); ?>

    var loggedUsername = "<?php echo $this->_tpl_vars['loggedUser']->username; ?>
";
    var pushServer = "";
    if (window.location.protocol === 'http:')
        pushServer = "//<?php echo $_SERVER['HTTP_HOST']; ?>
:3000/";
    else
        pushServer = "//<?php echo $_SERVER['HTTP_HOST']; ?>
:3043/";

<?php echo '

    $(window).on(\'storage\', message_receive);

    // use local storage for messaging. Set message in local storage and clear it right away
    // This is a safe way how to communicate with other tabs while not leaving any traces
    //
    function message_broadcast(message)
    {
        localStorage.setItem(\'message\',JSON.stringify(message));
        localStorage.removeItem(\'message\');
    }

    // receive message
    //
    function message_receive(ev)
    {
        if (ev.originalEvent.key!=\'message\') return; // ignore other keys
        var message=JSON.parse(ev.originalEvent.newValue);
        if (!message) return; // ignore empty msg or msg reset

        console.log(message);

        // here you act on messages.
        // you can send objects like { \'command\': \'doit\', \'data\': \'abcd\' }
        if (message.command == \'logout\')
        {
            $(\'#timesheet_button\').val(\'LOG IN\').css({color: \'green\'});
            $(\'#timesheet_div\').html(\'Timestamp: <font color="red">OUT</font>\');
            $("#company_id").prop("disabled", false);
        }
        else if (message.command == \'relogin\')
        {
            $("#company_id").val( message.company_id ).prop("disabled", true);
            $(\'#timesheet_div\').html(\'Timestamp: <font color="green">IN</font> for <span>\' + message.worktime + \'</span>\');
            setLoginTimer();
        }
        else if (message.command == \'login\')
        {
            $("#company_id").val( message.company_id ).prop("disabled", true);
            $(\'#timesheet_button\').val(\'LOG OUT\').css({color:\'red\'});
            $(\'#timesheet_div\').html(\'Timestamp: <font color="green">IN</font> for <span>00:00:00</span>\');
            setLoginTimer();
        }
        // etc.
    }

    var permission,
        timer = setTimeout( function() { permission = "default" }, 500 );
    if ("Notification" in window) {
        Notification.requestPermission( function(state){ clearTimeout(timer); permission = state } );
    }

    setInterval(function() {
        var issetConnection = localStorage.getItem("connection");
        if (issetConnection == null || issetConnection < new Date().getTime() - 5000)
        {
            localStorage.setItem("connection", new Date().getTime());
            console.log("START SOCKET");
            socketOn();
        }
    }, 5000);

    function socketOn()
    {
        setInterval(function() {
            localStorage.setItem("connection", new Date().getTime());
        }, 2000);

        try {
            var socket = io(pushServer);
            socket.on("prolo-channel", function(message){
                message = jQuery.parseJSON(message);
                console.log(message);

                if (typeof message.message !== "undefined")
                {
                    if (message.message === "message")
                    {
                        for (message_id in message.recipients) {
                            if (message.recipients[message_id] === loggedUsername) {
                                show_custom_alertify({
                                    type: \'alert\',
                                    title: "Subject: " + message.subj + \'&nbsp;\'.repeat(30) + "From: " + message.user,
                                    content:  message.body.replace(/(?:\\r\\n|\\r|\\n)/g, \'<br />\'),
                                    buttons_text: {
                                        ok: \'READ and UNDERSTOOD\'
                                    },
                                    on_ok: function(){
                                        read_msg(message_id);
                                    }
                                });

                                var notifyOptions = {
                                    title: message.subj,
                                    body: message.body,
                                    icon: \'//www.prologistics.info/favicon.ico\',
                                    tag : \'message\'
                                };

                                if ("Notification" in window) {
                                    if (Notification.permission === "granted") {
                                        var notification = new Notification(message.subj, notifyOptions);
                                        notification.onclick = function(event) {
                                            event.preventDefault(); // prevent the browser from focusing the Notification\'s tab
                                            window.focus();
                                        };
                                    }
                                    else if (Notification.permission !== \'denied\') {
                                        Notification.requestPermission(function (permission) {
                                            if (permission === "granted") {
                                                var notification = new Notification(message.subj, notifyOptions);
                                                notification.onclick = function(event) {
                                                    event.preventDefault(); // prevent the browser from focusing the Notification\'s tab
                                                    window.focus();
                                                };
                                            }
                                        });
                                    }

                                }

                                break;
                            }
                        }
                    }
                    else if (message.message === "timestamp")
                    {
                        message_broadcast({\'command\': \'logout\'});

                        if ($(\'#timesheet_button\').val() !== \'LOG IN\') {
                            soundManager.createSound({
                                id: \'sounds_out\',
                                url: \'/_mp3/sounds_out.mp3\'
                            });
                            // ...and play it
                            soundManager.play(\'sounds_out\');
                        }

                        $(\'#timesheet_button\').val(\'LOG IN\').css({color: \'green\'});
                        $(\'#timesheet_div\').html(\'Timestamp: <font color="red">OUT</font>\');
                    }
                    else if (message.message === "relogin")
                    {
                        message_broadcast({\'command\': \'relogin\', \'company_id\': message.body.company_id, \'worktime\': message.body.worktime});

                        $("#company_id").val( message.body.company_id );
                        $(\'#timesheet_div\').html(\'Timestamp: <font color="green">IN</font> for <span>\' + message.body.worktime + \'</span>\');
                    }
                }
            });
        }
        catch(e){
            console.log(e);
        }
    }

    function emp_monitor(id) {
        $.ajax({
            url: \'/monitor/\'+id,
            success: function(data) {
                if (data)
                {
                    show_custom_alertify({
                        type: \'alert\',
                        title: \'Timesheet monitor\',
                        content: data.replace(/(?:\\r\\n|\\r|\\n)/g, \'<br />\'),
                        buttons_text:{
                            ok: \'Ok\'
                        },
                        on_ok: function(el){
                            setTimeout(function(){emp_monitor('; ?>
<?php echo $this->_tpl_vars['loggedUser']->id; ?>
<?php echo ')},10000);
                        },
                        on_load: function(el){
                            JsHttpRequest.query(\'/js_backend.php\', {
                                fn: \'clear_monitor\',
                            }, function(result, errors) {
                            }, true);
                        }
                    });
                }
                else
                {
                    setTimeout(function(){emp_monitor('; ?>
<?php echo $this->_tpl_vars['loggedUser']->id; ?>
<?php echo ')},10000);
                }
            },
            cache: false
        });
    }

    '; ?>

        <?php if ($this->_tpl_vars['user_has_monitored_employees']): ?>
            emp_monitor(<?php echo $this->_tpl_vars['loggedUser']->id; ?>
);
        <?php endif; ?>
    <?php echo '

    function read_msg(id) {
        JsHttpRequest.query(\'/js_backend.php\', {
                fn: \'emp_msg_read\',
                id: id
            }, function(result, errors) {
            }, true);
    }

    function timesheet(working_place_id, warehouse_id) {
        var button = $(\'#timesheet_button\');
        JsHttpRequest.query(\'/js_backend.php\', {
                // pass a text value
                fn: \'timesheet\',
                company_id: $(\'#company_id\').val(),
                working_place_id: working_place_id,
                warehouse_id: warehouse_id,
                button: $(\'#timesheet_button\').val()
            }, function(result, errors) {
                // Enable Login/Logout button
                $(\'#timesheet_button\').prop("disabled", false);

                if (result.res0)
                {
                    alert(\'You cannot login to this company, pls reload the page\');
                }
                else
                {
                    if ($(\'#timesheet_button\').val()==\'LOG IN\') {
                        $(\'#company_id\').prop("disabled", true);
                        $(\'#timesheet_button\').val(\'LOG OUT\').css({color:\'red\'});
                        $(\'#timesheet_div\').html(\'Timestamp: <font color="green">IN</font> for <span></span>\');

                        soundManager.createSound({
                            id: \'sounds_in\',
                            url: \'/_mp3/sounds_in.mp3\'
                        });
                        // ...and play it
                        soundManager.play(\'sounds_in\');

                        message_broadcast({\'command\': \'login\', \'company_id\': $(\'#company_id\').val()});
                    }
                    else
                    {
                        $(\'#company_id\').prop("disabled", false);
                        $(\'#timesheet_button\').val(\'LOG IN\').css({color:\'green\'});
                        $(\'#timesheet_div\').html(\'Timestamp: <font color="red">OUT</font>\');
                        soundManager.createSound({
                            id: \'sounds_out\',
                            url: \'/_mp3/sounds_out.mp3\'
                        });
                        // ...and play it
                        soundManager.play(\'sounds_out\');

                        message_broadcast({\'command\': \'logout\'});
                    }

                    $(\'#timesheet_button\').prop("disabled", false);
                    setLoginTimer();
                }
            }, true);
    }

    $(document).on(\'click\',\'#timesheet_button\', function(){
        $(this).prop("disabled", true);

        if ($(this).val() == \'LOG OUT\')
        {
            timesheet();
        }
        else
        {
            var company_id = $(\'#company_id\').val();
            JsHttpRequest.query(\'/js_backend.php\', {
                    fn: \'get_working_place\',
                    company_id: company_id
                }, function(result, errors) {
                    var html_options = [];
                    for (var id in result.res) {
                        html_options.push( \'<option value="\'+id+\'">\'+result.res[id]+\'</option>\' );
                    }
                    html_options.join(\'\');
                    var select_country = \'<select name="select_country" id="select_country">\'+ html_options +\'</select>\';
                    show_custom_alertify({
                        type: \'confirm\',
                        title: \'Login Confirmation\',
                        content: \'<div class="clf">Do you really want to login to \' + $(\'#companies\'+company_id).val() + \'</div>\'
                            '; ?>

                                <?php if (! $this->_tpl_vars['loggedEmp']->ask4ware): ?>
                                    + '<div class="select_country" style="color:red;padding:10px 0;">Please, choose your working place: '+select_country+'</div>'
                                <?php endif; ?>
                            <?php echo '
                            + $(\'#warehouses4emp\').val(),
                        on_load: function(el) {
                            $(el).find(\'#select_country option\').eq(0).attr(\'selected\',\'selected\');
                            // Enable Login/Logout button
                            $(\'#timesheet_button\').prop("disabled", false);
                        },
                        on_ok: function(el){
                            var selected_val = $(el).find(\'#select_country option:selected\').val();
                            '; ?>

                                <?php if ($this->_tpl_vars['loggedEmp']->ask4ware): ?>
                                    var selected_warehouse = $(el).find('#select_warehouse option:selected').val();
                                <?php else: ?>
                                    var selected_warehouse = '';
                                <?php endif; ?>
                            <?php echo '
                            timesheet(selected_val, selected_warehouse);
                        }
                    });
                }, true);
        }
    });

    var intervalLoginTimer = false;
    function setLoginTimer() {

        var addZero = function (value) {
            return (value < 10) ? \'0\' + value : value;
        };

        var timeToSeconds = function (time) {
            time = time.split(/:/);
            return parseInt(time[0], 10) * 3600 + parseInt(time[1], 10) * 60 + parseInt(time[2], 10);
        };

        if (intervalLoginTimer) {
            clearInterval(intervalLoginTimer);
        }

        if ( ! $("#timesheet_div span").length) {
            return;
        }

        var startDate = (new Date()).getTime(),
                startValue = timeToSeconds($("#timesheet_div span").html());

        startValue = isNaN(startValue) || startValue < 1 ? 0 : startValue;

        function showTimer() {
            var _currValue = startValue + ((new Date()).getTime() - startDate) / 1000,
                _currValueText = addZero( Math.floor( _currValue / 3600 ) ) + ":" + addZero( Math.floor( (_currValue - Math.floor( _currValue / 3600 ) * 3600) / 60 ) )  + ":" + addZero( Math.floor( _currValue % 60 ) );

            $("#timesheet_div span").html(_currValueText);
        }

        intervalLoginTimer = setInterval(showTimer, 500);
    }

    setLoginTimer();
});
'; ?>

</script>
<table width="100%">
<tr>

<?php if (! $this->_tpl_vars['nomenu']): ?>
<td style="vertical-align:top; width:160px" class="leftSideMenu" nowrap
<?php  if ($_SERVER['HTTP_HOST']!='prologistics.info' && $_SERVER['HTTP_HOST']!='www.prologistics.info') echo 'bgcolor="#FFFFAA"'; ?>
>
<a href="/logout.php"><b>Logout <?php echo $this->_tpl_vars['loggedUser']->name; ?>
</b></font></a><br>
<!--<b><a href="/http://app.ess.ch/tudu/secure/showTodos.action" target="_new" <?php echo smarty_function_checkPermission(array('filename' => "users.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Todo list</a></b><br>-->
<b><a href="/loginchpw.php">Change PW</a></b><br>
<a href="/page_forlog.php" <?php echo smarty_function_checkPermission(array('filename' => "page_forlog.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Page log settings</a><br>
<a href="/acl_php.php" <?php echo smarty_function_checkPermission(array('filename' => "acl_php.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Access control settings</a><br>
<a href="/role.php" <?php echo smarty_function_checkPermission(array('filename' => "role.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Access control plans</a><br>
<a href="/log_triggers.php" <?php echo smarty_function_checkPermission(array('filename' => "log_triggers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Log settings</a><br>
<a href="/email_templates.php" <?php echo smarty_function_checkPermission(array('filename' => "email_templates.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Email templates</a><br>
<a href="/email_template_layout.php" <?php echo smarty_function_checkPermission(array('filename' => "email_template_layout.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Email template layout</a><br>
<a href="/gallerylib.php" <?php echo smarty_function_checkPermission(array('filename' => "gallerylib.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Image library</a><br>
<a href="/users.php" >Users accounts</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/user.php" >New user</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/users.php?deleted=1" <?php echo smarty_function_checkPermission(array('filename' => "users.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inactive users</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/statistics.php" <?php echo smarty_function_checkPermission(array('filename' => "statistics.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Statistics</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/generate_login_token.php" <?php echo smarty_function_checkPermission(array('filename' => "generate_login_token.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Generate login token</a><br>
<script language="JavaScript" src="/JsHttpRequest/JsHttpRequest.js"></script>

<script type="text/javascript" src="/js/bootbox.min.js"></script>
<script type="text/javascript" src="/js/socket.io.js"></script>

<script type="text/javascript" src="/js/select2/select2.full.min.js"></script>
<link rel="stylesheet" type="text/css" href="/css/select2/select2.css" />

<input type="hidden" id="warehouses4emp" value='<?php if ($this->_tpl_vars['loggedEmp']->ask4ware): ?><div class="select_country" style="color:red;padding:10px 0;">Please, choose your warehouse: <select name="select_warehouse" id="select_warehouse"><?php echo smarty_function_html_options(array('options' => $this->_tpl_vars['warehouses2shipNames'],'nonewline' => '1'), $this);?>
</select></div><?php endif; ?>'/>


<?php if ($this->_tpl_vars['loggedUser']->blocks['Time_stamp'] || $this->_tpl_vars['loggedUser']->admin): ?>

<?php if ($this->_tpl_vars['loggedUser']->timestamped): ?>
  <div id="timesheet_div">Timestamp: <font color="green">IN</font> for <span><?php echo $this->_tpl_vars['loggedUser']->timestamped_time; ?>
</span> </div>
  <input id="timesheet_button" type="button" value="LOG OUT" style="color:red"/><br>
<?php else: ?>
  <div id="timesheet_div">Timestamp: <font color="red">OUT</font></div>
  <input id="timesheet_button" type="button" value="LOG IN" style="color:green" <?php if (! $this->_tpl_vars['loggedUser']->companies): ?>disabled<?php endif; ?>/>
  <br>
<?php endif; ?>

Company <select id="company_id" <?php if ($this->_tpl_vars['loggedUser']->timestamped): ?>disabled<?php endif; ?>>
  <?php echo smarty_function_html_options(array('options' => $this->_tpl_vars['loggedUser']->companies,'selected' => $this->_tpl_vars['loggedUser']->timestamped_company_id), $this);?>

</select><br>
<?php $_from = $this->_tpl_vars['loggedUser']->companies; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['company_id'] => $this->_tpl_vars['company_name']):
?>
<input type="hidden" id="companies<?php echo $this->_tpl_vars['company_id']; ?>
" value="<?php echo $this->_tpl_vars['company_name']; ?>
"/>
<?php endforeach; endif; unset($_from); ?>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/timestamp_manual.php" <?php echo smarty_function_checkPermission(array('filename' => "timestamp_manual.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Manual time stamps</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/timestamp_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "timestamp_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Time stamp setting</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/timestamp_filter.php" <?php echo smarty_function_checkPermission(array('filename' => "timestamp_filter.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Time stamp filter</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/emp_vacation_sickness.php" <?php echo smarty_function_checkPermission(array('filename' => "emp_vacation_sickness.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Vacation and Sickness</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/reports_page/hr_reports/" <?php echo smarty_function_checkPermission(array('filename' => "react/reports_page",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>HR Reports</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/companies.php" <?php echo smarty_function_checkPermission(array('filename' => "companies.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Time stamp company</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/holi_plans.php" <?php echo smarty_function_checkPermission(array('filename' => "holi_plans.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Public holidays plans</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/work_plan.php" <?php echo smarty_function_checkPermission(array('filename' => "work_plan.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Working plans</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/limiter.php" <?php echo smarty_function_checkPermission(array('filename' => "limiter.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Limiter settings</a><br>
<?php endif; ?>
<a href="/sellers.php" <?php echo smarty_function_checkPermission(array('filename' => "sellers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Sellers accounts</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/sellerinfo.php" <?php echo smarty_function_checkPermission(array('filename' => "sellerinfo.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New seller</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/seller_sources.php" <?php echo smarty_function_checkPermission(array('filename' => "seller_sources.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Source seller</a><br>
<a href="/purge.php" <?php echo smarty_function_checkPermission(array('filename' => "purge.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Purge</a><br>
<a href="/recache_log.php" <?php echo smarty_function_checkPermission(array('filename' => "recache_log.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Cache log</a><br>
<a href="/configure.php" <?php echo smarty_function_checkPermission(array('filename' => "configure.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Configure</a><br>
<a href="/slaves.php" <?php echo smarty_function_checkPermission(array('filename' => "slaves.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Configure slave servers</a><br>
<a href="/sync_database.php" <?php echo smarty_function_checkPermission(array('filename' => "sync_database.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Sync tables with production</a><br>
<a href="/articles.php" <?php echo smarty_function_checkPermission(array('filename' => "articles.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Articles</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/article.php" <?php echo smarty_function_checkPermission(array('filename' => "article.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New article</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/article_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "article_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Article settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/stock_values.php" <?php echo smarty_function_checkPermission(array('filename' => "stock_values.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Stock value</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/export_paid_articles.php" <?php echo smarty_function_checkPermission(array('filename' => "export_paid_articles.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Export paid articles</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/articles_cons.php" <?php echo smarty_function_checkPermission(array('filename' => "articles_cons.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Consolidate articles</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/article_cons.php" <?php echo smarty_function_checkPermission(array('filename' => "article_cons.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New consolidate</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/articles.php?dead=1" <?php echo smarty_function_checkPermission(array('filename' => "articles.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Article EOL</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/articles_kpi.php" <?php echo smarty_function_checkPermission(array('filename' => "articles_kpi.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Articles KPI</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/articles_kpi_log.php" <?php echo smarty_function_checkPermission(array('filename' => "articles_kpi_log.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Articles KPI Log</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/articles_tr.php" <?php echo smarty_function_checkPermission(array('filename' => "articles_tr.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Article translation</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/avgstock_log.php" <?php echo smarty_function_checkPermission(array('filename' => "avgstock_log.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Article average stock log</a><br>
<a href="/sa_inventory.php" <?php echo smarty_function_checkPermission(array('filename' => "sa_inventory.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inventory SA</a><br>
<a href="/stock_takes.php" <?php echo smarty_function_checkPermission(array('filename' => "stock_takes.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inventory Stock Take</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/stock_take.php" <?php echo smarty_function_checkPermission(array('filename' => "stock_take.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New Stock Take</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/avgstock_log.php" <?php echo smarty_function_checkPermission(array('filename' => "avgstock_log.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inventory Stock Log</a><br>
<a href="/equip.php" <?php echo smarty_function_checkPermission(array('filename' => "equip.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Office equipment</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/eq_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "eq_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Office equipment settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/fork_lift.php" <?php echo smarty_function_checkPermission(array('filename' => "fork_lift.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Fork lift</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/fork_lift_statistics.php" <?php echo smarty_function_checkPermission(array('filename' => "fork_lift_statistics.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Fork lift statistics</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/soft.php" <?php echo smarty_function_checkPermission(array('filename' => "soft.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Software settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/imap.php" <?php echo smarty_function_checkPermission(array('filename' => "imap.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>IMAP settings</a><br>
<a href="/produce_labels.php" <?php echo smarty_function_checkPermission(array('filename' => "produce_labels.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Produce labels</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/produce_label.php?id=0" <?php echo smarty_function_checkPermission(array('filename' => "produce_label.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New label</a><br>
<a href="/offers.php" <?php echo smarty_function_checkPermission(array('filename' => "offers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Offers</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/offer.php" <?php echo smarty_function_checkPermission(array('filename' => "offer.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New offer</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/offers.php?old=1" <?php echo smarty_function_checkPermission(array('filename' => "offers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Old offers</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/offers.php?hidden=1" <?php echo smarty_function_checkPermission(array('filename' => "offers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Hidden offers</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/offer_details.php" <?php echo smarty_function_checkPermission(array('filename' => "offer_details.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Offer details</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/base_groups.php" <?php echo smarty_function_checkPermission(array('filename' => "base_groups.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Basegroups</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/base_groups.php?deleted=1" <?php echo smarty_function_checkPermission(array('filename' => "base_groups.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inactive Basegroups</a><br>
<a href="/warehouses.php" <?php echo smarty_function_checkPermission(array('filename' => "warehouses.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Warehouses</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/warehouses_page/dashboard/" <?php echo smarty_function_checkPermission(array('filename' => "react/warehouses_page",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Warehouse dashboard</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/logs/issue_logs/" <?php echo smarty_function_checkPermission(array('filename' => "react/logs",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Issue list</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/logs/issue_logs_settings/" <?php echo smarty_function_checkPermission(array('filename' => "react/logs",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Issue settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/ware_statistics.php" <?php echo smarty_function_checkPermission(array('filename' => "ware_statistics.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Warehouse Statistics</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/settings_page/warehouse_statistics/" <?php echo smarty_function_checkPermission(array('filename' => "react/settings_page",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Warehouse statistics settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/ware_locs.php" <?php echo smarty_function_checkPermission(array('filename' => "ware_locs.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Warehouse Location</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/loading_area.php" <?php echo smarty_function_checkPermission(array('filename' => "loading_area.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Loading area</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/ship2ware.php" <?php echo smarty_function_checkPermission(array('filename' => "ship2ware.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Warehouse distance setting</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/warehouse.php" <?php echo smarty_function_checkPermission(array('filename' => "warehouse.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New warehouse</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/understock.php" <?php echo smarty_function_checkPermission(array('filename' => "understock.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Understock page</a><br>

&nbsp;&nbsp;&nbsp;&nbsp;<a href="/ware2ware.php" <?php echo smarty_function_checkPermission(array('filename' => "ware2ware.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Warehouse-to-warehouse</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/ware2ware_list.php" <?php echo smarty_function_checkPermission(array('filename' => "ware2ware_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>WWO list</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/ware2ware_list.php?inactive=1" <?php echo smarty_function_checkPermission(array('filename' => "ware2ware_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inactive WWO list</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/ware2ware_order.php" <?php echo smarty_function_checkPermission(array('filename' => "ware2ware_order.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New WWO</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/wwo_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "wwo_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>WWO settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/wwo_forecast.php" <?php echo smarty_function_checkPermission(array('filename' => "wwo_forecast.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>WWO Forecast</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/ats_list.php" <?php echo smarty_function_checkPermission(array('filename' => "ats_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>ATS list</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/ats.php" <?php echo smarty_function_checkPermission(array('filename' => "ats.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New ATS</a><br>
<a href="/accounts.php" <?php echo smarty_function_checkPermission(array('filename' => "accounts.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Accounts</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/account.php" <?php echo smarty_function_checkPermission(array('filename' => "account.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New account</a><br>
<a href="/methods.php" <?php echo smarty_function_checkPermission(array('filename' => "methods.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shipping methods</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/shipping_pages/shipping_method_statistics/" <?php echo smarty_function_checkPermission(array('filename' => "react/shipping_pages",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shipping method statistics </a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/method.php" <?php echo smarty_function_checkPermission(array('filename' => "method.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New shipping method</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/tn_packet.php" <?php echo smarty_function_checkPermission(array('filename' => "tn_packet.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Parcel type</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/parcel_barcodes_manual.php" <?php echo smarty_function_checkPermission(array('filename' => "parcel_barcodes_manual.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Parcel type barcode creator</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/parcel_barcodes.php" <?php echo smarty_function_checkPermission(array('filename' => "parcel_barcodes.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Parcel type barcodes</a><br>

<a href="/shipping_plans.php" <?php echo smarty_function_checkPermission(array('filename' => "shipping_plans.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shipping plans</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shipping_plan.php" <?php echo smarty_function_checkPermission(array('filename' => "shipping_plan.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New shipping plan</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shipping_plans.php?deleted=1" <?php echo smarty_function_checkPermission(array('filename' => "shipping_plans.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shipping plans EOL</a><br>
<a href="/shipping_costs.php" <?php echo smarty_function_checkPermission(array('filename' => "shipping_costs.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shipping prices</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shipping_cost.php" <?php echo smarty_function_checkPermission(array('filename' => "shipping_cost.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New shipping price list</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shipping_costs.php?inactive=1" <?php echo smarty_function_checkPermission(array('filename' => "shipping_costs.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inactive Shipping prices</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/logs/shipping_price_monitor/"  <?php echo smarty_function_checkPermission(array('filename' => "react/logs",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Monitored shipping prices</a><br>
<a href="/search.php?express" <?php echo smarty_function_checkPermission(array('filename' => "search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
><font color="#009900"><b>Auftrag search express</b></font></a><br>
<a href="/search.php" <?php echo smarty_function_checkPermission(array('filename' => "search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
><b>Auftrag search</b></a><br>
<a href="/barcodes.php" <?php echo smarty_function_checkPermission(array('filename' => "barcodes.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
><b>Barcodes search</b></a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/barcode_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "barcode_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Barcodes settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/barcodes_manual.php" <?php echo smarty_function_checkPermission(array('filename' => "barcodes_manual.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Barcode creator</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/search.php?what=no_barcodes" <?php echo smarty_function_checkPermission(array('filename' => "search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>No barcode list</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/barcode_inventories.php" <?php echo smarty_function_checkPermission(array('filename' => "search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Barcode inventory</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/barcode_statistics.php" <?php echo smarty_function_checkPermission(array('filename' => "search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Barcode statistics</a><br>
<a href="/search_cfg.php" <?php echo smarty_function_checkPermission(array('filename' => "search_cfg.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Search configuration</a><br>
<a href="/rating.php" <?php echo smarty_function_checkPermission(array('filename' => "rating.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Rating</a><br>
<a href="/vat.php" <?php echo smarty_function_checkPermission(array('filename' => "vat.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>VAT</a><br>
<a href="/vat_state.php" <?php echo smarty_function_checkPermission(array('filename' => "vat_state.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>States</a><br>
<a href="/eco_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "eco_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Eco-Taxe</a><br>
<a href="/plz2city.php" <?php echo smarty_function_checkPermission(array('filename' => "plz2city.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Zip to city</a><br>
<a href="/calcs.php" <?php echo smarty_function_checkPermission(array('filename' => "calcs.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Calculations</a><br>
<a href="/refunds_report.php" <?php echo smarty_function_checkPermission(array('filename' => "refunds_report.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Refunds</a><br>
<a href="/calcs_offers.php" <?php echo smarty_function_checkPermission(array('filename' => "calcs_offers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Offers Calculations</a><br>
<a href="/export-last-orders.php" <?php echo smarty_function_checkPermission(array('filename' => "export-last-orders.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Export last orders</a><br>
<a href="/export.php" <?php echo smarty_function_checkPermission(array('filename' => "export.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Export payments</a><br>
<a href="/export_pdf.php" <?php echo smarty_function_checkPermission(array('filename' => "export_pdf.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Export invoices</a><br>
<a href="/export_invoice.php" <?php echo smarty_function_checkPermission(array('filename' => "export_invoice.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Export invoices (CSV)</a><br>
<a href="/export_pdf.php?pack=1" <?php echo smarty_function_checkPermission(array('filename' => "export_pdf.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Export packing list</a><br>
<a href="/tn_monitor.php" <?php echo smarty_function_checkPermission(array('filename' => "tn_monitor.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Monitor tracking numbers</a><br>
<a href="/export_rma.php" <?php echo smarty_function_checkPermission(array('filename' => "export_rma.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Export Ticket cases</a><br>
<a href="/export_rma_offer.php" <?php echo smarty_function_checkPermission(array('filename' => "export_rma_offer.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Export Ticket cases (offer based)</a><br>
<a href="/rma_pics.php" <?php echo smarty_function_checkPermission(array('filename' => "rma_pics.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Ticket picture search</a><br>
<a href="/rma_sell_chanels.php" <?php echo smarty_function_checkPermission(array('filename' => "rma_sell_chanels.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Ticket Sales Channels</a><br>
<a href="/fix_acc.php" <?php echo smarty_function_checkPermission(array('filename' => "fix_acc.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Fix account numbers</a><br>
<br>
<a href="/shipping_search.php?what=shipping_username" <?php echo smarty_function_checkPermission(array('filename' => "shipping_search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>My Shipping orders</a><br>
<!--&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shipping_search.php?what=shipping_username&shipping_mode=1" <?php echo smarty_function_checkPermission(array('filename' => "users.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shipping orders</a><br>-->
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/picking_list.php" <?php echo smarty_function_checkPermission(array('filename' => "picking_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Picking list</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/total_cycle_time.php" <?php echo smarty_function_checkPermission(array('filename' => "total_cycle_time.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Total cycle time</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/routes.php" <?php echo smarty_function_checkPermission(array('filename' => "routes.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Routes</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/routes.php?deleted=1" <?php echo smarty_function_checkPermission(array('filename' => "routes.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inactive Routes</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/search.php?what=driver_tasks" <?php echo smarty_function_checkPermission(array('filename' => "search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Driver tasks</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/route_task.php" <?php echo smarty_function_checkPermission(array('filename' => "route_task.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New task</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shipping_order_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "shipping_order_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shipping order settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/cars.php" <?php echo smarty_function_checkPermission(array('filename' => "cars.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Car settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/calendar.php" <?php echo smarty_function_checkPermission(array('filename' => "calendar.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Cars calendar</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/route_stat.php" <?php echo smarty_function_checkPermission(array('filename' => "route_stat.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Routes statistics</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/driver_search.php" <?php echo smarty_function_checkPermission(array('filename' => "driver_search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Driver search</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/sms_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "sms_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>SMS settings</a><br>
<!--&nbsp;&nbsp;&nbsp;&nbsp;<a href="/truck_route.php" <?php echo smarty_function_checkPermission(array('filename' => "truck_route.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Truck route planning</a><br>-->
<br>
<a href="/articles.php?shipping_username=" <?php echo smarty_function_checkPermission(array('filename' => "articles.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>My Inventory</a><br>
<a href="/search.php?what=ins_responsible_username" <?php echo smarty_function_checkPermission(array('filename' => "search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>My Insurance cases</a><br>
<br>
<a href="/packed_scan.php" <?php echo smarty_function_checkPermission(array('filename' => "packed_scan.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Parcel packed scan</a><br>
<a href="/parcel_shipping_list.php" <?php echo smarty_function_checkPermission(array('filename' => "parcel_shipping_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Parcel shipping list</a><br>
<a href="/shipping_refund_list.php" <?php echo smarty_function_checkPermission(array('filename' => "shipping_refund_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Make shipping refund list</a><br>
<a href="/orphaned.php?active=1" <?php echo smarty_function_checkPermission(array('filename' => "orphaned.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Active auctions</a><br>
<a href="/orphaned.php" <?php echo smarty_function_checkPermission(array('filename' => "orphaned.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Unassigned auctions</a><br>
<a href="/custominvoice.php" <?php echo smarty_function_checkPermission(array('filename' => "custominvoice.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
><font color="brown"><b>New invoice</b></font></a><br>
<a href="/massinvoice.php" <?php echo smarty_function_checkPermission(array('filename' => "massinvoice.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Mass invoice</a><br>
<a href="/marketing_camps.php" <?php echo smarty_function_checkPermission(array('filename' => "marketing_camps.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Marketing Campaigns</a><br>
<a href="/push_notifications.php" <?php echo smarty_function_checkPermission(array('filename' => "push_notifications.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>PUSH Notifications</a><br>
<a href="/customers.php" <?php echo smarty_function_checkPermission(array('filename' => "customers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Send newsletter</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/news_emails.php" <?php echo smarty_function_checkPermission(array('filename' => "news_emails.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Newsletter templates</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/spam_plan.php" <?php echo smarty_function_checkPermission(array('filename' => "spam_plan.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Newsletter Planning</a><br>
<!--&nbsp;&nbsp;&nbsp;&nbsp;<a href="/customer_pars.php" <?php echo smarty_function_checkPermission(array('filename' => "users.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Newsletter settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/customer_par.php" <?php echo smarty_function_checkPermission(array('filename' => "users.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New newsletter settings</a><br>-->
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/customer.php?id=0" <?php echo smarty_function_checkPermission(array('filename' => "customer.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New Customer</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shop_promo_partners.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_promo_partners.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Newsletter Partners</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/newsmail_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "newsmail_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Newsletter Settings</a><br>
<a href="/proxies.php" <?php echo smarty_function_checkPermission(array('filename' => "proxies.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Proxies</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/proxy_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "proxy_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Proxy settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/autoread.php" <?php echo smarty_function_checkPermission(array('filename' => "autoread.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Read auctions</a><br>
<a href="/customer_search.php" <?php echo smarty_function_checkPermission(array('filename' => "customer_search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Customer/Journalist search</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/jour_topics.php" <?php echo smarty_function_checkPermission(array('filename' => "jour_topics.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Journalist topics</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/customer_type_set.php" <?php echo smarty_function_checkPermission(array('filename' => "customer_type_set.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Import bounce mails</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/source_list.php" <?php echo smarty_function_checkPermission(array('filename' => "source_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Source list</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/customer_upload.php" <?php echo smarty_function_checkPermission(array('filename' => "customer_upload.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Import mail adresses</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/customer_stat.php?filter[src][shop]=shop&filter[src][auction]=auction&filter[lang]=&filter[shop_id]=&filter[username]=&filter[date_from]=&filter[date_to]=&filter[days]=99999&filter[group]=shop&filterbtn=Filter" <?php echo smarty_function_checkPermission(array('filename' => "customer_stat.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Customer statistics</a><br>
<a href="/shop_unfinished.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_unfinished.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Unfinished orders tool</a><br>
<a href="/firm_search.php" <?php echo smarty_function_checkPermission(array('filename' => "firm_search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Firm search</a><br>
<a href="/sa_price.php" <?php echo smarty_function_checkPermission(array('filename' => "sa_price.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>SA Price Calculation</a><br>
<a href="/saved_details.php" <?php echo smarty_function_checkPermission(array('filename' => "saved_details.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>SA Details</a><br>
<a href="/saved_available.php" <?php echo smarty_function_checkPermission(array('filename' => "saved_available.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>SA availability settings</a><br>
<a href="/saved.php?channel=0" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
><font color="brown"><b>Saved auctions: ALL</b></font></a><br>
<a href="/saved.php?channel=1" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
><font color="brown"><b>Saved auctions: eBay</b></font></a><br>
<a href="/saved.php?channel=2" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
><font color="brown"><b>Saved auctions: Ricardo</b></font></a><br>
<a href="/saved.php?channel=3" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
><font color="brown"><b>Saved auctions: Amazon</b></font></a><br>
<a href="/saved.php?channel=4" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
><font color="brown"><b>Saved auctions: Shop</b></font></a><br>
<a href="/saved.php?channel=5" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
><font color="brown"><b>Saved auctions: Allegro</b></font></a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/condensed/list/" <?php echo smarty_function_checkPermission(array('filename' => "react/condensed",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Consolidate SA</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/condensed/condensed_sa/0/" <?php echo smarty_function_checkPermission(array('filename' => "react/condensed",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Create new consolidate SA</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/autodescription/sa_types/" <?php echo smarty_function_checkPermission(array('filename' => "react/autodescription",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>SA Types</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/autodescription/sa_fields/" <?php echo smarty_function_checkPermission(array('filename' => "react/autodescription",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>SA Fields</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/autodescription/sa_contents/" <?php echo smarty_function_checkPermission(array('filename' => "react/autodescription",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>SA Contents</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/react/sa_composer/" <?php echo smarty_function_checkPermission(array('filename' => "react/sa_composer",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>SA Template</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/sa_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "sa_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>SA settings</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/newauction.php" <?php echo smarty_function_checkPermission(array('filename' => "newauction.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New saved auction</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/saved.php?channel=1&old=1" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Old Saved auctions: eBay</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/saved.php?channel=2&old=1" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Old Saved auctions: Ricardo</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/saved.php?channel=3&old=1" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Old Saved auctions: Amazon</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/saved.php?channel=4&old=1" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Old Saved auctions: Shop</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/saved.php?channel=5&old=1" <?php echo smarty_function_checkPermission(array('filename' => "saved.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Old Saved auctions: Allegro</a><br>
<a href="/sa_csv_list.php" <?php echo smarty_function_checkPermission(array('filename' => "sa_csv_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>CSV configuration tool</a><br>
<a href="/sa_custom_pars.php" <?php echo smarty_function_checkPermission(array('filename' => "sa_custom_pars.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>SA Custom parameters</a><br>
<a href="/sa_manda_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "sa_manda_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Field-checking of SA pages</a><br>
<a href="/sa_colors.php" <?php echo smarty_function_checkPermission(array('filename' => "sa_colors.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Colors</a><br>
<a href="/sa_materials.php" <?php echo smarty_function_checkPermission(array('filename' => "sa_materials.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Materials</a><br>
<a href="/suspend.php" <?php echo smarty_function_checkPermission(array('filename' => "suspend.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Suspend dates</a><br>
<a href="/translate.php" <?php echo smarty_function_checkPermission(array('filename' => "translate.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Translation</a><br>
<a href="/ebay_systems.php" <?php echo smarty_function_checkPermission(array('filename' => "ebay_systems.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>eBay system API settings</a><br>
<a href="/payment_methods.php" <?php echo smarty_function_checkPermission(array('filename' => "payment_methods.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Payment methods</a><br>
<a href="/payment_methods_stat.php" <?php echo smarty_function_checkPermission(array('filename' => "payment_methods.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Payment methods statistics</a><br>
<a href="/react/settings_page/bank_settings/" <?php echo smarty_function_checkPermission(array('filename' => "react/settings_page",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Bank settings</a><br>
<a href="/react/settings_page/booking_settings/" <?php echo smarty_function_checkPermission(array('filename' => "react/settings_page",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Booking methods setting</a><br>
<a href="/react/settings_page/import_payments/" <?php echo smarty_function_checkPermission(array('filename' => "react/settings_page",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Import payments</a><br>
<a href="/countries.php" <?php echo smarty_function_checkPermission(array('filename' => "countries.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Countries</a><br>
<a href="/country_settings_values.php?name=site_id" <?php echo smarty_function_checkPermission(array('filename' => "country_settings_values.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Country site settings</a><br>
<a href="/zip_ranges.php" <?php echo smarty_function_checkPermission(array('filename' => "zip_ranges.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Country ZIP ranges</a><br>
<a href="/fbackup.php" <?php echo smarty_function_checkPermission(array('filename' => "fbackup.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Full system backup settings</a><br>
<a href="/stop_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "stop_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Emergency Stop settings</a><br>
<br>
<a href="/employees.php" <?php echo smarty_function_checkPermission(array('filename' => "employees.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Employees list</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/emp_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "emp_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Employee settings</a><br>
<a href="/emp_message.php" <?php echo smarty_function_checkPermission(array('filename' => "emp_message.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Employee information system</a><br>
<a href="/emp_tel_cost.php" <?php echo smarty_function_checkPermission(array('filename' => "emp_tel_cost.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Enter telephone costs</a><br>
<a href="/emp_tel_cost_stat.php" <?php echo smarty_function_checkPermission(array('filename' => "emp_tel_cost_stat.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Show telephone costs</a><br>
<a href="/penalty.php" <?php echo smarty_function_checkPermission(array('filename' => "penalty.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Employees penalties</a><br>
<br>
<a href="/op_search.php" <?php echo smarty_function_checkPermission(array('filename' => "op_search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Order search</a><br>
<a href="/opt_list.php" <?php echo smarty_function_checkPermission(array('filename' => "opt_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Order planing tool</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/opt_list.php?inactive=1" <?php echo smarty_function_checkPermission(array('filename' => "opt_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inactive Order planing tool </a><br>
<a href="/op_sheet.php?mode=current" <?php echo smarty_function_checkPermission(array('filename' => "op_sheet.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Order process sheet</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/op_order.php" <?php echo smarty_function_checkPermission(array('filename' => "op_order.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New order</a><br>
<a href="/op_companies.php" <?php echo smarty_function_checkPermission(array('filename' => "op_companies.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Assign articles to supplier</a><br>
<a href="/op_suppliers.php" <?php echo smarty_function_checkPermission(array('filename' => "op_suppliers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Suppliers</a><br>
<a href="/inspection_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "inspection_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inspection settings</a><br>
<a href="/qc_search.php" <?php echo smarty_function_checkPermission(array('filename' => "qc_search.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>QC numbers</a><br>
<!--&nbsp;&nbsp;&nbsp;&nbsp;<a href="/op_suppliers.php?old=1" <?php echo smarty_function_checkPermission(array('filename' => "users.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Old suppliers</a><br>-->
<a href="/packing_docs.php" <?php echo smarty_function_checkPermission(array('filename' => "packing_docs.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Packing instructions</a><br>
<a href="/op_shcompanies.php" <?php echo smarty_function_checkPermission(array('filename' => "op_shcompanies.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shipping companies</a><br>
<a href="/op_categories.php" <?php echo smarty_function_checkPermission(array('filename' => "op_categories.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Assign articles to category</a><br>
<a href="/op_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "op_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Order settings</a><br>
<a href="/custom_numbers.php" <?php echo smarty_function_checkPermission(array('filename' => "custom_numbers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Custom numbers</a><br>
<a href="/classifier_tree.php" <?php echo smarty_function_checkPermission(array('filename' => "classifier_tree.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Classifier</a><br>
<br/>
<a href="/truck_route.php" <?php echo smarty_function_checkPermission(array('filename' => "truck_route.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Truck route planning</a><br>
<a href="/truck_route_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "truck_route_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Truck route settings</a><br>
<br/>
<a href="/merchants.php" <?php echo smarty_function_checkPermission(array('filename' => "merchants.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Merchants</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/merchant.php" <?php echo smarty_function_checkPermission(array('filename' => "merchant.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>New merchant</a><br>
<br/>
<a href="/shops.php" <?php echo smarty_function_checkPermission(array('filename' => "shops.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shops</a><br>
<a href="/shop_vouchers.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_vouchers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop vouchers</a><br>
<!--&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shop_vouchers.php?shop_id=1&inactive=1" <?php echo smarty_function_checkPermission(array('filename' => "users.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Inactive vouchers</a><br>-->
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shop_vouchers.php?unpaid=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_vouchers.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Unpaid vouchers</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shop_voucher_payment.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_voucher_payment.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Search voucher payment</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shop_voucher_partners.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_voucher_partners.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop voucher partners</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shop_conditions.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_conditions.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop voucher conditions</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/shop_promo_templates.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_promo_templates.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop voucher templates</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="/promo_plan.php" <?php echo smarty_function_checkPermission(array('filename' => "promo_plan.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Automatic voucher</a><br>
<a href="/shop_contests_list.php">Shop contests</a><br>
<a href="/shop_bonuses.php?country_code=CH" <?php echo smarty_function_checkPermission(array('filename' => "shop_bonuses.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop bonus records</a><br>
<a href="/shop_bonus_groups.php?country_code=CH" <?php echo smarty_function_checkPermission(array('filename' => "shop_bonus_groups.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop bonus groups</a><br>
<a href="/shop_bonus_stat.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_bonus_stat.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop bonus statistic</a><br>
<a href="/shop_sa_stats.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_sa_stats.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop articles statistics</a><br>
<a href="/shop_cats.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_cats.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop catalogue</a><br>
<a href="/react/shop_looks/list/" <?php echo smarty_function_checkPermission(array('filename' => "react/shop_looks",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop looks</a><br>
<a href="/react/shop_looks/settings/" <?php echo smarty_function_checkPermission(array('filename' => "react/shop_looks",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop looks settings</a><br>
<a href="/sa_similar.php" <?php echo smarty_function_checkPermission(array('filename' => "sa_similar.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop similar articles</a><br>
<a href="/shop_par_names.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_par_names.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop parameters</a><br>
<a href="/shop_sorting.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_sorting.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop sorting</a><br>
<a href="/shop_metas.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_metas.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop meta tags</a><br>
<a href="/shop_banners.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_banners.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop banners</a><br>
<a href="/react/sa_banners/" <?php echo smarty_function_checkPermission(array('filename' => "react/sa_banners",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop article banners</a><br>
<a href="/shop_logo_blocks.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_logo_blocks.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop logo blocks</a><br>
<a href="/shop_logos.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_logos.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop logos</a><br>
<a href="/shop_icons.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_icons.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop icons</a><br>
<a href="/shop_front_overlays.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_front_overlays.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop frontpage overlays</a><br>
<a href="/shop_disco_banners.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_disco_banners.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop article discount banner</a><br>
<a href="/shop_contents.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_contents.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop content</a><br>
<a href="/shop_news_list.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_news_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop news</a><br>
<a href="/shop_services.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_services.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop services</a><br>
<a href="/shop_partners.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_partners.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop partner sites</a><br>
<a href="/shop_ratings.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_ratings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop ratings</a><br>
<a href="/shop_ratings.php?aggr=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_ratings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop ratings statistic</a><br>
<a href="/shop_ratings_custom.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_ratings_custom.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop manual rating</a><br>
<a href="/shop_view_counter.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_view_counter.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop conversions</a><br>
<a href="/translate.php?table=translate_shop" <?php echo smarty_function_checkPermission(array('filename' => "translate.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop Translation</a><br>
<a href="/shop_questions.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_questions.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop questions</a><br>
<a href="/shop_question_kpi.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_question_kpi.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop questions KPI</a><br>
<a href="/shop_search_log.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_search_log.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop Search</a><br>
<a href="/shop_search_defs.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_search_defs.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop search definitions</a><br>
<a href="/shop_search_ranking.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_search_ranking.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop Search Ranking</a><br>
<a href="/shop_follows.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_follows.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop Follow Us On</a><br>
<a href="/shop_seo.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_seo.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop SEO</a><br>
<a href="/crafts.php" <?php echo smarty_function_checkPermission(array('filename' => "crafts.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop Crafts</a><br>
<a href="/redirects.php" <?php echo smarty_function_checkPermission(array('filename' => "redirects.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop URL redirect</a><br>
<a href="/shop_ambass.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_ambass.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop Ambassadors</a><br>
<a href="/shop_faq_list.php?shop_id=1" <?php echo smarty_function_checkPermission(array('filename' => "shop_faq_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop FAQ</a><br>
<a href="/shop_live_block_city.php" <?php echo smarty_function_checkPermission(array('filename' => "shop_live_block_city.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Shop Live Block City</a><br>
<br>
<a href="/mobile.php" <?php echo smarty_function_checkPermission(array('filename' => "mobile.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Warehouse Mobile</a><br>
<a href="/mobile_devs.php" <?php echo smarty_function_checkPermission(array('filename' => "mobile_devs.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Mobile devices</a><br>
<a href="/mobile_dev_settings.php" <?php echo smarty_function_checkPermission(array('filename' => "mobile_dev_settings.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Mobile Settings</a><br>
<a href="/mobile_stats.php" <?php echo smarty_function_checkPermission(array('filename' => "mobile_stats.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Mobile statistics</a><br>
<a href="/loading_list.php" <?php echo smarty_function_checkPermission(array('filename' => "loading_list.php",'user' => $this->_tpl_vars['loggedUser']), $this);?>
>Loading List </a><br>

</td>

<?php endif; ?>

<td valign="top" class="rightSideBlock">
  <table id="fulltable" border="0">
    <tr>
      <td align="center">
        <h1 align="center"><?php echo $this->_tpl_vars['title']; ?>
<?php echo $this->_tpl_vars['subtitle']; ?>
</h1>
        <h2 align="center"><?php echo $this->_tpl_vars['subsubtitle']; ?>
</h2>
        <h3 align="center"><?php echo $this->_tpl_vars['subsubsubtitle']; ?>
</h3>
<?php if ($this->_tpl_vars['id'] && 0): ?>
  <?php if (! $this->_tpl_vars['op_order']->data->locked_by || $this->_tpl_vars['op_order']->data->locked_by == $this->_tpl_vars['loggedUser']->username): ?>
    <?php if ($this->_tpl_vars['op_order']->data->locked_by): ?><span style="color:#FF0000"><b>Locked by <?php echo $this->_tpl_vars['op_order']->data->locked_by; ?>
</b></span><?php endif; ?>
  <input type="button" value="<?php if ($this->_tpl_vars['op_order']->data->locked_by): ?>Unlock<?php else: ?>Lock for edit<?php endif; ?>" onClick="lock4edit(<?php echo $this->_tpl_vars['id']; ?>
, this)"/>
  <?php else: ?>
    <span style="color:#FF0000"><b>Locked by <?php echo $this->_tpl_vars['op_order']->data->locked_by; ?>
</b></span>
    <?php $this->assign('disabledclosed', 'disabled'); ?>
  <?php endif; ?>
<?php endif; ?>
      </td>
    </tr>
  </table>
  <table width="100%" cellpadding="0" cellspacing="1">
    <tr>
      <td>
        <div id="errortext_div" <?php if (! $this->_tpl_vars['errortext']): ?>style="display:none"<?php endif; ?>>
          <b><?php echo $this->_tpl_vars['errortext']; ?>
</b>
        </div>
      </td>
    </tr>
  </table>
<?php if ($this->_tpl_vars['msgtext']): ?>
  <table width="100%" cellpadding="0" cellspacing="1" bgcolor="#00FF8A">
    <tr>
      <td>
        <table width="100%" bgcolor="#DDDDDD" cellpadding="4">
          <tr>
            <td>
              <b><?php echo $this->_tpl_vars['msgtext']; ?>
</b>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
<?php endif; ?>
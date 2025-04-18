<?php /* Smarty version 2.6.18, created on 2017-08-07 11:37:04
         compiled from login.tpl */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('modifier', 'escape', 'login.tpl', 65, false),)), $this); ?>
<?php $_smarty_tpl_vars = $this->_tpl_vars;
$this->_smarty_include(array('smarty_include_tpl_file' => "header.tpl", 'smarty_include_vars' => array()));
$this->_tpl_vars = $_smarty_tpl_vars;
unset($_smarty_tpl_vars);
 ?>

    <script src="/js/mobile_main.js?v=1"></script>
    <link rel="stylesheet" href="/css/mobile_layout_v2.css">
    <style>
        <?php echo '
        table td,
        table th{
            border:0!important;
        }
        .regenerate_btns{
            width:50%;
            float:left;
            color:black!important;
        }
        '; ?>

    </style>
    <script>
    <?php echo '
        $(document).ready(function(){
            $(\'.regenerate_btn\').on(\'click\',function(e){
                e.preventDefault();
                e.stopPropagation();
                console.log(\'click\');
                showMobilePopUp({
                    html:\'<li>Are you sure you want to change the password?</li>\',
                    buttons:[
                        {
                            title:\'Yes\',
                            class:\'regenerate_btns\',
                            callback: function(){
                                var reg = $(\'<input/>\',{
                                    type :"hidden",
                                    name :"regenerate",
                                    value:\'Regenerate\'
                                });
                                $(\'form\').append(reg).trigger(\'submit\');
                            }
                        },
                        {
                            class:\'regenerate_btns\',
                            title:\'No\'
                        }
                    ]
                });
                return false;
            })
        })
    '; ?>


    </script>
    <form class="b-login" method="post" autocomplete="off" <?php if ($this->_tpl_vars['return_url']): ?>action="/start.php"<?php endif; ?>>
        <div class="b-input_wrapper">
            <label><strong>Username</strong></label>
            <input class="nav_item" type="text" name="_username" data-target-focus=".input_password">
        </div>
        <div class="b-input_wrapper">
            <label><strong>Password</strong></label>
            <input class="nav_item input_password" type="password" name="_password" data-target-click=".login_button">
        </div>
        <div class="b-input-actions">
            <input class="nav_item login_button" type="submit" value="Login">
            <input class="nav_item regenerate_btn" type="submit" value="Regenerate">
            <?php if ($this->_tpl_vars['return_url']): ?>
                <input type="hidden" name="return_url" value="<?php echo ((is_array($_tmp=$this->_tpl_vars['return_url'])) ? $this->_run_mod_handler('escape', true, $_tmp) : smarty_modifier_escape($_tmp)); ?>
">
            <?php endif; ?>
            <br/><br/>
            <?php if ($this->_tpl_vars['loginsms']): ?>
                <input type="checkbox" name="token_via_email" id="token_via_email" />
                <label for="token_via_email" style="display:inline;">Send token via mail</label>
            <?php endif; ?>
        </div>
    </form>
<?php $_smarty_tpl_vars = $this->_tpl_vars;
$this->_smarty_include(array('smarty_include_tpl_file' => "footer.tpl", 'smarty_include_vars' => array()));
$this->_tpl_vars = $_smarty_tpl_vars;
unset($_smarty_tpl_vars);
 ?>
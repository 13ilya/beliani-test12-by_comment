<?php /* Smarty version 2.6.18, created on 2017-08-07 11:37:55
         compiled from footer.tpl */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('modifier', 'nl2br', 'footer.tpl', 5, false),)), $this); ?>
</td>
</tr>
</table>
<?php $_from = $this->_tpl_vars['_comments']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['_comment']):
?>
<input type="hidden" id="_comment[<?php echo $this->_tpl_vars['_comment']->element_id; ?>
]" value="<?php echo ((is_array($_tmp=$this->_tpl_vars['_comment']->note)) ? $this->_run_mod_handler('nl2br', true, $_tmp) : smarty_modifier_nl2br($_tmp)); ?>
"/>
<?php endforeach; endif; unset($_from); ?>

<?php 
	if (\label\DebugToolbar\DebugToolbar::isEnabled()) {
		$debugbar = \label\DebugToolbar\DebugToolbar::getInstance();
		$debugbarRenderer = $debugbar->getJavascriptRenderer();
		echo $debugbarRenderer->render();
	}
 ?>

</body>
</html>
<?php if ($this->_tpl_vars['order_checkout']): ?>
</div>
<?php endif; ?>

<script>document.getElementById('fulltable').style.maxWidth=(eval(screen.availWidth)<?php if (! $this->_tpl_vars['nomenu']): ?>-180<?php endif; ?>)+'px';</script>
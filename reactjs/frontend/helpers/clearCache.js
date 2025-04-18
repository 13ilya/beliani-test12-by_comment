//clearCache.jsx
import React from 'react'
import PureRenderMixin from 'react-addons-pure-render-mixin'
import {sendToServer} from './help_funs'

function recacheAlert(id,btn,result){

	if (result.res=='ok')alert('The cache has been cleared for # '+id);
	else alert('Error: Cache was not droped! ');
	$(btn).removeAttr('disabled')
}
export default React.createClass({
	mixins:[PureRenderMixin],

	render(){
		const master_id = this.props.master_id?this.props.master_id:'';
		const saved_id = this.props.saved_id?this.props.saved_id:'';
		let data={};
		data['fn']='purge_shop_cache';
		if(master_id) data['master_id']=master_id;
		if(saved_id) data['saved_id']=saved_id;
		return(
				<div style={{position:'absolute',right:'157px',top:'79.5px'}} className='clearCash'>
					<button className='form-control' onClick={(e)=>{
						if(confirm('Will be cleared  cache for #'+saved_id+', continue?')){
							$(e.target).attr('disabled','true');
							sendToServer(data,recacheAlert.bind(null,saved_id,e.target))
						}
						}}>Clear cache for SA#{saved_id}</button>
				</div>
		)
	}
})

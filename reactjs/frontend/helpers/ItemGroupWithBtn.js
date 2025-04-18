//ItemGroupWithBtn.js
import React from 'react'
import PureRenderMixin from 'react-addons-pure-render-mixin'

export default React.createClass({
    mixins:[PureRenderMixin],
    render(){
        const btnLabel = this.props.btnLabel || 'ok',
              btnFunc = this.props.btnFunc || false,
              pic = this.props.pic || '',
              label = this.props.label || '';

        return(
            <div className='col-xs-12 col-md-4 col-lg-4'>
                <div className='ParamBlock '>
                    <span>{label}</span>
                    <input type='button' className='btn btn-default ' style={{float:'right'}} value={btnLabel} onClick={()=>{confirm('Are you sure want delete this parameter?')?btnFunc():''}}/>
                    {pic?<img  src={pic}/>:''}
                <div className='clearfix'></div>
                </div>
            </div>
        )
    }
})

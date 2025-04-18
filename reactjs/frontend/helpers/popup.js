//clearCache.jsx
import React from 'react'
import PureRenderMixin from 'react-addons-pure-render-mixin'
import './css/popup.css'
export default React.createClass({
  mixins:[PureRenderMixin],
  render(){
    const children = this.props.children?this.props.children:'';
    const bsClass = this.props.bsClass?this.props.bsClass:'';
    const func = this.props.func?this.props.func:null;
    return(
        <div className={'pupupInner '+bsClass} onClick={(e)=>{
            if($(e.target).hasClass('pupupInner')){
              $('.pupupInner').css({display:'none'})
              if(func)func()
            }
          }}>
          <div className='popup'>
            {children}
          </div>
        </div>
    )
  }
})

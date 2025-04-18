//itemGroup.jsx
import React from 'react'
import PureRenderMixin from 'react-addons-pure-render-mixin'

export default React.createClass({
    mixins:[PureRenderMixin],
    render(){
        const iValue=this.props.iValue || ''; // value for checked
        const label=this.props.label || ''; // box title
        const value=this.props.value || ''; // value when checked
        const bsClass=this.props.bsClass || ''; // custom class
        const func=this.props.func || ''; // onChange callback
        const name=this.props.name || ''; // name for radiobuttons
        const type=this.props.type || 'checkbox'; // box type
        const linkUrl=this.props.linkUrl || '';//set url if we need active link for label
        const target=this.props.target || '_blank'; // wnd target if label is link
        const disabled=this.props.disabled || false;
        const placeholder=this.props.placeholder || 'Insert text here....';
        const labelWidth=this.props.labelWidth || '90%';
        const showTitle=this.props.showTitle || false;
        const styles = {
            maxWidth: labelWidth
        }
        return(
                <div
                    className={'check_wrapper ' + bsClass}
                    title = {showTitle?label:''}
                    onClick = {func.bind(null,value)}
                    >
                    <div className='check_area pull-left'>
                        <input
                            type={type}
                            disabled={disabled}
                            placeholder={placeholder}
                            checked={iValue}
                            style={{width:'14px'}}
                            value={value}
                            name={name}
                            onChange={()=>{}}/>
                    </div>
                    {linkUrl?
                        <div className='label_area pull-left'>
                            <a href = {linkUrl} target={target} style={styles}>{label}</a>
                        </div>
                        :
                        <div className='label_area pull-left' style={styles}>{label}</div>
                    }
                    <div className='clearfix'></div>
                </div>
        )
    }
})

import React from 'react'
import CopyToClipboard from 'react-copy-to-clipboard';

export default React.createClass({
    render(){
        const {text,asIcon,style,title} = this.props;
        function markAsCopy(param){
            $('.paramName').removeClass('copied');
            $(param).addClass('copied');

        }
    return(
        <CopyToClipboard text={text}>
            {asIcon?
            <span className='paramIcon paramName' style={style || {}} ><i>{title || 'P'}</i><i onClick={(e)=>(

                markAsCopy($(e.target).parent()))}>{text}</i></span>
            :
            <p className='small paramName' style={style?style:{}} onClick={(e)=>(markAsCopy(e.target))}>{text}</p>}
        </CopyToClipboard>)}
})

import React, { Component } from 'react'
import { connect } from 'react-redux'

class Confirm extends Component {
    render (){
            if(!this.props.confirm.text)return(<div></div>)
            const {
                text = '',
                confirmType = 'success',
                callback,
                maxWidth = 400
            } = this.props.confirm;
            // Types: success, info, warning, danger

            let classAlert = 'alert alert-'+confirmType,
                style = {
                    lineHeight:'1em',
                    position:'fixed',
                    left:'0px',
                    right:'0px',
                    top:'0px',
                    zIndex:'1000',
                    margin: 'auto',
                    maxWidth,
                    textAlign: 'center'
                };
            return(
                <div
                    className={classAlert}
                    style={style}
                    role='confirm'
                    ref={node => this.confirm = node}>
                    <div>{text}</div>
                    <br/>
                    <button
                        className='def_btn focusLink'
                        onClick={()=>{
                            if(callback.resolve) {
                                callback.resolve();
                            }
                            this._handleHide();
                        }}
                        >Yes</button>
                    <button
                        className='def_btn focusLink'
                        onClick={()=>{
                            if(callback.reject){
                                callback.reject();
                            }
                            this._handleHide();
                        }}
                        >No</button>
                </div>
            )
    }

    _handleShow(){
        if(this.props.confirm){
            const confirm = $(this.confirm);
            confirm.hide().slideDown();
        }
    }

    _handleHide(){
        const confirm = $(this.confirm);

        confirm.slideUp( ()=>{
            this.props.removeConfirm();
        });
    }

    componentDidUpdate(){
        this._handleShow();
    }
}
const mapStateToProps = function(store) {
  return {
    confirm: store.confirm
  }
};
const mapDispatchToProps = function(dispatch) {
  return {
    removeConfirm: () => {dispatch({type: 'REMOVE_CONFIRM'})},
  }
};
export default connect(mapStateToProps,mapDispatchToProps)(Confirm);

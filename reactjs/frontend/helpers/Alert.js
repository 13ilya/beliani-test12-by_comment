import React, { Component } from 'react'
import { connect } from 'react-redux'

class Alert_view extends Component {
    render (){
            if(!this.props.alert)return(<div></div>)
            const {
                text = '',
                alertType = '',
            } = this.props.alert;
            // Types: success, info, warning, danger
            let classAlert = '';
            if(alertType){
                classAlert = 'alert alert-'+alertType;
            }
            return(
                <div className={classAlert} style={{lineHeight:'1em',position:'fixed',left:'0px', right:'0px', top:'0px', zIndex:'1000' }} role='alert' ref={node => this.alert = node}>
                    <div>{text}</div>
                </div>
            )
    }

    _handleShowHideAlert(){
        if(this.props.alert){
            const alert = $(this.alert);
            alert.hide().slideDown( ()=>{
                alert.delay(3000).slideUp( ()=>{
                    this.props.removeAlert();
                });
            });
        }
    }

    componentDidUpdate(){
        this._handleShowHideAlert();
    }
}
const mapStateToProps = function(store) {
  return {
    alert: store.alerts
  }
};
const mapDispatchToProps = function(dispatch) {
  return {
    removeAlert: () => {dispatch({type: 'REMOVE_ALERT'})},
  }
};
export default connect(mapStateToProps,mapDispatchToProps)(Alert_view);

import React from 'react'

export default React.createClass(
{
    componentWillMount() {
        this.setState({
            showAll:false
        })
    },
    showAll_btn_change(){
        const state = this.state || {},
              showAll = state.showAll || false;
        this.setState({
            showAll:!showAll
        })
    },
    render() {
        const mailLog=this.props.mailLog;
        const state = this.state || {},
              showAll = state.showAll || false;
        return (
                <div>
                    <table className='table table-bordered table-hover text-center'>
                    <thead>
                        <tr>
                            <td style={{widtd:'20%'}}>Date</td>
                            <td style={{widtd:'20%'}}>Content</td>
                            <td>From</td>
                            <td>to</td>
                            <td>SMTP server</td>
                        </tr>
                    </thead>
                    <tbody>
                    {mailLog.map((value,idx)=>{
                        if(idx>2 && !showAll) return false
                        return(
                        <tr key={idx}>
                            <td>{value.date}</td>
                            <td>
                                {value.content?
                                    <span>
                                        <a href='#' onClick={()=>{
                                            let wnd = window.open('_blank','Print mail');
                                            wnd.onload = function(){
                                                $(wnd.document.body).html('<div>'+value.content+'</div>');
                                                wnd.print();
                                            };
                                            return false;
                                        }}><img src='/images/react/print.png' /></a>
                                    &nbsp;<a target='_self' title={value.content} >{value.template}</a>
                                    </span>
                                    :
                                    value.template
                                }
                            </td>
                            <td>{encodeURI(value.sender)}</td>
                            <td>{encodeURI(value.recipient)}</td>
                            <td>{encodeURI(value.smtp_server)}</td>
                        </tr>
                        )
                    })}
                    </tbody>
                </table>
                <br/>
                <input  type='button'
                        className='btn btn-default'
                        value={!showAll ? 'Show all' : 'Show last'}
                        style={{display:mailLog.length<3 ?'none':'block'}}
                        onClick={()=>{this.showAll_btn_change()}}/>
            </div>
        )
    }
})

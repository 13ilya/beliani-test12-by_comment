import React from 'react'
import {SetCalendar} from '../../../../../helpers/calendar'
import PanelCollapsible from '../../../../../helpers/PanelCollapsible'

export default  React.createClass({
    render() {
        const {
            _alarm={},
            //reAssign,
            alarm_send,
            commentChange,
        }=this.props,
        {
            status:_status = '',
            date = '',
            comment = ''
        }=_alarm;

        const status = _status=='Pending';
        const alarm_status=status?'ON':'OFF';
        return (
                <PanelCollapsible title={'Alarm'}>
                    <p>alarm status: <span id='alarmStatus' style={{color:status?'green':'red'}}>{alarm_status}</span></p>
                    <div className='form-inline'>
                        <input
                            type='button'
                            className='form-control'
                            id='AlarmSet'
                            value = {status?'Update alarm':'Set alarm'}
                            onClick ={alarm_send.bind(null,{status:status ? 'Update alarm':'Set alarm'})}
                            />{' '}
                        <input
                            type='button'
                            className='form-control'
                            id='AlarmOff'
                            style={{ display: status ? 'inline-block' : 'none' }}
                            value = 'Off alarm'
                            onClick ={alarm_send.bind(null,{status:'Off alarm'})}/>{' '}
                        {SetCalendar(date,'alarm_calendar',commentChange.bind(null,'date'))}
                    </div><br/>
                    <div className='form-group'>
                        <span>Alarm comment: </span>
                        <textarea
                            className='form-control'
                            style={{height:'100px'}}
                            value={comment}
                            onChange={(e)=>{commentChange('comment',e.target.value)}}
                            ></textarea>
                    </div>
                </PanelCollapsible>
        )
    }
})

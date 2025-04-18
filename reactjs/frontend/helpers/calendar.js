import Calendar from 'rc-calendar';
import React from 'react'
import './css/calendar.css'

export function SetCalendar(value,ref,func,disabled = false){
    $(document).on('click',(e)=>{
        console.log();
        if(!$(e.target).parents('.calendar-inner').length){
            $('.calendar-inner').hide();
        }
    });
  return(
  <div
    style={{display:'inline',position:'relative'}}
    className='form-inline'
    onClick={(e)=>{
        e.stopPropagation();
        e.preventDefault();
        return false;
    }}
    >
    <input
        type='text'
        className='form-control text'
        value={value}
        disabled = {disabled}
        id={ref+'_input'}
        onChange={(e)=>{func(e.target.value,ref)}}/>
    <div
      className={'id'+ref+' calendar-inner'}
      style={{display:'none'}}
      >
      <Calendar
        id={ref}
        showDateInput={false}
        format='YYYY-MM-DD'
        onChange={()=>{
            console.log('ok');
        }}
        onSelect={(e)=>{
          var date=e._d.toISOString();
          date=date.substring(0,date.indexOf('T'));
          func(date,ref);
          $('div.id'+ref).fadeOut('slow');
        }}
        />
    </div>
    <b><button
      id={'id'+ref}
      className='btn btn-default'
      onClick={(e)=>{
        e.stopPropagation();
        e.preventDefault();
        if ($('div.'+e.target.id).css('display')=='block')$('div.'+e.target.id).fadeOut('slow');
        else $('div.'+e.target.id).fadeIn('slow');
        return false;}}>...</button></b>
  </div>)
}

import {getSelectValue} from '../helpers/support_functions'
import Select from '../helpers/Select'
import React from 'react'



export const SERVER_SIDE_FILTERS_CREATOR = function(optionMaps){
    return [
            {title:'Seller',param:'seller',options:optionMaps.active_sellers || []},
            {title:'Source seller',param:'source_seller',options:optionMaps.source_sellers || []},
            {title:'Shipping country',param:'shipping_country',options:optionMaps.countries || []},
            {title:'Shipping method',param:'shipping_method',options:optionMaps.shipping_methods || []},
            {title:'Warehouse shipped from',param:'warehouse_shipped_from',options:optionMaps.warehouses || []},
            {title:'Warehouse shipped from',param:'warehouse_shipped_from',options:optionMaps.warehouses || []},
        ]
}
export const FAST_FILTERS_STACK_CREATOR = function(optionMaps){
    return [
        {title:'Status',param:'status',options:optionMaps.issue_states || []},
        {title:'Where',param:'where_did',options:optionMaps.issue_created_where || []},
        {title:'Types',param:'issue_type',options:optionMaps.issue_types || []},
        {title:'Reporting person',param:'added_person',options:optionMaps.users || []},
        {title:'Department',param:'department_id',options:optionMaps.departments || []},
        {title:'Responsible user',param:'resp_username',options:optionMaps.users || []},
        {title:'Solving responsible user',param:'solving_resp_username',options:optionMaps.users || []},
        {title:'Show recurring issues',param:'recurring',options:[
            {label:'All',value:''},
            {label:'No',value:'0'},
            {label:'Yes',value:'1'},
        ]},
    ]
}
export const ISSUE_LOGS_HEADERS_STACK = function(vars_options){
    return [
          {title:'#id', param:'id',width:10,component:'route',url:'/react/logs/issue_logs/[param]/'},
          {
              title:'Type',
              param:['issue_type'],
              width:10,
              component:'custom',
              creator:(params)=>{
                  let types = params.issue_type || [];
                  if(!types.length){
                      types.push({name:vars_options.default_issue_type_value});
                  }
                  return (
                    <span >
                        {types.map((type, idx)=>{
                            return(<p key = {idx}>{type.name}</p>)
                        })}
                    </span>
                  )
              }
          },
          {title:'Date and time',param:'added_time',width:100},
          {title:'Reporting person',param:'added_person',width:70},
          {
              title:'Where',
              param:['where_did','url','number','txnid'],
              width:50,
              component:'custom',
              creator:(params)=>{
                  return (
                      params.url ? <a href={'/'+params.url} target='_blank'>
                          {params.where_did}{params.number}{params.txnid?'/'+params.txnid:''}
                      </a> : params.where_did
                  )
              }
          },
          {title:'Issue',param:'issue',component:'html', td_class:'text_in_table'},
          {title:'Department',param:'department_name',width:70},
          {title:'Responsible user',param:'user_name',width:50},
          {title:'Solving responsible user',param:'solving_user_name',width:50},
          {
              title:'Recurring',
              param:['recurring','added_time_to_recurring','added_person_to_recurring'],
              component:'custom',
              creator:(params)=>{
                  let time = params.added_time_to_recurring ?
                          (' on '+ params.added_time_to_recurring) : '',
                      person = params.added_person_to_recurring ? (' by '+ params.added_person_to_recurring) : '',
                      log = person + time;
                  return (
                      <div>
                          <span
                              style = {{color:params.recurring == '1' ? 'red' : 'green'}}
                              >
                              {params.recurring == '1' ? 'Yes' : 'No'}
                          </span><br/>
                      <span style={{fontSize:'0.7em'}}>{log}</span>
                      </div>
                  )
              }
          },
          {title:'Last comment added',param:'last_comment_added', width:140},
          {
              title:'Status',
              param:['id','status','closed_log','change_time','change_by'],
              width:'140',
              component:'custom',
              creator:((rowChange,params)=>{
                  const change_time = params.change_time ? ' on ' + params.change_time:'',
                        change_by = params.change_by ? ' by ' + params.change_by : '',
                        closed_log = params.closed_log || change_by + change_time,
                        disabled = !((_.find( vars_options.allow_change_filds_creator,(item) => item.id == params.id )) || vars_options.admin == '1');


                  return (
                      <div style={{minWidth:'140px'}}>
                          <div className='col-md-12'>
                              <Select
                                 optionsMap = {[{label:'---',value:''}].concat(getSelectValue( vars_options.filters.options.issue_states))}
                                 value = {params.status}
                                 clear = {false}
                                 disabled = {disabled}
                                 search = {true}
                                 onChangeSelect = {
                                     (value)=>{
                                         let data = {
                                             fn      :'changeIssueState',
                                             page_id : params.id,
                                             issue_state:value
                                         }
                                         rowChange(params.id,'closed',data)
                                     }
                                 }
                              />
                          </div>
                          <div>
                              {closed_log}
                          </div>
                      </div>
                  )
              }).bind(null,vars_options.rowChange)
          },
          {title:'# of days pending',param:'days_passed',width:70}
    ]
};
export const NEW_ISSUE_BLOCK_CREATOR = function(vars_options){
    return [
          {title:'Department',options:getSelectValue(vars_options.filters.options.departments),value:'dep_for_new_issue'},
          {title:'Responsible user',options:getSelectValue(vars_options.filters.options.users),value:'resp_for_new_issue'}
    ];
}

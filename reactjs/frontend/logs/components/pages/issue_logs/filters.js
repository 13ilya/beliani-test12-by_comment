import React from 'react'
import Select from '../../../../helpers/Select'
import {SetCalendar} from '../../../../helpers/calendar'
import {getSelectValue} from '../../../../helpers/support_functions'
import { connect } from 'react-redux'
import {FAST_FILTERS_STACK_CREATOR,SERVER_SIDE_FILTERS_CREATOR} from '../../../vars'
import action_creator from '../../../action_creator'
export const filters_block_view = React.createClass(
    {
        componentWillMount() {
        this.props.filters?
            this.props.fetch('/api/filtersOptions/',{type:[
                'active_sellers',
                'countries',
                'source_sellers',
                'users',
                'departments',
                'issue_created_where',
                'shipping_methods',
                'issue_types',
                'issue_states',
                'warehouses']},'filtersOptions'):false;
                this.checkDefaults({status:'open'});

        },
        checkDefaults(params){
            let { data = {} } = this.props.filters,
                result = {};
            for(let key in params){
                if(!data[key]){
                    result[key] = params[key];
                }
            }
            this.props.defaults_actions.filterDefaults({...result});
        },
        _updateURL(data){
            function setLocation(curLoc){
                console.log(curLoc);
            try {
              history.pushState(null, null, curLoc);
              return;
          } catch(e) { console.log(e); }
            location.search = curLoc;
            }
            let str = '',
                keys_arr = Object.keys(data),
                values_arr = Object.values(data) || [],
                last_key = keys_arr[keys_arr.length-1];

            if(!values_arr.join('').trim()){
                console.log('location.search',location.search);
                if(location.search){
                    setLocation(location.pathname);
                }
                return false;
            }


            for(let key in data){
                if(data[key] && key !='active_column'){
                    str += key + '=' + data[key] + (last_key == key ? '' : '&');
                }
            }
            setLocation('?' + str);
        },
        render() {
            const filters = this.props.filters || {},
                  filtersData = filters.data || {};
            this._updateURL(filtersData);
            const send_filters = this.props.send_filters;
            const lastDate = this.props.lastDate;
            const filterFieldChange = this.props.filterFieldChange;
            const filtersOpt = filters.options || {}
            let optionMaps = {}
            for(let key in filtersOpt){
                optionMaps[key]=[{
                    label:'----',
                    value:''
                }];
                optionMaps[key] =optionMaps[key].concat(getSelectValue(filtersOpt[key]))
            }

            const FAST_FILTERS_STACK = FAST_FILTERS_STACK_CREATOR(optionMaps),
                  SERVER_SIDE_FILTERS = SERVER_SIDE_FILTERS_CREATOR(optionMaps)
           return (
               <div className='filtersBlock'>
                   <div className='col-md-6 bordered_block'>
                       {SERVER_SIDE_FILTERS.map((item,idx)=>{
                           return (
                              <DropDown_filter
                                  item = {item}
                                  key = {idx}
                                  filtersData = {filtersData}
                                  filterFieldChange = {filterFieldChange}
                                  />
                           )
                       })}
                       <div className='row'>
                           <div className='col-md-4 title'>from:</div>
                           <div className='col-md-6'>
                               {SetCalendar(filtersData['date_from'],'from',filterFieldChange.bind(null,'date_from'))}
                           </div>
                       </div>
                       <div className='row'>
                           <div className='col-md-4'>to:</div>
                           <div className='col-md-6'>
                               {SetCalendar(filtersData['date_to'],'to',filterFieldChange.bind(null,'date_to'))}
                           </div>
                       </div>
                       <div className='row'>
                           <div className='col-md-4'>Period:</div>
                           <div className='col-md-6'>
                               <button
                                   className='btn btn-default'
                                   onClick={()=>{lastDate('week')}}
                                >last week</button>{' '}
                               <button
                                   className='btn btn-default'
                                   onClick={()=>{lastDate('mounth')}}
                                   >last month</button>{' '}
                               <button
                                   className='btn btn-default'
                                   onClick={()=>{lastDate('year')}}
                                   >last year</button>{' '}
                           </div>
                       </div>
                       <div className='row'>
                           <div className='col-md-5'>Show also opened by mistake:</div>
                           <div className='col-md-2'>
                               <input
                                   type = 'checkbox'
                                   onChange = {()=>{
                                       filterFieldChange('show_with_inactive',filtersData['show_with_inactive'] == '1' ? '0':'1')
                                   }}
                                   value = {filtersData['show_with_inactive'] == '1' ? '0':'1'}
                                   checked = {filtersData['show_with_inactive'] == '1'}
                                   />
                           </div>
                       </div>
                       <div className='row'>
                           <div className='col-md-4'>By comment:</div>
                           <div className='col-md-6'>
                               <input
                                   type='text'
                                   onChange ={evt => filterFieldChange('by_comment', evt.target.value)}
                                   value ={filtersData.by_comment || ''}
                                />
                           </div>
                       </div>
                       <div>
                           <button className='btn btn-default'
                               onClick = {()=>{
                                   let data ={}
                                   for(let key in filtersData){
                                       if(filtersData[key] && key != 'status' && key != 'active_column') data[key] = filtersData[key];
                                   }
                                   send_filters(filtersData);
                               }}
                               >
                               Filter
                           </button>
                       </div>
                   </div>
                   <div className='col-md-6 bordered_block'>
                       <h4>Fast filters</h4>
                       {FAST_FILTERS_STACK.map((item,idx)=>{
                           return (
                               <DropDown_filter
                                   key = {idx}
                                   item = {item}
                                   filtersData = {filtersData}
                                   filterFieldChange = {filterFieldChange}
                                   />
                           )
                       })}
                   </div>
               </div>
           )
       }
   }
)

export class DropDown_filter extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
      const {
          item,
          filterFieldChange,
          filtersData
      } = this.props
    return (
        <div className='row'>
            <div className='col-md-4 title'>{item.title}:</div>
            <div className='col-md-6'>
                <Select
                   optionsMap={item.options}
                   value={filtersData[item.param]}
                   onChangeSelect={filterFieldChange.bind(null,item.param)}
                   clear={false}
                   search={true}
                />
            </div>
        </div>
    );
  }
}



const mapStateToProps = function(store) {
    return {
        filters:store.filters
    }
};

const filters_block = connect(mapStateToProps, action_creator)(filters_block_view);

export default filters_block

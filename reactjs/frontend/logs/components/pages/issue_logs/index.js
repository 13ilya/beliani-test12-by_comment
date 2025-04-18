import React from 'react'
import Panel from '../../../../helpers/Panel'
import Filters from './filters'
import { connect } from 'react-redux'
import {getSelectValue} from '../../../../helpers/support_functions'
import action_creator from '../../../action_creator'
import {ISSUE_LOGS_HEADERS_STACK , NEW_ISSUE_BLOCK_CREATOR} from '../../../vars'
import Select from '../../../../helpers/Select'
import Action_buttons from '../../../../helpers/Action_buttons'
import {Types_block} from '../../../../helpers/Issue_log_comp'
import {SetCalendar} from '../../../../helpers/calendar'

import Table_component from '../../../../helpers/table_component'
export const issue_logs_view = React.createClass({
    _send_filters(params){
        const not_for_send = [
            'status',
            'where_did',
            'solving_resp_username',
            'department_id',
            'active_column',
            'issue_type',
            'recurring',
            'added_person',
            'resp_username'
        ],
        remoute_filters = {};
        for(let key in params){
            if(not_for_send.indexOf(key) == -1){
                remoute_filters[key] = params[key];
            }
        }
        this.props.defaults_actions.fetch({
            url:'/api/issueLog/list/',
            data:remoute_filters,
            name:'issueLog',
            logged:true
        });
    },
    update_table(){
        let filters = decodeURI(location.search || '').replace('?','').split('&'),
            params = {};

        if(filters.length){
            filters.forEach((param)=>{
                let tmp_arr = param.split('=');
                params[tmp_arr[0]]=tmp_arr[1];
            });
            this.props.defaults_actions.filterDefaults(params);
        }
        this._send_filters(params);
        this.props.fetch('/api/issueLog/getIssueTypes',{},'issueLogSetting');
    },
    componentWillMount() {
        this.update_table()
    },
    checkValue(obj,filtersData,options,param){
        let filter_value = options[filtersData[param] || ''] || '';
        let obj_value = obj[param] || '';
        if(param == 'issue_type' && obj_value){
            obj_value = obj_value.length ? obj_value[0]['name'] : ''
        }
        obj_value = obj_value ? obj_value.toLowerCase(): '';
        filter_value = filter_value.toLowerCase();

        return (obj_value.indexOf(filter_value) > -1)
    },
    is_filtered(filters,obj){
        const options = filters.options || {},
              filtersData = filters.data || {},
              {
                users = {},
                issue_created_where = {},
                issue_types = {}
            } = options || {};

            let res = true,
                stack =[
                    'status',
                    'where_did',
                    'solving_resp_username',
                    'department_id',
                    'issue_type',
                    'added_person',
                    'resp_username',
                    'recurring'
                ];

        stack.forEach((filter_type)=>{
            if(filtersData[filter_type]){
                switch (filter_type) {
                    case 'where_did':
                        res = this.checkValue(obj,filtersData,issue_created_where, filter_type) ? res : false;
                        break;
                    case 'added_person':
                        res = this.checkValue(obj,filtersData,users, filter_type) ? res : false;
                        break;
                    case 'issue_type':
                        res = this.checkValue(obj,filtersData,issue_types, filter_type) ? res : false;
                        break;
                    default:
                        if(filtersData[filter_type] !== obj[filter_type] ) res = false;
                }
            }
        })
        return res
    },
    render() {
        const issueLog = this.props.issueLog? this.props.issueLog.data : [],
              {
                  files = [],
                  new_fields = {}
              } = this.props.custom_data || {},
              issueLogSettings = this.props.issueLogSettings || {},
              issueTypesOpt = issueLogSettings.data || [],
              default_issue_type_id = issueLogSettings.default_issue_type || 0,
              default_issue_type_obj = _.find(issueTypesOpt,(type) => type.id == default_issue_type_id) || {},
              default_issue_type_value = default_issue_type_obj.name || '',
              rowChange = this.props.rowChange,

              filters = this.props.filters || {},
              filtersData = filters.data || {},

              active_column = filtersData.active_column || '',

              is_filtered = this.is_filtered.bind(null,filters),
              {
                   allow_change_filds_creator = [],
                   admin = '0'
              } = this.props.issueLog,

              HEADERS_STACK = ISSUE_LOGS_HEADERS_STACK({
                  default_issue_type_value,
                  rowChange,
                  allow_change_filds_creator,
                  admin,
                  filters
              });

        let values = [];

        issueLog.forEach((item) => {
            if(is_filtered(item)) values.push(item);
        });

        return (
            <div>
                <h3 className='text-center'>Issue Log</h3>
                <Panel title=''>
                    <Filters
                        lastDate={this.props.lastDate}
                        send_filters = {this._send_filters}
                    />
                    <div className='clearfix'></div>
                    <br/>
                    <Table_component
                        key = {'table'}
                        pagination = {100}
                        stack = {HEADERS_STACK}
                        filterFieldChange = {this.props.filterFieldChange}
                        values = {values}
                        block_under_table = {'Total Number of Issues: '+ values.length}
                        bsClass = 'issue_log_list'
                        active_column = {active_column}
                    />
                    <Add_new_issue_fld
                        key = {'new_issue'}
                        length = {values.length}
                        colspan = {HEADERS_STACK.length}
                        filters = {filters}
                        defaults_actions = {this.props.defaults_actions}
                        new_fields = {new_fields}
                        files = {files}
                        />
                </Panel>
            </div>
        )
    }
});

export  class Add_new_issue_fld extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        const {
            length = 0,
            colspan = 1,
            filters = {},
            new_fields = {},
            files = []
        } = this.props,
        NEW_ISSUE_BLOCK = NEW_ISSUE_BLOCK_CREATOR({filters});
        return (
            <table className='table table-bordered'>
                <tbody>
                    <tr>
                        <td colSpan = {colspan}>
                            <p>
                                Create new Issue
                            </p>
                            <div className='col-md-12 new_line'>
                                <Types_block
                                    issue_types = {getSelectValue(filters.options['issue_types'])}
                                    values = {new_fields['types'] || []}
                                    fld = 'types'
                                    {...this.props.defaults_actions}
                                    title = 'Choose issue types'
                                    />
                            </div>
                            <div className='col-md-12 new_line'>
                                <Due_fld value={new_fields.due_date || ''} {...this.props.defaults_actions}/>
                            </div>
                            <br/>
                            {
                                NEW_ISSUE_BLOCK.map((elem,idx)=>{
                                    return (
                                        <Selection_Field
                                            key = {idx+'new_issue_block'}
                                            {...this.props.defaults_actions}
                                            elem = {elem}
                                            new_fields = {new_fields}
                                            />
                                    )
                                })
                            }
                            <Checkbox_Field
                                {...this.props.defaults_actions}
                                checked = {new_fields['recurring']}
                                type = 'recurring'
                                title = 'Marked as recurring:'
                            />

                            <Description_Field/>
                            <Files_Field files = {files} {...this.props.defaults_actions}/>
                            <Controls_Field
                                files = {files}
                                new_fields = {new_fields}
                                {...this.props.defaults_actions}
                                update_table = {this.update_table}
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        );
    }
}


export  class Due_fld extends React.Component {
    constructor(props) {
        super(props);
        this.update_due_date = this.update_due_date.bind(this)
    }
    update_due_date(value){
        this.props.generateSimpleAction(
            'CUSTOM_DATA_CHANGE',
            'new_fields',
            'due_date',
            value
        )
    }
    render() {
        const {
            value = '',
            func
        } = this.props
        return (
            <div>
                <div className='col-md-3 title'>Due date</div>
                <div className='col-md-3'>{SetCalendar(value,'due_date',this.update_due_date)}</div>
            </div>
        );
    }
}

export class Selection_Field extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    const {
        elem,
        new_fields
    } = this.props
    return (
        <div className='col-md-12 new_line'>
            <div className='col-md-3 title'>{elem.title}</div>
            <div className='col-md-3'>
                <Select
                   optionsMap={elem.options || []}
                   value={new_fields[elem.value] || ''}
                   clear={false}
                   search={true}
                   onChangeSelect={
                       this.props.generateSimpleAction.bind(
                           null,
                           'CUSTOM_DATA_CHANGE',
                           'new_fields',
                           elem.value
                       )
                   }
                />
            </div>
        </div>
    );
  }
}

export class Checkbox_Field extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    const {
        title,
        type,
        checked,
    } = this.props
    return (
        <div className='col-md-12'>
            <div className='col-md-3'>{title}</div>
            <div className='col-md-3'>
                <input
                    type='checkbox'
                    value = {checked == '1' ? '0' : '1'}
                    onChange = {(e)=>{
                        this.props.generateSimpleAction(
                            'CUSTOM_DATA_CHANGE',
                            'new_fields',
                            type,
                            e.target.value
                        )
                    }}
                    checked = {checked == '1'}
                    />
            </div>
        </div>
    );
  }
}

export class Description_Field extends React.Component {
    constructor(props) {
        super(props);
    }
    render() {
        return (
            <div className='col-md-12'>
                <div className='col-md-12'>Description: </div>
                <div className='col-md-12'>
                    <textarea
                        rows = {6}
                        style={{width:'100%'}}
                        ref  = 'new_issue_comment_area'
                        id   = 'new_issue_comment_area'
                        ></textarea>
                </div>
            </div>
        );
    }
}

export class Files_Field extends React.Component {
    constructor(props) {
        super(props);
        this._onchangeHandle = this._onchangeHandle.bind(this);
    }
    _onchangeHandle(files,e){
        let input_files = e.target.files,
            res_arr = files;
        for(let key = 0; key<input_files.length; key++){
            res_arr.push(input_files[key]);
        }
        this.props.generateSimpleAction('CUSTOM_DATA_FETCH', 'files', '', res_arr)
    }
    render() {
        const {
            files
        } = this.props
        return (
        <div className='col-md-12'>
            <div className='col-md-12'>
                <p>Files:</p>
                <ol className='files_list'>
                    {files.map((file,idx)=>{
                        return (<li key = {idx}>{file.name}</li>)
                    })}
                </ol>
            </div>

            <div className='col-md-12'>
                <input  type='file'
                    multiple = {true}
                    style={{display:'none'}}
                    className = 'multiple_file_field'
                    onChange={this._onchangeHandle.bind(this,files)}/>
            </div>
        </div>
    );
  }
}

export class Controls_Field extends React.Component {
    constructor(props) {
        super(props);
        this.create_new_issue = this.create_new_issue.bind(this);
    }
    create_new_issue(new_fields,update_table,files){
        const department = new_fields['dep_for_new_issue'] || '';
        const person = new_fields['resp_for_new_issue'] || '';
        const issue_type = new_fields['types'] || [];
        const due_date = new_fields['due_date'] || [];
        const recurring = new_fields['recurring'] || '';

        if(!department || !person || !issue_type.length){
            this.props.defaults_actions.showAlert({
                type:'danger',
                mess: 'Please set department, responsible person, and type'
            })
            return false
        }
        const data = {
            fn:'addIssueLog',
            depatament_id:department,
            issue_type:issue_type,
            recurring,
            due_date,
            issue_name:$('#new_issue_comment_area').val(),
            responsible_id:person,
        }
        $('#new_issue_comment_area').val('');
        this.props.create_issue(data,files);
        update_table();
        this.props.generateSimpleAction(
            'CUSTOM_DATA_CLEAR',
            [
                {name:'files'},
                {name:'new_fields',fields:['recurring']}
            ]
        );

  }
  render() {
    const {
        files,
        new_fields,
        update_table,
    } = this.props,
    buttons = [
        {title:'Add files', click:()=>{ $('.multiple_file_field').click() }},
        {title:'Create Issue',click:this.create_new_issue.bind(this,new_fields,update_table,files)}
    ];
    return (
        <div className='col-md-12'>
            <div className='col-md-12'>
                <Action_buttons buttons = {buttons}/>
            </div>
        </div>
    );
  }
}

const mapStateToProps = function(store) {
    return {
        issueLog:store.issueLog,
        filters:store.filters,
        custom_data:store.custom_data,
        issueLogSettings:store.issueLogSettings
    }
};

const issue_logs = connect(mapStateToProps, action_creator)(issue_logs_view);

export default issue_logs

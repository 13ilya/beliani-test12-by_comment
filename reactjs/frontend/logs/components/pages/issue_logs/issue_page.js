import React from 'react'
import Panel from '../../../../helpers/Panel'
import SANavigation from '../../../../helpers/SANavigation'
import { connect } from 'react-redux'
import action_creator from '../../../action_creator'
import Comments from '../../helpers/comment_block'
import Select from '../../../../helpers/Select'
import {getSelectValue,goToTop} from '../../../../helpers/support_functions'
import ImageCell from '../../../../helpers/imageCell'
import VideoPlayer from '../../helpers/VideoPleyer'
import {Types_block} from '../../../../helpers/Issue_log_comp'
import Action_buttons from '../../../../helpers/Action_buttons'
import {SetCalendar} from '../../../../helpers/calendar'


export  class issue_logs_view extends React.Component {
    constructor(props) {
        super(props);
        this.issue_type_update_handler = this.issue_type_update_handler.bind(this)
    }

    componentWillMount() {
        const id = this.props.params.id || 0;
        this.props.fetch('/api/issueLog/list/',{page_id:id,show_with_inactive:'1'},'issueLog');
        this.props.fetch('/api/issueLog/getIssueImages/',{page_id:id},'issueImagesLog');
        this.props.filters?
            this.props.fetch('/api/filtersOptions/',{type:['users','departments','issue_types','issue_states']},'filtersOptions'):false;
        goToTop();
    }

    render() {
        const ID = this.props.params.id || 0,
              ISSUELOG_ARR = this.props.issueLog ? this.props.issueLog.data : [],
              ISSUELOG = ISSUELOG_ARR.length ? ISSUELOG_ARR[0] : {},
              {
                  allow_change_filds_creator,
                  allow_change_filds = false
              } = this.props.issueLog || [],
              admin = this.props.issueLog.admin || '0',
              ALLOW_CHANGE_FILDS = (admin == '1' || allow_change_filds),
              filters = this.props.filters || {},
              filtersOptions = filters.options || {},
              {
                  departments = {},
                  users = {},
                  issue_states= {}
              }= filtersOptions,
              rowChange = this.props.rowChange.bind(null,ID,'closed'),
              {
                  images:issueLogImages = [],
                  files:issueLogFiles = [],
              } = this.props.issueLogFiles || [],
              status = ISSUELOG.status || '',
              defaults_actions = this.props.defaults_actions,

              LINKS_LIST = [
                    {
                        onlyActiveOnIndex : false,
                        url : '/react/logs/issue_logs/',
                        bsClass : '',
                        name : 'Back to list'
                    }
                ],

              BUTTONS_LIST = [
                    {
                        title:'Add comment',
                        type:'Issuelog_comment'
                    },
                    {
                        title:'Add corrective action',
                        type:'issuelog_corrective'
                    }
              ],
              DETAILS_LIST = [
                {
                    title : 'Department',
                    param: !ALLOW_CHANGE_FILDS ?  'department_name' : 'department_id',
                    type:ALLOW_CHANGE_FILDS ? 'select' : '',
                    remote_type:'changeDepartment',
                    optionsMap:getSelectValue(departments),
                },
                {
                    title : 'Solving person',
                    remote_type:'changeSolvingResponsible',
                    param: !ALLOW_CHANGE_FILDS ? 'solving_user_name' : 'solving_resp_username',
                    type: ALLOW_CHANGE_FILDS ? 'select' : '',
                    optionsMap:getSelectValue(users)
                },
                {
                    title : 'Issue status',
                    remote_type:'changeIssueState',
                    param: ALLOW_CHANGE_FILDS ?'status' : issue_states[ISSUELOG['status']],
                    type: ALLOW_CHANGE_FILDS ? 'select' : '',
                    optionsMap:getSelectValue(issue_states),
                    callback:(value)=>{
                        rowChange({
                            fn      :'changeIssueState',
                            page_id : ID,
                            issue_state:value
                        },true)
                    }
                },
                { title : 'Reporting person:', param:'added_person' },
                { title : 'Days passed:', param:'days_passed' },
                { title : 'Added time:', param:'added_time' },
                { title : 'Where did:', param:'where_did', type:'link' },
                { title : 'Issue:',param:'issue', style:{wordWrap:'break-word'}},
                {
                    title : 'Opened by mistake:',
                    param:['inactive'],
                    type:'checkbox',
                    callback:rowChange.bind(null,{
                        fn      :'set_issue_inactive',
                        page_id : ISSUELOG['id']
                    })
                },
                {
                    title : 'Due date:',
                    param:'due_date',
                    type:'due',
                    data: {
                        fn      :'set_issue_due_date',
                        page_id : ISSUELOG['id']
                    },
                    callback:rowChange
                },
                {
                    title : 'Marked as recurring:',
                    param:['recurring','added_time_to_recurring','added_person_to_recurring'],
                    type:'checkbox',
                    callback:rowChange.bind(null,{
                        fn      :'set_issue_recurring',
                        page_id : ISSUELOG['id']
                    })
                }
              ];

        let details_arr_left = [],
            details_arr_right = [];

        DETAILS_LIST.forEach( (list_item,idx) => {
            if( list_item.type ){
                let elem = '';
                switch (list_item.type) {
                    case 'textarea':
                        elem = (<p>{ISSUELOG[list_item.param]}</p>)
                        break;
                    case 'due':
                        elem = <Due_fld
                                {...list_item}
                                ID = {ISSUELOG.id}
                                value = {ISSUELOG[list_item.param]}
                                change_issue_log = {this.props.change_issue_log}
                                />
                        break;
                    case 'checkbox':
                        let time = ISSUELOG[list_item.param[1]] ? (' on '+ ISSUELOG[list_item.param[1]]) : '',
                            person = ISSUELOG[list_item.param[2]] ? (' by '+ ISSUELOG[list_item.param[2]]) : '',
                            log = person + time;

                        elem = (
                            <div>
                                <div className='pull-left'>
                                    <input
                                        type='checkbox'
                                        value = {ISSUELOG[list_item.param[0]]}
                                        disabled = {!ALLOW_CHANGE_FILDS}
                                        onChange = {()=>{
                                            list_item.callback(true);
                                        }}
                                        checked = {ISSUELOG[list_item.param[0]] == '1'}
                                        />
                                </div>
                                <div
                                    className='pull-left'
                                    style={{fontSize:'0.7em',paddingTop:'5px'}}>{log}</div>
                            </div>
                            )
                        break;
                    case 'select':
                        elem =  <IssueSelect
                                    {...list_item}
                                    ID = {ISSUELOG.id}
                                    value = {ISSUELOG[list_item.param]}
                                    change_issue_log = {this.props.change_issue_log}
                                    defaults_actions = {this.props.defaults_actions}
                                    change_issue_main_params = {this.props.change_issue_main_params}
                                />
                        break;
                    case 'link':
                        elem = ISSUELOG['url'] ? (
                                <a href={'/'+ISSUELOG['url']}  target='_blank' >
                                    {ISSUELOG['where_did']}{ISSUELOG['number']}{ISSUELOG['txnid']?'/'+ISSUELOG['txnid']:''}
                                </a> ) : '----'
                        break;
                    case 'button':
                        elem = <IssueButton {...list_item} value = {ISSUELOG[list_item.param]}/>
                        break;
                }
                details_arr_left.push(
                    <div className='row' key = {idx}>
                        <div className='col-md-5 col-xs-4'>{list_item.title}</div>
                        <div className='col-md-6 col-xs-8'>
                             {elem}
                        </div>
                    </div>
                )
            }
            else {
                details_arr_right.push (
                    <div className='row' key = {idx}>
                        <div className='col-md-6 col-xs-4'>{list_item.title}</div>
                        <div className='col-md-6 col-xs-8' style = {list_item.style || {}}>{ISSUELOG[list_item.param] || list_item.param}</div>
                    </div>
                )
            }
        } )
        let types_arr = ISSUELOG['issue_type'] ? ISSUELOG['issue_type'].map((item) => item.id) : [];


        return (
            <div className=''>
                <SANavigation links_list={LINKS_LIST}/>
                <h3 className='text-center'>
                    Issue Log# {ID} {' '}
                    <span style = {this.set_status_color(issue_states[status])}>
                        {issue_states[status]}
                    </span>
                </h3>
                <p className='text-center'>
                    {ISSUELOG.change_by ? ' by ' + ISSUELOG.change_by : ''}
                    {ISSUELOG.change_time ? ' on ' + ISSUELOG.change_time:''}
                </p>
                <Panel title = 'Selected issue types'>
                    <Types_block
                        issue_types = {getSelectValue(filters.options['issue_types'])}
                        values = {types_arr || []}
                        disabled = {!ALLOW_CHANGE_FILDS}
                        func = {(value)=>{
                            let res_arr = value.map((id)=>{
                                return {id, name:filters.options['issue_types'][id]}
                            })
                            this.props.change_issue_log(ID,'issue_type',res_arr)
                        }}
                        title = ''
                    />
                    <div className='col-md-12'>
                        <Action_buttons
                            buttons = {[
                                {
                                    title:'Update',
                                    click:this.issue_type_update_handler.bind(this,types_arr,ISSUELOG.id),
                                    disabled:!ALLOW_CHANGE_FILDS}
                            ]}
                            />
                    </div>
                </Panel>
                <Panel title='Issue details'>
                    <div className='row'>
                        <div className='col-md-6'>{details_arr_left}</div>
                        <div className='col-md-6'>{details_arr_right}</div>
                    </div>
                </Panel>
                <Panel title='Comments'>
                    <Comments
                            options = {
                                {
                                    comment_type : 'issuelog',
                                    page_id      : ID
                                }
                            }
                            bsClass = 'issuelog_table'
                            resp_update_name = {'issueLog_update_resp'}
                            name = {'comments'}
                            responsible_uname = {ISSUELOG['resp_username']}
                            url = {'/api/comments/list/'}
                            list_of_buttons = {BUTTONS_LIST}
                        />
                </Panel>
                <Panel title='Images'>
                    <div className='imagesTable'>
                            {
                                issueLogImages.map((image,idx)=>{
                                    return (
                                        <ImageZoomer key = {idx}>
                                            <div
                                                 className='col-md-3 image_inner '>
                                                <ImageCell
                                                    multiple = {true}
                                                    params={
                                                        {
                                                            id:image.id,
                                                            show_delete_btn:true,
                                                            type:'issue_img',
                                                            doc_id:image.id,
                                                            name:'upload_form',
                                                            drop_options:{
                                                                id:image.id,
                                                                path:image.url,
                                                                bsClass:'dropImgInner',
                                                                original:image.url ? image.url.replace(/x_\d+/gi,'x_1000') : ''
                                                            }

                                                        }
                                                    }
                                                    imageChangeCell={()=>{}}
                                                    fileLoad={(files)=>{
                                                        this.props.defaults_actions.fileLoad(
                                                            '/api/issueLog/saveImages/',
                                                            {page_id:ID},
                                                            files,
                                                            ()=>{
                                                                this.props.fetch(
                                                                    '/api/issueLog/getIssueImages/',
                                                                    {page_id:ID},
                                                                    'issueImagesLog'
                                                                );
                                                            }
                                                        );
                                                    }}
                                                    deleteSingleImage={this.props.fetch.bind(null,
                                                        '/api/issueLog/deleteImages/',
                                                        {page_id:ID,images_delete:[image.id]},
                                                        'issueImagesLog')}
                                                />
                                            </div>
                                        </ImageZoomer>
                                        )
                                    })
                                }
                        <div className='col-md-3 image_inner '>
                            <ImageCell
                                multiple = {true}
                                params={
                                    {
                                        id:'new',
                                        show_delete_btn:false,
                                        type:'issue_img',
                                        doc_id:'new',
                                        name:'upload_form',
                                        drop_options:{
                                            id:'new',
                                            path:'',
                                            bsClass:'dropImgInner',
                                            original:''
                                        }

                                    }
                                }
                                imageChangeCell={()=>{}}
                                fileLoad={(files)=>{
                                    this.props.defaults_actions.fileLoad(
                                        '/api/issueLog/saveImages/',
                                        {page_id:ID},
                                        files,
                                        ()=>{
                                            this.props.fetch(
                                                '/api/issueLog/getIssueImages/',
                                                {page_id:ID},
                                                'issueImagesLog'
                                            );
                                        }
                                    );
                                }}
                                deleteSingleImage={()=>{}}
                            />
                        </div>
                    </div>
                </Panel>
                <Panel title='Files' >
                    <ul className='clear_ul'>
                        <li>Uploaded files:</li>
                        {
                            issueLogFiles.map((link,idx)=>{
                                return (
                                        <li key={idx}>
                                            <a href={link.url}>{link.name}</a>
                                            <span
                                                className='close'
                                                style ={{float: 'none', fontSize: '1.2em'}}
                                                onClick={()=>{
                                                    this.props.defaults_actions.showConfirm({
                                                        mess:'Are you sure want to delete file: '+ link.name +' ?',
                                                        type:'info',
                                                        resolve:this.props.fetch.bind(null,
                                                            '/api/issueLog/deleteImages/',
                                                            {page_id:ID,images_delete:[link.id]},
                                                            'issueImagesLog')
                                                    })
                                                }}
                                                >(x)</span>
                                        </li>
                                    )
                                })
                            }
                    </ul>
                    <div className='clearfix'></div>
                    <br/>
                    <div className='col-md-12'>
                        <input  type='file'
                            multiple = {true}
                            style={{display:'none'}}
                            className = 'multiple_file_field'
                            onChange={(e)=>{
                                let input_files = e.target.files;
                                this.props.defaults_actions.fileLoad(
                                    '/api/issueLog/saveImages/',
                                    {page_id:ID},
                                    input_files,
                                    ()=>{
                                        this.props.defaults_actions.fetch({
                                            url:'/api/issueLog/getIssueImages/',
                                            data:{page_id:ID},
                                            name:'issueImagesLog'
                                        }
                                    )}
                                );
                            }}/>
                    </div>
                    <div className='col-md-12'>
                        <div className='col-md-12'>
                            <button
                                className='def_btn'
                                onClick = {()=>{
                                    $('.multiple_file_field').click();
                                }}
                                >
                                Add files
                            </button>
                        </div>
                    </div>
                </Panel>
                <div style={{display:'none'}}>
                    <Panel title='Videos'>
                        <div className='col-md-3'>
                            <VideoPlayer
                                options = {{
                                    url:'psR5nMAUPjw',
                                    autoplay: '1'
                                }}
                                />
                        </div>
                    </Panel>
                </div>
            </div>
        )
    }
    issue_type_update_handler(types_arr,id){
        if(types_arr.length){
            let data = {
                id, value:types_arr, type:'changeIssueType'
            }
            this.props.change_issue_main_params(data);
        }else{
            this.props.defaults_actions.showAlert({
                mess:'Please choose type for the issue',
                type:'danger'
            })
        }
    }
    set_status_color(value){
        switch (value) {
            case 'Open': return {color:'green'}
            case 'Closed': return {color:'red'}
            case 'On hold': return {color:'yellow'}


        }
    }
}

export  class IssueSelect extends React.Component {
    constructor(props) {
        super(props);
        this.is_changeIssueSelect = this.is_changeIssueSelect.bind(this);
        this._resolve = this._resolve.bind(this);
        this._reject = this._reject.bind(this);
    }
    _resolve(props,value){
        let data = {
            fld:props.param,
            id:props.ID,
            value,
            type:props.remote_type
        }
        props.change_issue_main_params(data);
    }
    _reject(props,value,old_value){
        props.change_issue_log(
            props.ID,
            props.param || props.sub_param,
            props.param ? old_value : _.find(props.optionsMap,(item)=>item.value == old_value).label
        );
    }
    is_changeIssueSelect(props,value){
            let mess = 'Are you sure want to change '+props.title.toLowerCase()+'?',
                old_value = props.value || props.alt,
                new_value = props.param ? value : _.find(props.optionsMap,(item)=>item.value == value).label,
                param = props.param || props.sub_param;

            props.change_issue_log(
                props.ID,
                props.param,
                new_value
            );
            if(props.callback){
                props.callback(value);
            }else{
                props.defaults_actions.showConfirm({
                    mess,
                    type:'danger',
                    resolve:this._resolve.bind(this,props,value),
                    reject:this._reject.bind(this,props,value,old_value)
                })
            }

    }
    render() {
        const {
            optionsMap = [],
            value = '',
            alt = '',
        } = this.props
        return (
            <Select
                optionsMap = {optionsMap || []}
                search = {true}
                value = {value || alt}
                onChangeSelect = {this.is_changeIssueSelect.bind(this,this.props)}
                />
        );
    }
}

export class Due_fld extends React.Component {
    constructor(props) {
        super(props);
    }
    render() {
        const {
            value = '',
            callback,
            disabled = '',
            data
        } = this.props
        return (
                <div className='disabled'>
                    {
                        SetCalendar(value,'due_date',(value)=>{
                            callback({...data,value},true);
                        },true)
                    }
                </div>
        );
    }
}


export  class IssueButton extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        const {disabled,callback,alt,value} = this.props;
        return (
            <div>
                <Action_buttons
                    buttons = {[
                        {
                            title:alt || value,
                            click:()=>{
                                let data = {
                                    fn      :'reverseIssueLog',
                                    page_id : ISSUELOG['id']
                                }
                                list_item.callback(data,true);
                            },
                            disabled}
                    ]}
                    />
            </div>
        );
    }
}

export class ImageZoomer extends React.Component {
  constructor(props) {
    super(props);
  }
  componentDidMount() {
      var zoomIn = this.props.zoomIn,
          do_zoomIn = this.do_zoomIn;
      $(document).on('mouseenter','.dropBox',function(e){
          var context = this,
              target = $(e.target);
              do_zoomIn(zoomIn,target);
      })
  }
  render() {
      const {
          children,
          zoomIn = 600
      } = this.props;
    return (
        <div
            className = 'zoomerInner'
            onMouseLeave = {()=>{$('.previewImg').remove();}}
            >{children}
        </div>

    );
  }

  do_zoomIn(zoomIn,elem){
        let target = $(elem).has('img').length ? $(elem).find('img') : $(elem).parent().find('img'),
            src = target.attr('src');
        if (!src ) return;
        src = src.replace(/x_\d+/gi,'x_'+zoomIn);
        let img = $('<img/>',{
            src,
            style:'max-height:100%; max-width:100%',
            load:(e)=>{
                var width =$(e.target).width(),
                    height = $(e.target).height();
                $('.previewImg').css({background:'none',width,height});
            }
        });
        let container = $('<div/>',{
            class:'previewImg',
            style:'position:fixed; right:10px; min-width:40px; min-height:40px; top:10px;background:url("/images/react/ajax-loader.gif") no-repeat 100% 0px;max-width:50%;height:100%;z-index:1000'
        })
        img.appendTo(container);
        $(target).closest('.zoomerInner').append(container);
    }
}



const mapStateToProps = function(store) {
    return {
        issueLog:store.issueLog,
        issueLogFiles:store.issueLogFiles,
        custom_data:store.custom_data,
        filters : store.filters
    }
};

const issue_logs = connect(mapStateToProps, action_creator)(issue_logs_view);

export default issue_logs

import {fetchFromServer,SaveBlock} from './components/helpers/help_funs'
import default_action_creator from '../helpers/default_action_creator'
import {
    getDateNow,
    sendToServer
} from '../helpers/support_functions'

export default (dispatch)=>{
    var defaults_actions = default_action_creator(dispatch,{fetchFromServer, SaveBlock})
    return {
        defaults_actions,
        clearData: (name) => {dispatch({type:'BLOCK_SAVE',name})},
        filterFieldChange: (fld,value) => {
            dispatch({type:'FILTER_CHANGE',value,fld})
        },
        change_issue_main_params: (params) => {
            var url = '/api/issueLog/'+params.type+'/',
                data = {
                    page_id:params.id
                };
            switch (params.type) {
                case 'changeDepartment':
                    data['department'] = params.value
                    break;
                case 'changeIssueType':
                    data['issue_type_id'] = params.value
                    break;
                case 'changeSolvingResponsible':
                    data['solving_resp_person'] = params.value
                    break;
            }
            if(params.fld){
                dispatch({
                    type:'CHANGE_ISSUE_LOG',
                    idx:params.id,
                    value:params.value,
                    fld:params.fld
                })
            }
            fetchFromServer('GET',url,{...data},'updateIssueComments',dispatch);
        },
        change_issue_log:(idx,fld,value)=>{
            dispatch({type:'CHANGE_ISSUE_LOG',idx,value,fld})
        },
        rowChange: (idx,fld,value,is_page) => {
            if(fld!='closed') //we need this condition for issue_page, where we can edit the description
                dispatch({type:'CHANGE_ISSUE_LOG',idx,value,fld})
            else {
                sendToServer ({...value},'/js_backend.php',()=>{
                    fetchFromServer('GET','/api/issueLog/list/',is_page ? {page_id:idx,show_with_inactive:'1'} : {},'issueLog',dispatch);
                })
            }
        },
        updateIssueTypes: (fld,value,id) => {
            let data = {
                [fld]:value
            }
            if(id) data['type_id'] = id;
            SaveBlock(dispatch, {...data},'issueLogSetting','/api/issueLog/setIssueTypes/',false);
        },
        settingsRowChange: (block,fld,value) => {
            dispatch({type:'CHANGE_ISSUE_LOG_SETTINGS',value,fld,block})
        },
        shipping_prices_monitor_change: (fld,idx,value) => {
            dispatch({type:'CHANGE_SHIPPING_PRICES_MONITOR',idx,value,fld})
        },
        lastDate:(param)=>{
            var _from,_to;
            switch (param) {
                case 'week':
                    _from = getDateNow(true,{day:-7});
                break;
                case 'mounth':
                    _from = getDateNow(true,{mounth:-1});
                break;
                case 'year':
                    _from = getDateNow(true,{year:-1});
                break;
            }
            _to = getDateNow(true);
            let action={
                type:'FILTER_CHANGE',
                fld:'date_from',
                value:_from
            }
            dispatch(action);
            action['fld'] = 'date_to';
            action['value'] = _to;
            dispatch(action);
        },
        fetch: (url,data,name) => {
            fetchFromServer('GET',url,{...data},name,dispatch);
        },
        save: (url,block,name) => {
            SaveBlock(dispatch, block ,name,url);
        },
        comments_actions:(name) => {
            let url = '/api/comments/';
            return {
                commentChange:(block,fld,subFld,value)=>{
                    dispatch({type:'COMMENT_CHANGE',block,fld,subFld,value});
                },
                addComment: (params)=>{
                    dispatch({type: 'COMMENT_CHANGE', block:'data', fld:'new_comment', subFld:'', value:' '});
                    SaveBlock(dispatch, params,name,url+'save/');
                },
                deleteComment:(params)=>{
                    fetchFromServer('GET', url+'delete/', params, name, dispatch)
                },
                reassignComment:(callback,params,url,name)=>{
                    fetchFromServer('GET', url, params, name, dispatch);
                    callback();
                },
                changeCommentNotification:(params)=>{
                    let data = {
                        fn : 'comment_notif',
                        obj: 'issuelog',
                        id : params.page_id,
                    }
                    sendToServer (data,'/js_backend.php',()=>{
                        fetchFromServer('GET',url+'list/',params.options,params.name,dispatch);
                    })
                },
                changeAlarmState:(params) => {
                    let data = {
                        fn : 'alarm',
                        status: params.status,
                        type : params.type,
                        type_id : params.page_id,
                        date : params.date,
                        comment : params.comment,
                        username : params.username
                    }
                    sendToServer (data,'/js_backend.php',()=>{
                        fetchFromServer('GET',url+'list/',params.options,params.name,dispatch);
                    })
                }
            }
        },
        images_actions:(name)=>{
            return {
                fileLoad:(url, data, inputFile) => {
                    var fd = new FormData;
                    if(inputFile.length){
                        inputFile.forEach((value)=>{
                            fd.append('imgs[]', value);
                        })
                        fd.append('data',JSON.stringify(data));
                        console.log('fd',fd);
                        SaveBlock(dispatch, fd, name, url,true,true);
                    }
                }
            }
        }
    }
}

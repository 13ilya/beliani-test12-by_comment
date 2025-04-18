import {spinShow,getSelectValue} from '../../../helpers/support_functions'
export function fetchFromServer (type, url, data, name, dispatch) {
  spinShow(true, dispatch)
  jQuery.ajax({
    method: type,
    url: url,
    data: data,
    success: (data) => {
      sendDispath(name, dispatch, data)
    }
  })
}

export function SaveBlock (dispatch, data, name,url,silent,as_formData) {
    console.log('Saving ', name, data)
    spinShow(true, dispatch)
    let uri = '/api/sa_types/save'
    if (url)uri=url;
    var ajax_data = {
        method: 'POST',
        url:uri,
        data,
        success: (data) => {
            console.log('name',name);
            spinShow(false, dispatch)
            console.log('[' + name + '] Saved response ', data)
            if(!silent)
                if (!data.error) dispatch({
                  type: 'ALERT',
                  alertKey: name,
                  alertType: 'success',
                  text: 'Successfully saved!',
                  show: true
                })
                else dispatch({
                    type: 'ALERT',
                    alertKey: name,
                    alertType: 'danger',
                    text: 'Error: ' + data.error,
                    show: true
                })
            dispatch({type: 'BLOCK_SAVE',name})
            switch (name) {
                default: sendDispath(name, dispatch, data)
            }
        }
    }
    if (as_formData){
        ajax_data['processData'] = false;
        ajax_data['contentType'] = false;
    }
    jQuery.ajax(ajax_data);
}
export function sendDispath (name, dispatch, data) {
    spinShow(false, dispatch)
    console.log('[FETCH] Promise response ', name, data)
    let action = {}
    switch (name) {
        case 'issueLog':
            action={
                type:'FETCH_ISSUE_LOG',
                data:data.issue_list || [],
                allow_change_filds_creator:data.allow_change_filds_creator || [],
                admin:data.admin || '0',
                allow_change_filds: data.allow_change_filds || false
            }
        break;
        case 'issueLogSetting':
            action={
                type:'FETCH_ISSUE_LOG_SETTINGS',
                data:data.issue_types || [],
                default_issue_type: data.default_issue_type || ''
            }
        break;
        case 'issueImagesLog':
            action={
                type:'FETCH_ISSUE_LOG_IMAGES',
                images:data.images || [],
                files:data.files || []
            }
        break;
        case 'comments':
            action={
                type: 'FETCH_COMMENT_BLOCK',
                data: data.comments || [],
                logs: data.email_log || [],
                alarm: data.alarm || {},
                other_params:{
                    comment_notified: data.comment_notified || false,
                }
            }
        break;
        case 'issueLog_update_resp':
            action={
                type:'UPDATE_ISSUE_RESPONSIBLE',
                data:{
                    resp_username: data.resp_username || {},
                    id:data.issuelog_id || 0
                }
            }
        break;
        case 'comments_options':
            action={
                type:'FETCH_COMMENT_BLOCK_USERS',
                data:data.options? getSelectValue(data.options.users) : []
            }
        break;
        case 'shipping_prices_monitor':
            action={
                type:'FETCH_SHIPPING_PRICES_MONITOR',
                data:data.result || {},
                data1:data || {}
            }
        break;
        case 'filtersOptions':
            action={
                type:'FILTERS_FETCH_OPTIONS',
                options:data.options || {}
            }
        break;
        case 'updateIssueComments':
            if(data.issuelog_id){
                fetchFromServer(
                    'GET',
                    '/api/comments/list/',
                    {
                        comment_type:'issuelog',
                        page_id:data.issuelog_id,
                    },'comments',dispatch);
            }

        break;
    }
    if (action.type != undefined) dispatch(action)
}

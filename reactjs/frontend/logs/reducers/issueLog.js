export default function(state = {data:[]}, action) {
    let clone = Object.assign([], state);
    switch (action.type) {
        case 'FETCH_ISSUE_LOG':
            clone.data = action.data;
            clone.allow_change_filds = action.allow_change_filds;
            clone.admin = action.admin;
            clone.allow_change_filds_creator = action.allow_change_filds_creator;
            return clone;
        case 'UPDATE_ISSUE_RESPONSIBLE':
            var data = action.data || {}
            var obj = _.find(clone.data,function(item){return item.id == data.id})
            for(let key in data.resp_username){
                obj['resp_username'] = key;
                obj['user_name'] = data.resp_username[key];
            }
            return clone;
        case 'CHANGE_ISSUE_LOG':
            const issue = _.find(clone.data,(issue)=>{return issue.id == action.idx})
            if(action.fld != 'closed')
                issue[action.fld] = action.value;
            else
                issue[action.fld] = issue[action.fld] == '1'?'0':'1'
            return clone;
        default:
            return state;
    }
}

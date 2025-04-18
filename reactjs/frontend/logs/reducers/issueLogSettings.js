let initial_state = {
    new_type_title:'',
    data:[]
}
export default function(state = initial_state, action) {
    let clone = Object.assign({}, state);
    switch (action.type) {
        case 'FETCH_ISSUE_LOG_SETTINGS':
            clone.data = action.data;
            clone.default_issue_type = action.default_issue_type;
            return clone;
        case 'CHANGE_ISSUE_LOG_SETTINGS':
            if(action.block){
                let block = _.find(clone.data, item => item.id === action.block)
                block[action.fld] = action.value;
            }else{
                clone[action.fld] = action.value;
            }
            return clone;
        default:
            return state;
    }
}
